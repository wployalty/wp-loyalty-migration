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
     *
     * @param object $job_data The job data containing migration details.
     *
     * @return void
     */
    function migrateToLoyalty($job_data)
    {
        if (empty($job_data) || !is_object($job_data)) {
            return;
        }
        $job_id = (int)isset($job_data->uid) && !empty($job_data->uid) ? $job_data->uid : 0;
        $admin_mail = (string)isset($job_data->admin_mail) && !empty($job_data->admin_mail) ? $job_data->admin_mail : '';
        $action_type = (string)isset($job_data->action_type) && !empty($job_data->action_type) ? $job_data->action_type : "migration_to_wployalty";

        // Get WP Users
        global $wpdb;
        $where = $wpdb->prepare(" WHERE wp_user.ID > %d ", (int)$job_data->last_processed_id);
        $join = " LEFT JOIN " . $wpdb->prefix . "wc_points_rewards_user_points AS woo_points_table ON wp_user.ID = woo_points_table.user_id ";
        $limit_offset = "";
        if (isset($job_data->limit) && ($job_data->limit > 0)) {
            $limit_offset .= $wpdb->prepare(" LIMIT %d OFFSET %d ", (int)$job_data->limit, 0);
        }
        $select = "SELECT wp_user.ID AS user_id,
                COALESCE(woo_points_table.points, 0) AS points,
                COALESCE(woo_points_table.points_balance, 0) AS points_balance, wp_user.user_email 
                FROM " . $wpdb->users . " AS wp_user " . $join .
            $where . " ORDER BY wp_user.ID ASC " . $limit_offset;

        $wp_users = $wpdb->get_results(stripslashes($select));
        $this->migrateUsers($wp_users, $job_id, $job_data, $admin_mail, $action_type);
    }

    /**
     * Migrates users based on the provided data.
     *
     * @param array $wp_users Array of WordPress users to migrate.
     * @param int $job_id The ID of the migration job.
     * @param object $data Migration job data.
     * @param string $admin_mail The admin's email.
     * @param string $action_type The action type of the migration.
     *
     * @return void
     */
    public function migrateUsers($wp_users, $job_id, $data, $admin_mail, $action_type)
    {
        $migration_job_model = new ScheduledJobs();
        $migration_log_model = new MigrationLog();
        $data_logs = array(
            'job_id' => $job_id,
        );

        if (count($wp_users) == 0) {
            $data_logs['action'] = 'woocommerce_migration_completed';
            $data_logs['note'] = __('No available records for processing.', 'wp-loyalty-migration');
            $migration_log_model->saveLogs($data_logs, "woocommerce_migration");
            $update_data = array(
                "status" => "completed",
            );
            $migration_job_model->updateRow($update_data, array("uid" => $job_id));

            return;
        }

        $loyalty_user_model = new Users();
        $campaign = EarnCampaign::getInstance();
        $helper_base = new \Wlr\App\Helpers\Base();
        $conditions = isset($data->conditions) && !empty($data->conditions) ? json_decode($data->conditions, true) : array();

        foreach ($wp_users as $wp_user) {
            $user_email = !empty($wp_user) && is_object($wp_user) && isset($wp_user->user_email) && !empty($wp_user->user_email) ? $wp_user->user_email : "";
            if (empty($user_email)) {
                continue;
            }
            $user_id = !empty($wp_user) && is_object($wp_user) && isset($wp_user->user_id) && !empty($wp_user->user_id) ? $wp_user->user_id : 0;
            $new_points = (int)(!empty($wp_user->points_balance)) ? $wp_user->points_balance : 0;

            // Check if the user exists in the loyalty system
            $user_points = $loyalty_user_model->getQueryData(array(
                'user_email' => array(
                    'operator' => '=',
                    'value' => sanitize_email($user_email)
                )
            ), '*', array(), false, true);

            $created_at = strtotime(date("Y-m-d h:i:s"));

            if (is_object($user_points) &&
                (
                    (isset($user_points->user_email) && isset($conditions['update_point']) && $conditions['update_point'] == 'skip') ||
                    (isset($user_points->is_banned_user) && $user_points->is_banned_user == 1 && isset($conditions['update_banned_user']) && $conditions['update_banned_user'] == 'skip')
                )
            ) {
                $data->last_processed_id = $user_id;
                $update_status = array(
                    "status" => "processing",
                    "last_processed_id" => $data->last_processed_id,
                    "updated_at" => $created_at,
                );
                $migration_job_model->updateRow($update_status, array(
                    'uid' => $job_id,
                    'source_app' => 'wlr_migration'
                ));
                continue;
            }

            if (is_object($user_points) && isset($user_points->refer_code)) {
                $refer_code = $user_points->refer_code;
            } else {
                $refer_code = $helper_base->get_unique_refer_code('', false, $user_email);
            }

            $action_data = array(
                "user_email" => $user_email,
                "customer_command" => $data->comment,
                "points" => $new_points,
                "referral_code" => $refer_code,
                "action_process_type" => "earn_point",
                "customer_note" => sprintf(__("Added %d %s by site administrator(%s) via WPLoyalty migration", "wp-loyalty-migration"), $new_points, $campaign->getPointLabel($new_points), $admin_mail),
                "note" => sprintf(__("%s customer migrated from WooCommerce points and rewards with %d %s by administrator(%s) via WPLoyalty migration", "wp-loyalty-migration"), $user_email, $new_points, $campaign->getPointLabel($new_points), $admin_mail),
            );

            $trans_type = 'credit';
            $wployalty_migration_status = $campaign->addExtraPointAction($action_type, (int)$new_points, $action_data, $trans_type);
            $data_logs = array(
                'job_id' => $job_id,
                'action' => 'woocommerce_migration',
                'user_email' => $user_email,
                'referral_code' => $refer_code,
                'points' => $new_points,
                'earn_total_points' => $new_points,
                'created_at' => strtotime(date("Y-m-d h:i:s")),
            );

            if (!$wployalty_migration_status) {
                $data_logs['action'] = 'woocommerce_migration_failed';
            }

            if ($migration_log_model->saveLogs($data_logs, "woocommerce_migration") > 0) {
                $data->offset = $data->offset + 1;
                $data->last_processed_id = $user_id;
                $update_status = array(
                    "status" => "processing",
                    "offset" => $data->offset,
                    "last_processed_id" => $data->last_processed_id,
                    "updated_at" => strtotime(date("Y-m-d h:i:s")),
                );
                $migration_job_model->updateRow($update_status, array('uid' => $job_id));
            }
        }
    }
}