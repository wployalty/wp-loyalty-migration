<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlrmg\App\Models;

use stdClass;
use Wlr\App\Helpers\Input;
use Wlr\App\Models\Base;

class MigrationLog extends Base
{
    function __construct()
    {
        parent::__construct();
        $this->table = self::$db->prefix . "wlr_migration_log";
        $this->primary_key = "id";
        $this->fields = [
            "job_id" => "%s",
            "action" => "%s",
            "user_email" => "%s",
            "referral_code" => "%s",
            "points" => "%d",
            "created_at" => "%d",
            "updated_at" => "%d",
        ];
    }

    public static function getLogCount($action, $job_id_or_ids)
    {
        if (empty($job_id_or_ids) || empty($action) || !is_string($action)) {
            return 0;
        }

        $log_table = new MigrationLog();
        // Base filter
        $query = self::$db->prepare(" id > 0 AND action != %s AND user_email !='' ", [
            $action . "_completed",
        ]);
        // Filter by one or many job ids
        if (is_array($job_id_or_ids)) {
            $ids = array_map('strval', $job_id_or_ids);
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '%s'));
                $query .= self::$db->prepare(" AND job_id IN ($placeholders) ", $ids);
            }
        } else {
            $query .= self::$db->prepare(" AND job_id = %s ", [(string)$job_id_or_ids]);
        }
        $log_count = $log_table->getWhere($query, "count(*) as total_count", true);

        return !empty($log_count) ? $log_count->total_count : 0;
    }

    function beforeTableCreation()
    {
    }

    function runTableCreation()
    {
        $create_table_query = "CREATE TABLE IF NOT EXISTS {$this->table} (
                `{$this->getPrimaryKey()}` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `job_id` VARCHAR(8) NOT NULL,
                `action` varchar(180) DEFAULT NULL,
                `user_email` varchar(180) DEFAULT NULL,
                `referral_code` varchar(180) DEFAULT NULL,
                `points` BIGINT DEFAULT NULL,
                `created_at` BIGINT DEFAULT 0,
                `updated_at` BIGINT DEFAULT 0,
                PRIMARY KEY (`{$this->getPrimaryKey()}`)
        )";
        $this->createTable($create_table_query);
    }

    function afterTableCreation()
    {
        $index_fields = ['job_id', 'action'];
        $this->insertIndex($index_fields);
    }

    function saveLogs($data, $action)
    {
        if (empty($data) || !is_array($data) || !is_string($action) || !$this->checkActionType($action)) {
            return false;
        }

        // Prepare log data
        $log_data = [
            "job_id" => (string)(isset($data['job_id']) && !empty($data['job_id']) ? $data['job_id'] : ''),
            "action" => (string)(isset($data['action']) && $data['action'] ? $data['action'] : ''),
            "user_email" => (string)(isset($data['user_email']) && !empty($data['user_email']) ? $data['user_email'] : ''),
            "referral_code" => (string)(isset($data['referral_code']) && !empty($data['referral_code']) ? $data['referral_code'] : ''),
            "points" => (int)(isset($data['points']) && $data['points'] > 0 ? $data['points'] : 0),
            "updated_at" => strtotime(gmdate("Y-m-d h:i:s")),
        ];

        // Check if user and action exist
        $existing_log = $this->getWhere(
            self::$db->prepare("user_email = %s AND action = %s", [$log_data['user_email'], $log_data['action']]),
            "*",
            true
        );

        if ($existing_log) {
            // Update existing row
            $update_where = self::$db->prepare("id = %d", [$existing_log->id]);
            return $this->updateRow($log_data, $update_where);
        } else {
            // Insert new row
            $log_data["created_at"] = strtotime(gmdate("Y-m-d h:i:s"));
            return $this->insertRow($log_data);
        }
    }


    function checkActionType($action_type)
    {
        return in_array($action_type, apply_filters('wlrmg_action_types', [
            'wp_swings_migration',
            'wlpr_migration',
            'woocommerce_migration'
        ]));
    }

    function getActivityList($job_id_or_ids, $current_page)
    {
        if (empty($job_id_or_ids) || empty($current_page)) {
            return [];
        }
        $input = new Input();
        $cron_job_modal = new ScheduledJobs();
        // Determine a representative job for metadata (category, exports)
        $representative_job_id = is_array($job_id_or_ids) ? (string)reset($job_id_or_ids) : (string)$job_id_or_ids;
        $where = self::$db->prepare(" uid = %s AND source_app=%s", [$representative_job_id, 'wlr_migration']);
        $job_data = $cron_job_modal->getwhere($where);

        $settings = get_option('wlrmg_settings');
        $default_pagination_limit = !empty($settings) && is_array($settings) && $settings['pagination_limit'] > 0 ? $settings['pagination_limit'] : 10;
        $search = (string)$input->post_get("search", "");
        $search = sanitize_text_field($search);
        $limit = (int)$input->post_get("per_page", $default_pagination_limit);
        $offset = $limit * ($current_page - 1);
        $where = self::$db->prepare(" id > %d AND action NOT IN(%s)", [
            0,
            $job_data->category . '_completed'
        ]);
        if (!empty($job_id_or_ids)) {
            if (is_array($job_id_or_ids)) {
                $ids = array_map('strval', $job_id_or_ids);
                $placeholders = implode(',', array_fill(0, count($ids), '%s'));
                $where .= self::$db->prepare(" AND job_id IN ($placeholders) ", $ids);
            } else {
                $where .= self::$db->prepare(" AND job_id=%s ", [(string)$job_id_or_ids]);
            }
        }
        if (!empty($search)) {
            $search_key = '%' . $search . '%';
            $where .= self::$db->prepare(' AND (user_email like %s )', [$search_key]);
        }
        $where .= self::$db->prepare(" ORDER BY %s DESC ", ['id']);
        $total_count = $this->getWhere($where, "COUNT(id) as total_count", true);
        if (($offset >= 0) && !empty($limit)) {
            $where .= self::$db->prepare(" LIMIT %d OFFSET %d ", [$limit, $offset]);
        }
        $log_list = $this->getWhere($where, "*", false);
        $export_uid = $representative_job_id;
        if (!empty($job_data) && is_object($job_data) && !empty($job_data->conditions)) {
            $decoded = json_decode($job_data->conditions, true);
            if (is_array($decoded) && !empty($decoded['batch_info']['parent_job_id'])) {
                $export_uid = (string)$decoded['batch_info']['parent_job_id'];
            }
        }

        $export_files = $this->exportFileList(['category' => $job_data->category, 'job_id' => $export_uid]);

        return apply_filters('wlrmg_before_activity_log_list', [
            'data' => $log_list,
            "total_rows" => isset($total_count->total_count) && $total_count->total_count > 0 ? $total_count->total_count : 0,
            "current_page" => $current_page,
            "per_page" => $limit,
            'export_file_list' => $export_files,
        ]);
    }

    function exportFileList($post)
    {
        $path = WLRMG_PLUGIN_DIR . '/App/File/' . $post['job_id'];

	    switch ($post['category']) {
		    case 'woocommerce_migration' :
			    $file_name = 'wc_customer_migration_export_*.*';
			    break;// woocommerce export
		    case 'wlpr_migration':
			    $file_name = 'wlr_customer_migration_export_*.*'; //loyalty export
			    break;
		    case 'wp_swings_migration':
			    $file_name = 'wpswing_customer_migration_export_*.*';
			    break;
		    default:
			    $file_name = 'customer_migration_export_*.*';
			    break;
	    }

        $delete_file_path = trim($path . '/' . $file_name);
        $download_list = [];
        foreach (glob($delete_file_path) as $file_path) {
            if (file_exists($file_path)) {
                $file_detail = new stdClass();
                $file_detail->file_name = basename($file_path);
                $file_detail->file_path = $file_path;
                $file_detail->file_url = rtrim(WLRMG_PLUGIN_URL, '/') . '/App/File/' . $post['job_id'] . '/' . $file_detail->file_name;
                $download_list[] = $file_detail;
            }
        }

        return $download_list;
    }
}