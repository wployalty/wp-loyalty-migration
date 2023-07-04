<?php

namespace Wlrm\App;
use Wlrm\App\Helper\CompatibleCheck;

defined('ABSPATH') or die();
class Router
{
    private static $admin;

    /**
     * @return false|void
     */
    function init()
    {
        self::$admin = empty(self::$admin) ? new Controller\Admin\Admin() : self::$admin;
        add_filter('wlr_loyalty_apps',array(self::$admin,'getAppDetails'));
        $activation_check = new CompatibleCheck();
        if (!$activation_check->init_check()) {
            if (is_admin()) add_action("all_admin_notices", array($activation_check, "inActiveNotice"));
            return false;
        }
        if (is_admin()){
            register_activation_hook(WLRMG_PLUGIN_FILE,array(self::$admin,'pluginActivation'));
            add_action("admin_menu", array(self::$admin, "addAdminMenu"), 11);
            add_action("admin_enqueue_scripts",array(self::$admin,'addAssets'));
            add_action('wp_ajax_wlrmg_create_job',array(self::$admin,'createJob'));
            add_filter("wlr_extra_action_list", array(self::$admin, "addExtraAction"), 10, 1);
            //schedule
            add_action('woocommerce_init', array(self::$admin, 'initSchedule'), 10);
            register_deactivation_hook(WLRMG_PLUGIN_FILE, array(self::$admin, 'removeSchedule'));
            add_action('wlrmg_migration_jobs',array(self::$admin,'triggerMigrations'));
        }
    }
}