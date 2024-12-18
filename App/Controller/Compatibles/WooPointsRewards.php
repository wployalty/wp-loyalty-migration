<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

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
     * Checks if the WooCommerce Points and Rewards plugin is active.
     *
     * @return bool Returns true if the plugin is active, false otherwise.
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
     * Retrieves the migration job from the ScheduledJobs table.
     *
     * @return object Returns the migration job object if found, otherwise a new stdClass object.
     */
    static function getMigrationJob()
    {
        $job_table = new ScheduledJobs();
        $job = $job_table->getWhere("category = 'woocommerce_migration'");

        return (!empty($job) && is_object($job) && isset($job->uid)) ? $job : new stdClass();
    }

    /**
     * Migrates user data to the loyalty system.
     * @param object $job_data The job data containing migration details.
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

        $select = "
        SELECT 
            wp_user.ID AS user_id,
            wp_user.user_email,
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
     * Update the wp_wlr_migration_log DB to completed status when the cron action and migration action is completed
     * @param $job_id
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
     * This function is responsible for getting and setting the processed item count
     *
     * @param $wp_users
     * @param $job_id
     * @param $data
     * @param $admin_mail
     * @param $action_type
     * @return void
     */
    private function processUserMigration($wp_users, $job_id, $data, $admin_mail, $action_type)
    {
        $migration_job_model = new ScheduledJobs();
        $helper_base = new \Wlr\App\Helpers\Base();
        $migration_log_model = new MigrationLog();
        $loyalty_user_model = new Users();
        $campaign = EarnCampaign::getInstance();

        $processed_count = 0; // Track only processed users
        $total_users = count($wp_users);

        foreach ($wp_users as $wp_user) {
            $user_email = sanitize_email($wp_user->user_email ?? "");
            if (empty($user_email)) {
                continue; // Skip invalid users
            }

            $user_id = (int)($wp_user->user_id ?? 0);
            $total_points = (int)($wp_user->total_points_balance ?? 0);

            // Check if the user is banned or should be skipped based on conditions
            $user_points = $loyalty_user_model->getQueryData(
                [
                    'user_email' => [
                        'operator' => '=',
                        'value' => $user_email
                    ]
                ],
                '*', [], false, true
            );

            // Skip banned users or based on custom conditions
            $conditions = isset($data->conditions) && !empty($data->conditions) ? json_decode($data->conditions, true) : [];
            if (is_object($user_points) &&
                (
                    (isset($user_points->user_email) && isset($conditions['update_point']) && $conditions['update_point'] == 'skip') ||
                    (isset($user_points->is_banned_user) && $user_points->is_banned_user == 1 && isset($conditions['update_banned_user']) && $conditions['update_banned_user'] == 'skip')
                )
            ) {
                $this->updateJobProgress($job_id, $data, $user_id, $processed_count); // Do not increment processed count
                continue;
            }

            if (is_object($user_points) && isset($user_points->refer_code)) {
                $refer_code = $user_points->refer_code;
            } else {
                $refer_code = $helper_base->get_unique_refer_code('', false, $user_email);
            }
            $action_data = [
                "user_email" => $user_email,
                "customer_command" => $data->comment,
                "points" => $total_points,
                "referral_code" => $refer_code,
                "action_process_type" => "earn_point",
                "customer_note" => sprintf(
                    __("Added %d %s by site administrator(%s) via WPLoyalty migration", "wp-loyalty-migration"),
                    $total_points, $campaign->getPointLabel($total_points), $admin_mail
                ),
                "note" => sprintf(
                    __("%s customer migrated from WooCommerce points and rewards with %d %s by administrator(%s) via WPLoyalty migration", "wp-loyalty-migration"),
                    $user_email, $total_points, $campaign->getPointLabel($total_points), $admin_mail
                ),
            ];

            $trans_type = 'credit';
            $log = wc_get_logger();
            $log->add('sri', 'Action type : ' . $action_type, ',Total Points : ' . $total_points . ', Action data : ' . json_encode($action_data) . ',Transaction type : ' . $trans_type);
            $migration_status = $campaign->addExtraPointAction($action_type, $total_points, $action_data, $trans_type);
            $log->add('sri', 'Migration status for ' . $action_data['user_email'] . $migration_status);
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

            $this->updateJobProgress($job_id, $data, $user_id, ++$processed_count); // Increment processed count only here
        }

        // Check if all users are processed and update the job status
        if ($processed_count == $total_users) {
            $this->logMigrationCompletion($job_id); // All users processed
        } else {
            $this->updateJobStatus($job_id, 'processing'); // Update job status if there are more users
        }
    }

    /**
     * This function is used to update the Job progress (Pending, Processing, Completed) based on cron and migration action
     * @param $job_id
     * @param $data
     * @param $user_id
     * @param $processed_count
     * @return void
     */
    private function updateJobProgress($job_id, $data, $user_id, $processed_count)
    {
        $update_data = [
            "status" => "processing",
            "last_processed_id" => $user_id,
            "offset" => $processed_count, // Update the offset with processed count
            "updated_at" => strtotime(date("Y-m-d h:i:s")),
        ];

        $migration_job_model = new ScheduledJobs();
        $migration_job_model->updateRow($update_data, ['uid' => $job_id]);
    }

    /**
     * Updating the job preference in processUserMigration Method
     * @param $job_id
     * @param $status
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

    /**
     * Implementation of referral code generation logic
     * @param $email
     * @return string
     */
    private function generateReferCode($email)
    {
        return substr(md5($email . time()), 0, 8);
    }
}