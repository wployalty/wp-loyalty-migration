<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlrm\App;


use Wlrm\App\Controller\Common;
use Wlrm\App\Controller\Migration;

defined('ABSPATH') or die();

class Router
{

    /**
     * @return void
     */
    static function init()
    {
        if (is_admin()) {
            add_action('admin_menu', [Common::class, 'addMenu'], 11);
            add_action('admin_footer', [Common::class, 'hideMenu']);
            add_action('admin_enqueue_scripts', [Common::class, 'addAssets']);

	        if (wp_doing_ajax()) {
                add_action('wp_ajax_wlrmg_confirm_update_points', [Migration::class, 'getConfirmContent']);
                add_action('wp_ajax_wlrmg_migrate_users', [Migration::class, 'migrateUsersWithPointsJob']);
                add_action('wp_ajax_wlrmg_save_settings', [Migration::class, 'saveSettings']);
                add_action('wp_ajax_wlrmg_export_popup', [Migration::class, 'exportPopup']);
                add_action('wp_ajax_wlrmg_handle_export', [Migration::class, 'handleExport']);
                add_action('wp_ajax_wlrmg_get_exported_list', [Migration::class, 'getExportFiles']);
            }
        }
        add_filter('wlr_extra_action_list', [Common::class, 'addExtraAction'], 10, 1);
        add_filter('wlr_extra_point_ledger_action_list', [Common::class, 'addExtraPointLedgerAction'], 10, 1);

        add_action('woocommerce_init', [Common::class, 'initSchedule'], 10);
        add_filter('cron_schedules', [Common::class, 'addMinutes']);
        add_action('wlrmg_migration_jobs', [Common::class, 'triggerMigrations']);
    }
}