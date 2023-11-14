<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */
defined("ABSPATH") or die(); ?>

<div class="wlrmg-export-popup">
    <div class="wlrmg-section">
        <div class="wlrmg-exported-content">
            <div class="wlrmg-popup-header">
                <h4><?php _e("Download Exports", "wp-loyalty-bulk-action"); ?></h4>
                <i class="wlr wlrf-close-circle wlrmg-cursor" onclick="wlrmg.closePopUp();"></i>
            </div>
            <div class="wlrmg-popup-download-files">
                <?php if (isset($export_files) && !empty($export_files)):?>
                    <?php foreach ($export_files as $file): ?>
                        <div class="wlrmg-exported-file">
                            <div class="wlrmg-file-name">
                                <div><i class="wlr wlrf-file"></i></div>
                                <p><?php echo (isset($file->file_name) && !empty($file->file_name)) ? $file->file_name : ""; ?></p>
                            </div>
                            <a href="<?php echo isset($file->file_url) && !empty($file->file_url) ? $file->file_url : "#"; ?>"
                               download="<?php echo isset($file->file_name) && !empty($file->file_name) ? $file->file_name : ""; ?>"><?php _e("Download", "wp-loyalty-bulk-action"); ?></a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>