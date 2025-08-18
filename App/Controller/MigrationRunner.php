<?php

namespace Wlrm\App\Controller;

use Wlrm\App\Models\ScheduledJobs;
use Wlrm\App\Controller\Migration;

defined('ABSPATH') or die();

/**
 * Schedules child jobs and processes Action Scheduler tasks for migration.
 *
 * Provides queueing with dedupe and a worker entrypoint that resolves job data,
 * invokes the appropriate compatible migrator, and updates job status with retry.
 */
class MigrationRunner
{
    /**
     * Schedule pending children for the active category, with dedupe.
     *
     * @param string $activeCategory
     * @param int $maxInFlight
     * @return void
     */
    public static function schedulePendingChildren($activeCategory, $maxInFlight)
    {
        $pending = ScheduledJobs::getPendingJobsByCategory($activeCategory);
        if (empty($pending)) {
            return;
        }
        $jobTable = new ScheduledJobs();
        $inFlight = 0;
        foreach ($pending as $job) {
            if (!function_exists('as_schedule_single_action')) {
                continue;
            }
            if ($inFlight >= $maxInFlight) {
                break;
            }
            $time = time() + 10;
            $uid = isset($job->uid) ? (int)$job->uid : 0;
            if ($uid <= 0) {
                continue;
            }
            if (false === as_next_scheduled_action('wlrmg_process_migration_job', [['uid' => $uid]], 'wlrmg_migration_queue')) {
                as_schedule_single_action($time, 'wlrmg_process_migration_job', [['uid' => $uid]], 'wlrmg_migration_queue');
            }
            $inFlight++;
        }
    }

    /**
     * Process a single action-scheduler job.
     *
     * @param array|object $jobData
     * @return void
     */
    public static function processAction($jobData)
    {
        if (is_array($jobData) && isset($jobData['uid'])) {
            $jobTable = new ScheduledJobs();
            global $wpdb;
            $loaded = $jobTable->getWhere($wpdb->prepare('uid = %d AND source_app = %s', [(int)$jobData['uid'], 'wlr_migration']));
            if (empty($loaded) || !is_object($loaded)) {
                return;
            }
            $jobData = $loaded;
        } elseif (is_object($jobData) && (!isset($jobData->category) || empty($jobData->category))) {
            $jobTable = new ScheduledJobs();
            global $wpdb;
            $loaded = $jobTable->getWhere($wpdb->prepare('uid = %d AND source_app = %s', [(int)$jobData->uid, 'wlr_migration']));
            if (empty($loaded) || !is_object($loaded)) {
                return;
            }
            $jobData = $loaded;
        }

        $jobTable = new ScheduledJobs();
        $jobTable->updateRow([
            'status' => 'processing',
            'updated_at' => time(),
        ], [
            'uid' => $jobData->uid,
            'source_app' => 'wlr_migration'
        ]);

        try {
            $batchInfo = ScheduledJobs::getBatchInfo($jobData);
            $category = is_object($jobData) ? $jobData->category : $jobData['category'];
            $users = null;
            if ($batchInfo && isset($batchInfo['start_id']) && isset($batchInfo['end_id'])) {
                $startId = (int)$batchInfo['start_id'];
                $endId = (int)$batchInfo['end_id'];
                $lastProcessedId = isset($jobData->last_processed_id) ? (int)$jobData->last_processed_id : 0;
                $effectiveStart = max($startId, $lastProcessedId);
                $users = Migration::getUsersForRange($category, $effectiveStart, $endId);
            }

            switch ($category) {
                case 'wp_swings_migration':
                    $svc = new \Wlrm\App\Controller\Compatibles\WPSwings();
                    $svc->migrateToLoyalty($jobData, $users);
                    break;
                case 'wlpr_migration':
                    $svc = new \Wlrm\App\Controller\Compatibles\WLPRPointsRewards();
                    $svc->migrateToLoyalty($jobData, $users);
                    break;
                case 'woocommerce_migration':
                    $svc = new \Wlrm\App\Controller\Compatibles\WooPointsRewards();
                    $svc->migrateToLoyalty($jobData, $users);
                    break;
                default:
                    return;
            }

            global $wpdb;
            $latest = $jobTable->getWhere($wpdb->prepare('uid = %d AND source_app = %s', [$jobData->uid, 'wlr_migration']));
            $batchInfo = ScheduledJobs::getBatchInfo($latest);
            if (!empty($latest) && is_object($latest) && $latest->status !== 'completed') {
                $jobTable->updateRow([
                    'status' => $batchInfo ? 'completed' : 'pending',
                    'updated_at' => time(),
                ], [
                    'uid' => $jobData->uid,
                    'source_app' => 'wlr_migration'
                ]);
            }
        } catch (\Exception $e) {
            $jobTable = new ScheduledJobs();
            global $wpdb;
            $latest = $jobTable->getWhere($wpdb->prepare('uid = %d AND source_app = %s', [$jobData->uid, 'wlr_migration']));
            $conditions = [];
            if (!empty($latest) && is_object($latest) && !empty($latest->conditions)) {
                $decoded = json_decode($latest->conditions, true);
                if (is_array($decoded)) {
                    $conditions = $decoded;
                }
            }
            $attempts = isset($conditions['attempts']) ? (int)$conditions['attempts'] : 0;
            $attempts++;
            $maxAttempts = (int)apply_filters('wlrmg_max_attempts', 3);
            $conditions['attempts'] = $attempts;
            $update = [
                'conditions' => json_encode($conditions),
                'updated_at' => time(),
            ];
            if ($attempts < $maxAttempts) {
                $update['status'] = 'pending';
            } else {
                $update['status'] = 'failed';
            }
            $jobTable->updateRow($update, [
                'uid' => $jobData->uid,
                'source_app' => 'wlr_migration'
            ]);
            throw $e;
        }
    }
}


