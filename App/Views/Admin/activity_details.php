<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */
defined( "ABSPATH" ) or die();
$current_page         = ( isset( $current_page ) && ! empty( $current_page ) ) ? $current_page : $current_page = "activity_details";
$activity             = ( isset( $activity ) && ! empty( $activity ) ) ? $activity : array();
$job_data             = isset( $activity['job_data'] ) && ! empty( $activity['job_data'] ) ? $activity['job_data'] : array();
$action               = ( isset( $action ) && ! empty( $action ) ) ? $action : '';
$earn_campaign_helper = \Wlr\App\Helpers\EarnCampaign::getInstance();
?>
<div id="wlrmg-activity-details"
     class="wlrmg-body-active-content <?php echo ( $current_page == "activity_details" ) ? "active-content" : ""; ?>">
    <div class="wlrmg-activity-details-header">
        <a href="<?php echo admin_url( "admin.php?" . http_build_query( array(
				"page" => WLRMG_PLUGIN_SLUG,
				"view" => "actions"
			) ) ) ?>"><img
                    src="<?php echo ( isset( $back ) && ! empty( $back ) ) ? $back : ""; ?>" class="wlrmg-back-btn"
                    alt="<?php echo esc_html__( "Back", "wp-loyalty-migration" ) ?>"></a>
        <h3><?php _e( "ACTIVITY DETAILS", "wp-loyalty-migration" ); ?></h3>
    </div>
	<?php if ( ! empty( $activity ) ): ?>
        <div class="wlrmg-activity-details-content">
            <div class="wlrmg-job-details">
                <div
                        class="wlrmg-header">
                    <h4><?php echo esc_html( sprintf( __( "Activity - %s ", "wp-loyalty-migration" ), $job_data['action_label'] ) ); ?></h4>
                </div>
                <div class="wlrmg-description">
                    <div class="wlrmg-activity-date">
                        <p class="wlrmg-desc-label"><?php echo esc_html__( "Date created", "wp-loyalty-migration" ) ?></p>
						<?php if ( isset( $job_data["created_at"] ) && ! empty( $job_data["created_at"] ) ): ?>
                            <p class="wlrmg-desc-value"><?php echo esc_html__( $job_data["created_at"], "wp-loyalty-migration" ); ?></p>
						<?php endif; ?>
                    </div>
                    <div class="wlrmg-activity-date">
                        <p class="wlrmg-desc-label"><?php echo esc_html__( "Processed items", "wp-loyalty-migration" ) ?></p>
						<?php if ( isset( $job_data["offset"] ) ): ?>
                            <p class="wlrmg-desc-value"><?php echo esc_html__( $job_data["offset"], "wp-loyalty-migration" ); ?></p>
						<?php endif; ?>
                    </div>
                    <div>
                        <p class=".wlrmg-desc-label"><?php echo esc_html__( 'Status', 'wp-loyalty-migration' ); ?></p>
                        <p class="wlrmg-desc-value wlrmg-activity-status">
                            <span class="<?php echo ! empty( $job_data['status'] ) ? "wlrmg-" . $job_data['status'] : ""; ?>"><?php echo ucfirst( $job_data['status'] ); ?></span>
                        </p>
                    </div>
                </div>
            </div>
			<?php if ( isset( $activity['activity'] ) && ! empty( $activity['activity'] ) &&
			           is_array( $activity['activity'] ) && ( $activity['job_id'] > 0 ) && ! empty( $activity['activity']['activity_list'] ) ):
				$action_activity = $activity['activity'];
				?>
                <div class="wlrmg-activity-log-list">
                    <div class="wlrmg-table-heading-section">
                        <div>
                            <h4><?php echo esc_html__( "Action details", "wp-loyalty-migration" ); ?></h4>
                        </div>
                        <div class="wlrmg-table-search-export">
                            <div class="search-box">
                                <input type="text" name="search" id="search_email"
                                       placeholder="<?php esc_attr_e( 'Search by email', 'wp-loyalty-rules' ) ?>"
                                       value="<?php echo isset( $search ) && ! empty( $search ) ? esc_attr( $search ) : ""; ?>"/>
                                <span id="search_button"
                                      onclick="wlrmg.searchActivityByEmail('<?php echo admin_url( "admin.php?" . http_build_query( array(
										      "page"   => WLRMG_PLUGIN_SLUG,
										      "view"   => "activity_details",
										      "type"   => $action,
										      "job_id" => $job_id
									      ) ) ); ?>');">
                                <i class="wlrf-search"></i>
                            </span>

                            </div>
							<?php
							if ( isset( $action_activity['activity_list'] ) && count( $action_activity['activity_list'] ) > 0 ): ?>
                                <div class="wlrmg-activity-button-section">
									<?php if ( isset( $action_activity['show_export_file_download'] ) && ! empty( $action_activity['show_export_file_download'] ) ): ?>
                                        <button class="wlrmg-button-action" type="button"
                                                onclick="wlrmg.showExported(<?php echo $activity['job_id']; ?>,'<?php echo $action; ?>')"><?php echo __( 'Show Exported File', 'wp-loyalty-migration' ); ?></button>
									<?php endif; ?>
                                    <button class="wlrmg-button-action wlrmg-export-button" type="button"
                                            onclick="wlrmg.exportPopUp(<?php echo $activity['job_id']; ?>,'<?php echo $action; ?>')"><?php echo __( 'Export', 'wp-loyalty-migration' ); ?></button>
                                </div>
							<?php endif; ?>
                        </div>
                    </div>
                    <div id="wlrmg-activity-list-table" class="wlrmg-table">
                        <div id="wlrmg-activity-list-table-header" class="wlrmg-table-header">
                            <p><?php esc_html_e( 'User email', 'wp-loyalty-migration' ) ?></p>
                            <p><?php esc_html_e( 'Referral code', 'wp-loyalty-migration' ) ?></p>
                            <p class="set-center"><?php echo esc_html( $earn_campaign_helper->getPointLabel( 3 ) ); ?></p>
                        </div>
                        <div id="wlrmg-activity-list-table-body" class="wlrmg-table-body">
							<?php foreach ( $action_activity['activity_list'] as $bulk_activity ): ?>
                                <div class="wlrmg-table-row">
                                    <div class="wlrmg-text-wrap">
                                        <p><?php echo $bulk_activity->user_email; ?></p>
                                    </div>
                                    <div class="wlrmg-text-nowrap">
                                        <p><?php echo $bulk_activity->referral_code; ?></p></div>
                                    <div class="wlrmg-text-nowrap">
                                        <p><?php echo $bulk_activity->points; ?></p></div>
                                </div>
							<?php endforeach; ?>
                        </div>
                        <div class="wlrmg-pagination">
							<?php if ( isset( $action_activity['pagination'] ) && ! empty( $action_activity['pagination'] ) ): ?>
								<?php echo $action_activity['pagination']->createLinks(
									array(
										'page_number_name' => 'migration_page',
										'focus_id'         => 'wlrmg-activity-list-table'
									)
								); ?>
							<?php endif; ?>
                        </div>
                    </div>
                </div>
			<?php endif; ?>
        </div>
	<?php else: ?>
        <div class="no-activity-block">
            <div>
                <img
                        src="<?php echo isset( $no_activity_icon ) && ! empty( $no_activity_icon ) ? $no_activity_icon : "" ?>"/>
            </div>
            <div>
                <span class="no-activity-label-1"><?php echo esc_html__( "No activities yet", "wp-loyalty-migration" ) ?></span>
            </div>
            <div>
                <span
                        class="no-activity-label-2"><?php echo esc_html__( "You are in pending status", "wp-loyalty-migration" ) ?></span>
            </div>
        </div>
	<?php endif; ?>
</div>