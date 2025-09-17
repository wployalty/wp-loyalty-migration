<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */
defined( "ABSPATH" ) or die(); ?>

<div class="wlrmg-export-popup">
    <div class="wlrmg-section">
        <div class="wlrmg-exported-content">
            <div class="wlrmg-popup-header">
                <h4><?php echo esc_html__( "Download Exports", "wp-loyalty-migration" ); ?></h4>
                <i class="wlr wlrf-close-circle wlrmg-cursor" onclick="wlrmg.closePopUp();"></i>
            </div>
            <div class="wlrmg-popup-download-files">
				<?php if ( isset( $export_files ) && ! empty( $export_files ) ): ?>
					<?php foreach ( $export_files as $file ): ?>
                        <div class="wlrmg-exported-file">
                            <div class="wlrmg-file-name">
                                <div><i class="wlr wlrf-file"></i></div>
                                <p><?php echo ( ! empty( $file->file_name ) ) ? esc_html( $file->file_name ) : ""; ?></p>
                            </div>
                            <a href="<?php echo ! empty( $file->file_url ) ? esc_url( $file->file_url ) : "#"; ?>"
                               download="<?php echo ! empty( $file->file_name ) ? esc_html( $file->file_name ) : ""; ?>"><?php echo esc_html__( "Download", "wp-loyalty-migration" ); ?></a>
                        </div>
					<?php endforeach; ?>
				<?php endif; ?>
            </div>
        </div>

    </div>
</div>