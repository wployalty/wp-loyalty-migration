<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */
namespace Wlrm\App\Models;

use Wlr\App\Helpers\Input;
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
        return in_array($action_type, apply_filters('wlrmg_action_types', array('wp_swings_migration','wlpr_migration','woocommerce_migration')));
    }

    function saveLogs($data, $action)
    {
        if (empty($data) || !is_array($data) || !is_string($action) ||
            !$this->checkActionType($action)) return false;
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

    function getActivityList($job_id, $current_page)
    {
        if (empty($job_id) || empty($current_page)) {
            return array();
        }
        $input = new Input();
        $cron_job_modal = new ScheduledJobs();
        $where = self::$db->prepare(" uid = %d AND source_app=%s",array($job_id,'wlr_migration'));
        $job_data = $cron_job_modal->getwhere($where);

        $settings = get_option('wlrmg_settings');
        $default_pagination_limit = !empty($settings) && is_array($settings) && $settings['pagination_limit'] > 0 ? $settings['pagination_limit'] : 10;
        $search = (string)$input->post_get("search", "");
        $search = sanitize_text_field($search);
        $limit = (int)$input->post_get("per_page", $default_pagination_limit);
        $offset = $limit * ($current_page - 1);
        $where = self::$db->prepare(" id > %d AND action NOT IN(%s)", array(0,$job_data->category.'_completed'));
        if (!empty($job_id)) {
            $where .= self::$db->prepare(" AND job_id=%d ", array($job_id));
        }
        if (!empty($search)) {
            $search_key = '%' . $search . '%';
            $where .= self::$db->prepare(' AND (user_email like %s )', array($search_key));
        }
        $where .= self::$db->prepare(" ORDER BY %s DESC ", array('id'));
        $total_count = $this->getWhere($where, "COUNT(id) as total_count", true);
        if (($offset >= 0) && !empty($limit)) {
            $where .= self::$db->prepare(" LIMIT %d OFFSET %d ", array($limit, $offset));
        }
        $log_list = $this->getWhere($where, "*", false);

        $export_files = $this->exportFileList(array('category' => $job_data->category, 'job_id' => $job_id));
        return apply_filters('wlrmg_before_activity_log_list', array(
            'data' => $log_list,
            "total_rows" => isset($total_count->total_count) && $total_count->total_count > 0 ? $total_count->total_count : 0,
            "current_page" => $current_page,
            "per_page" => $limit,
            'export_file_list' => $export_files,
        ));
    }
    function exportFileList($post)
    {
        $path = WLRMG_PLUGIN_DIR . '/App/File/' . $post['job_id'];
        $file_name = $post['category'] . '_' . $post['job_id'] . '_export_*.*';
        $delete_file_path = trim($path . '/' . $file_name);
        $download_list = array();
        foreach (glob($delete_file_path) as $file_path) {
            if (file_exists($file_path)) {
                $file_detail = new \stdClass();
                $file_detail->file_name = basename($file_path);
                $file_detail->file_path = $file_path;
                $file_detail->file_url = rtrim(WLRMG_PLUGIN_URL, '/') . '/App/File/' . $post['job_id'] . '/' . $file_detail->file_name;
                $download_list[] = $file_detail;
            }
        }
        return $download_list;
    }
}