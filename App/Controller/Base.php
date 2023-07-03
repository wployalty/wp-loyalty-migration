<?php

namespace Wlrm\App\Controller;
use Wlr\App\Helpers\Input;
use Wlr\App\Helpers\Template;
use Wlr\App\Helpers\Woocommerce;
defined('ABSPATH') or die();
class Base
{
    public static $db,$input,$template;
    function __construct(){
        global $wpdb;
        self::$db = $wpdb;
        self::$input = empty(self::$input) ? new Input() : self::$input;
        self::$template = empty(self::$template) ? new Template() : self::$template;
    }
    function securityCheck($nonce_name = '')
    {
        $wlba_nonce = (string)self::$input->post_get("wlrmg_nonce", "");
        return (!Woocommerce::hasAdminPrivilege() || !Woocommerce::verify_nonce($wlba_nonce, $nonce_name)) == false;
    }
}