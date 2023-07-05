<?php

namespace Wlrm\App\Controller\Compatibles;

use Wlrm\App\Controller\Base;

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

}