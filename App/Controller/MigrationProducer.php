<?php

namespace Wlrmg\App\Controller;

use Wlrmg\App\Models\ScheduledJobs;
use Wlrmg\App\Controller\Migration;
use Wlrmg\App\Helper\Settings;

defined('ABSPATH') or die();

/**
 * Produces child range batches and finalizes parent jobs for migration.
 *
 * Chooses the active category, maintains a per-parent producer lock,
 * enqueues child jobs using ID windows, and marks the parent completed/failed
 * when all children have finished.
 */
class MigrationProducer
{
    /**
     * Produce child range batches for the active migration category and finalize parent when done.
     *
     * @return void
     */
    public static function produceChildBatches()
    {
        $active_opt = get_option('wlrmg_active_category', []);
        $active_category = is_array($active_opt) && !empty($active_opt['category']) ? (string)$active_opt['category'] : '';
        $parent_job = null;

        if (empty($active_category)) {
            $parents = ScheduledJobs::getParentJobsPendingOrActive();
            if (empty($parents)) {
                return;
            }
            $parent_job = is_array($parents) ? reset($parents) : $parents;
            if (empty($parent_job) || !is_object($parent_job)) {
                return;
            }
            $active_category = isset($parent_job->category) ? $parent_job->category : '';
            if (empty($active_category)) {
                return;
            }
            update_option('wlrmg_active_category', ['category' => $active_category, 'set_at' => time()]);
        } else {
            $parent_job = ScheduledJobs::getParentJobByCategory($active_category);
            if (empty($parent_job) || !is_object($parent_job)) {
                delete_option('wlrmg_active_category');
                return;
            }
        }

        $conditions = !empty($parent_job->conditions) ? json_decode($parent_job->conditions, true) : [];
        $batch_limit = (int)($conditions['batch_limit'] ?? (int)$parent_job->limit ?? Settings::get('batch_limit', 50));
        $last_enqueued_id = (int)($conditions['last_enqueued_id'] ?? 0);

        $parent_uid = (string)$parent_job->uid;
        if (!self::acquireProducerLock($parent_uid)) {
            return;
        }

        try {
            $current_max_id = Migration::getCurrentMaxId($active_category);
            if ($current_max_id <= 0 || $last_enqueued_id >= $current_max_id) {
                self::finalizeParentIfNoActiveChildren($parent_uid);
                return;
            }

            $max_batches = (int)apply_filters('wlrmg_max_batches_per_tick', 10);
            for ($i = 0; $i < $max_batches; $i++) {
                $ids = Migration::getIdsWindow($active_category, $last_enqueued_id, $batch_limit, $current_max_id);
                if (empty($ids)) {
                    break;
                }
                $end_id = (int)end($ids);
                $insert_id = ScheduledJobs::insertChildRangeJob($parent_job, $last_enqueued_id, $end_id, $batch_limit);
                if ($insert_id > 0) {
                    ScheduledJobs::updateParentEnqueuedCursor($parent_uid, $end_id);
                    $last_enqueued_id = $end_id;
                } else {
                    break;
                }
                if ($last_enqueued_id >= $current_max_id) {
                    break;
                }
            }

            if ($last_enqueued_id >= $current_max_id) {
                self::finalizeParentIfNoActiveChildren($parent_uid);
            }
        } finally {
            self::releaseProducerLock($parent_uid);
        }
    }

    /**
     * Acquire producer lock for a parent job using options API.
     *
     * @param string $parent_uid
     * @return bool
     */
    private static function acquireProducerLock($parent_uid)
    {
        $option_name = 'wlrmg_producer_lock_' . (string)$parent_uid;
        $now = time();
        if (add_option($option_name, ['locked_at' => $now])) {
            return true;
        }
        $existing = get_option($option_name, []);
        $locked_at = (int)($existing['locked_at'] ?? 0);
        if ($locked_at > 0 && ($now - $locked_at) > (10 * MINUTE_IN_SECONDS)) {
            delete_option($option_name);
            return add_option($option_name, ['locked_at' => $now]);
        }
        return false;
    }

    /**
     * Release producer lock for a parent job.
     *
     * @param string $parent_uid
     * @return void
     */
    private static function releaseProducerLock($parent_uid)
    {
        $option_name = 'wlrmg_producer_lock_' . (string)$parent_uid;
        delete_option($option_name);
    }

    /**
     * Finalize parent job if no active children; set failed if any child failed, else completed.
     *
     * @param string $parent_uid
     * @return bool
     */
    public static function finalizeParentIfNoActiveChildren($parent_uid)
    {
        $children = ScheduledJobs::getBatchesByParent((string)$parent_uid);
        $has_active = false;
        $has_failed = false;
        if (!empty($children) && is_array($children)) {
            foreach ($children as $child) {
                if ((string)$child->uid === (string)$parent_uid) {
                    continue;
                }
                $st = isset($child->status) ? (string)$child->status : '';
                if (in_array($st, ['pending', 'processing'], true)) {
                    $has_active = true;
                } elseif ($st === 'failed') {
                    $has_failed = true;
                }
            }
        }
        if (!$has_active) {
            $table = new ScheduledJobs();
            $status = $has_failed ? 'failed' : 'completed';
            $table->updateRow([
                'status' => $status,
                'updated_at' => time()
            ], [
                'uid' => (string)$parent_uid,
                'source_app' => 'wlr_migration'
            ]);
            delete_option('wlrmg_active_category');
            return true;
        }
        return false;
    }
}
