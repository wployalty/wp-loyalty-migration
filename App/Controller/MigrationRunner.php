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
     * @param string $active_category
     * @param int $max_in_flight
     * @return void
     */
    public static function schedulePendingChildren($active_category, $max_in_flight)
    {
        $pending = ScheduledJobs::getPendingJobsByCategory($active_category);
        if (empty($pending)) {
            return;
        }
        $job_table = new ScheduledJobs();
        $in_flight = 0;
        foreach ($pending as $job) {
            if (!function_exists('as_schedule_single_action')) {
                continue;
            }
            if ($in_flight >= $max_in_flight) {
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
            $in_flight++;
        }
    }

    /**
     * Process a single action-scheduler job.
     *
     * @param array|object $job_data
     * @return void
     */
    public static function processAction($job_data)
    {
        if (is_array($job_data) && isset($job_data['uid'])) {
            $job_table = new ScheduledJobs();
            global $wpdb;
            $loaded = $job_table->getWhere($wpdb->prepare('uid = %d AND source_app = %s', [(int)$job_data['uid'], 'wlr_migration']));
            if (empty($loaded) || !is_object($loaded)) {
                return;
            }
            $job_data = $loaded;
        } elseif (is_object($job_data) && (!isset($job_data->category) || empty($job_data->category))) {
            $job_table = new ScheduledJobs();
            global $wpdb;
            $loaded = $job_table->getWhere($wpdb->prepare('uid = %d AND source_app = %s', [(int)$job_data->uid, 'wlr_migration']));
            if (empty($loaded) || !is_object($loaded)) {
                return;
            }
            $job_data = $loaded;
        }

        $job_table = new ScheduledJobs();
        $job_table->updateRow([
            'status' => 'processing',
            'updated_at' => time(),
        ], [
            'uid' => $job_data->uid,
            'source_app' => 'wlr_migration'
        ]);

        try {
            $batch_info = ScheduledJobs::getBatchInfo($job_data);
            $category = is_object($job_data) ? $job_data->category : $job_data['category'];
            $users = null;
            if ($batch_info && isset($batch_info['start_id']) && isset($batch_info['end_id'])) {
                $start_id = (int)$batch_info['start_id'];
                $end_id = (int)$batch_info['end_id'];
                $last_processed_id = isset($job_data->last_processed_id) ? (int)$job_data->last_processed_id : 0;
                $effective_start = max($start_id, $last_processed_id);
                $users = Migration::getUsersForRange($category, $effective_start, $end_id);
            }

            switch ($category) {
                case 'wp_swings_migration':
                    $svc = new \Wlrm\App\Controller\Compatibles\WPSwings();
                    $svc->migrateToLoyalty($job_data, $users);
                    break;
                case 'wlpr_migration':
                    $svc = new \Wlrm\App\Controller\Compatibles\WLPRPointsRewards();
                    $svc->migrateToLoyalty($job_data, $users);
                    break;
                case 'woocommerce_migration':
                    $svc = new \Wlrm\App\Controller\Compatibles\WooPointsRewards();
                    $svc->migrateToLoyalty($job_data, $users);
                    break;
                default:
                    return;
            }

            global $wpdb;
            $latest = $job_table->getWhere($wpdb->prepare('uid = %d AND source_app = %s', [$job_data->uid, 'wlr_migration']));
            $batch_info = ScheduledJobs::getBatchInfo($latest);
            if (!empty($latest) && is_object($latest) && $latest->status !== 'completed') {
                $job_table->updateRow([
                    'status' => $batch_info ? 'completed' : 'pending',
                    'updated_at' => time(),
                ], [
                    'uid' => $job_data->uid,
                    'source_app' => 'wlr_migration'
                ]);
            }
        } catch (\Exception $e) {
            $job_table = new ScheduledJobs();
            global $wpdb;
            $latest = $job_table->getWhere($wpdb->prepare('uid = %d AND source_app = %s', [$job_data->uid, 'wlr_migration']));
            $conditions = [];
            if (!empty($latest) && is_object($latest) && !empty($latest->conditions)) {
                $decoded = json_decode($latest->conditions, true);
                if (is_array($decoded)) {
                    $conditions = $decoded;
                }
            }
            $attempts = isset($conditions['attempts']) ? (int)$conditions['attempts'] : 0;
            $attempts++;
            $max_attempts = (int)apply_filters('wlrmg_max_attempts', 3);
            $conditions['attempts'] = $attempts;
            $update = [
                'conditions' => json_encode($conditions),
                'updated_at' => time(),
            ];
            if ($attempts < $max_attempts) {
                $update['status'] = 'pending';
            } else {
                $update['status'] = 'failed';
            }
            $job_table->updateRow($update, [
                'uid' => $job_data->uid,
                'source_app' => 'wlr_migration'
            ]);
            throw $e;
        }
    }
}


