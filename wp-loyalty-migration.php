<?php
/**
 * Plugin Name: WPLoyalty - Migration
 * Plugin URI: https://www.wployalty.net
 * Description: The WPLoyalty Migration Add-On allows you to transfer your customers and their earned points from other loyalty plugins like Loyalty Points Legacy, WPswings, or WooCommerce Points and Rewards into WPLoyalty.
 * Version: 1.0.1
 * Author: WPLoyalty
 * Slug: wp-loyalty-migration
 * Text Domain: wp-loyalty-migration
 * Domain Path: /i18n/languages/
 * Requires at least: 6.0
 * WC requires at least: 10.0.0
 * WC tested up to: 10.1
 * Contributors: WPLoyalty
 * Author URI: https://wployalty.net/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * WPLoyalty: 1.4.0
 * WPLoyalty Page Link: wp-loyalty-migration
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Wlrmg\App\Helper\Plugin;
use Wlrmg\App\Router;
use Wlrmg\App\Setup;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) or die();


add_action( 'before_woocommerce_init', function () {
	if ( class_exists( FeaturesUtil::class ) ) {
		FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__ );
	}
} );

if ( ! function_exists( 'isWLRMGWooCommerceActive' ) ) {
	function isWLRMGWooCommerceActive() {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', [] ) );
		}

		return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
	}
}

if ( ! function_exists( 'isWLRMGLoyaltyActive' ) ) {
	function isWLRMGLoyaltyActive() {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', [] ) );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', [] ) );
		}

		return in_array( 'wp-loyalty-rules/wp-loyalty-rules.php', $active_plugins ) || array_key_exists( 'wp-loyalty-rules/wp-loyalty-rules.php', $active_plugins )
		       || in_array( 'wployalty/wp-loyalty-rules-lite.php', $active_plugins ) || array_key_exists( 'wployalty/wp-loyalty-rules-lite.php', $active_plugins );
	}
}

if ( ! isWLRMGWooCommerceActive() || ! isWLRMGLoyaltyActive() ) {
	return;
}

defined( 'WLRMG_PLUGIN_NAME' ) or define( 'WLRMG_PLUGIN_NAME', 'WPLoyalty - Migration' );
defined( 'WLRMG_PLUGIN_VERSION' ) or define( 'WLRMG_PLUGIN_VERSION', '1.0.1' );
defined( 'WLRMG_PLUGIN_SLUG' ) or define( 'WLRMG_PLUGIN_SLUG', 'wp-loyalty-migration' );
defined( 'WLRMG_PLUGIN_DIR' ) or define( 'WLRMG_PLUGIN_DIR', str_replace( "\\", '/', __DIR__ ) );
defined( 'WLRMG_PLUGIN_URL' ) or define( 'WLRMG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
defined( 'WLRMG_PLUGIN_FILE' ) or define( 'WLRMG_PLUGIN_FILE', __FILE__ );
defined( 'WLRMG_VIEW_PATH' ) or define( 'WLRMG_VIEW_PATH', str_replace( "\\", '/', __DIR__ ) . '/App/Views' );
defined( 'WLRMG_MINIMUM_PHP_VERSION' ) or define( 'WLRMG_MINIMUM_PHP_VERSION', '7.4.0' );
defined( 'WLRMG_MINIMUM_WP_VERSION' ) or define( 'WLRMG_MINIMUM_WP_VERSION', '6.0' );
defined( 'WLRMG_MINIMUM_WC_VERSION' ) or define( 'WLRMG_MINIMUM_WC_VERSION', '10.0.0' );
defined( 'WLRMG_MINIMUM_WLR_VERSION' ) or define( 'WLRMG_MINIMUM_WLR_VERSION', '1.4.0' );

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}
require_once __DIR__ . '/vendor/autoload.php';

$my_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/wployalty/wp-loyalty-migration',
	__FILE__,
	'wp-loyalty-migration'
);
$my_update_checker->getVcsApi()->enableReleaseAssets();
add_filter( 'plugins_loaded', function () {
	if ( ! class_exists( '\Wlr\App\Helpers\Input' ) ) {
		return;
	}
	if ( ! class_exists( '\Wlrmg\App\Router' ) ) {
		return;
	}
	if ( Plugin::checkDependencies() ) {
		Router::init();
	}
} );
if ( class_exists( \Wlrmg\App\Helper\Plugin::class ) ) {
	Setup::init();
} // init setup
