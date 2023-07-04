<?php
defined('ABSPATH') or die();
$current_page = (isset($current_page) && !empty($current_page)) ? $current_page : $current_page = "actions";
?>
<div id="wlrmg-actions"
     class="wlrmg-body-active-content <?php echo ($current_page == "actions") ? "active-content" : ""; ?>">
    <div class="wlrmg-heading-data">
        <div class="headings">
            <div class="heading-section">
                <h3><?php _e("MIGRATION ACTION", "wp-loyalty-migration"); ?></h3>
            </div>
            <div class="heading-buttons">
                <button type="button" class="wlrmg-button-action non-colored-button"
                        onclick="wlrmg.redirectToUrl('<?php echo isset($back_to_apps_url) && !empty($back_to_apps_url) ? $back_to_apps_url : '#'; ?>');">
                    <img src="<?php echo (isset($previous) && !empty($previous)) ? $previous : ""; ?>"
                         alt="<?php echo __("Cancel", "wp-loyalty-migration") ?>">
                    <?php _e("Back to WPLoyalty", "wp-loyalty-migration"); ?></button>
                <button type="button" class="wlrmg-button-action colored-button"
                        onclick="wlrmg.createJob('#wlrmg-migration-form')">
                    <img src="<?php echo (isset($save) && !empty($save)) ? $save : ""; ?>"
                         alt="<?php echo __("Save", "wp-loyalty-migration") ?>">
                    <?php _e("Save", "wp-loyalty-migration"); ?></button>
            </div>
        </div>
    </div>
    <div class="wlrmg-body-data">
        <form action="" method="post" id="wlrmg-migration-form">
            <div class="wlrmg-main-content active-page">
                <div>
                    <label for="wlrmg-action-type"><?php _e('Migrate to WPLoyalty', 'wp-loyalty-migration'); ?></label>
                    <select name="migration_action" id="wlrmg-action-type">
                        <option value=""><?php _e('select option', 'wp-loyalty-migration'); ?></option>
                        <option value="wp_swings_migration" selected><?php _e('WPSwings', 'wp-loyalty-migration'); ?></option>
                    </select>
                </div>
                <div>
                    <label for="wlrmg-comment"><?php _e('Comment', 'wp-loyalty-migration'); ?></label>
                    <textarea name="comment" id="wlrmg-comment"></textarea>
                </div>
            </div>
        </form>
    </div>
</div>
