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
            </div>
        </div>
    </div>
    <div class="wlrmg-body-data">
        <form action="" method="post" id="wlrmg-migration-form">
            <div class="wlrmg-main-content active-page">
                <?php if (isset($migration_cards) && is_array($migration_cards) && !empty($migration_cards)): ?>
                    <div class="wlrmg-migation-card-section">
                        <?php foreach ($migration_cards as $card):
                            $is_active = (isset($card['is_active']) && $card['is_active']); ?>
                            <div class="wlrmg-card <?php echo $is_active ? 'active' : ''; ?>" <?php echo !$is_active ? 'disabled' : ''; ?>>
                                <div>
                                    <h5><?php echo (isset($card['title']) && !empty($card['title'])) ? $card['title'] : ''; ?></h5>
                                </div>
                                <div>
                                    <p><?php echo (isset($card['description']) && !empty($card['description'])) ? $card['description'] : ''; ?></p>
                                </div>
                                <?php if (isset($card['is_show_migrate_button']) && $card['is_show_migrate_button']): ?>
                                    <div>
                                        <button class="wlrmg-button" type="button" <?php echo !$is_active ? 'disabled' : ''; ?> <?php echo $is_active ? 'onclick="wlrmg.migrateUsers(\''.$card['type'].'\')"' : ''; ?> ><?php _e('Migrate', 'wp-loyalty-migration') ?></button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
