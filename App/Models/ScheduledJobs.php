<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlrm\App\Models;

use stdClass;
use Wlr\App\Models\Base;
use Wlrm\App\Helper\Settings;
use Wlrm\App\Helper\WC;

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

    public static function getJobByAction($action = '')
    {
        if (empty($action) || !is_string($action)) {
            return [];
        }
        global $wpdb;
        /* check job exist or not */
        $where = self::$db->prepare('id > %d AND source_app = %s AND category =%s', [
            0,
            'wlr_migration',
            $action
        ]);
        $scheduled_jobs = new ScheduledJobs();

        return $scheduled_jobs->getWhere($where);
    }

    public static function insertData($post)
    {
        if (empty($post) || !is_array($post)) {
            return 0;
        }
        if (!empty($post['job_id'])) {
            $max_uid = $post['job_id'];
        } else {
            $max_uid = ScheduledJobs::getMaxUid();
        }

        $admin_mail = WC::getLoginUserEmail();
        $conditions = [
            'update_point' => !empty($post['update_point']) ? $post['update_point'] : 'skip',
            'update_banned_user' => !empty($post['update_banned_user']) ? $post['update_banned_user'] : 'skip',
        ];
        $job_data = [
            'uid' => $max_uid,
            'source_app' => 'wlr_migration',
            'admin_mail' => $admin_mail,
            'category' => !empty($post['migration_action']) ? $post['migration_action'] : "",
            'action_type' => 'migration_to_wployalty',
            'conditions' => json_encode($conditions),
            'status' => 'pending',
            'limit' => (int)Settings::get('batch_limit', 10),
            'offset' => 0,
            'last_processed_id' => 0,
            'created_at' => strtotime(date('Y-m-d h:i:s')),
        ];
        $job_table_model = new ScheduledJobs();

        return $job_table_model->insertRow($job_data);
    }

    public static function getMaxUid()
    {
        $cron_job_modal = new ScheduledJobs();
        $where = self::$db->prepare(' id > %d', [0]);

        $data_job = $cron_job_modal->getWhere($where, 'MAX(uid) as max_uid');
        $max_uid = 1;
        if (!empty($data_job) && is_object($data_job) && isset($data_job->max_uid)) {
            $max_uid = $data_job->max_uid + 1;
        }

        return $max_uid;
    }

    public static function getAvailableJob()
    {
        $job_table = new ScheduledJobs();
        $where = self::$db->prepare("  source_app = %s AND id > 0 AND status IN (%s,%s) ORDER BY id ASC", [
            "wlr_migration",
            "pending",
            "processing"
        ]);

        return $job_table->getWhere($where);
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
        $index_fields = array(
            "source_app",
            "category",
            "action_type",
            "status",
            "last_processed_id",
            "revert_enabled",
            "revert_status",
            "created_at"
        );
        $this->insertIndex($index_fields);
    }

    function getDataById($job_id)
    {
        if (empty($job_id) || $job_id == 0) {
            return new stdClass();
        }
        $where = self::$db->prepare(" source_app = %s AND uid = %d ", array("wlr_bulk_action", $job_id));

        return $this->getWhere($where, '*', true);
    }
}