<?php
defined('ABSPATH') or die();
$current_page = (isset($current_page) && !empty($current_page)) ? $current_page : $current_page = "activity";
?>

<div id="wlrmg-main-page">
    <div>
        <div class="wlrmg-main-header">
            <h1><?php echo WLRMG_PLUGIN_NAME; ?> </h1>
            <div><b><?php echo "v" . WLRMG_PLUGIN_VERSION; ?></b></div>
        </div>
        <div class="wlrmg-admin-main">
            <div class="wlrmg-admin-nav">
                <a href="<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLRMG_PLUGIN_SLUG, "view" => 'activity'))) ?>"
                   class="<?php echo (in_array($current_page, array('activity', 'activity_details'))) ? "active-nav" : ""; ?>"
                ><?php _e("Activity", "wp-loyalty-bulk-action"); ?></a>
                <a class="<?php echo (in_array($current_page, array('actions'))) ? "active-nav" : ""; ?>"
                   href="<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLRMG_PLUGIN_SLUG, "view" => 'actions'))) ?>"
                ><?php _e("Actions", "wp-loyalty-bulk-action"); ?></a>
                <a class="<?php echo (in_array($current_page, array('settings'))) ? "active-nav" : ""; ?>"
                   href="<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLRMG_PLUGIN_SLUG, "view" => 'settings'))) ?>"
                ><?php _e("Settings", "wp-loyalty-bulk-action"); ?></a>
            </div>
        </div>
        <div class="wlrmg-parent">
            <div class="wlrmg-body-content">
                <?php if (isset($main_page) && !empty($main_page) && is_array($main_page)): ?>
                    <?php foreach ($main_page as $page): ?>
                        <?php echo $page; ?>
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