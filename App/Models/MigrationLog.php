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
            "refer_code" => "%s",
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
                `refer_code` varchar(180) DEFAULT NULL,
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
        $index_fields = array('job_id','action','last_processed_id','status');
        $this->insertIndex($index_fields);
    }
}