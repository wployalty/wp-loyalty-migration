<?php

namespace Wlrm\App\Controller\Admin;

use Wlr\App\Helpers\Woocommerce;
use Wlrm\App\Controller\Base;
use Wlrm\App\Controller\Compatibles\WPSwings;
use Wlrm\App\Helper\CompatibleCheck;
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
            case 'activity':
            default:
                $params['main_page']['activity'] = $this->getActivityPage();
                break;
        }
        self::$template->setData(WLRMG_VIEW_PATH . '/Admin/main.php', $params)->display();
    }

    function getActivityPage()
    {
        $view = (string)self::$input->post_get("view", "activity");
        $args = array(
            'current_page' => $view,
            "no_activity_list" => WLRMG_PLUGIN_URL . "Assets/svg/no_activity_list.svg",
        );
        return self::$template->setData(WLRMG_VIEW_PATH . '/Admin/activity.php', $args)->render();
    }

    function getActionsPage()
    {
        $view = (string)self::$input->post_get("view", "actions");
        $args = array(
            'current_page' => $view,
            "back_to_apps_url" => admin_url('admin.php?' . http_build_query(array('page' => WLR_PLUGIN_SLUG))) . '#/apps',
            "previous" => WLRMG_PLUGIN_URL . "Assets/svg/previous.svg",
            "save" => WLRMG_PLUGIN_URL . "Assets/svg/save.svg",
        );
        return self::$template->setData(WLRMG_VIEW_PATH . '/Admin/actions.php', $args)->render();
    }

    function getSettingsPage()
    {
        $args = array();
        return self::$template->setData(WLRMG_VIEW_PATH . '/Admin/settings.php', $args)->render();
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
        wp_enqueue_style(WLRMG_PLUGIN_SLUG . "-main-style", WLRMG_PLUGIN_URL . "Assets/Admin/Css/wlrmg-main.css", array("woocommerce_admin_styles"), WLRMG_PLUGIN_VERSION);
        wp_enqueue_script(WLRMG_PLUGIN_SLUG . "-main-script", WLRMG_PLUGIN_URL . "Assets/Admin/Js/wlrmg-main" . $suffix . ".js", array("jquery"), WLRMG_PLUGIN_VERSION . "&t=" . time());
        $localize_data = apply_filters('wlrmg_before_localize_data', array(
            "ajax_url" => admin_url("admin-ajax.php"),
            "create_job" => Woocommerce::create_nonce("wlrmg_create_job_nonce"),
        ));
        wp_localize_script(WLRMG_PLUGIN_SLUG . "-main-script", "wlrmg_localize_data", $localize_data);
    }

    function createJob()
    {
        $result = array(
            "success" => false,
            "data" => array(
                "message" => __("Security check failed", "wp-loyalty-migration"),
            )
        );
        if (!$this->securityCheck('wlrmg_create_job_nonce')) wp_send_json($result);
        $post = self::$input->post();
        $validate_data = self::$validation->validateSaveData($post);
        if (is_array($validate_data) && !empty($validate_data) && count($validate_data) > 0) {
            foreach ($validate_data as $key => $validate) {
                $validate_data[$key] = current($validate);
            }
            $data["data"]["field_error"] = $validate_data;
            $data["data"]["message"] = __("Records not saved", "wp-loyalty-migration");
            wp_send_json($data);
        }
        $wlrmg_setttings = get_option('wlrmg_settings');
        $batch_limit = (int)is_array($wlrmg_setttings) && isset($wlrmg_setttings['batch_limit']) && !empty($wlrmg_setttings['batch_limit'])
        && $wlrmg_setttings['batch_limit'] > 0 ? $wlrmg_setttings['batch_limit'] : 10;
        $cron_job_modal = new MigrationJob();
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
            "comment" => isset($post['comment']) && !empty($post['comment']) ? $post['comment'] : "",
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
        $result['data']['message'] = __("Settings saved successfully.", "wp-loyalty-migration");
        $result['data']['job_id'] = $cron_job_id;
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
            $action_type = isset($data->action_type) && !empty($data->action_type) ? $data->action_type : "";
            switch ($action_type) {
                case 'wp_swings_migration':
                    $wp_swings = new WPSwings();
                    $wp_swings->Migrate($data);
                    break;
                default:
                    return;
            }
        }
        delete_transient($process_identifier);
    }

    function addExtraAction($action_list)
    {
        if (empty($action_list) || !is_array($action_list)) return $action_list;
        $action_list["migration_to_wployalty"] = __("User migrated via WPLoyalty migration", "wp-loyalty-migration");
        return $action_list;
    }
}