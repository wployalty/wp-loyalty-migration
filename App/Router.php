<?php

namespace App;
defined('ABSPATH') or die();
class Router
{
    private static $admin;
    function init()
    {
        if (is_admin()){

            register_activation_hook(WLRMG_PLUGIN_FILE,array());
        }
    }
}