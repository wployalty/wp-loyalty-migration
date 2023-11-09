<?php
defined("ABSPATH") or die();
$category = isset($category) && !empty($category) ? $category : '';
?>
<div id="wlrmg-popup" class="wlrmg-popup">
    <div class="wlrmg-popup-head">
        <h4><?php _e('Update points','wp-loyalty-migration')?></h4>
        <span class="wlrmg-cursor wlrmg-close-icon" onclick="wlrmg_jquery('#wlrmg-main-page #wlrmg-overlay-section').removeClass('active');">&#10005;</span>
    </div>
    <div class="wlrmg-popup-body">
        <div>
            <label for="update_point"><?php _e('Add/skip points to existing customers while migrate?','wp-loyalty-migration');?></label>
        </div>
        <div>
            <input type="hidden" name="migration_type" id="migration_type" value="<?php echo $category;?>">
            <select name="update_point" id="update_point" class="wlrmg-multi-select">
                <option value="skip"><?php _e('Skip customer','wp-loyalty-migration');?></option>
                <option value="add"><?php _e('Add points to customer','wp-loyalty-migration');?></option>
            </select>
        </div>
    </div>
    <div class="wlrmg-popup-foot">
        <button type="button"  onclick="wlrmg.migrateUsers()"><?php _e('Migrate','wp-loyalty-migration') ?></button>
    </div>
</div>

