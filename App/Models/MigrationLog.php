<?php

namespace Wlrm\App\Models;

use Wlr\App\Models\Base;

class MigrationLog extends Base
{
    function __construct()
    {
        parent::__construct();
        $this->table = self::$db->prefix . "wlr_migration_log";
        $this->primary_key = "id";
        $this->fields = array(
            "job_id" => "%d",
            "action" => "%s",
            "user_email" => "%s",
            "referral_code" => "%s",
            "points" => "%d",
            "used_total_points" => "%d",
            "earn_total_points" => "%d",
            "birth_date" => "%d",
            "created_at" => "%d",
            "updated_at" => "%d",
        );
    }

    function beforeTableCreation()
    {
    }

    function runTableCreation()
    {
        $create_table_query = "CREATE TABLE IF NOT EXISTS {$this->table} (
                `{$this->getPrimaryKey()}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `job_id` BIGINT UNSIGNED NOT NULL,
                `action` varchar(180) DEFAULT NULL,
                `user_email` varchar(180) DEFAULT NULL,
                `referral_code` varchar(180) DEFAULT NULL,
                `points` BIGINT DEFAULT NULL,             
                `used_total_points` BIGINT DEFAULT NULL,             
                `earn_total_points` BIGINT DEFAULT NULL,             
                `birth_date` BIGINT DEFAULT 0,
                `created_at` BIGINT DEFAULT 0,
                `updated_at` BIGINT DEFAULT 0,
                PRIMARY KEY (`{$this->getPrimaryKey()}`)
        )";
        $this->createTable($create_table_query);
    }

    function afterTableCreation()
    {
        $index_fields = array('job_id', 'action');
        $this->insertIndex($index_fields);
    }

    function checkActionType($action_type)
    {
        return in_array($action_type, apply_filters('wlrmg_action_types', array('wp_swings_migration')));
    }

    function saveLogs($data, $action_type)
    {
        if (empty($data) || !is_array($data) || !is_string($action_type) ||
            !$this->checkActionType($action_type)) return false;
        $log_data = array(
            "job_id" => (int)isset($data['job_id']) && $data['job_id'] > 0 ? $data['job_id'] : 0,
            "action" => (string)isset($data['action']) && $data['action'] ? $data['action'] : '',
            "user_email" => (string)isset($data['user_email']) && !empty($data['user_email']) ? $data['user_email'] : '',
            "referral_code" => (string)isset($data['referral_code']) && !empty($data['referral_code']) ? $data['referral_code'] : '',
            "points" => (int)isset($data['points']) && $data['points'] > 0 ? $data['points'] : 0,
            "used_total_points" => (int)isset($data['used_total_points']) && $data['used_total_points'] > 0 ? $data['used_total_points'] : 0,
            "earn_total_points" => (int)isset($data['earn_total_points']) && $data['earn_total_points'] > 0 ? $data['earn_total_points'] : 0,
            "birth_date" => (int)isset($data['birth_date']) && $data['birth_date'] > 0 ? $data['birth_date'] : 0,
            "created_at" => strtotime(date("Y-m-d h:i:s")),
        );

        return $this->insertRow($log_data);
    }
}