<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */
defined("ABSPATH") or die();
$current_page = (isset($current_page) && !empty($current_page)) ? $current_page : $current_page = "settings";
$option_settings = isset($option_settings) && !empty($option_settings) && is_array($option_settings) ? $option_settings : array();
?>

<div id="wlrmg-settings"
     class="wlrmg-body-active-content <?php echo ($current_page == "settings") ? "active-content" : ""; ?>">
    <div class="wlrmg-heading-data">
        <div class="headings">
            <div class="heading-section">
                <h3><?php echo esc_html__("SETTINGS", "wp-loyalty-migration"); ?></h3>
            </div>
            <div class="heading-buttons">
                <button type="button" class="wlrmg-button-action non-colored-button"
                        onclick="wlrmg.redirectToUrl('<?php echo isset($back_to_apps_url) && !empty($back_to_apps_url) ? esc_url($back_to_apps_url) : '#'; ?>');">
                    <img src="<?php echo (isset($previous) && !empty($previous)) ? esc_url($previous) : "";  // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage	?>"
                         alt="<?php echo esc_attr(__("Cancel", "wp-loyalty-migration")) ?>">
                    <?php echo esc_html__("Back to WPLoyalty", "wp-loyalty-migration"); ?></button>
                <button class="wlrmg-button-action colored-button" id="wlrmg-save-settings"
                        onclick="wlrmg.saveSettings()">
                    <i class="wlr wlrf-save"></i><?php  echo esc_html__('Save', 'wp-loyalty-migration'); ?>
                </button>
            </div>
        </div>
    </div>
    <div class="wlrmg-body-data">
        <form action="" method="post" id="settings-form">
            <div>
                <div class="menu-title">
                    <p><?php echo esc_html__('Batch limit', 'wp-loyalty-migration'); ?></p>
                </div>
                <?php if (isset($batch_limit) && !empty($batch_limit) && is_array($batch_limit)):
                    $selected_batch = isset($option_settings['batch_limit']) && !empty($option_settings['batch_limit']) ? $option_settings['batch_limit'] : 50;
                    ?>
                    <div class="menu-lists">
                        <select name="batch_limit" id="batch-limit"
                                class="wlrmg-multi-select">
                            <?php foreach ($batch_limit as $batch_key => $batch_value): ?>
                                <option value="<?php echo esc_attr($batch_key); ?>"
                                    <?php echo ($batch_key == $selected_batch) ? "selected" : ""; ?>>
                                    <?php echo esc_html__($batch_value, "wp-loyalty-migration"); //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText	 ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
            <div>
                <div class="menu-title">
                    <p><?php esc_html_e('Pagination limit', 'wp-loyalty-migration'); ?></p>
                </div>
                <?php
                if (isset($pagination_limit) && !empty($pagination_limit) && is_array($pagination_limit)):
                    $selected_pagination = isset($option_settings['pagination_limit']) && !empty($option_settings['pagination_limit']) ? $option_settings['pagination_limit'] : 10;
                    ?>
                    <div class="menu-lists">
                        <select name="pagination_limit" id="pagination_limit"
                                class="wlrmg-multi-select">
                            <?php foreach ($pagination_limit as $pagination_key => $pagination_value): ?>
                                <option value="<?php echo esc_attr($pagination_key); ?>"<?php echo ($pagination_key == $selected_pagination) ? esc_attr("selected") : ""; ?>>
                                    <?php echo esc_html__($pagination_value, "wp-loyalty-migration") //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText	?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
        </form>

    </div>
</div>
