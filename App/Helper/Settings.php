<?php

namespace Wlrm\App\Helper;

defined('ABSPATH') || exit();

class Settings
{
    public static function get($key, $value)
    {
        $settings = self::gets();

        return $settings[$key] ?? $value;
    }

    public static function gets()
    {
        return get_option('wlrmg_settings', self::getDefaultSettings());
    }

    public static function getDefaultSettings()
    {
        return [
            'batch_limit' => 50,
            'pagination_limit' => 10
        ];
    }
}