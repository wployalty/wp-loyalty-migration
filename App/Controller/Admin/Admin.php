<?php

namespace Wlrm\App\Controller\Admin;

use Wlr\App\Helpers\Woocommerce;
use Wlrm\App\Controller\Base;
use Wlrm\App\Controller\Compatibles\WPSwings;
use Wlrm\App\Controller\Compatibles\Yith;
use Wlrm\App\Helper\CompatibleCheck;
use Wlrm\App\Helper\Pagination;
use Wlrm\App\Models\MigrationJob;
use Wlrm\App\Models\MigrationLog;

defined("ABSPATH") or die();

class Admin extends Base
{
    function pluginActivation()
    {
        $check = new CompatibleCheck();
        if ($check->init_check(true)) {
            try {
                $this->createRequiredTable();
            } catch (\Exception $e) {
                exit(esc_html(WLRMG_PLUGIN_NAME . __('Plugin required table creation failed.', 'wp-loyalty-migration')));
            }
        }
        return true;
    }

    function createRequiredTable()
    {
        try {
            $job = new MigrationJob();
            $job->create();
            $log = new MigrationLog();
            $log->create();
        } catch (\Exception $e) {
            exit(WLRMG_PLUGIN_NAME . __("Plugin required table creation failed.", "wp-loyalty-migration"));
        }
    }

    public function addAdminMenu()
    {
        if (Woocommerce::hasAdminPrivilege()) {
            add_menu_page(__("WPLoyalty: Migration", "wp-loyalty-migration"), __("WPLoyalty: Migration", "wp-loyalty-migration"), "manage_woocommerce", WLRMG_PLUGIN_SLUG, array($this, "addMigrationPage"), 'dashicons-megaphone', 58);
        }
    }

    /**
     * @return string|void
     */
    function addMigrationPage()
    {
        if (!Woocommerce::hasAdminPrivilege()) return "";
        $view = (string)self::$input->post_get("view", "activity");
        $params = array(
            'current_page' => $view,
            'main_page' => array(),
        );
        switch ($view) {
            case 'actions':
                $params['main_page']['actions'] = $this->getActionsPage();
                break;
            case 'settings':
                $params['main_page']['settings'] = $this->getSettingsPage();
                break;
            case 'activity_details':
                $params['main_page']['activity_details'] = $this->getActivityDetailsPage();
                break;
            case 'activity':
            default:
                $params['main_page']['activity'] = $this->getActivityPage();
                break;
        }
        self::$template->setData(WLRMG_VIEW_PATH . '/Admin/main.php', $params)->display();
    }

    function getActivityDetailsPage()
    {
        $view = (string)self::$input->post_get("view", "activity");
        $type = (string)self::$input->post_get("type", "");
        $job_id = (int)self::$input->post_get("job_id", 0);
        $args = array(
            "current_page" => $view,
            "action" => $type,
            "activity" => $this->getActivityDetailsData($job_id),
            "back" => WLRMG_PLUGIN_URL . "Assets/svg/back_button.svg",
            "no_activity_icon" => WLRMG_PLUGIN_URL . "Assets/svg/no_activity_list.svg",
        );
        $args = apply_filters('wlba_before_activity_details_page', $args);
        return self::$template->setData(WLRMG_VIEW_PATH . "/Admin/activity_details.php", $args)->render();
    }

    function getActivityDetailsData($job_id)
    {
        if (empty($job_id) || $job_id <= 0) return array();
        $job_table = new MigrationJob();
        $where = self::$db->prepare(" uid = %d ", array($job_id));
        $job_data = $job_table->getWhere($where);
        $result = array(
            'job_id' => $job_id,
        );
        if (!empty($job_data) && is_object($job_data)) {
            $result['job_data'] = $this->processJobData($job_data);
            $result['activity'] = $this->getActivityLogsData($job_id);
        }
        return apply_filters('wlba_before_acitivity_view_details_data', $result);
    }
    function processJobData($job_data){
        if (empty($job_data) || !is_object($job_data)) return array();
        $result = array(
                'created_at' => isset($job_data->created_at) && !empty($job_data->created_at) ? self::$woocommerce->beforeDisplayDate($job_data->created_at) : '',
                'offset' => isset($job_data->offset) && !empty($job_data->offset) ? $job_data->offset : 0,
                'admin_mail' => isset($job_data->admin_mail) && !empty($job_data->admin_mail) ? $job_data->admin_mail : '',
                'status' => isset($job_data->status) && !empty($job_data->status) ? $job_data->status : '',
                'action' => isset($job_data->action) && !empty($job_data->action) ? $job_data->action : '',
        );
        if (is_object($job_data) && isset($job_data->action)){
            switch ($job_data->action){
                case 'wp_swings_migration':
                    $result['action_label'] = __('WP Swings Migration','wp-loyalty-migration');
                    break;
                case 'yith_migration':
                case 'woocommerce_migration':
                default:
                    break;
            }
        }
        return $result;
    }

    function getActivityLogsData($job_id)
    {
        if (empty($job_id) || $job_id <= 0) return array();
        $bulk_action_log = new MigrationLog();
        $url = admin_url('admin.php?' . http_build_query(array(
                'page' => WLRMG_PLUGIN_SLUG,
                'view' => 'activity_details',
                'job_id' => $job_id,
            )));
        $current_page = (int)self::$input->post_get("migration_page", 1);
        $activity_list = $bulk_action_log->getActivityList($job_id, $current_page);
        $per_page = (int)is_array($activity_list) && isset($activity_list["per_page"]) ? $activity_list["per_page"] : 0;
        $current_page = (int)is_array($activity_list) && isset($activity_list["current_page"]) ? $activity_list["current_page"] : 0;
        $pagination_param = array(
            "totalRows" => (int)is_array($activity_list) && isset($activity_list["total_rows"]) ? $activity_list["total_rows"] : 0,
            "perPage" => $per_page,
            "baseURL" => $url,
            "currentPage" => $current_page,
            "queryStringSegment" => "migration_page",
        );
        $pagination = new Pagination($pagination_param);
        return apply_filters('wlrmg_bulk_action_activity_details', array(
            "base_url" => $url,
            "pagination" => $pagination,
            "per_page" => $per_page,
            "page_number" => $current_page,
            "activity_list" => (array)is_array($activity_list) && isset($activity_list["data"]) ? $activity_list["data"] : array(),
            'show_export_file_download' => (int)is_array($activity_list) && isset($activity_list["export_file_list"]) ? count($activity_list["export_file_list"]) : 0,
            'export_csv_file_list' => is_array($activity_list) && isset($activity_list["export_file_list"]) ? $activity_list["export_file_list"] : array(),
        ));
    }

    function getActivityPage()
    {
        $view = (string)self::$input->post_get("view", "activity");
        $condition = (string)self::$input->post_get("condition", "all");
        $url = admin_url("admin.php?" . http_build_query(array("page" => WLRMG_PLUGIN_SLUG, "view" => "activity", "condition" => $condition)));
        $activity_list = $this->getActivityLogs();
        $per_page = (int)is_array($activity_list) && isset($activity_list["per_page"]) ? $activity_list["per_page"] : 0;
        $current_page = (int)is_array($activity_list) && isset($activity_list["current_page"]) ? $activity_list["current_page"] : 0;
        $params = array(
            "totalRows" => (int)is_array($activity_list) && isset($activity_list["total_rows"]) ? $activity_list["total_rows"] : 0,
            "perPage" => $per_page,
            "baseURL" => $url,
            "currentPage" => $current_page,
        );
        $filter_condition_list = apply_filters('wlrmg_activity_condition_list',array(
            "all" => __("All", "wp-loyalty-migration"))
        );
        if (WPSwings::checkPluginIsActive()){
            $filter_condition_list["wp_swings"] =  __("WPSwings", "wp-loyalty-migration");
        }
        $pagination = new Pagination($params);
        $args = array(
            "base_url" => $url,
            "pagination" => $pagination,
            "per_page" => $per_page,
            "page_number" => $current_page,
            "current_page" => $view,
            "condition_status" => $filter_condition_list,
            "condition" => $condition,
            "activity_list" => (array)is_array($activity_list) && isset($activity_list["data"]) ? $activity_list["data"] : array(),
            "filter" => WLRMG_PLUGIN_URL . "Assets/svg/filter.svg",
            "current_filter_status" => WLRMG_PLUGIN_URL . "Assets/svg/current_filter_status.svg",
            "no_activity_list" => WLRMG_PLUGIN_URL . "Assets/svg/no_activity_list.svg",
        );
        return self::$template->setData(WLRMG_VIEW_PATH . '/Admin/activity.php', $args)->render();
    }

    function getActivityLogs()
    {
        $where = "";
        $current_page = (int)self::$input->post_get("page_number", 1);
        $settings = get_option('wlrmg_settings');
        $default_pagination_limit = !empty($settings) && is_array($settings) && $settings['pagination_limit'] > 0 ? $settings['pagination_limit'] : 10;
        $limit = (int)self::$input->post_get("per_page", $default_pagination_limit);
        $offset = $limit * ($current_page - 1);
        $condition = (string)self::$input->post_get("condition", "all");
        $count_where = self::$db->prepare(" id > %d ", array(0));
        if (!empty($condition) && $condition !== "all") {
            $where .= self::$db->prepare("action_type = %s AND ", array($condition));
            $count_where .= self::$db->prepare(" AND action_type = %s  ", array($condition));
        }
        $where .= self::$db->prepare(" id > %d ORDER BY id DESC", array(0));
        if (($offset >= 0) && !empty($limit)) {
            $where .= self::$db->prepare(" LIMIT %d OFFSET %d ", array($limit, $offset));
        }
        $job_table = new MigrationJob();
        $bulk_action_lists = $job_table->getWhere($where, "*", false);
        $total_count = $job_table->getWhere($count_where, "COUNT(id) as total_count", true);
        $migration_actions = array();
        $woocommerce_helper = Woocommerce::getInstance();
        foreach ($bulk_action_lists as $list) {
            if (!is_object($list)) {
                continue;
            }
            $show_edit_category = '';
            if ($list->action_type == 'wp_swings_migration') {
                $show_edit_category = 'wp_swings';
            }
            $job_id = isset($list->uid) && !empty($list->uid) ? $list->uid : 0;
            $migration_actions[] = array(
                "image_icon" => isset($list->action_type) && !empty($list->action_type) ? $list->action_type : "",
                "action" => isset($list->action) && !empty($list->action) ? $list->action : "",
                "points" => isset($list->points) && !empty($list->points) ? $list->points : 0,
                "job_id" => $job_id,
                "status" => isset($list->status) && !empty($list->status) ? $list->status : 0,
                "processed_count" => $list->offset,
                "total_count" => isset($list->total) && !empty($list->total) ? $list->total : 0,
                "date" => $woocommerce_helper->beforeDisplayDate($list->created_at, "d M,Y h:i a"),
                "show_edit_button" => (isset($list->status) && !empty($list->status) && in_array($list->status, array("draft", "pending"))),
                "show_edit_category" => $show_edit_category,
            );
        }
        return apply_filters('wlrmg_before_activity_log_lists', array(
            "data" => $migration_actions,
            "total_rows" => isset($total_count->total_count) && $total_count->total_count > 0 ? $total_count->total_count : 0,
            "current_page" => $current_page,
            "per_page" => $limit,
        ));
    }

    function getActionsPage()
    {
        $view = (string)self::$input->post_get("view", "actions");
        $args = array(
            'current_page' => $view,
            "back_to_apps_url" => admin_url('admin.php?' . http_build_query(array('page' => WLR_PLUGIN_SLUG))) . '#/apps',
            "previous" => WLRMG_PLUGIN_URL . "Assets/svg/previous.svg",
            'migration_cards' => array(
                array(
                    'type' => 'wp_swings_migration',
                    'title' => __('WPSwings points and rewards', 'wp-loyalty-migration'),
                    'description' => __('Migrate users with points', 'wp-loyalty-migration'),
                    'is_active' => WPSwings::checkPluginIsActive(),
                    'is_job_created' => WPSwings::checkMigrationJobIsCreated(),
                    'job_id' => WPSwings::getJobId(),
                    'is_show_migrate_button' => true,
                ),
                array(
                    'type' => 'yith_migration',
                    'title' => __('YITH points and rewards', 'wp-loyalty-migration'),
                    'description' => __('Migrate users with points', 'wp-loyalty-migration'),
                    'is_active' => false,
                    'is_job_created' => false,
                    'is_show_migrate_button' => true,
                ),
                array(
                    'type' => 'woocommerce',
                    'title' => __('Woocommerce points and rewards', 'wp-loyalty-migration'),
                    'description' => __('Migrate users with points', 'wp-loyalty-migration'),
                    'is_active' => false,
                    'is_job_created' => false,
                    'is_show_migrate_button' => true,
                ),
            )
        );

        return self::$template->setData(WLRMG_VIEW_PATH . '/Admin/actions.php', $args)->render();
    }

    function getSettingsPage()
    {
        $view = (string)self::$input->post_get("view", "settings");
        $args = array(
            "batch_limit" => $this->getBatchLimit(),
            "pagination_limit" => $this->getPaginationLimit(),
            "current_page" => $view,
            "back_to_apps_url" => admin_url('admin.php?' . http_build_query(array('page' => WLR_PLUGIN_SLUG))) . '#/apps',
            "back" => WLRMG_PLUGIN_URL . "Assets/svg/back_button.svg",
            "previous" => WLRMG_PLUGIN_URL . "Assets/svg/previous.svg",
            "option_settings" => get_option('wlrmg_settings', array()),
        );
        return self::$template->setData(WLRMG_VIEW_PATH . '/Admin/settings.php', $args)->render();
    }

    public function getBatchLimit()
    {
        return array(
            "10" => "10",
            "20" => "20",
            "30" => "30",
            "40" => "40",
            "50" => "50",
        );
    }

    public function getPaginationLimit()
    {
        return array(
            "5" => "5",
            "10" => "10",
            "20" => "20",
            "50" => "50",
            "100" => "100",
        );
    }

    function getAppDetails($plugins)
    {
        if (is_array($plugins) && !empty($plugins)) {
            foreach ($plugins as &$plugin) {
                if (is_array($plugin) && isset($plugin['plugin']) && $plugin['plugin'] == 'wp-loyalty-migration/wp-loyalty-migration.php') {
                    $plugin['page_url'] = admin_url('admin.php?' . http_build_query(array('page' => WLRMG_PLUGIN_SLUG)));
                    break;
                }
            }
        }
        return $plugins;
    }

    function addAssets()
    {
        if (self::$input->get("page") != WLRMG_PLUGIN_SLUG) {
            return;
        }
        $suffix = ".min";
        if (defined("SCRIPT_DEBUG")) {
            $suffix = SCRIPT_DEBUG ? "" : ".min";
        }
        remove_all_actions("admin_notices");
        wp_enqueue_style(WLR_PLUGIN_SLUG . '-wlr-font', WLR_PLUGIN_URL . 'Assets/Site/Css/wlr-fonts' . $suffix . '.css', array(), WLR_PLUGIN_VERSION . '&t=' . time());
        wp_enqueue_script(WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Js/alertify' . $suffix . '.js', array(), WLR_PLUGIN_VERSION . '&t=' . time());
        wp_enqueue_style(WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Css/alertify' . $suffix . '.css', array(), WLR_PLUGIN_VERSION . '&t=' . time());
        wp_enqueue_style(WLRMG_PLUGIN_SLUG . "-main-style", WLRMG_PLUGIN_URL . "Assets/Admin/Css/wlrmg-main.css", array("woocommerce_admin_styles"), WLRMG_PLUGIN_VERSION);
        wp_enqueue_script(WLRMG_PLUGIN_SLUG . "-main-script", WLRMG_PLUGIN_URL . "Assets/Admin/Js/wlrmg-main" . $suffix . ".js", array("jquery"), WLRMG_PLUGIN_VERSION . "&t=" . time());
        $localize_data = apply_filters('wlrmg_before_localize_data', array(
            "ajax_url" => admin_url("admin-ajax.php"),
            "migrate_users" => Woocommerce::create_nonce("wlrmg_migrate_users_nonce"),
            "save_settings" => Woocommerce::create_nonce("wlrmg_save_settings_nonce"),
        ));
        wp_localize_script(WLRMG_PLUGIN_SLUG . "-main-script", "wlrmg_localize_data", $localize_data);
    }

    function migrateUsersWithPointsJob()
    {
        $result = array(
            "success" => false,
            "data" => array(
                "message" => __("Security check failed", "wp-loyalty-migration"),
            )
        );
        if (!$this->securityCheck('wlrmg_migrate_users_nonce')) wp_send_json($result);
        $post = self::$input->post();
        $validate_data = self::$validation->validateMigrationData($post);
        if (is_array($validate_data) && !empty($validate_data) && count($validate_data) > 0) {
            foreach ($validate_data as $key => $validate) {
                $validate_data[$key] = current($validate);
            }
            $result["data"]["field_error"] = $validate_data;
            $result["data"]["message"] = __("Records not saved", "wp-loyalty-migration");
            wp_send_json($result);
        }
        $cron_job_modal = new MigrationJob();
        /* check job exist or not */
        $where = self::$db->prepare('id > %d AND action =%s', array(0, $post['migration_action']));
        $check_data_job = $cron_job_modal->getWhere($where, '*', true);
        if (!empty($check_data_job)) {
            $result['data']['message'] = __('Migration job already created', 'wp-loyalty-migration');
            wp_send_json($result);
        }
        $wlrmg_setttings = get_option('wlrmg_settings');
        $batch_limit = (int)is_array($wlrmg_setttings) && isset($wlrmg_setttings['batch_limit']) && !empty($wlrmg_setttings['batch_limit'])
        && $wlrmg_setttings['batch_limit'] > 0 ? $wlrmg_setttings['batch_limit'] : 10;
        $where = self::$db->prepare('id > %d', array(0));
        $data_job = $cron_job_modal->getWhere($where, 'MAX(uid) as max_uid', true);
        $max_uid = 1;
        if (!empty($data_job) && is_object($data_job) && isset($data_job->max_uid) && !empty($data_job->max_uid)) {
            $max_uid = ($data_job->max_uid > 0) && isset($post['job_id']) && ($post['job_id'] > 0) ? $data_job->max_uid : $data_job->max_uid + 1;
        }
        $admin_mail = self::$woocommerce->get_login_user_email();
        $job_data = array(
            "uid" => $max_uid,
            "admin_mail" => $admin_mail,
            "action_type" => 'migration_to_wployalty',
            "action" => isset($post['migration_action']) && !empty($post['migration_action']) ? $post['migration_action'] : "",
            "status" => 'pending',
            "limit" => (int)isset($batch_limit) && $batch_limit > 0 ? $batch_limit : 0,
            "offset" => 0,
            "last_processed_id" => 0,
            "created_at" => strtotime(date("Y-m-d h:i:s")),
        );
        $job_table_model = new MigrationJob();
        $cron_job_id = $job_table_model->insertRow($job_data);
        if ($cron_job_id <= 0) {
            wp_send_json($result);
        }
        $result['success'] = true;
        $result['data']['message'] = __("Migration job created successfully.", "wp-loyalty-migration");
        $result['data']['job_id'] = $cron_job_id;
        wp_send_json($result);
    }

    function saveSettings()
    {
        $result = array(
            "success" => false,
            "data" => array(
                "message" => __("Security check failed", "wp-loyalty-migration"),
            )
        );
        if (!$this->securityCheck('wlrmg_save_settings_nonce')) wp_send_json($result);
        $post = self::$input->post();
        $validate_data = self::$validation->validateSettingsData($post);
        if (is_array($validate_data) && !empty($validate_data) && count($validate_data) > 0) {
            foreach ($validate_data as $key => $validate) {
                $validate_data[$key] = current($validate);
            }
            $data["data"]["field_error"] = $validate_data;
            $data["data"]["message"] = __("Records not saved", "wp-loyalty-migration");
            wp_send_json($data);
        }
        $need_to_remove_fields = array('action', 'wlrmg_nonce');
        foreach ($need_to_remove_fields as $field) {
            unset($post[$field]);
        }
        $data = apply_filters('wlrmg_before_save_settings', $post);
        update_option('wlrmg_settings', $data);
        $result['success'] = true;
        $result['data']['message'] = __("Settings saved successfully.", "wp-loyalty-bulk-action");
        wp_send_json($result);
    }

    function initSchedule()
    {
        $hook = "wlrmg_migration_jobs";
        $timestamp = wp_next_scheduled($hook);
        if (false === $timestamp) {
            $scheduled_time = strtotime("+1 hour", current_time("timestamp"));
            wp_schedule_event($scheduled_time, "hourly", $hook);
        }
    }

    function removeSchedule()
    {
        $next_scheduled = wp_next_scheduled("wlrmg_migration_jobs");
        wp_unschedule_event($next_scheduled, "wlrmg_migration_jobs");
    }

    function triggerMigrations()
    {
        $job_table = new MigrationJob();
        $where = self::$db->prepare(" id > 0 AND (status IN (%s,%s) ) ORDER BY id ASC", array("pending", "processing"));
        $data = $job_table->getWhere($where);
        $process_identifier = 'wlrmg_cron_job_running';
        if (get_transient($process_identifier)) {
            return;
        }
        set_transient($process_identifier, true, 60);
        if (is_object($data) && !empty($data)) {
            //process
            $action = isset($data->action) && !empty($data->action) ? $data->action : "";
            switch ($action) {
                case 'wp_swings_migration':
                    $wp_swings = new WPSwings();
                    $wp_swings->migrateToLoyalty($data);
                    break;
                case 'yith_migration':
                case 'woo_migration':
                default:
                    return;
            }
        }
        delete_transient($process_identifier);
    }
    function menuHide(){
        ?>
        <style>
            #toplevel_page_wp-loyalty-migration {
                display: none !important;
            }
        </style>
        <?php
    }

    function addExtraAction($action_list)
    {
        if (empty($action_list) || !is_array($action_list)) return $action_list;
        $action_list["migration_to_wployalty"] = __("User migrated via WPLoyalty migration", "wp-loyalty-migration");
        return $action_list;
    }
}