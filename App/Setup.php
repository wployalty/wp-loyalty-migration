<?php

namespace Wlrm\App;

use Wlrm\App\Helper\Plugin;
use Wlrm\App\Models\MigrationLog;
use Wlrm\App\Models\ScheduledJobs;

defined('ABSPATH') || exit();

class Setup
{
    public static function init()
    {
        register_activation_hook(WLRMG_PLUGIN_FILE, [__CLASS__, 'activate']);
        register_deactivation_hook(WLRMG_PLUGIN_FILE, [__CLASS__, 'deactivate']);
        register_uninstall_hook(WLRMG_PLUGIN_FILE, [__CLASS__, 'uninstall']);

        add_filter('plugin_row_meta', [__CLASS__, 'getPluginRowMeta'], 10, 2);
        add_action('plugins_loaded', [__CLASS__, 'maybeRunMigration']);
        add_action('upgrader_process_complete', [__CLASS__, 'maybeRunMigration']);
    }

    /**
     * Run plugin activation scripts.
     */
    public static function activate()
    {
        Plugin::checkDependencies(true);
        self::maybeRunMigration();
    }

    /**
     * Maybe run database migration.
     */
    public static function maybeRunMigration()
    {
        $db_version = get_option('wlrm_version', '0.0.1');
        if (version_compare($db_version, WLRMG_PLUGIN_VERSION, '<')) {
            self::runMigration();
            update_option('wlrm_version', WLRMG_PLUGIN_VERSION);
        }
    }

    /**
     * Run database migration.
     */
    private static function runMigration()
    {
        $models = [
            new ScheduledJobs(),
            new MigrationLog()
        ];
        foreach ($models as $model) {
            if (is_a($model, '\Wlr\App\Models\Base')) {
                $model->create();
            }
        }
    }

    /**
     * Run plugin activation scripts.
     */
    public static function deactivate()
    {
        $next_scheduled = wp_next_scheduled("wlrmg_migration_jobs");
        wp_unschedule_event($next_scheduled, "wlrmg_migration_jobs");
        if (function_exists('as_unschedule_all_actions')) {
            as_unschedule_all_actions('', [], 'wlrmg_migration_queue');
        }
    }

    /**
     * Run plugin activation scripts.
     */
    public static function uninstall()
    {
        // silence is golden
    }

    /**
     * Retrieves the plugin row meta to be displayed on the Woocommerce appointments plugin page.
     *
     * @param array $links The existing plugin row meta links.
     * @param string $file The path to the plugin file.
     *
     * @return array
     */
    public static function getPluginRowMeta($links, $file)
    {
        if ($file != plugin_basename(WLRMG_PLUGIN_FILE)) {
            return $links;
        }
        $row_meta = [
            'support' => '<a href="' . esc_url('https://wployalty.net/support/') . '" aria-label="' . esc_attr__('Support', 'wp-loyalty-migration') . '">' . esc_html__('Support', 'wp-loyalty-migration') . '</a>',
        ];

        return array_merge($links, $row_meta);
    }
}