<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlrm\App\Models;

use Wlr\App\Models\Base;

defined("ABSPATH") or die();

class ScheduledJobs extends Base
{
    function __construct()
    {
        parent::__construct();
        $this->table = self::$db->prefix . "wlr_scheduled_jobs";
        $this->primary_key = "id";
        $this->fields = array(
            "uid" => "%d",
            "source_app" => "%s",
            "admin_mail" => "%s",
            "category" => "%s",
            "action_type" => "%s",
            "conditions" => "%s",
            "status" => "%s",
            "limit" => "%d",
            "offset" => "%d",
            "last_processed_id" => "%d",
            "revert_enabled" => "%s",
            "revert_status" => "%s",
            "revert_offset" => "%d",
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
				 `source_app` varchar(180) DEFAULT NULL,
				 `admin_mail` varchar(180) DEFAULT NULL,
				 `category` varchar(180) DEFAULT NULL,
				 `action_type` varchar(180) DEFAULT NULL,
				 `conditions` longtext DEFAULT NULL,
				 `status` varchar(50) DEFAULT NULL,
				 `limit` INT(11) DEFAULT 0,
                 `offset` BIGINT DEFAULT NULL,
				 `last_processed_id` BIGINT DEFAULT 0,
  				 `revert_enabled` varchar(50) DEFAULT 'not_yet',             
                 `revert_status` varchar(50) DEFAULT 'not_yet',
                 `revert_offset` INT(11) DEFAULT NULL,
                 `created_at` BIGINT DEFAULT 0,
				 `updated_at` BIGINT DEFAULT 0,
				  PRIMARY KEY (`{$this->getPrimaryKey()}`),
                  UNIQUE KEY (`uid`))
				 ";
        $this->createTable($create_table_query);
    }

    function afterTableCreation()
    {
        $index_fields = array("source_app", "category", "action_type", "status", "last_processed_id", "revert_enabled", "revert_status", "created_at");
        $this->insertIndex($index_fields);
    }

    function getDataById($job_id)
    {
        if (empty($job_id) || $job_id == 0) return new \stdClass();
        $where = self::$db->prepare(" source_app = %s AND uid = %d ", array("wlr_bulk_action", $job_id));
        return $this->getWhere($where, '*', true);
    }
}