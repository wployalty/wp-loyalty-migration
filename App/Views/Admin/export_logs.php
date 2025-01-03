<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */
defined("ABSPATH") or die(); ?>

<div class="wlrmg-export-popup">
    <div class="wlrmg-section">
        <div class="wlrmg-export-content">
            <div class="wlrmg-popup-header">
                <h4><?php _e("EXPORT", "wp-loyalty-migration"); ?></h4>
                <i class="wlr wlrf-close-circle wlba-cursor" onclick="wlrmg.closePopUp(true);"></i>
            </div>
            <div>
                <form method="post" class="wlrmg-export-preview" id="wlrmg-export-preview" enctype="multipart/form-data"
                      action="<?php echo isset($base_url) && !empty($base_url) ? $base_url : ""; ?>"
                      id="wlrmg-export-preview">
                    <input type="hidden" name="page" value="<?php echo WLRMG_PLUGIN_SLUG; ?>"/>
                    <input type="hidden" name="action" value="wlrmg_handle_export"/>
                    <input type="hidden" name="category"
                           value="<?php echo isset($category) && !empty($category) ? $category : ''; ?>">
                    <input type="hidden" id="wlrmg_limit_start" name="limit_start"
                           value="<?php echo isset($limit_start) && !empty($limit_start) ? $limit_start : 0; ?>"/>
                    <input type="hidden" id="job_id" name="job_id"
                           value="<?php echo isset($job_id) && !empty($job_id) ? $job_id : 0; ?>"/>
                    <input type="hidden" name="wlrmg_nonce"
                           value="<?php echo isset($wlrmg_nonce) && !empty($wlrmg_nonce) ? $wlrmg_nonce : " "; ?>"/>
                    <input type="hidden" id="limit" name="limit"
                           value="<?php echo (isset($limit) && !empty($limit)) ? $limit : 5; ?>"/>
                    <input type="hidden" id="wlrmg-total-count" name="total_count"
                           value="<?php echo (isset($total_count) && !empty($total_count)) ? $total_count : 0; ?>"/>
                    <div class="wlrmg-export-label">
                        <div>
                            <p><?php _e("Total items", "wp-loyalty-migration"); ?></p>
                            <p><?php echo isset($total_count) && !empty($total_count) ? $total_count : 0; ?></p>
                        </div>
                        <div>
                            <p><?php _e("Processed items", "wp-loyalty-migration"); ?></p>
                            <p id="wlrmg-process-count"><?php echo isset($process_count) && !empty($process_count) ? $process_count : 0; ?></p>
                        </div>
                    </div>
                </form>
            </div>
            <div id="wlrmg-notification" class="wlrmg-notification" style="display: none;">
            </div>
            <div>
                <button style="float: right;" type="button" id="wlrmg-process-export-button"
                        class="wlrmg-pop-select-button"
                        onclick="wlrmg.startExport()"><?php echo __("Export", "wp-loyalty-migration"); ?></button>
            </div>
        </div>

    </div>
</div>