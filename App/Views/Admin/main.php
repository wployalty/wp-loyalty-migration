<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */
defined('ABSPATH') or die();
$current_page = (isset($current_page) && !empty($current_page)) ? $current_page : $current_page = "activity";
?>

<div id="wlrmg-main-page">
    <div>
        <div class="wlrmg-main-header">
            <h1><?php echo esc_html(WLRMG_PLUGIN_NAME); ?> </h1>
            <div><b><?php echo "v" . esc_html(WLRMG_PLUGIN_VERSION); ?></b></div>
        </div>
        <div class="wlrmg-notice-header">
            <b><?php echo wp_kses_post( __("Note : During the migration, only customer's loyalty points will be transferred. Customer history    or any other data will not be included.",'wp-loyalty-migration')) ?></b>
        </div>
        <div class="wlrmg-admin-main">
            <div class="wlrmg-admin-nav">
                <a class="<?php echo (in_array($current_page, array(
                    'actions',
                    'activity_details'
                ))) ? "active-nav" : ""; ?>"
                   href="<?php echo esc_url(admin_url("admin.php?" . http_build_query(array(
                           "page" => WLRMG_PLUGIN_SLUG,
                           "view" => 'actions'
                       )))) ?>"
                ><?php  esc_html_e("Actions", "wp-loyalty-migration"); ?></a>
                <a class="<?php echo (in_array($current_page, array('settings'))) ? "active-nav" : ""; ?>"
                   href="<?php echo esc_url(admin_url("admin.php?" . http_build_query(array(
                           "page" => WLRMG_PLUGIN_SLUG,
                           "view" => 'settings'
                       )))) ?>"
                ><?php esc_html_e("Settings", "wp-loyalty-migration"); ?></a>
            </div>
        </div>
        <div class="wlrmg-parent">
            <div class="wlrmg-body-content">
                <?php if (isset($main_page) && !empty($main_page) && is_array($main_page)): ?>
                    <?php foreach ($main_page as $page): ?>
                        <?php echo $page;  //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped	?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div id="wlrmg-overlay-section" class="wlrmg-overlay-section">
        <div class="wlrmg-overlay">
        </div>
    </div>
</div>