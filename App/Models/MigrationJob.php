<?php

namespace Wlrm\App\Models;
use Wlr\App\Models\Base;

defined('ABSPATH') or die();

class MigrationJob extends Base
{
    function __construct()
    {
        parent::__construct();
        $this->table = self::$db->prefix . "wlr_migration_job";
        $this->primary_key = "id";
        $this->fields = array(
            "uid" => "%d",
            "admin_mail" => "%s",
            "action_type" => "%s",
            "action" => "%s",
            "comment" => "%s",
            "status" => "%s",
            "limit" => "%d",
            "offset" => "%d",
            "last_processed_id" => "%d",
            "condition" => "%s",
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
                `uid` BIGINT UNSIGNED NOT NULL,
                `admin_mail` varchar(180) DEFAULT NULL,
                `action_type` varchar(180) DEFAULT NULL,
                `action` varchar(180) DEFAULT NULL,
                `comment` longtext DEFAULT NULL,
                `status` varchar(180) DEFAULT NULL,
                `limit` INT(11) DEFAULT NULL,
                `offset` BIGINT DEFAULT 0,
                `last_processed_id` BIGINT DEFAULT 0,
                `condition` longtext DEFAULT NULL,
                `created_at` BIGINT DEFAULT 0,
                `updated_at` BIGINT DEFAULT 0,
                PRIMARY KEY (`{$this->getPrimaryKey()}`),
                UNIQUE KEY (`uid`)
        )";
        $this->createTable($create_table_query);
    }

    function afterTableCreation()
    {
        $index_fields = array('action_type','action','last_processed_id','status');
        $this->insertIndex($index_fields);
    }
}