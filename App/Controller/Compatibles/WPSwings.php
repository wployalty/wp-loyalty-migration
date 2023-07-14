<?php

namespace Wlrm\App\Controller\Compatibles;

use Wlr\App\Helpers\EarnCampaign;
use Wlr\App\Models\EarnCampaignTransactions;
use Wlr\App\Models\Logs;
use Wlr\App\Models\Users;
use Wlrm\App\Models\MigrationJob;
use Wlrm\App\Models\MigrationLog;

defined('ABSPATH') or die();

class WPSwings implements Base
{
    static function checkPluginIsActive()
    {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', array()));
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        return in_array('points-and-rewards-for-woocommerce/points-rewards-for-woocommerce.php', $active_plugins, false);
    }
    static function checkMigrationJobIsCreated(){
        $job_table = new MigrationJob();
        $job = $job_table->getWhere("action = 'wp_swings_migration'");
        return (!empty($job) && is_object($job) && isset($job->uid));
    }
    static function getJobId()
    {
        $job_table = new MigrationJob();
        $job = $job_table->getWhere("action = 'wp_swings_migration'");
        return !empty($job) && is_object($job) && isset($job->uid)  ? $job->uid : 0;
    }

    function migrateToLoyalty($job_data)
    {
        if (empty($data) || !is_object($data)) {
            return;
        }
        $job_id = (int)isset($data->uid) && !empty($data->uid) ? $data->uid : 0;
        $admin_mail = (string)isset($data->admin_mail) && !empty($data->admin_mail) ? $data->admin_mail : '';
        $action_type = (string)isset($data->action_type) && !empty($data->action_type) ? $data->action_type : "migration_to_wployalty";
        //Get WPUsers
        $where = self::$db->prepare(" WHERE wp_user.ID > %d ", array(0));
        $where .= self::$db->prepare(" AND wp_user.ID > %d ", array((int)$data->last_processed_id));
        $join = " LEFT JOIN " . self::$db->usermeta . " AS meta ON wp_user.ID = meta.user_id AND meta.meta_key = 'wps_wpr_points' ";
        $limit_offset = "";
        if (isset($data->limit) && ($data->limit > 0)) {
            $limit_offset .= self::$db->prepare(" LIMIT %d OFFSET %d ", array((int)$data->limit, 0));
        }
        $select = " SELECT wp_user.ID,wp_user.user_email,IFNULL(meta.meta_value, 0) AS wps_points FROM " . self::$db->users . " as wp_user ";
        $query = $select . $join . $where . $limit_offset;
        $wp_users = self::$db->get_results(stripslashes($query));
        $this->migrateUsers($wp_users, $job_id, $data, $admin_mail, $action_type);
    }

    public function migrateUsers($wp_users, $job_id, $data, $admin_mail, $action_type)
    {
        $migration_job_model = new MigrationJob();
        $migration_log_model = new MigrationLog();
        if (count($wp_users) == 0) {
            $data_logs['action'] = 'wp_swings_migration_failed';
            $data_logs['note'] = __('No available records for processing.', 'wp-loyalty-bulk-action');
            $migration_log_model->saveLogs($data_logs, "wp_swings_migration");
            $update_data = array(
                "status" => "completed",
            );
            $migration_job_model->updateRow($update_data, array("uid" => $job_id));
            return;
        }
        $loyalty_user_model = new Users();

        $campaign = EarnCampaign::getInstance();
        $earn_campaign_transaction_model = new EarnCampaignTransactions();
        $helper_base = new \Wlr\App\Helpers\Base();
        $log_model = new Logs();
        foreach ($wp_users as $wp_user) {
            $user_email = !empty($wp_user) && is_object($wp_user) && isset($wp_user->user_email) && !empty($wp_user->user_email) ? $wp_user->user_email : "";
            if (empty($user_email)) {
                continue;
            }
            $user_id = !empty($wp_user) && is_object($wp_user) && isset($wp_user->ID) && !empty($wp_user->ID) ? $wp_user->ID : 0;
            $new_points = (int)(!empty($wp_user->wps_points)) ? $wp_user->wps_points : 0;
            //check user exist in loyalty
            $user_points = $loyalty_user_model->getQueryData(array('user_email' => array('operator' => '=', 'value' => sanitize_email($user_email))), '*', array(), false, true);
            $created_at = strtotime(date("Y-m-d h:i:s"));
            if (is_object($user_points) && !empty($user_points) && isset($user_points->user_email)) {
                //update last processed id
                $data->offset = $data->offset + 1;
                $data->last_processed_id = $user_id;
                $update_status = array(
                    "status" => "processing",
                    "offset" => $data->offset,
                    "last_processed_id" => $data->last_processed_id,
                    "updated_at" => $created_at,
                );
                $migration_job_model->updateRow($update_status, array('uid' => $job_id));
                continue;
            }
            $refer_code = $helper_base->get_unique_refer_code('', false, $user_email);
            $action_data = array(
                "user_email" => $user_email,
                "customer_command" => $data->comment,
                "points" => $new_points,
                "referral_code" => $refer_code,
                "action_process_type" => "earn_point",
                "customer_note" => sprintf(__("Added %d %s by site administrator(%s) via loyalty migration", "wp-loyalty-migration"), $new_points, $campaign->getPointLabel($new_points), $admin_mail),
                "note" => sprintf(__("%s customer migrated with %d %s by administrator(%s) via loyalty migration", "wp-loyalty-migration"), $user_email, $new_points, $campaign->getPointLabel($new_points), $admin_mail),
            );
            $trans_type = 'credit';
            if ($new_points == 0) {
                //add new user
                $_data = array(
                    'user_email' => sanitize_email($user_email),
                    'refer_code' => $refer_code,
                    'used_total_points' => 0,
                    'points' => (int)$new_points,
                    'earn_total_point' => (int)$new_points,
                    'birth_date' => 0,
                    'birthday_date' => null,
                    'created_date' => $created_at,
                );
                $wployalty_migration_status = $loyalty_user_model->insertRow($_data);
                $status = true;
                //campaign transaction
                $args = array(
                    'user_email' => $action_data['user_email'],
                    'action_type' => $action_type,
                    'campaign_type' => 'point',
                    'points' => (int)$new_points,
                    'transaction_type' => $trans_type,
                    'campaign_id' => (int)isset($action_data['campaign_id']) && !empty($action_data['campaign_id']) ? $action_data['campaign_id'] : 0,
                    'created_at' => $created_at,
                    'modified_at' => 0,
                    'product_id' => (int)isset($action_data['product_id']) && !empty($action_data['product_id']) ? $action_data['product_id'] : 0,
                    'order_id' => (int)isset($action_data['order_id']) && !empty($action_data['order_id']) ? $action_data['order_id'] : 0,
                    'order_currency' => isset($action_data['order_currency']) && !empty($action_data['order_currency']) ? $action_data['order_currency'] : '',
                    'order_total' => isset($action_data['order_total']) && !empty($action_data['order_total']) ? $action_data['order_total'] : '',
                    'referral_type' => isset($action_data['referral_type']) && !empty($action_data['referral_type']) ? $action_data['referral_type'] : '',
                    'display_name' => isset($action_data['reward_display_name']) && !empty($action_data['reward_display_name']) ? $action_data['reward_display_name'] : null,
                    'reward_id' => (int)isset($action_data['reward_id']) && !empty($action_data['reward_id']) ? $action_data['reward_id'] : 0,
                    'admin_user_id' => null,
                    'log_data' => '{}',
                    'customer_command' => isset($action_data['customer_command']) && !empty($action_data['customer_command']) ? $action_data['customer_command'] : '',
                    'action_sub_type' => isset($action_data['action_sub_type']) && !empty($action_data['action_sub_type']) ? $action_data['action_sub_type'] : '',
                    'action_sub_value' => isset($action_data['action_sub_value']) && !empty($action_data['action_sub_value']) ? $action_data['action_sub_value'] : '',
                );
                if (is_admin()) {
                    $admin_user = wp_get_current_user();
                    $args['admin_user_id'] = $admin_user->ID;
                }
                $earn_trans_id = $earn_campaign_transaction_model->insertRow($args);
                //wlr_log
                if ($earn_trans_id == 0) {
                    $status = false;
                }
                if ($status) {
                    $log_data = array(
                        'user_email' => sanitize_email($action_data['user_email']),
                        'action_type' => $action_type,
                        'earn_campaign_id' => (int)$earn_trans_id > 0 ? $earn_trans_id : 0,
                        'campaign_id' => $args['campaign_id'],
                        'note' => $action_data['note'],
                        'customer_note' => isset($action_data['customer_note']) && !empty($action_data['customer_note']) ? $action_data['customer_note'] : '',
                        'order_id' => $args['order_id'],
                        'product_id' => $args['product_id'],
                        'admin_id' => $args['admin_user_id'],
                        'created_at' => $created_at,
                        'modified_at' => 0,
                        'points' => (int)$new_points,
                        'action_process_type' => $action_data['action_process_type'],
                        'referral_type' => isset($action_data['referral_type']) && !empty($action_data['referral_type']) ? $action_data['referral_type'] : '',
                        'reward_id' => (int)isset($action_data['reward_id']) && !empty($action_data['reward_id']) ? $action_data['reward_id'] : 0,
                        'user_reward_id' => (int)isset($action_data['user_reward_id']) && !empty($action_data['user_reward_id']) ? $action_data['user_reward_id'] : 0,
                        'expire_email_date' => isset($action_data['expire_email_date']) && !empty($action_data['expire_email_date']) ? $action_data['expire_email_date'] : 0,
                        'expire_date' => isset($action_data['expire_date']) && !empty($action_data['expire_date']) ? $action_data['expire_date'] : 0,
                        'reward_display_name' => isset($action_data['reward_display_name']) && !empty($action_data['reward_display_name']) ? $action_data['reward_display_name'] : null,
                        'required_points' => (int)isset($action_data['required_points']) && !empty($action_data['required_points']) ? $action_data['required_points'] : 0,
                        'discount_code' => isset($action_data['discount_code']) && !empty($action_data['discount_code']) ? $action_data['discount_code'] : null,
                    );
                    $log_model->saveLog($log_data);
                }
            } else {
                $wployalty_migration_status = $campaign->addExtraPointAction($action_type, (int)$new_points, $action_data, $trans_type);
            }
            $data_logs = array(
                'job_id' => $job_id,
                'action' => 'wp_swings_migration',
                'user_email' => $user_email,
                'referral_code' => $refer_code,
                'points' => $new_points,
                'earn_total_points' => $new_points,
                'created_at' => strtotime(date("Y-m-d h:i:s")),
            );
            if (!$wployalty_migration_status) {
                $data_logs['action'] = 'wp_swings_migration_failed';
            }
            if ($migration_log_model->saveLogs($data_logs, "wp_swings_migration") > 0) {
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