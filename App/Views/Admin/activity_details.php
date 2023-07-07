<?php defined("ABSPATH") or die();
$current_page = (isset($current_page) && !empty($current_page)) ? $current_page : $current_page = "activity_details";
$activity = (isset($activity) && !empty($activity)) ? $activity : array();
$earn_campaign_helper = \Wlr\App\Helpers\EarnCampaign::getInstance();
?>
<div id="wlrmg-activity-details"
     class="wlrmg-body-active-content <?php echo ($current_page == "activity_details") ? "active-content" : ""; ?>">
    <div class="wlrmg-activity-details-header">
        <a href="<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLRMG_PLUGIN_SLUG, "view" => "activity"))) ?>"><img
                src="<?php echo (isset($back) && !empty($back)) ? $back : ""; ?>" class="wlrmg-back-btn"
                alt="<?php echo esc_html__("Back", "wp-loyalty-migration") ?>"></a>
        <h3><?php _e("ACTIVITY DETAILS", "wp-loyalty-migration"); ?></h3>
    </div>
    <?php if (!empty($activity)): ?>
        <div class="wlrmg-activity-details-content">
            <div class="wlrmg-points-details">
                <div
                    class="wlrmg-header">
                    <h4><?php echo esc_html(sprintf(__("Activity - %s", "wp-loyalty-migration"), $activity['action_type'])); ?></h4>
                </div>
                <div class="wlrmg-description">
                    <div class="wlrmg-activity-date">
                        <p class="wlrmg-desc-label"><?php echo esc_html__("Date added", "wp-loyalty-migration") ?></p>
                        <?php if (isset($activity["date"]) && !empty($activity["date"])): ?>
                            <p class="wlrmg-desc-value"><?php echo esc_html__($activity["date"], "wp-loyalty-migration"); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="wlrmg-no-of-points-added">
                        <p class="wlrmg-desc-label"><?php echo esc_html(sprintf(__("%s ", "wp-loyalty-migration"), $earn_campaign_helper->getPointLabel(3))); ?></p>
                        <?php if (isset($activity["points"]) && ($activity["points"] != "")): ?>
                            <p class="wlrmg-desc-value"><?php echo $activity["points"]; ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="wlrmg-points-through">
                        <p class="wlrmg-desc-label"><?php echo esc_html__("Points through", "wp-loyalty-migration") ?></p>
                        <?php if (isset($activity["point_type"]) && !empty($activity["point_type"])): ?>
                            <p class="wlrmg-desc-value"><?php echo esc_html__($activity["point_type"], "wp-loyalty-migration"); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php if (isset($activity['bulk_action_activity']) && !empty($activity['bulk_action_activity']) &&
                is_array($activity['bulk_action_activity']) && ($activity['job_id'] > 0) && !empty($activity['bulk_action_activity']['activity_list'])):
                $bulk_action_activity = $activity['bulk_action_activity'];
                ?>
                <div class="wlrmg-bulk-activity-list">
                    <div class="wlrmg-table-heading-section">
                        <div>
                            <h4><?php esc_html_e(sprintf(__("Bulk action details - %s", "wp-loyalty-migration"), $activity['condition']['status'])); ?></h4>
                        </div>
                        <?php if (isset($bulk_action_activity['activity_list']) && count($bulk_action_activity['activity_list']) > 1 && $activity['condition']['status'] == 'completed'): ?>
                            <div class="wlrmg-activity-button-section">
                                <?php if (isset($bulk_action_activity['show_export_file_download']) && !empty($bulk_action_activity['show_export_file_download'])): ?>
                                    <button class="wlrmg-button-action" type="button"
                                            onclick="wlrmg.showExported(<?php echo $activity['job_id']; ?>,'bulk_action','<?php echo $activity['bulk_action_type']; ?>')"><?php echo __('Show Exported File', 'wp-loyalty-migration'); ?></button>
                                <?php endif; ?>
                                <button class="wlrmg-button-action wlrmg-export-button" type="button"
                                        onclick="wlrmg.exportPopUp(<?php echo $activity['job_id']; ?>,'bulk_action','<?php echo $activity['bulk_action_type']; ?>')"><?php echo __('Export', 'wp-loyalty-migration'); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div id="wlrmg-activity-list-table" class="wlrmg-table">
                        <div id="wlrmg-activity-list-table-header" class="wlrmg-table-header">
                            <p><?php esc_html_e('User email', 'wp-loyalty-migration') ?></p>
                            <p><?php esc_html_e('Trans type', 'wp-loyalty-migration') ?></p>
                            <p class="set-center"><?php echo esc_html($earn_campaign_helper->getPointLabel(3)); ?></p>
                            <p><?php esc_html_e('Note', 'wp-loyalty-migration') ?></p>
                        </div>
                        <div id="wlrmg-activity-list-table-body" class="wlrmg-table-body">
                            <?php foreach ($bulk_action_activity['activity_list'] as $bulk_activity): ?>
                                <div class="wlrmg-table-row">
                                    <div class="wlrmg-text-wrap">
                                        <p><?php echo $bulk_activity->user_email; ?></p>
                                    </div>
                                    <div class="wlrmg-text-nowrap">
                                        <p><?php echo $bulk_activity->trans_type; ?></p></div>
                                    <div class="wlrmg-text-nowrap">
                                        <p><?php echo $bulk_activity->after_trans_point; ?></p></div>
                                    <div class="wlrmg-text-wrap"><p><?php echo $bulk_activity->note; ?></p></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="wlrmg-pagination">
                            <?php if (isset($bulk_action_activity['pagination']) && !empty($bulk_action_activity['pagination'])): ?>
                                <?php echo $bulk_action_activity['pagination']->createLinks(
                                    array('page_number_name' => 'bulk_action_page', 'focus_id' => 'wlrmg-activity-list-table')
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