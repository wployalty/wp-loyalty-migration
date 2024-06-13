<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlrm\App\Helper;
defined( 'ABSPATH' ) or die();

class CompatibleCheck {
	function init_check( $active_check = false ) {
		$status = true;
		if ( ! $this->isEnvironmentCompatible() ) {
			if ( $active_check ) {
				exit( WLRMG_PLUGIN_NAME . __( " plugin can not be activated because it requires minimum PHP version of ", "wp-loyalty-migration" ) . WLRMG_MINIMUM_PHP_VERSION );
			}
			$status = false;
		}
		if ( ! $this->isWordPressCompatible() ) {
			if ( $active_check ) {
				exit( WLRMG_PLUGIN_NAME . __( " plugin can not be activated because it requires minimum Wordpress version of ", "wp-loyalty-migration" ) . WLRMG_MINIMUM_WP_VERSION );
			}
			$status = false;
		}
		if ( ! $this->isWooCompatible() ) {
			if ( $active_check ) {
				exit( WLRMG_PLUGIN_NAME . __( " plugin can not be activated because it requires minimum Woocommerce version of ", "wp-loyalty-migration" ) . WLRMG_MINIMUM_WC_VERSION );
			}
			$status = false;
		}

		return $status;
	}

	protected function isEnvironmentCompatible() {
		return version_compare( PHP_VERSION, WLRMG_MINIMUM_PHP_VERSION, ">=" );
	}

	public function isWordPressCompatible() {
		return ( ! WLRMG_MINIMUM_WP_VERSION ) ? true : version_compare( get_bloginfo( "version" ), WLRMG_MINIMUM_WP_VERSION, ">=" );
	}

	function isWooCompatible() {
		$woo_version = $this->woo_version();

		return ( ! WLRMG_MINIMUM_WC_VERSION ) ? true : version_compare( $woo_version, WLRMG_MINIMUM_WC_VERSION, ">=" );
	}

	function woo_version() {
		require_once ABSPATH . "/wp-admin/includes/plugin.php";
		$plugin_folder = get_plugins( "/woocommerce" );
		$plugin_file   = "woocommerce.php";

		return ( isset( $plugin_folder[ $plugin_file ]["Version"] ) ) ? $plugin_folder[ $plugin_file ]["Version"] : "1.0.0";
	}

	function inActiveNotice() {
		$message = "";
		if ( ! $this->isEnvironmentCompatible() ) {
			$message = WLRMG_PLUGIN_NAME . __( " is inactive. Because, it requires minimum PHP version of ", "wp-loyalty-migration" ) . WLRMG_MINIMUM_PHP_VERSION;
		} elseif ( ! $this->isWordPressCompatible() ) {
			$message = WLRMG_PLUGIN_NAME . __( " is inactive. Because, it requires minimum Wordpress version of ", "wp-loyalty-migration" ) . WLRMG_MINIMUM_WP_VERSION;
		} elseif ( ! $this->isWoocommerceActive() ) {
			$message = __( "Woocommerce must installed and activated in-order to use ", "wp-loyalty-migration" ) . WLRMG_PLUGIN_NAME;
		} elseif ( ! $this->isWooCompatible() ) {
			$message = WLRMG_PLUGIN_NAME . __( " is inactive. Because, it requires minimum Woocommerce version of ", "wp-loyalty-migration" ) . WLRMG_MINIMUM_WC_VERSION;
		}

		return '<div class="error"><p><strong>' . $message . '</strong></p></div>';
	}

	function isWoocommerceActive() {
		$active_plugins = apply_filters( "active_plugins", get_option( "active_plugins", array() ) );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( "active_sitewide_plugins", array() ) );
		}

		return ( in_array( "woocommerce/woocommerce.php", $active_plugins ) || array_key_exists( "woocommerce/woocommerce.php", $active_plugins ) );
	}
}