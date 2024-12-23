<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */
defined("ABSPATH") or die();
$category = isset($category) && !empty($category) ? $category : '';
?>
<div id="wlrmg-popup" class="wlrmg-popup">
    <div class="wlrmg-popup-head">
        <h3><?php _e('Update points', 'wp-loyalty-migration') ?></h3>
        <span class="wlrmg-cursor wlrmg-close-icon"
              onclick="wlrmg_jquery('#wlrmg-main-page #wlrmg-overlay-section').removeClass('active');">&#10005;</span>
    </div>
    <div class="wlrmg-popup-body">
        <div>
            <div>
                <label for="update_point"><?php _e('Add/skip points to existing customers while migrate?', 'wp-loyalty-migration'); ?></label>
            </div>
            <div>
                <input type="hidden" name="migration_type" id="migration_type" value="<?php echo $category; ?>">
                <select name="update_point" id="update_point" class="wlrmg-multi-select">
                    <option value="skip"><?php _e('Skip customer', 'wp-loyalty-migration'); ?></option>
                    <option value="add"><?php _e('Add points to customer', 'wp-loyalty-migration'); ?></option>
                </select>
            </div>
        </div>
        <div>
            <div>
                <label for="update_banned_user"><?php _e('Add/skip points to banned customers while migrate?', 'wp-loyalty-migration'); ?></label>
            </div>
            <div>
                <select name="update_banned_user" id="update_banned_user" class="wlrmg-multi-select">
                    <option value="skip"><?php _e('Skip banned customer', 'wp-loyalty-migration'); ?></option>
                    <option value="add"><?php _e('Add points to banned customer', 'wp-loyalty-migration'); ?></option>
                </select>
            </div>
        </div>
    </div>
    <div class="wlrmg-popup-foot">
        <button type="button" onclick="wlrmg.migrateUsers()"><?php _e('Migrate', 'wp-loyalty-migration') ?></button>
    </div>
</div>

