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

        // Store single-job batch metadata
        if (isset($post['total_count'])) {
            $conditions['total_count'] = (int) $post['total_count'];
        }
        if (isset($post['batch_limit'])) {
            $conditions['batch_limit'] = (int) $post['batch_limit'];
        }
        // Store per-batch info (for multi-row batches)
        if (!empty($post['batch_info']) && is_array($post['batch_info'])) {
            $conditions['batch_info'] = $post['batch_info'];
        }
        
        $job_data = [
            'uid' => $max_uid,
            'source_app' => 'wlr_migration',
            'admin_mail' => $admin_mail,
            'category' => !empty($post['migration_action']) ? $post['migration_action'] : "",
            'action_type' => 'migration_to_wployalty',
            'conditions' => json_encode($conditions),
            'status' => 'pending',
            'limit' => (int)Settings::get('batch_limit', 50),
            'offset' => 0,
            'last_processed_id' => 0,
            'created_at' => strtotime(gmdate('Y-m-d h:i:s')),
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

    /**
     * Get batch information from job conditions
     *
     * @param object|array $job_data Job data object or array
     * @return array|null Batch information or null if not found
     */
    public static function getBatchInfo($job_data)
    {
        if (empty($job_data)) {
            return null;
        }
        
        // Handle both arrays and objects
        $conditions = null;
        if (is_object($job_data) && !empty($job_data->conditions)) {
            $conditions = $job_data->conditions;
        } elseif (is_array($job_data) && !empty($job_data['conditions'])) {
            $conditions = $job_data['conditions'];
        } else {
            return null;
        }
        
        $conditions = json_decode($conditions, true);
        return isset($conditions['batch_info']) ? $conditions['batch_info'] : null;
    }



    /**
     * Check if a job is a batch job (has batch information)
     *
     * @param object|array $job_data Job data object or array
     * @return bool True if job has batch information
     */
    public static function isBatchJob($job_data)
    {
        return self::getBatchInfo($job_data) !== null;
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

    /**
     * Get all available jobs for parallel processing
     * This method returns multiple jobs that can be processed simultaneously
     *
     * @return array Array of job objects
     */
    public static function getAvailableJobs()
    {
        $job_table = new ScheduledJobs();
        $where = self::$db->prepare("source_app = %s AND id > 0 AND status = %s ORDER BY id ASC", [
            "wlr_migration",
            "pending"
        ]);

        return $job_table->getWhere($where, '*', false);
    }

    /**
     * Get all batch jobs for a given parent job ID
     *
     * @param int $parent_job_id Parent job ID
     * @return array Array of batch job objects
     */
    public static function getBatchesByParent($parent_job_id)
    {
        if (empty($parent_job_id) || $parent_job_id <= 0) {
            return [];
        }
        
        $job_table = new ScheduledJobs();
        // Match both numeric and string parent_job_id encodings in JSON
        $like_numeric = '%"parent_job_id":' . (int)$parent_job_id . '%';
        $like_string  = '%"parent_job_id":"' . (int)$parent_job_id . '"%';
        $where = self::$db->prepare(
            "source_app = %s AND (uid = %d OR conditions LIKE %s OR conditions LIKE %s) ORDER BY uid ASC",
            [
                "wlr_migration",
                $parent_job_id,
                $like_numeric,
                $like_string
            ]
        );
        
        return $job_table->getWhere($where, '*', false);
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