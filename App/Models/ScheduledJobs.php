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

/**
 * Data access object for the wlr_scheduled_jobs table.
 *
 * Creates, reads, and updates parent and child migration jobs, including
 * helpers for range-batch metadata, enqueue cursor, and status queries.
 */
class ScheduledJobs extends Base
{
    function __construct()
    {
        parent::__construct();
        $this->table = self::$db->prefix . "wlr_scheduled_jobs";
        $this->primary_key = "id";
        $this->fields = [
            "uid" => "%s",
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
        ];
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
            $max_uid = ScheduledJobs::generateUuid();
        }

        $admin_mail = WC::getLoginUserEmail();
        $conditions = [
            'update_point' => !empty($post['update_point']) ? $post['update_point'] : 'skip',
            'update_banned_user' => !empty($post['update_banned_user']) ? $post['update_banned_user'] : 'skip',
        ];

        if (isset($post['total_count'])) {
            $conditions['total_count'] = (int) $post['total_count'];
        }
        if (isset($post['batch_limit'])) {
            $conditions['batch_limit'] = (int) $post['batch_limit'];
        }
        if (isset($post['last_enqueued_id'])) {
            $conditions['last_enqueued_id'] = (int) $post['last_enqueued_id'];
        }
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

    /**
     * Get batch information from job conditions
     *
     * @param object|array $job_data Job data object or array
     * @return array|null Batch information or null if not found
     */
    public static function getBatchInfo($job_data)
    {
        if (empty($job_data) || !is_object($job_data) || !isset($job_data->conditions)) {
            return null;
        }
        
        $conditions = $job_data->conditions;
        
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

    /**
     * Get pending jobs for a specific category. Used for queueing child jobs only.
     * Excludes parent jobs by requiring batch_info to be present in conditions JSON.
     *
     * @param string $category
     * @return array
     */
    public static function getPendingJobsByCategory($category)
    {
        if (empty($category)) {
            return [];
        }
        $job_table = new ScheduledJobs();
        $where = self::$db->prepare(
            "source_app = %s AND category = %s AND id > 0 AND status = %s AND conditions LIKE %s ORDER BY id ASC",
            [
                'wlr_migration',
                $category,
                'pending',
                '%\"batch_info\"%'
            ]
        );

        return $job_table->getWhere($where, '*', false);
    }

    /**
     * Get parent jobs that are pending or active (processing) across all categories.
     * Parent jobs are identified by conditions not containing parent_job_id (i.e., no batch_info/parent link).
     *
     * @return array
     */
    public static function getParentJobsPendingOrActive()
    {
        $job_table = new ScheduledJobs();
        $where = self::$db->prepare(
            "source_app = %s AND id > 0 AND status IN (%s,%s) AND (conditions NOT LIKE %s) ORDER BY created_at ASC, id ASC",
            [
                'wlr_migration',
                'pending',
                'processing',
                '%\"parent_job_id\"%'
            ]
        );
        return $job_table->getWhere($where, '*', false);
    }

    /**
     * Get parent job for a specific category that is pending or active
     *
     * @param string $category
     * @return object|null
     */
    public static function getParentJobByCategory($category)
    {
        if (empty($category)) {
            return null;
        }
        $job_table = new ScheduledJobs();
        $where = self::$db->prepare(
            "source_app = %s AND category = %s AND id > 0 AND status IN (%s,%s) AND (conditions NOT LIKE %s) ORDER BY created_at ASC, id ASC",
            [
                'wlr_migration',
                $category,
                'pending',
                'processing',
                '%\"parent_job_id\"%'
            ]
        );
        return $job_table->getWhere($where);
    }

    /**
     * Get all batch jobs for a given parent job ID
     *
     * @param string $parent_job_id Parent job ID
     * @return array Array of batch job objects
     */
    public static function getBatchesByParent($parent_job_id)
    {
        if (empty($parent_job_id)) {
            return [];
        }
        
        $job_table = new ScheduledJobs();
        $like_numeric = '%"parent_job_id":' . (string)$parent_job_id . '%';
        $like_string  = '%"parent_job_id":"' . (string)$parent_job_id . '"%';
        $where = self::$db->prepare(
            "source_app = %s AND (uid = %s OR conditions LIKE %s OR conditions LIKE %s) ORDER BY uid ASC",
            [
                "wlr_migration",
                (string)$parent_job_id,
                $like_numeric,
                $like_string
            ]
        );
        
        return $job_table->getWhere($where, '*', false);
    }

    /**
     * Insert a child job representing a range batch.
     * The child job inherits category and options from parent and stores batch_info with start/end ids.
     *
     * @param object|array $parent_job
     * @param int $start_id
     * @param int $end_id
     * @param int $limit
     * @return int Inserted row id or 0 on failure
     */
    public static function insertChildRangeJob($parent_job, $start_id, $end_id, $limit)
    {
        if (empty($parent_job) || $end_id <= $start_id) {
            return 0;
        }
        if (is_array($parent_job)) {
            $parent_job = (object) $parent_job;
        }

        $job_table_model = new ScheduledJobs();
        $max_uid = ScheduledJobs::generateUuid();

        $parent_conditions = [];
        if (!empty($parent_job->conditions)) {
            $decoded = json_decode($parent_job->conditions, true);
            if (is_array($decoded)) {
                $parent_conditions = $decoded;
            }
        }

        $parent_uid = isset($parent_job->uid) ? (string)$parent_job->uid : '';
        $batch_info = [
            'parent_job_id' => $parent_uid,
            'start_id' => (int)$start_id,
            'end_id' => (int)$end_id,
            'limit' => (int)$limit,
        ];
        $parent_conditions['batch_info'] = $batch_info;

        $job_data = [
            'uid' => $max_uid,
            'source_app' => 'wlr_migration',
            'admin_mail' => isset($parent_job->admin_mail) ? $parent_job->admin_mail : WC::getLoginUserEmail(),
            'category' => isset($parent_job->category) ? $parent_job->category : '',
            'action_type' => 'migration_to_wployalty',
            'conditions' => json_encode($parent_conditions),
            'status' => 'pending',
            'limit' => (int) $limit,
            'offset' => 0,
            'last_processed_id' => 0,
            'created_at' => strtotime(gmdate('Y-m-d h:i:s')),
        ];

        return $job_table_model->insertRow($job_data);
    }

    /**
     * Update parent's last_enqueued_id cursor
     *
     * @param string $parent_uid
     * @param int $end_id
     * @return bool
     */
    public static function updateParentEnqueuedCursor($parent_uid, $end_id)
    {
        if (empty($parent_uid) || $end_id < 0) {
            return false;
        }
        $job_table = new ScheduledJobs();
        global $wpdb;
        $parent = $job_table->getWhere($wpdb->prepare(" uid = %s AND source_app = %s", [$parent_uid, 'wlr_migration']));
        if (empty($parent) || !is_object($parent)) {
            return false;
        }
        $conditions = [];
        if (!empty($parent->conditions)) {
            $decoded = json_decode($parent->conditions, true);
            if (is_array($decoded)) {
                $conditions = $decoded;
            }
        }
        $conditions['last_enqueued_id'] = (int) $end_id;
        $update_data = [
            'conditions' => json_encode($conditions),
            'updated_at' => strtotime(gmdate('Y-m-d h:i:s')),
        ];
        return (bool) $job_table->updateRow($update_data, [
            'uid' => (string)$parent_uid,
            'source_app' => 'wlr_migration'
        ]);
    }

    /**
     * Count active jobs by category (pending, processing)
     *
     * @param string $category
     * @return int
     */
    public static function countActiveJobsByCategory($category)
    {
        if (empty($category)) {
            return 0;
        }
        $job_table = new ScheduledJobs();
        $where = self::$db->prepare(
            "source_app = %s AND category = %s AND id > 0 AND status IN (%s,%s)",
            ['wlr_migration', $category, 'pending', 'processing']
        );
        $row = $job_table->getWhere($where, 'COUNT(1) AS cnt');
        if (!empty($row) && is_object($row) && isset($row->cnt)) {
            return (int) $row->cnt;
        }
        return 0;
    }



    function beforeTableCreation()
    {
    }

    function runTableCreation()
    {
        $create_table_query = "CREATE TABLE IF NOT EXISTS {$this->table} (
				 `{$this->getPrimaryKey()}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				 `uid` VARCHAR(8) NOT NULL,
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
        $index_fields = [
            "source_app",
            "category",
            "action_type",
            "status",
            "last_processed_id",
            "revert_enabled",
            "revert_status",
            "created_at"
        ];
        $this->insertIndex($index_fields);
    }

    /**
     * Generate UUID
     *
     * @param int $length
     * @return string
     */
    public static function generateUuid($length = 8)
    {
        return substr(md5(uniqid()), -$length);
    }

}