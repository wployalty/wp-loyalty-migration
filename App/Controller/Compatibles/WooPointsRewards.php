<?php

namespace Wlrm\App\Controller\Compatibles;

use stdClass;
use Wlr\App\Helpers\EarnCampaign;
use Wlr\App\Models\Users;
use Wlrm\App\Models\MigrationLog;
use Wlrm\App\Models\ScheduledJobs;

defined('ABSPATH') or die();

class WooPointsRewards implements Base
{
    /**
     * Checks if the WooCommerce Points and Rewards plugin is active
     *
     * @return bool
     */
    static function checkPluginIsActive()
    {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', array()));
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        return in_array('woocommerce-points-and-rewards/woocommerce-points-and-rewards.php', $active_plugins, false);
    }

    /**
     * Retrieves the migration job from the ScheduledJobs table
     *
     * @return object
     */
    static function getMigrationJob()
    {
        $job_table = new ScheduledJobs();
        $job = $job_table->getWhere("category = 'woocommerce_migration'");
        return (!empty($job) && is_object($job) && isset($job->uid)) ? $job : new stdClass();
    }

    /**
     * Migrates user data to the loyalty system
     *
     * @param object $job_data
     * @return void
     */
    function migrateToLoyalty($job_data)
    {
        if (empty($job_data) || !is_object($job_data)) {
            return;
        }

        $job_id = (int)($job_data->uid ?? 0);
        $admin_mail = (string)($job_data->admin_mail ?? '');
        $action_type = (string)($job_data->action_type ?? "migration_to_wployalty");

        global $wpdb;
        $where = $wpdb->prepare(" WHERE wp_user.ID > %d ", (int)$job_data->last_processed_id);

        $select = " SELECT wp_user.ID AS user_id, wp_user.user_email, 
                    IFNULL(SUM(woo_points_table.points_balance), 0) AS total_points_balance 
                    FROM " . $wpdb->prefix . "users AS wp_user 
                    LEFT JOIN " . $wpdb->prefix . "wc_points_rewards_user_points AS woo_points_table 
                    ON wp_user.ID = woo_points_table.user_id 
                    $where 
                    GROUP BY wp_user.ID, wp_user.user_email 
                    ORDER BY wp_user.ID ASC";

        $wp_users = $wpdb->get_results(stripslashes($select));

        if (empty($wp_users)) {
            $this->logMigrationCompletion($job_id);
            return;
        }

        $this->processUserMigration($wp_users, $job_id, $job_data, $admin_mail, $action_type);
    }

    /**
     * Logs migration completion
     *
     * @param int $job_id
     * @return void
     */
    private function logMigrationCompletion($job_id)
    {
        $migration_job_model = new ScheduledJobs();
        $update_data = [
            "status" => "completed",
            "updated_at" => strtotime(date("Y-m-d h:i:s")),
        ];
        $migration_job_model->updateRow($update_data, ['uid' => $job_id]);
    }

    /**
     * Processes user migration
     *
     * @param array $wp_users
     * @param int $job_id
     * @param object $data
     * @param string $admin_mail
     * @param string $action_type
     * @return void
     */
    private function processUserMigration($wp_users, $job_id, $data, $admin_mail, $action_type)
    {
        $migration_job_model = new ScheduledJobs();
        $helper_base = new \Wlr\App\Helpers\Base();
        $migration_log_model = new MigrationLog();
        $loyalty_user_model = new Users();
        $campaign = EarnCampaign::getInstance();

        $processed_count = 0;
        $total_users = count($wp_users);

        foreach ($wp_users as $wp_user) {
            $user_email = sanitize_email($wp_user->user_email ?? "");
            if (empty($user_email)) {
                continue;
            }

            $user_id = (int)($wp_user->user_id ?? 0);
            $total_points = (int)($wp_user->total_points_balance ?? 0);

            $user_points = $loyalty_user_model->getQueryData(
                ['user_email' => ['operator' => '=', 'value' => $user_email]],
                '*', [], false, true
            );

            $conditions = isset($data->conditions) && !empty($data->conditions)
                ? json_decode($data->conditions, true)
                : [];

            if (is_object($user_points) && (
                    (isset($user_points->user_email) && isset($conditions['update_point']) && $conditions['update_point'] == 'skip') ||
                    (isset($user_points->is_banned_user) && $user_points->is_banned_user == 1 && isset($conditions['update_banned_user']) && $conditions['update_banned_user'] == 'skip')
                )) {
                $this->updateJobProgress($job_id, $data, $user_id, $processed_count);
                continue;
            }

            $refer_code = is_object($user_points) && isset($user_points->refer_code)
                ? $user_points->refer_code
                : $helper_base->get_unique_refer_code('', false, $user_email);

            $action_data = [
                "user_email" => $user_email,
                "customer_command" => $data->comment,
                "points" => $total_points,
                "referral_code" => $refer_code,
                "action_process_type" => "earn_point",
                "customer_note" => sprintf(
                    __("Added %d %s by site administrator(%s) via WPLoyalty migration", "wp-loyalty-migration"),
                    $total_points,
                    $campaign->getPointLabel($total_points),
                    $admin_mail
                ),
                "note" => sprintf(
                    __("%s customer migrated from WooCommerce points and rewards with %d %s by administrator(%s) via WPLoyalty migration", "wp-loyalty-migration"),
                    $user_email,
                    $total_points,
                    $campaign->getPointLabel($total_points),
                    $admin_mail
                ),
            ];

            $trans_type = 'credit';
            $migration_status = $campaign->addExtraPointAction($action_type, $total_points, $action_data, $trans_type);

            $data_logs = [
                'job_id' => $job_id,
                'action' => $migration_status ? 'woocommerce_migration' : 'woocommerce_migration_failed',
                'user_email' => $user_email,
                'referral_code' => $refer_code,
                'points' => $total_points,
                'earn_total_points' => $total_points,
                'created_at' => strtotime(date("Y-m-d h:i:s")),
            ];
            $migration_log_model->saveLogs($data_logs, "woocommerce_migration");

            $this->updateJobProgress($job_id, $data, $user_id, ++$processed_count);
        }

        if ($processed_count == $total_users) {
            $this->logMigrationCompletion($job_id);
        } else {
            $this->updateJobStatus($job_id, 'processing');
        }
    }

    /**
     * Updates job progress
     *
     * @param int $job_id
     * @param object $data
     * @param int $user_id
     * @param int $processed_count
     * @return void
     */
    private function updateJobProgress($job_id, $data, $user_id, $processed_count)
    {
        $update_data = [
            "status" => "processing",
            "last_processed_id" => $user_id,
            "offset" => $processed_count,
            "updated_at" => strtotime(date("Y-m-d h:i:s")),
        ];
        $migration_job_model = new ScheduledJobs();
        $migration_job_model->updateRow($update_data, ['uid' => $job_id]);
    }

    /**
     * Updates job status
     *
     * @param int $job_id
     * @param string $status
     * @return void
     */
    private function updateJobStatus($job_id, $status)
    {
        $migration_job_model = new ScheduledJobs();
        $update_data = [
            "status" => $status,
            "updated_at" => strtotime(date("Y-m-d h:i:s")),
        ];
        $migration_job_model->updateRow($update_data, ['uid' => $job_id]);
    }
}
