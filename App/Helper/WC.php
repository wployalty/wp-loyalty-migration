<?php

namespace Wlrm\App\Helper;

defined('ABSPATH') || exit();

class WC
{
    /**
     * render template.
     *
     * @param string $file File path.
     * @param array $data Template data.
     * @param bool $display Display or not.
     *
     * @return string|void
     */
    public static function renderTemplate(string $file, array $data = [], bool $display = true)
    {
        $content = '';
        if (file_exists($file)) {
            ob_start();
            extract($data);
            include $file;
            $content = ob_get_clean();
        }
        if ($display) {
            //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $content; 
        } else {
            return $content;
        }
    }

    /**
     * Before display date conversion.
     *
     * @param int $date timestamp.
     * @param string $format Date format.
     *
     * @return string|null
     */
    public static function beforeDisplayDate($date, string $format = 'Y-m-d H:i:s')
    {
        if (empty($date)) {
            return null;
        }

        return self::convertUTCToWPTime(gmdate('Y-m-d H:i:s', $date), $format);
    }

    /**
     * Convert UTC time to WP time.
     *
     * @param string $date Date.
     * @param string $format Date format.
     *
     * @return string|null
     */
    public static function convertUTCToWPTime(string $date, string $format = '')
    {
        if (empty($date)) {
            return null;
        }
        $converted_time = get_date_from_gmt($date, $format);
        if (apply_filters('wdr_translate_display_date', false)) {
            $time = strtotime($converted_time);
            $converted_time = date_i18n($format, $time);
        }

        return $converted_time;
    }

    /**
     * Generates a cryptographic nonce based on the provided action.
     *
     * @param int|string $action Optional. Action for which the nonce is created. Default is -1.
     *
     * @return string The generated cryptographic nonce.
     */
    public static function createNonce($action = -1)
    {
        return wp_create_nonce($action);
    }

    /**
     * Check the validity of a security nonce and the admin privilege.
     *
     * @param string $nonce_name The name of the nonce.
     *
     * @return bool
     */
    public static function isSecurityValid(string $nonce_name = ''): bool
    {
        $nonce = Input::get('wlrmg_nonce');
        if (!self::hasAdminPrivilege() || !self::verifyNonce($nonce, $nonce_name)) {
            return false;
        }

        return true;
    }

    public static function hasAdminPrivilege()
    {
        if (current_user_can('manage_woocommerce')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Verify nonce.
     *
     * @param string $nonce Nonce.
     * @param string $action Action.
     *
     * @return bool
     */
    public static function verifyNonce(string $nonce, string $action = ''): bool
    {
        if (empty($nonce) || empty($action)) {
            return false;
        }

        return wp_verify_nonce($nonce, $action);
    }

    public static function getLoginUserEmail()
    {
        $user = get_user_by('id', get_current_user_id());
        $user_email = '';
        if (!empty($user)) {
            $user_email = $user->user_email;
        }

        return $user_email;
    }
}
