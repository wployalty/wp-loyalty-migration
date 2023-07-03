<?php
defined("ABSPATH") or die();
$current_page = (isset($current_page) && !empty($current_page)) ? $current_page : $current_page = "settings";
$option_settings = isset($option_settings) && !empty($option_settings) && is_array($option_settings) ? $option_settings : array();
?>
<div id="wlrmg-settings"
     class="wlrmg-body-active-content <?php echo ($current_page == "settings") ? "active-content" : ""; ?>">
    <div class="wlrmg-heading-data">
        <div class="headings">
            <div class="heading-section">
                <h3><?php _e("SETTINGS", "wp-loyalty-bulk-action"); ?></h3>
            </div>
            <div class="heading-buttons">
                <button type="button" class="wlrmg-button-action non-colored-button"
                        onclick="wlrmg.redirectToUrl('<?php echo isset($back_to_apps_url) && !empty($back_to_apps_url) ? $back_to_apps_url : '#'; ?>');">
                    <img src="<?php echo (isset($previous) && !empty($previous)) ? $previous : ""; ?>"
                         alt="<?php echo __("Cancel", "wp-loyalty-bulk-action") ?>">
                    <?php _e("Back to WPLoyalty", "wp-loyalty-bulk-action"); ?></button>
                <button class="wlrmg-button-action colored-button" onclick="wlrmg.saveSettings()">
                    <i class="wlr wlrf-save"></i><?php _e('Save', 'wp-loyalty-bulk-action'); ?>
                </button>
            </div>
        </div>
    </div>
    <div class="wlrmg-body-data">
        <form action="" method="post" id="settings-form">
            <div>
                <div class="menu-title">
                    <p><?php _e('Batch limit', 'wp-loyalty-bulk-action'); ?></p>
                </div>
                <?php if (isset($batch_limit) && !empty($batch_limit) && is_array($batch_limit)):
                    $selected_batch = isset($option_settings['batch_limit']) && !empty($option_settings['batch_limit']) ? $option_settings['batch_limit'] : 0;
                    ?>
                    <div class="menu-lists">
                        <select name="batch_limit" id="batch-limit"
                                class="wlrmg-multi-select">
                            <?php foreach ($batch_limit as $batch_key => $batch_value): ?>
                                <option value="<?php echo $batch_key; ?>"
                                    <?php echo ($batch_key == $selected_batch) ? "selected" : ""; ?>>
                                    <?php esc_html_e($batch_value, "wp-loyalty-bulk-action"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <div class="menu-title">
                    <p><?php _e('Pagination limit', 'wp-loyalty-bulk-action'); ?></p>
                </div>
                <?php
                if (isset($pagination_limit) && !empty($pagination_limit) && is_array($pagination_limit)):
                    $selected_pagination = isset($option_settings['pagination_limit']) && !empty($option_settings['pagination_limit']) ? $option_settings['pagination_limit'] : 10;
                    ?>
                    <div class="menu-lists">
                        <select name="pagination_limit" id="pagination_limit"
                                class="wlrmg-multi-select">
                            <?php foreach ($pagination_limit as $pagination_key => $pagination_value): ?>
                                <option value="<?php echo $pagination_key; ?>"
                                    <?php echo ($pagination_key == $selected_pagination) ? "selected" : ""; ?>>
                                    <?php esc_html_e($pagination_value, "wp-loyalty-bulk-action") ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
        </form>

    </div>
</div>
