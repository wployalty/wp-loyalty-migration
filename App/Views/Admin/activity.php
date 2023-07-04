<?php defined("ABSPATH") or die();
$current_page = (isset($current_page) && !empty($current_page)) ? $current_page : $current_page = "activity";
$activity_list_data = (isset($activity_list) && !empty($activity_list)) ? $activity_list : array();
$condition = isset($condition) && !empty($condition) ? $condition : "";
$condition_status = isset($condition_status) && !empty($condition_status) ? $condition_status : array();
?>
<div id="wlrmg-activity"
     class="wlrmg-body-active-content <?php echo ($current_page == "activity") ? "active-content" : ""; ?>">
    <div>
        <form action="<?php echo isset($base_url) ? $base_url : ""; ?>" method="post"
              id="wlrmg_activity_form"
              name="wlrmg_activity_form">
            <div class="wlrmg-activity-header">

                <div class="wlrmg-activity-heading">
                    <h3><?php _e("ACTIVITIES", "wp-loyalty-migration"); ?></h3>
                </div>
                <div class="wlrmg-filter-selection-block">
                    <?php if (isset($condition_status) && !empty($condition_status) && isset($condition) && !empty($condition)): ?>
                        <?php foreach ($condition_status as $key => $status): ?>
                            <div class="wlrmg-filter-status">
                                <a href="<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLBA_PLUGIN_SLUG, "view" => "activity", "condition" => $key))); ?>" <?php echo $key === $condition ? 'class="active-filter"' : "" ?>><?php echo __($status, "wp-loyalty-migration") ?></a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="condition"
                       value="<?php echo isset($condition) ? esc_attr($condition) : "all"; ?>"/>
                <input type="hidden" name="page" value="<?php echo WLBA_PLUGIN_SLUG; ?>"/>
                <input type="hidden" name="view" value="activity"/>
            </div>
        </form>
    </div>
    <div class="wlrmg-activity-list">
        <?php if (empty($activity_list_data)): ?>
            <div class="wlrmg-no-activity-yet">
                <div>
                    <img
                        src="<?php echo (isset($no_activity_list) && !empty($no_activity_list)) ? $no_activity_list : ""; ?>"
                        alt="<?php echo __("Filter", "wp-loyalty-migration") ?>">
                </div>
                <div>
                    <p style="font-size: 20px; color: #161F31;"><?php echo __("No activities yet", "wp-loyalty-migration") ?></p>
                </div>
                <div>
                    <p style="font-size: 16px; color: #535863;"><?php echo __("Get started by migrate customers from wordpress", "wp-loyalty-migration") ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="wlrmg-activity-list-header">
                <div></div>
                <div class="wlrmg-activity-header "
                ><?php echo __("JOB ID", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header wlba-grid-span-2"
                ><?php echo __("ACTIVITY", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header wlba-grid-span-2"
                ><?php echo __("CATEGORY", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header"
                ><?php echo __("PROCESSED", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header "
                ><?php echo __("STATUS", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header"
                ><?php echo __("REVERT PROCESSED", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header"
                ><?php echo __("REVERT STATUS", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header wlba-grid-span-2"
                ><?php echo __("ACTION", "wp-loyalty-migration") ?></div>
            </div>
            <div class="wlrmg-activity-data">
                <?php foreach ($activity_list_data as $activity): ?>
                    <div class="wlrmg-activity-list-row">
                        <div class="wlrmg-activity-row-data"
                             onclick="wlrmg.redirectToUrl('<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLBA_PLUGIN_SLUG, "view" => "activity_details", "job_id" => $activity["job_id"]))) ?>')">
                            <img
                                src="<?php echo (isset($activity["image_icon"]) && !empty($activity["image_icon"])) ? $activity["image_icon"] : ""; ?>"
                                alt="<?php echo __("Order point", "wp-loyalty-migration") ?>">
                        </div>
                        <div class="wlrmg-activity-row-data "
                             onclick="wlrmg.redirectToUrl('<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLBA_PLUGIN_SLUG, "view" => "activity_details", "job_id" => $activity["job_id"]))) ?>')">
                            <?php echo $activity["job_id"]; ?>
                        </div>
                        <div class="wlrmg-activity-row-data wlba-grid-span-2"
                             onclick="wlrmg.redirectToUrl('<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLBA_PLUGIN_SLUG, "view" => "activity_details", "job_id" => $activity["job_id"]))) ?>')">
                            <?php echo sprintf(__("%s %s updated", "wp-loyalty-migration"), $activity["points"], $activity["points"]) ?>
                        </div>
                        <div class="wlrmg-activity-row-data wlba-grid-span-2"
                             onclick="wlrmg.redirectToUrl('<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLBA_PLUGIN_SLUG, "view" => "activity_details", "job_id" => $activity["job_id"]))) ?>')">
                            <?php echo __($activity["action"], "wp-loyalty-migration"); ?>
                        </div>
                        <div class="wlrmg-activity-row-data"
                             onclick="wlrmg.redirectToUrl('<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLBA_PLUGIN_SLUG, "view" => "activity_details", "job_id" => $activity["job_id"]))) ?>')">
                            <?php echo __($activity["processed_count"], "wp-loyalty-migration"); ?>
                        </div>
                        <div class="wlrmg-activity-row-data "
                             onclick="wlrmg.redirectToUrl('<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLBA_PLUGIN_SLUG, "view" => "activity_details", "job_id" => $activity["job_id"]))) ?>')">
                            <?php echo __($activity["status"], "wp-loyalty-migration"); ?>
                        </div>
                        <div class="wlrmg-activity-row-data"
                             onclick="wlrmg.redirectToUrl('<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLBA_PLUGIN_SLUG, "view" => "activity_details", "job_id" => $activity["job_id"]))) ?>')">
                            <?php echo __($activity["revert_processed_count"], "wp-loyalty-migration"); ?>
                        </div>
                        <div class="wlrmg-activity-row-data"
                             onclick="wlrmg.redirectToUrl('<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLBA_PLUGIN_SLUG, "view" => "activity_details", "job_id" => $activity["job_id"]))) ?>')">
                            <?php echo __($activity["revert_status"], "wp-loyalty-migration"); ?>
                        </div>
                        <div class="actions wlba-grid-span-2">
                            <div>
                                <a class="view_details"
                                   title="<?php echo __("View Details", "wp-loyalty-migration") ?>"
                                   href="<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLBA_PLUGIN_SLUG, "view" => "activity_details", "job_id" => $activity["job_id"]))) ?>">
                                    <i class="wlr wlrf-view "></i></a>
                            </div>
                            <?php if (isset($activity['show_edit_button']) && $activity['show_edit_button']): ?>
                                <div>
                                    <a class="view_details" title="<?php echo __("Edit", "wp-loyalty-migration") ?>"
                                       onclick="wlrmg.checkActivityStatus(<?php echo $activity['job_id']; ?>,'<?php echo admin_url("admin.php?" . http_build_query(array("page" => WLBA_PLUGIN_SLUG, "view" => $activity['show_edit_category'], "job_id" => $activity["job_id"]))) ?>')"
                                       href="javascript:void(0);">
                                        <i class="wlr wlrf-edit "></i></a>
                                </div>
                            <?php endif; ?>
                            <?php if (isset($activity['show_revert_button']) && $activity['show_revert_button']): ?>
                                <div>
                                    <i title="<?php echo __("Revert", "wp-loyalty-migration") ?>"
                                       onclick="wlrmg.revertAction(<?php echo $activity['job_id']; ?>)"
                                       class="wlr wlrf-refresh "></i>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <?php if (!empty($activity_list_data)): ?>
        <div class="wlrmg-pagination">
            <?php if (isset($pagination) && !empty($pagination)): ?>
                <?php echo $pagination->createLinks(); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
