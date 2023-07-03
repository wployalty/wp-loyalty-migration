<?php

namespace Wlrm\App\Controller\Admin;

use Wlr\App\Helpers\Woocommerce;
use Wlrm\App\Controller\Base;
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
        $args = array();
        self::$template->setData(WLRMG_VIEW_PATH . '/Admin/activity.php', $args)->display();
    }

    function getActionsPage()
    {
        $args = array();
        self::$template->setData(WLRMG_VIEW_PATH . '/Admin/actions.php', $args)->display();
    }

    function getSettingsPage()
    {
        $args = array();
        self::$template->setData(WLRMG_VIEW_PATH . '/Admin/settings.php', $args)->display();
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
            "data"=>array(
                "message" => __("Security check failed","wp-loyalty-migration"),
            )
        );
        if (!$this->securityCheck('wlrmg_create_job_nonce')) wp_send_json($result);
        $post = self::$input->post();
print_r($post);
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
        set_transient($process_identifier,true,60);
        if (is_object($data) && !empty($data)){
            //process
        }
        delete_transient($process_identifier);
    }
}