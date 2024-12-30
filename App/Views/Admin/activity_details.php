<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

use Wlr\App\Helpers\EarnCampaign;

defined("ABSPATH") or die();
$current_page = (isset($current_page) && !empty($current_page)) ? $current_page : $current_page = "activity_details";
$activity = (isset($activity) && !empty($activity)) ? $activity : array();
$job_data = isset($activity['job_data']) && !empty($activity['job_data']) ? $activity['job_data'] : array();
$action = (isset($action) && !empty($action)) ? $action : '';
$earn_campaign_helper = EarnCampaign::getInstance();
?>
<div id="wlrmg-activity-details"
     class="wlrmg-body-active-content <?php echo ($current_page == "activity_details") ? "active-content" : ""; ?>">
    <div class="wlrmg-activity-details-header">
        <a href="<?php echo admin_url("admin.php?" . http_build_query(array(
				"page" => WLRMG_PLUGIN_SLUG,
				"view" => "actions"
			))) ?>"><img
                    src="<?php echo (isset($back) && !empty($back)) ? $back : ""; ?>" class="wlrmg-back-btn"
                    alt="<?php echo esc_html__("Back", "wp-loyalty-migration") ?>"></a>
        <h3><?php _e("ACTIVITY DETAILS", "wp-loyalty-migration"); ?></h3>
    </div>
	<?php if (!empty($activity)): ?>
        <div class="wlrmg-activity-details-content">
            <div class="wlrmg-job-details">
                <div
                        class="wlrmg-header">
                    <h4><?php echo esc_html(sprintf(__("Activity - %s ", "wp-loyalty-migration"), $job_data['action_label'])); ?></h4>
                </div>
                <div class="wlrmg-description">
                    <div class="wlrmg-activity-date">
                        <p class="wlrmg-desc-label"><?php echo esc_html__("Date created", "wp-loyalty-migration") ?></p>
						<?php if (isset($job_data["created_at"]) && !empty($job_data["created_at"])): ?>
                            <p class="wlrmg-desc-value"><?php echo esc_html__($job_data["created_at"], "wp-loyalty-migration"); ?></p>
						<?php endif; ?>
                    </div>
                    <div class="wlrmg-activity-date">
                        <p class="wlrmg-desc-label"><?php echo esc_html__("Processed items", "wp-loyalty-migration") ?></p>
						<?php if (isset($job_data["offset"])): ?>
                            <p class="wlrmg-desc-value"><?php echo esc_html__($job_data["offset"], "wp-loyalty-migration"); ?></p>
						<?php endif; ?>
                    </div>
                    <div>
                        <p class=".wlrmg-desc-label"><?php echo esc_html__('Status', 'wp-loyalty-migration'); ?></p>
                        <p class="wlrmg-desc-value wlrmg-activity-status">
                            <span class="<?php echo !empty($job_data['status']) ? "wlrmg-" . $job_data['status'] : ""; ?>"><?php echo ucfirst($job_data['status']); ?></span>
                        </p>
                    </div>
					<?php if (!empty($job_data['conditions']['update_point'])): ?>
                        <div>
                            <p class=".wlrmg-desc-label"><?php echo esc_html__('Update points', 'wp-loyalty-migration'); ?></p>
                            <p class="wlrmg-desc-value ">
                                <span><?php echo ucfirst($job_data['conditions']['update_point']); ?></span>
                            </p>
                        </div>
					<?php endif; ?>
					<?php if (!empty($job_data['conditions']['update_banned_user'])): ?>
                        <div>
                            <p class=".wlrmg-desc-label"><?php echo esc_html__('Update banned user', 'wp-loyalty-migration'); ?></p>
                            <p class="wlrmg-desc-value ">
                                <span><?php echo ucfirst($job_data['conditions']['update_banned_user']); ?></span>
                            </p>
                        </div>
					<?php endif; ?>
                </div>
            </div>
			<?php
			// Check if a search parameter exists in the URL
			$search = isset( $_GET['search'] ) ? $_GET['search'] : '';

			if (!empty($search)) {
				$v = new Valitron\Validator(['search' => $search]);
				$v->rule('regex', 'search', '/^[^<>]*$/')->message('Basic Validation Failed');

				if (!$v->validate()) {
					// Trigger Alertify styled for WPLoyalty
					echo '<script>

            document.addEventListener("DOMContentLoaded", function() {
                alertify.error("' . htmlspecialchars($v->errors('search')[0], ENT_QUOTES, 'UTF-8') . '");
                alertify.set("notifier","position", "top-right"); 
                
            });
        </script>';

					$search = ''; // Reset invalid input
				} else {
					$search = sanitize_text_field($search); // Sanitize valid input
				}
			}

			// Filter the activity list based on the search email
			if (!empty($search)) {
				$filtered_activities = array_filter($activity['activity']['activity_list'], function ($bulk_activity) use ($search) {
					return strpos($bulk_activity->user_email, $search) !== false;
				});
			} else {
				$filtered_activities = $activity['activity']['activity_list'];
			}

			// Check if there are activities to display
			if (isset($activity['activity']) && !empty($activity['activity']) && is_array($activity['activity']) && ($activity['job_id'] > 0)):
				?>
                <div class="wlrmg-activity-log-list">
                    <div class="wlrmg-table-heading-section">
                        <div>
                            <h4><?php echo esc_html__("Action details", "wp-loyalty-migration"); ?></h4>
                        </div>
						<?php if ($action == $activity['job_data']['action'] && $job_data['offset'] >0 ) : ?>
                            <div class="wlrmg-table-search-export">
                                <div class="search-box">
                                    <input type="text" name="search" id="search_email"
                                           placeholder="<?php esc_attr_e('Search by email', 'wp-loyalty-rules'); ?>"
                                           value="<?php echo esc_attr($search); ?>"/>
                                    <span id="search_button"
                                          onclick="
                                                  const searchEmail = document.getElementById('search_email').value;
                                                  const baseUrl = '<?php echo admin_url("admin.php?" . http_build_query(array(
											      "page" => WLRMG_PLUGIN_SLUG,
											      "view" => "activity_details",
											      "type" => $action,
											      "job_id" => $job_id
										      ))); ?>';
                                                  const newUrl = searchEmail ? baseUrl + '&search=' + encodeURIComponent(searchEmail) : baseUrl;
                                                  window.location.href = newUrl;
                                                  ">
                                     <i class="wlrf-search"></i>
                                </span>
                                </div>
                                <!-- Always show the export button -->
                                <div class="wlrmg-activity-button-section">
									<?php if (!empty($activity['activity']['show_export_file_download'])): ?>
                                        <button class="wlrmg-button-action" type="button"
                                                onclick="wlrmg.showExported(<?php echo $activity['job_id']; ?>,'<?php echo $action; ?>')"><?php echo __('Show Exported File', 'wp-loyalty-migration'); ?></button>
									<?php endif; ?>
                                    <button class="wlrmg-button-action wlrmg-export-button" type="button"
                                            onclick="wlrmg.exportPopUp(<?php echo $activity['job_id']; ?>,'<?php echo $action; ?>')"><?php echo __('Export', 'wp-loyalty-migration'); ?></button>
                                </div>
                            </div>
						<?php endif; ?>
                    </div>
                    <div id="wlrmg-activity-list-table" class="wlrmg-table">
                        <div id="wlrmg-activity-list-table-header" class="wlrmg-table-header">
                            <p><?php esc_html_e('User email', 'wp-loyalty-migration'); ?></p>
                            <p><?php esc_html_e('Referral code', 'wp-loyalty-migration'); ?></p>
                            <p class="set-center"><?php echo esc_html($earn_campaign_helper->getPointLabel(3)); ?></p>
                        </div>
                        <div id="wlrmg-activity-list-table-body" class="wlrmg-table-body">
							<?php if (!empty($filtered_activities)): ?>
								<?php foreach ($filtered_activities as $bulk_activity): ?>
                                    <div class="wlrmg-table-row">
                                        <div class="wlrmg-text-wrap">
                                            <p><?php echo esc_html($bulk_activity->user_email); ?></p>
                                        </div>
                                        <div class="wlrmg-text-nowrap">
                                            <p><?php echo esc_html($bulk_activity->referral_code); ?></p>
                                        </div>
                                        <div class="wlrmg-text-nowrap">
                                            <p><?php echo esc_html($bulk_activity->points); ?></p>
                                        </div>
                                    </div>
								<?php endforeach; ?>
							<?php else: ?>
                                <div class="wlrmg-no-results">
                                    <p><?php esc_html_e('No activities found for the entered email.', 'wp-loyalty-migration'); ?></p>
                                </div>
							<?php endif; ?>
                        </div>
						<?php if (!empty($activity['activity']['pagination'])): ?>
                            <div class="wlrmg-pagination">
								<?php echo $activity['activity']['pagination']->createLinks(
									array(
										'page_number_name' => 'migration_page',
										'focus_id' => 'wlrmg-activity-list-table'
									)
								); ?>
                            </div>
						<?php endif; ?>
                    </div>
                </div>
			<?php endif; ?>
        </div>
	<?php else: ?>
        <div class="no-activity-block">
            <div>
                <img
                        src="<?php echo isset($no_activity_icon) && !empty($no_activity_icon) ? $no_activity_icon : "" ?>"/>
            </div>
            <div>
                <span class="no-activity-label-1"><?php echo esc_html__("No activities yet", "wp-loyalty-migration") ?></span>
            </div>
            <div>
                <span
                        class="no-activity-label-2"><?php echo esc_html__("You are in pending status", "wp-loyalty-migration") ?></span>
            </div>
        </div>
	<?php endif; ?>
</div>