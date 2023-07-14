<?php
/**
 * Plugin Name: WPLoyalty - Migration
 * Plugin URI: https://www.wployalty.net
 * Description: WPLoyalty - Migration for Users to WPLoyalty
 * Version: 1.0.0
 * Author: WPLoyalty
 * Slug: wp-loyalty-migration
 * Text Domain: wp-loyalty-migration
 * Domain Path: /i18n/languages/
 * Requires at least: 4.9.0
 * WC requires at least: 6.5
 * WC tested up to: 7.6
 * Contributors: WPLoyalty, Ilaiyaraja
 * Author URI: https://wployalty.net/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * WPLoyalty: 1.2.3
 * WPLoyalty Page Link: wp-loyalty-migration
 */
defined('ABSPATH') or die();
if (!function_exists('checkWoocommerceActive')) {
    function checkWoocommerceActive()
    {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', array()));
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
    }
}
if (!function_exists('checkWployaltyActiveOrNot')) {
    function checkWployaltyActiveOrNot()
    {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', array()));
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        return in_array('wp-loyalty-rules/wp-loyalty-rules.php', $active_plugins, false);
    }
}
if (!checkWployaltyActiveOrNot() || !checkWoocommerceActive()) {
    return;
}
if (!class_exists('\Wlr\App\Helpers\Input') && file_exists(WP_PLUGIN_DIR . '/wp-loyalty-rules/vendor/autoload.php')) {
    require_once WP_PLUGIN_DIR . '/wp-loyalty-rules/vendor/autoload.php';
}
if (!class_exists('\Wlr\App\Helpers\Input')) {
    return;
}
defined('WLRMG_PLUGIN_NAME') or define('WLRMG_PLUGIN_NAME', 'WPLoyalty - Migration');
defined('WLRMG_PLUGIN_VERSION') or define('WLRMG_PLUGIN_VERSION', '1.0.0');
defined('WLRMG_TEXT_DOMAIN') or define('WLRMG_TEXT_DOMAIN', 'wp-loyalty-migration');
defined('WLRMG_PLUGIN_SLUG') or define('WLRMG_PLUGIN_SLUG', 'wp-loyalty-migration');
defined('WLRMG_PLUGIN_PATH') or define('WLRMG_PLUGIN_PATH', __DIR__ . '/');
defined('WLRMG_PLUGIN_DIR') or define('WLRMG_PLUGIN_DIR', __DIR__ );
defined('WLRMG_PLUGIN_URL') or define('WLRMG_PLUGIN_URL', plugin_dir_url(__FILE__));
defined('WLRMG_PLUGIN_FILE') or define('WLRMG_PLUGIN_FILE', __FILE__);
defined('WLRMG_PLUGIN_AUTHOR') or define('WLRMG_PLUGIN_AUTHOR', 'WPLoyalty');
defined('WLRMG_VIEW_PATH') or define('WLRMG_VIEW_PATH', __DIR__.'/App/Views');
defined('WLRMG_MINIMUM_PHP_VERSION') or define('WLRMG_MINIMUM_PHP_VERSION', '5.6.0');
defined('WLRMG_MINIMUM_WP_VERSION') or define('WLRMG_MINIMUM_WP_VERSION', '4.9');
defined('WLRMG_MINIMUM_WC_VERSION') or define('WLRMG_MINIMUM_WC_VERSION', '6.0');
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    return;
}
require_once __DIR__.'/vendor/autoload.php';
if (class_exists('\Wlrm\App\Router')){
    $router = new \Wlrm\App\Router();
    $router->init();
}
