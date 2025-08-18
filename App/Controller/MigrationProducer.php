<?php

namespace Wlrm\App\Controller;

use Wlrm\App\Models\ScheduledJobs;
use Wlrm\App\Controller\Migration;

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
        $activeOpt = get_option('wlrmg_active_category', array());
        $activeCategory = is_array($activeOpt) && !empty($activeOpt['category']) ? (string)$activeOpt['category'] : '';
        $parentJob = null;

        if (empty($activeCategory)) {
            $parents = ScheduledJobs::getParentJobsPendingOrActive();
            if (empty($parents)) {
                return;
            }
            $parentJob = is_array($parents) ? reset($parents) : $parents;
            if (empty($parentJob) || !is_object($parentJob)) {
                return;
            }
            $activeCategory = isset($parentJob->category) ? $parentJob->category : '';
            if (empty($activeCategory)) {
                return;
            }
            update_option('wlrmg_active_category', array('category' => $activeCategory, 'set_at' => time()));
        } else {
            $parentJob = ScheduledJobs::getParentJobByCategory($activeCategory);
            if (empty($parentJob) || !is_object($parentJob)) {
                delete_option('wlrmg_active_category');
                return;
            }
        }

        $conditions = !empty($parentJob->conditions) ? json_decode($parentJob->conditions, true) : [];
        $batchLimit = (int)($conditions['batch_limit'] ?? (int)$parentJob->limit ?? 50);
        if ($batchLimit <= 0) {
            $batchLimit = 50;
        }
        $lastEnqueuedId = (int)($conditions['last_enqueued_id'] ?? 0);

        $parentUid = (int)$parentJob->uid;
        if (!self::acquireProducerLock($parentUid)) {
            return;
        }

        try {
            $currentMaxId = Migration::getCurrentMaxId($activeCategory);
            if ($currentMaxId <= 0 || $lastEnqueuedId >= $currentMaxId) {
                self::finalizeParentIfNoActiveChildren($parentUid);
                return;
            }

            $maxBatches = (int)apply_filters('wlrmg_max_batches_per_tick', 3);
            for ($i = 0; $i < $maxBatches; $i++) {
                $ids = Migration::getIdsWindow($activeCategory, $lastEnqueuedId, $batchLimit, $currentMaxId);
                if (empty($ids)) {
                    break;
                }
                $endId = (int)end($ids);
                $insertId = ScheduledJobs::insertChildRangeJob($parentJob, $lastEnqueuedId, $endId, $batchLimit);
                if ($insertId > 0) {
                    ScheduledJobs::updateParentEnqueuedCursor($parentUid, $endId);
                    $lastEnqueuedId = $endId;
                } else {
                    break;
                }
                if ($lastEnqueuedId >= $currentMaxId) {
                    break;
                }
            }

            if ($lastEnqueuedId >= $currentMaxId) {
                self::finalizeParentIfNoActiveChildren($parentUid);
            }
        } finally {
            self::releaseProducerLock($parentUid);
        }
    }

    /**
     * Acquire producer lock for a parent job using options API.
     *
     * @param int $parentUid
     * @return bool
     */
    private static function acquireProducerLock($parentUid)
    {
        $optionName = 'wlrmg_producer_lock_' . (int)$parentUid;
        $now = time();
        if (add_option($optionName, array('locked_at' => $now))) {
            return true;
        }
        $existing = get_option($optionName, array());
        $lockedAt = (int)($existing['locked_at'] ?? 0);
        if ($lockedAt > 0 && ($now - $lockedAt) > (10 * MINUTE_IN_SECONDS)) {
            delete_option($optionName);
            return add_option($optionName, array('locked_at' => $now));
        }
        return false;
    }

    /**
     * Release producer lock for a parent job.
     *
     * @param int $parentUid
     * @return void
     */
    private static function releaseProducerLock($parentUid)
    {
        $optionName = 'wlrmg_producer_lock_' . (int)$parentUid;
        delete_option($optionName);
    }

    /**
     * Finalize parent job if no active children; set failed if any child failed, else completed.
     *
     * @param int $parentUid
     * @return bool
     */
    public static function finalizeParentIfNoActiveChildren($parentUid)
    {
        $children = ScheduledJobs::getBatchesByParent((int)$parentUid);
        $hasActive = false;
        $hasFailed = false;
        if (!empty($children) && is_array($children)) {
            foreach ($children as $child) {
                if ((int)$child->uid === (int)$parentUid) {
                    continue;
                }
                $st = isset($child->status) ? (string)$child->status : '';
                if (in_array($st, ['pending', 'processing'], true)) {
                    $hasActive = true;
                } elseif ($st === 'failed') {
                    $hasFailed = true;
                }
            }
        }
        if (!$hasActive) {
            $table = new ScheduledJobs();
            $status = $hasFailed ? 'failed' : 'completed';
            $table->updateRow([
                'status' => $status,
                'updated_at' => time()
            ], [
                'uid' => (int)$parentUid,
                'source_app' => 'wlr_migration'
            ]);
            delete_option('wlrmg_active_category');
            return true;
        }
        return false;
    }
}


