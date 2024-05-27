<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

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
        add_filter('wlr_loyalty_apps', array(self::$admin, 'getAppDetails'));
        $activation_check = new CompatibleCheck();
        if (!$activation_check->init_check()) {
            if (is_admin()) add_action("all_admin_notices", array($activation_check, "inActiveNotice"));
            return false;
        }
        if (is_admin()) {
            register_activation_hook(WLRMG_PLUGIN_FILE, array(self::$admin, 'pluginActivation'));
            add_action("admin_menu", array(self::$admin, "addAdminMenu"), 11);
            add_action("admin_footer", array(self::$admin, 'menuHide'));
            add_action("admin_enqueue_scripts", array(self::$admin, 'addAssets'));
            add_action('wp_ajax_wlrmg_confirm_update_points', array(self::$admin, 'getConfirmContent'));
            add_action('wp_ajax_wlrmg_migrate_users', array(self::$admin, 'migrateUsersWithPointsJob'));
            add_action('wp_ajax_wlrmg_save_settings', array(self::$admin, 'saveSettings'));
            add_action("wp_ajax_wlrmg_export_popup", array(self::$admin, "exportPopup"));
            add_action("wp_ajax_wlrmg_handle_export", array(self::$admin, "handleExport"));
            add_action("wp_ajax_wlrmg_get_exported_list", array(self::$admin, "getExportFiles"));

        }
        add_filter("wlr_extra_action_list", array(self::$admin, "addExtraAction"), 10, 1);
        add_filter("wlr_extra_point_ledger_action_list", array(self::$admin, "addExtraPointLedgerAction"), 10, 1);
        //schedule
        add_action('woocommerce_init', array(self::$admin, 'initSchedule'), 10);
        register_deactivation_hook(WLRMG_PLUGIN_FILE, array(self::$admin, 'removeSchedule'));
        add_action('wlrmg_migration_jobs', array(self::$admin, 'triggerMigrations'));
    }
}