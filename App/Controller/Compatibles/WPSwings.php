<?php

namespace Wlrm\App\Controller\Compatibles;

use Wlr\App\Helpers\EarnCampaign;
use Wlr\App\Models\Users;
use Wlrm\App\Controller\Base;
use Wlrm\App\Models\MigrationJob;
use Wlrm\App\Models\MigrationLog;

defined('ABSPATH') or die();

class WPSwings extends Base
{
    function Migrate($data)
    {
        if (empty($data) || !is_object($data)) {
            return;
        }
        $job_id = (int)isset($data->uid) && !empty($data->uid) ? $data->uid : 0;
        $admin_mail = (string)isset($data->admin_mail) && !empty($data->admin_mail) ? $data->admin_mail : '';
        $action_type = (string)isset($data->action_type) && !empty($data->action_type) ? $data->action_type : "migration_to_wployalty";
        //1. get wp users and points in usermeta in key like wps_wpr_usermeta
        //2. foreach user check user exist in WPLoyalty
        //3. if user exist then update last process id and continue to next user
        //4. if not exist then add user with points and update level for their points
        //Get WPUsers
        $where = self::$db->prepare(" WHERE wp_user.ID > %d ", array(0));
        $where .= self::$db->prepare(" AND wp_user.ID > %d ", array((int)$data->last_process_id));
        $join = " LEFT JOIN " . self::$db->usermeta . " AS meta ON wp_user.ID = meta.user_id AND meta.meta_key = 'wps_wpr_points' ";
        $limit_offset = "";
        if (isset($data->limit) && ($data->limit > 0)) {
            $limit_offset .= self::$db->prepare(" LIMIT %d OFFSET %d ", array((int)$data->limit, 0));
        }
        $select = " SELECT wp_user.ID,wp_user.user_email,IFNULL(meta.meta_value, 0) AS wps_points FROM " . self::$db->users . " as wp_user ";
        $query = $select . $join . $where . $limit_offset;
        $wp_users = self::$db->get_results(stripslashes($query));
        $loyalty_user_model = new Users();
        $migration_job_model = new MigrationJob();
        $migration_log_model = new MigrationLog();
        $campaign = EarnCampaign::getInstance();
        $helper_base = new \Wlr\App\Helpers\Base();
        foreach ($wp_users as $wp_user) {
            $user_email = !empty($wp_user) && is_object($wp_user) && isset($wp_user->user_email) && !empty($wp_user->user_email) ? $wp_user->user_email : "";
            if (empty($user_email)) {
                continue;
            }
            $user_id = !empty($wp_user) && is_object($wp_user) && isset($wp_user->ID) && !empty($wp_user->ID) ? $wp_user->ID : 0;
            $new_points = (int)(!empty($wp_user->wps_points)) ? $wp_user->wps_points : 0;
            //check user exist in loyalty
            $user_points = $loyalty_user_model->getQueryData(array('user_email' => array('operator' => '=', 'value' => sanitize_email($user_email))), '*', array(), false, true);
            if (is_object($user_points) && !empty($user_points) && isset($user_points->user_email)) {
                //update last processed id
                $data->offset = $data->offset + 1;
                $data->last_process_id = $user_id;
                $update_status = array(
                    "status" => "processing",
                    "offset" => $data->offset,
                    "last_process_id" => $data->last_process_id,
                    "modified_date" => strtotime(date("Y-m-d h:i:s")),
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
            $wployalty_migration_status = $campaign->addExtraPointAction($action_type, (int)$new_points, $action_data);
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
                $data->last_process_id = $user_id;
                $update_status = array(
                    "status" => "processing",
                    "offset" => $data->offset,
                    "last_process_id" => $data->last_process_id,
                    "modified_date" => strtotime(date("Y-m-d h:i:s")),
                );
                $migration_job_model->updateRow($update_status, array('uid' => $job_id));
            }
        }
    }
}