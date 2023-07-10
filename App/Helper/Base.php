<?php

namespace Wlrm\App\Helper;
defined('ABSPATH') or die();

class Base
{
    static function checkPluginActive($type)
    {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', array()));
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        switch ($type) {
            case 'wp_swings_migration':
                $status = in_array('points-and-rewards-for-woocommerce/points-rewards-for-woocommerce.php', $active_plugins, false);
                break;
            case 'yith_migration':
            case 'woo_migration':
            default:
                $status = false;
        }
        return $status;
    }
}