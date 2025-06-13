<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */
defined('ABSPATH') or die();
$current_page = (isset($current_page) && !empty($current_page)) ? $current_page : $current_page = "actions";
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            </div>
        </div>
    </div>
    <div class="wlrmg-body-data">
        <form action="" method="post" id="wlrmg-migration-form">
            <div class="wlrmg-main-content active-page">
                <?php if (isset($migration_cards) && is_array($migration_cards) && !empty($migration_cards)): ?>
                    <div class="wlrmg-migation-card-section">
                        <?php foreach ($migration_cards as $card):
                            $is_completed = (isset($card['job_data']) && is_object($card['job_data']) && isset($card['job_data']->status) && $card['job_data']->status == "completed");
                            $is_active = (isset($card['is_active']) && $card['is_active']); ?>
                            <div class="wlrmg-card <?php echo $is_active || $is_completed ? 'active' : ''; ?>">
                                <div>
                                    <h5><?php echo (isset($card['title']) && !empty($card['title'])) ? $card['title'] : ''; ?></h5>
                                </div>
                                <div>
                                    <p><?php echo (isset($card['description']) && !empty($card['description'])) ? $card['description'] : ''; ?></p>
                                </div>
                                <div class="wlrmg-button-section">
                                    <?php if (isset($card['job_data']) && is_object($card['job_data']) && !isset($card['job_data']->uid)): ?>
                                        <button class="wlrmg-button"
                                                type="button" <?php echo !$is_active ? 'disabled' : ''; ?> <?php echo $is_active ? 'onclick="wlrmg.needConfirmPointUpdate(\'' . $card['type'] . '\')"' : ''; ?> ><?php _e('Migrate', 'wp-loyalty-migration') ?></button>
                                    <?php endif; ?>
                                    <?php if ((isset($card['job_data']) && is_object($card['job_data']) && isset($card['job_data']->uid) && $is_active) || $is_completed): ?>
                                        <a class="wlrmg-button wlrmg-view-button"
                                           title="<?php echo __("View Details", "wp-loyalty-migration") ?>"
                                           href="<?php echo admin_url("admin.php?" . http_build_query(array(
                                                   "page" => WLRMG_PLUGIN_SLUG,
                                                   "view" => "activity_details",
                                                   "type" => $card['type'],
                                                   "job_id" => $card['job_data']->uid
                                               ))) ?>">
                                            <?php echo __("View Details", "wp-loyalty-migration") ?></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
