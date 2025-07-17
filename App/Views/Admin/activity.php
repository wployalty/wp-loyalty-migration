<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */
defined("ABSPATH") or die();
$current_page = (isset($current_page) && !empty($current_page)) ? $current_page : $current_page = "activity";
$activity_list_data = (isset($activity_list) && !empty($activity_list)) ? $activity_list : array();
$condition = isset($condition) && !empty($condition) ? $condition : "";
$condition_status = isset($condition_status) && !empty($condition_status) ? $condition_status : array();
?>
<div id="wlrmg-activity"
     class="wlrmg-body-active-content <?php echo ($current_page == "activity") ? "active-content" : ""; ?>">
    <div>
        <form action="<?php echo isset($base_url) ? esc_url($base_url) : ""; ?>" method="post"
              id="wlrmg_activity_form"
              name="wlrmg_activity_form">
            <div class="wlrmg-activity-header">
                <div class="wlrmg-activity-heading">
                    <h3><?php esc_html_e("ACTIVITIES", "wp-loyalty-migration"); ?></h3>
                </div>
                <div class="wlrmg-filter-selection-block">
                    <?php if (isset($condition_status) && !empty($condition_status) && isset($condition) && !empty($condition)): ?>
                        <?php foreach ($condition_status as $key => $status): ?>
                            <div class="wlrmg-filter-status">
                                <a href="<?php echo esc_url(admin_url("admin.php?" . http_build_query(array(
                                        "page" => WLRMG_PLUGIN_SLUG,
                                        "view" => "activity",
                                        "condition" => $key
                                    )))); ?>" <?php echo $key === $condition ? 'class="active-filter"' : "" ?>><?php echo esc_html__($status, "wp-loyalty-migration") //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText	 ?></a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <input type="hidden" name="condition"
                       value="<?php echo isset($condition) ? esc_html($condition) : "all"; ?>"/>
                <input type="hidden" name="page" value="<?php echo esc_html(WLRMG_PLUGIN_SLUG); ?>"/>
                <input type="hidden" name="view" value="activity"/>
            </div>
        </form>
    </div>
    <div class="wlrmg-activity-list">
        <?php if (empty($activity_list_data)): ?>
            <div class="wlrmg-no-activity-yet">
                <div>
                    <img
                            src="<?php echo (isset($no_activity_list) && !empty($no_activity_list)) ? esc_url($no_activity_list) : ""; ?>"
                            alt="<?php echo esc_attr__("Filter", "wp-loyalty-migration") ?>">
                </div>
                <div>
                    <p style="font-size: 20px; color: #161F31;"><?php echo esc_html__("No activities yet", "wp-loyalty-migration") ?></p>
                </div>
                <div>
                    <p style="font-size: 16px; color: #535863;"><?php echo esc_html__("Get started by migrate customers from wordpress", "wp-loyalty-migration") ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="wlrmg-activity-list-header">
                <div></div>
                <div class="wlrmg-activity-header "
                ><?php echo esc_html__("JOB ID", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header wlba-grid-span-2"
                ><?php echo esc_html__("ACTIVITY", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header wlba-grid-span-2"
                ><?php echo esc_html__("CATEGORY", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header"
                ><?php echo esc_html__("PROCESSED", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header "
                ><?php echo esc_html__("STATUS", "wp-loyalty-migration") ?></div>
                <div class="wlrmg-activity-header wlba-grid-span-2"
                ><?php echo esc_html__("ACTION", "wp-loyalty-migration") ?></div>
            </div>
            <div class="wlrmg-activity-data">
                <?php foreach ($activity_list_data as $activity): ?>
                    <div class="wlrmg-activity-list-row">
                        <div class="wlrmg-activity-row-data">
                            <img
                                    src="<?php echo (isset($activity["image_icon"]) && !empty($activity["image_icon"])) ? esc_url($activity["image_icon"]) : ""; ?>"
                                    alt="<?php echo esc_attr__("Order point", "wp-loyalty-migration") ?>">
                        </div>
                        <div class="wlrmg-activity-row-data ">
                            <?php echo esc_html__($activity["job_id"]); ?>
                        </div>
                        <div class="wlrmg-activity-row-data wlba-grid-span-2">
                            <?php echo sprintf( /* translators: 1: number of points, 2: point label */
	                            __( '%1$s %2$s updated', 'wp-loyalty-migration' ), $activity["points"], $activity["points"]) ?>
                        </div>
                        <div class="wlrmg-activity-row-data wlba-grid-span-2">
                            <?php echo esc_html__($activity["action"], "wp-loyalty-migration"); //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText	 ?>
                        </div>
                        <div class="wlrmg-activity-row-data">
                            <?php echo esc_html__($activity["processed_count"], "wp-loyalty-migration"); //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText	?>
                        </div>
                        <div class="wlrmg-activity-row-data ">
                            <?php echo esc_html__($activity["status"], "wp-loyalty-migration"); //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText	?>
                        </div>
                        <div class="actions wlba-grid-span-2">
                            <div>
                                <a class="view_details"
                                   title="<?php echo esc_attr__("View Details", "wp-loyalty-migration") ?>"
                                   href="<?php echo esc_url(admin_url("admin.php?" . http_build_query(array(
                                           "page" => WLRMG_PLUGIN_SLUG,
                                           "view" => "activity_details",
                                           "job_id" => $activity["job_id"]
                                       )))) ?>">
                                    <i class="wlr wlrf-view "></i></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <?php if (!empty($activity_list_data)): ?>
        <div class="wlrmg-pagination">
            <?php if (isset($pagination) && !empty($pagination)): ?>
                <?php echo $pagination->createLinks(); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped	?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
