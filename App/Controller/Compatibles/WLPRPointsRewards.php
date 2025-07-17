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

class WLPRPointsRewards implements Base
{

    /**
     * Checks if the 'loyalty-points-rewards/wp-loyalty-points-rewards.php' plugin is active.
     *
     * This method fetches the list of active plugins and checks if the specified plugin is present in the list.
     *
     * @return bool Returns true if the 'loyalty-points-rewards/wp-loyalty-points-rewards.php' plugin is active, false otherwise.
     */
    static function checkPluginIsActive()
    {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', []));
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', []));
        }

        return in_array('loyalty-points-rewards/wp-loyalty-points-rewards.php', $active_plugins);
    }

    /**
     * Retrieves the migration job from the ScheduledJobs database table.
     *
     * This method fetches a migration job from the ScheduledJobs table based on the category 'wlpr_migration'.
     * If a job with the specified category is found, it is returned. Otherwise, a new stdClass object is returned.
     *
     * @return object Returns the migration job object if found, or a new stdClass object if no job is found.
     */
    static function getMigrationJob()
    {
        $job_table = new ScheduledJobs();
        $job = $job_table->getWhere("category = 'wlpr_migration'");

        return (!empty($job) && is_object($job) && isset($job->uid)) ? $job : new stdClass();
    }

    /**
     * Migrates user data to the loyalty system based on the provided job data.
     *
     * This method processes the job data object to extract necessary information for migrating users to the loyalty system.
     *
     * @param object $job_data The data object containing information for the migration job.
     *                        - $job_data->uid (int) The ID of the job.
     *                        - $job_data->admin_mail (string) The email of the administrator.
     *                        - $job_data->action_type (string) The type of action for migration.
     *                        - $job_data->last_processed_id (int) The ID of the last processed user.
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
        $last_processed_id = (int)isset($job_data->last_processed_id) && !empty($job_data->last_processed_id) ? $job_data->last_processed_id : 0;
        global $wpdb;
        $where = $wpdb->prepare(" WHERE id > %d ", array((int)$last_processed_id));
        $limit_offset = "";
        if (isset($job_data->limit) && ($job_data->limit > 0)) {
            $limit_offset .= $wpdb->prepare(" LIMIT %d OFFSET %d ", array((int)$job_data->limit, 0));
        }
        $select = " SELECT * FROM " . $wpdb->prefix . "wlpr_points ";
	//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $query = $select . $where . " ORDER BY id ASC " . $limit_offset; 
	//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $users = $wpdb->get_results(stripslashes($query)); 
	    $this->migrateUsers($users, $job_id, $job_data, $admin_mail, $action_type);
    }

    /**
     * Migrates users with loyalty points and rewards.
     *
     * This method migrates user data including loyalty points and rewards based on the provided parameters.
     *
     * @param array $users Array of user data to migrate.
     * @param int $job_id The ID of the migration job.
     * @param object $data Object containing additional migration data.
     * @param string $admin_mail Email of the administrator initiating the migration.
     * @param string $action_type Type of action for migration.
     *
     * @return void
     */
    function migrateUsers($users, $job_id, $data, $admin_mail, $action_type)
    {
        global $wpdb;
        $migration_job_model = new ScheduledJobs();
        $migration_log_model = new MigrationLog();
        $data_logs = array(
            'job_id' => $job_id,
        );
        if (count($users) == 0) {
            $data_logs['action'] = 'wlpr_migration_completed';
            $data_logs['note'] = __('No available records for processing.', 'wp-loyalty-migration');
            $migration_log_model->saveLogs($data_logs, "wlpr_migration");
            $update_data = array(
                "status" => "completed",
            );
            $migration_job_model->updateRow($update_data, array("uid" => $job_id, 'source_app' => 'wlr_migration'));

            return;
        }
        $loyalty_user_model = new Users();
        $campaign = EarnCampaign::getInstance();
        $helper_base = new \Wlr\App\Helpers\Base();
        $conditions = isset($data->conditions) && !empty($data->conditions) ? json_decode($data->conditions, true) : array();
        foreach ($users as $user) {
            $user_email = !empty($user) && is_object($user) && isset($user->user_email) && !empty($user->user_email) ? $user->user_email : "";
            if (empty($user_email)) {
                continue;
            }
            $user_id = !empty($user) && is_object($user) && isset($user->id) && !empty($user->id) ? $user->id : 0;
            $new_points = (int)(!empty($user->points)) ? $user->points : 0;
            //check user exist in loyalty
            $user_points = $loyalty_user_model->getQueryData(array(
                'user_email' => array(
                    'operator' => '=',
                    'value' => sanitize_email($user_email)
                )
            ), '*', array(), false, true);
            $created_at = strtotime(gmdate("Y-m-d h:i:s"));
            if (is_object($user_points) &&
                (
                    (isset($user_points->user_email) && isset($conditions['update_point']) && $conditions['update_point'] == 'skip') ||
                    (isset($user_points->is_banned_user) && $user_points->is_banned_user == 1 && isset($conditions['update_banned_user']) && $conditions['update_banned_user'] == 'skip')
                )
            ) {
                $data->last_processed_id = $user_id;
                $update_status = [
                    "status" => "processing",
                    "last_processed_id" => $data->last_processed_id,
                    "updated_at" => $created_at,
                ];
                $migration_job_model->updateRow($update_status, array(
                    'uid' => $job_id,
                    'source_app' => 'wlr_migration'
                ));
                continue;
            }
	    //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $refer_code_query = $wpdb->prepare( "SELECT refer_code FROM {$wpdb->prefix}wlpr_points WHERE user_email = %s LIMIT 1", $user_email );
	    //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	    $refer_code_result = $wpdb->get_var($refer_code_query); 
            if (is_object($user_points) && isset($user_points->refer_code)) {
                $refer_code = $user_points->refer_code;
            } else if (!empty($refer_code_result)) {
                $refer_code = $refer_code_result; // Use the referral code from the core plugin
            } else {
                $refer_code = $helper_base->get_unique_refer_code('', false, $user_email);
            }
            $action_data = array(
                "user_email" => $user_email,
                "customer_command" => "",
                "points" => $new_points,
                "referral_code" => $refer_code,
                "action_process_type" => "earn_point",
                "customer_note" => sprintf(	    /* translators: 1: number of points, 2: point label (e.g. points), 3: admin email */
	                __( 'Added %1$d %2$s by site administrator (%3$s) via WPLoyalty migration', 'wp-loyalty-migration' ), $new_points, $campaign->getPointLabel($new_points), $admin_mail),
                "note" => sprintf(    /* translators: 1: customer email, 2: number of points, 3: point label, 4: admin email */
	                __( '%1$s customer migrated from WooCommerce Loyalty Points and Rewards with %2$d %3$s by administrator (%4$s) via WPLoyalty migration', 'wp-loyalty-migration' ), $user_email, $new_points, $campaign->getPointLabel($new_points), $admin_mail),
            );
            $trans_type = 'credit';
	        $wployalty_migration_status = $campaign->addExtraPointAction($action_type, (int)$new_points, $action_data, $trans_type);
            $data_logs = array(
                'job_id' => $job_id,
                'action' => 'wlpr_migration',
                'user_email' => $user_email,
                'referral_code' => $refer_code,
                'points' => $new_points,
                'earn_total_points' => $new_points,
                'created_at' => strtotime(gmdate("Y-m-d h:i:s")),
            );
            if (!$wployalty_migration_status) {
                $data_logs['action'] = 'wlpr_migration_failed';
            }
            if ($migration_log_model->saveLogs($data_logs, "wlpr_migration") > 0) {
                $data->offset = $data->offset + 1;
                $data->last_processed_id = $user_id;
                $update_status = array(
                    "status" => "processing",
                    "offset" => $data->offset,
                    "last_processed_id" => $data->last_processed_id,
                    "updated_at" => strtotime(gmdate("Y-m-d h:i:s")),
                );
                $migration_job_model->updateRow($update_status, array(
                    'uid' => $job_id,
                    'source_app' => 'wlr_migration'
                ));
            }
        }

    }
}
