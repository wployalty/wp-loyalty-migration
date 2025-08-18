<?php

namespace Wlrm\App\Controller;

defined('ABSPATH') or die();

use ParseCsv\Csv;
use stdClass;
use Wlrm\App\Helper\Input;
use Wlrm\App\Helper\Validation;
use Wlrm\App\Helper\WC;
use Wlrm\App\Helper\Settings;
use Wlrm\App\Models\MigrationLog;
use Wlrm\App\Models\ScheduledJobs;

/**
 * Handles migration entrypoints, job creation, data windowing, and export.
 *
 * Provides methods to create parent jobs, fetch ID windows and users for
 * range-based batches, and export migration logs.
 */
class Migration
{
    /**
     * Generates the confirm content for a specific category migration.
     *
     * This method first checks the security validation for the migration users nonce.
     * It then retrieves the category input and validates it using the alpha input validation.
     * The file path for the popup template is determined, falling back to a default path if not found.
     * The HTML content is rendered by including the category data in the template.
     * Finally, a JSON success response is sent with the rendered HTML and a success message.
     *
     * @return void This method does not return a value but sends JSON responses.
     */
    public static function getConfirmContent()
    {
        if (!WC::isSecurityValid('wlrmg_migrate_users_nonce')) {
            wp_send_json_error(['message' => __('Basic check failed', 'wp-loyalty-migration')]);
        }
        $category = Input::get('category');
        $category = Validation::validateInputAlpha($category);

        $file_path = get_theme_file_path('wp-loyalty-migration/Admin/popup.php');
        if (!file_exists($file_path)) {
            $file_path = WLRMG_VIEW_PATH . '/Admin/popup.php';
        }
        $html = WC::renderTemplate($file_path, ['category' => $category], false);
        wp_send_json_success([
            'html' => $html,
            'message' => __('Update points to customers', 'wp-loyalty-migration')
        ]);
    }

    /**
     * Migrates users with points based on the provided migration data.
     *
     * This method first checks the security validation for the migration users nonce.
     * It then retrieves the migration data from the request and validates it using the custom validation method.
     * If the validation fails, it sends a JSON error response with the specific field errors.
     * It then checks if a job with the same migration action already exists, and if so, sends an error response.
     * If not, it inserts the migration data as a scheduled job and sends a success response with the job ID.
     *
     * @return void This method does not return a value but sends JSON responses.
     */
    public static function migrateUsersWithPointsJob()
    {
        if (!WC::isSecurityValid('wlrmg_migrate_users_nonce')) {
            wp_send_json_error(['message' => __('Basic check failed', 'wp-loyalty-migration')]);
        }
        $post = [
            'migration_action' => Input::get('migration_action'),
            'update_point' => Input::get('update_point'),
            'update_banned_user' => Input::get('update_banned_user'),
        ];
	//phpcs:ignore WordPress.Security.NonceVerification.Missing    
        $validate_data = Validation::validateMigrationData($_POST);
        if (is_array($validate_data) && !empty($validate_data) && count($validate_data) > 0) {
            foreach ($validate_data as $key => $validate) {
                $validate_data[$key] = current($validate);
            }
            wp_send_json_error([
                'field_error' => $validate_data,
                'message' => __('Invalid fields', 'wp-loyalty-migration')
            ]);
        }

        $check_data_job = ScheduledJobs::getJobByAction($post['migration_action']);

        if (!empty($check_data_job)) {
            wp_send_json_error(['message' => __('Migration job already created', 'wp-loyalty-migration')]);
        }

        $batch_limit = (int)Settings::get('batch_limit', 50);
        if ($batch_limit <= 0) {
            $batch_limit = 50;
        }

        $parent_job_post = $post;
        $parent_job_post['batch_limit'] = $batch_limit;
        $parent_job_post['last_enqueued_id'] = 0;

        $parent_job_id = ScheduledJobs::insertData($parent_job_post);
        if ($parent_job_id <= 0) {
            wp_send_json_error(['message' => __('Unable to create parent job', 'wp-loyalty-migration')]);
        }

        wp_send_json_success([
            'message' => __('Migration job created', 'wp-loyalty-migration'),
            'job_id' => $parent_job_id
        ]);
    }

    /**
     * Get the current maximum ID for a given migration category
     *
     * @param string $migration_action
     * @return int
     */
    public static function getCurrentMaxId($migration_action)
    {
        global $wpdb;
        switch ($migration_action) {
            case 'wlpr_migration':
                return (int)$wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}wlpr_points");
            case 'wp_swings_migration':
            case 'woocommerce_migration':
                return (int)$wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->users}");
            default:
                return 0;
        }
    }

    /**
     * Get next window of primary IDs after a cursor up to a limit and within an upper bound
     *
     * @param string $migration_action
     * @param int $after_id
     * @param int $limit
     * @param int $upto_id
     * @return array<int>
     */
    public static function getIdsWindow($migration_action, $after_id, $limit, $upto_id)
    {
        global $wpdb;
        if ($limit <= 0 || $upto_id <= 0 || $after_id >= $upto_id) {
            return [];
        }
        switch ($migration_action) {
            case 'wlpr_migration':
                $sql = $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}wlpr_points WHERE id > %d AND id <= %d ORDER BY id ASC LIMIT %d",
                    (int)$after_id,
                    (int)$upto_id,
                    (int)$limit
                );
                return array_map('intval', $wpdb->get_col($sql));
            case 'wp_swings_migration':
            case 'woocommerce_migration':
                $sql = $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->users} WHERE ID > %d AND ID <= %d ORDER BY ID ASC LIMIT %d",
                    (int)$after_id,
                    (int)$upto_id,
                    (int)$limit
                );
                return array_map('intval', $wpdb->get_col($sql));
            default:
                return [];
        }
    }

    /**
     * Fetch users for a specific ID range (no OFFSET) per migration category
     *
     * @param string $migration_action
     * @param int $start_id exclusive lower bound
     * @param int $end_id inclusive upper bound
     * @return array
     */
    public static function getUsersForRange($migration_action, $start_id, $end_id)
    {
        global $wpdb;
        if ($end_id <= $start_id) {
            return [];
        }
        switch ($migration_action) {
            case 'wlpr_migration':
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}wlpr_points WHERE id > %d AND id <= %d ORDER BY id ASC",
                    (int)$start_id,
                    (int)$end_id
                ));
            case 'wp_swings_migration':
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT wp_user.ID, wp_user.user_email, COALESCE(meta.meta_value, 0) AS wps_points 
                     FROM {$wpdb->users} AS wp_user 
                     LEFT JOIN {$wpdb->usermeta} AS meta ON wp_user.ID = meta.user_id AND meta.meta_key = 'wps_wpr_points' 
                     WHERE wp_user.ID > %d AND wp_user.ID <= %d 
                     ORDER BY wp_user.ID ASC",
                    (int)$start_id,
                    (int)$end_id
                ));
            case 'woocommerce_migration':
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT wp_user.ID AS user_id, wp_user.user_email, IFNULL(SUM(woo_points_table.points_balance), 0) AS total_points_balance 
                     FROM {$wpdb->users} AS wp_user 
                     LEFT JOIN {$wpdb->prefix}wc_points_rewards_user_points AS woo_points_table ON wp_user.ID = woo_points_table.user_id 
                     WHERE wp_user.ID > %d AND wp_user.ID <= %d 
                     GROUP BY wp_user.ID, wp_user.user_email 
                     ORDER BY wp_user.ID ASC",
                    (int)$start_id,
                    (int)$end_id
                ));
            default:
                return [];
        }
    }

    /**
     * Saves the settings for WP Loyalty Migration plugin.
     *
     * This method first checks the security validation for the settings nonce.
     * It retrieves the batch limit and pagination limit from the input data.
     * The settings data is then validated using a specific method.
     * If there are any validation errors, they are formatted and sent as a JSON error response.
     * The validated settings data are filtered through a hook before saving.
     * Finally, the settings are updated in the database, and a JSON success response is sent.
     *
     * @return void This method does not return a value but sends JSON responses.
     */
    public static function saveSettings()
    {
        if (!WC::isSecurityValid('wlrmg_save_settings_nonce')) {
            wp_send_json_error(['message' => __('Basic check failed', 'wp-loyalty-migration')]);
        }
        $post = [
            'batch_limit' => Input::get('batch_limit'),
            'pagination_limit' => Input::get('pagination_limit'),
        ];
        $validate_data = Validation::validateSettingsData($post);

        if (is_array($validate_data) && !empty($validate_data) && count($validate_data) > 0) {
            foreach ($validate_data as $key => $validate) {
                $validate_data[$key] = current($validate);
            }
            wp_send_json_error([
                'field_error' => $validate_data,
                'message' => __('Invalid fields', 'wp-loyalty-migration')
            ]);
        }

        $data = apply_filters('wlrm_before_save_settings', $post);
        update_option('wlrmg_settings', $data);
        wp_send_json_success(['message' => __('Settings saved', 'wp-loyalty-migration')]);
    }

    /**
     * Generates the export popup content based on the provided migration details.
     *
     * This method first checks the security validation for the export popup nonce.
     * It verifies if the directory is writable and attempts to set write permissions if needed.
     * The migration action and job ID are retrieved from the input.
     * Any existing export files for the job are deleted based on the job ID and migration action.
     * A base URL for administrative links related to the export is constructed.
     * Page details including log counts, limits, and nonce values are prepared.
     * The HTML content of the export logs template is rendered with the page details.
     * Finally, a JSON success response is sent with the rendered HTML and a success message.
     *
     * @return void This method does not return a value but sends JSON responses.
     */
    public static function exportPopup()
    {
        if (!WC::isSecurityValid('wlrmg_popup_nonce')) {
            wp_send_json_error(['message' => __('Basic check failed', 'wp-loyalty-migration')]);
        }
	    global $wp_filesystem;
	    if ( ! function_exists( 'WP_Filesystem' ) ) {
		    require_once ABSPATH . 'wp-admin/includes/file.php';
	    }
	    WP_Filesystem();
	    $directory = WLRMG_PLUGIN_DIR . '/App/File/';
	    if ( ! $wp_filesystem->is_writable( $directory ) ) {
		    $permission_set = $wp_filesystem->chmod( $directory, 0777 );
		    if ( ! $permission_set ) {
			    wp_send_json_error([
				    'message' => __( 'Permission denied to write a file', 'wp-loyalty-migration' )
			    ]);
		    }
	    }


        $post = [
            'migration_action' => Input::get('migration_action'),
            'job_id' => Input::get('job_id'),
        ];

        $path = WLRMG_PLUGIN_DIR . '/App/File/' . $post['job_id'];
	    switch ($post['migration_action']) {
		    case 'woocommerce_migration' :
			    $file_name = 'wc_customer_migration_export_*.*';
			    break;
		    case 'wlpr_migration':
			    $file_name = 'wlr_customer_migration_export_*.*';
			    break;
		    case 'wp_swings_migration':
			    $file_name = 'wpswing_customer_migration_export_*.*';
			    break;
		    default:
			    $file_name = 'customer_migration_export_*.*';
			    break;
	    }
		$delete_file_path = trim($path . '/' . $file_name);
        foreach (glob($delete_file_path) as $file_path) {
            if (file_exists($file_path)) {
                wp_delete_file($file_path);
            }
        }

        $base_url = admin_url('admin.php?' . http_build_query([
                'page' => WLRMG_PLUGIN_SLUG,
                'view' => 'activity_details',
                'job_id' => $post['job_id']
            ]));

        $parent_uid = (int)$post['job_id'];
        $job_table = new ScheduledJobs();
        global $wpdb;
        $job_row = $job_table->getWhere($wpdb->prepare(" uid = %d AND source_app = %s", [$parent_uid, 'wlr_migration']));
        if (!empty($job_row) && is_object($job_row) && !empty($job_row->conditions)) {
            $decoded = json_decode($job_row->conditions, true);
            if (isset($decoded['batch_info']['parent_job_id']) && (int)$decoded['batch_info']['parent_job_id'] > 0) {
                $parent_uid = (int)$decoded['batch_info']['parent_job_id'];
            }
        }
        $all_batches = ScheduledJobs::getBatchesByParent($parent_uid);
        $all_batch_ids = [];
        if (!empty($all_batches) && is_array($all_batches)) {
            foreach ($all_batches as $row) {
                if (isset($row->uid)) {
                    $all_batch_ids[] = (int)$row->uid;
                }
            }
        }
        if (empty($all_batch_ids)) {
            $all_batch_ids = [$parent_uid];
        }

        $page_details = [
            'base_url' => $base_url,
            'total_count' => MigrationLog::getLogCount($post['migration_action'], $all_batch_ids),
            'process_count' => 0,
            'limit_start' => 0,
            'limit' => 5,
            'wlrmg_nonce' => WC::createNonce('wlrmg_export_nonce'),
            'job_id' => $post['job_id'],
            'category' => $post['migration_action'],
        ];
        $file_path = get_theme_file_path('wp-loyalty-migration/Admin/export_logs.php');
        if (!file_exists($file_path)) {
            $file_path = WLRMG_VIEW_PATH . '/Admin/export_logs.php';
        }
        $html = WC::renderTemplate($file_path, $page_details, false);
        wp_send_json_success(['html' => $html, 'success' => 'completed']);
    }

    /**
     * Handles the export functionality.
     *
     * This method first checks the security validation for the export nonce.
     * It retrieves the limit, limit_start, and total_count inputs and casts them to integers.
     * Constructs a file path for the export file and creates directories if needed with proper permissions.
     * Determines the file name and count based on the limit_start and predefined conditions.
     * Retrieves data from the database using MigrationLog class and prepares a CSV file using Csv class.
     * Saves the CSV file with data retrieved and sends a success JSON response with necessary details.
     *
     * @return void This method does not return a value but sends JSON responses.
     */
    public static function handleExport()
    {
        if (!WC::isSecurityValid('wlrmg_export_nonce')) {
            wp_send_json_error(['message' => __('Basic check failed', 'wp-loyalty-migration')]);
        }

        $limit = 25;
        $limit_start = (int)Input::get('limit_start', 0);
        $total_count = (int)Input::get('total_count', 0);

        $post = [
            'job_id' => Input::get('job_id'),
            'category' => Input::get('category')
        ];

        if ($total_count > $limit_start) {

	        global $wp_filesystem;
	        if ( ! function_exists( 'WP_Filesystem' ) ) {
		        require_once ABSPATH . 'wp-admin/includes/file.php';
	        }
	        WP_Filesystem();
            $path = WLRMG_PLUGIN_DIR . '/App/File/' . $post['job_id'];
	        if ( ! $wp_filesystem->is_dir( $path ) ) {
		        $created = $wp_filesystem->mkdir( $path, FS_CHMOD_DIR );
		        if ( $created ) {
			        $wp_filesystem->chmod( $path, 0777, true );
		        } else {
			        wp_send_json_error([
			        	'message' => __( 'Failed to create directory for job files.', 'wp-loyalty-migration' )
			        ]);
		        }
	        }
            switch ($post['category']) {
                case 'woocommerce_migration' :
                    $file_name = 'wc_customer_migration_export_';
                    break;
                case 'wlpr_migration':
                    $file_name = 'wlr_customer_migration_export_';
                    break;
                case 'wp_swings_migration':
                    $file_name = 'wpswing_customer_migration_export_';
                    break;
                default:
                    $file_name = 'customer_migration_export_';
                    break;
            }

            $file_count = (int)floor($limit_start / 500);
            $file_path = trim($path . '/' . $file_name . $file_count . '.csv');

            $log_table = new MigrationLog();
            global $wpdb;
            $table = $log_table->getTableName();

            $where = "id > 0";
            $where .= $wpdb->prepare(" AND action = %s AND user_email !=''", [ $post['category'] ]);

            $parent_uid = (int)$post['job_id'];
            $job_table = new ScheduledJobs();
            $job_row = $job_table->getWhere($wpdb->prepare(" uid = %d AND source_app = %s", [$parent_uid, 'wlr_migration']));
            if (!empty($job_row) && is_object($job_row) && !empty($job_row->conditions)) {
                $decoded = json_decode($job_row->conditions, true);
                if (isset($decoded['batch_info']['parent_job_id']) && (int)$decoded['batch_info']['parent_job_id'] > 0) {
                    $parent_uid = (int)$decoded['batch_info']['parent_job_id'];
                }
            }
            $all_batches = ScheduledJobs::getBatchesByParent($parent_uid);
            $all_batch_ids = [];
            if (!empty($all_batches) && is_array($all_batches)) {
                foreach ($all_batches as $row) {
                    if (isset($row->uid)) {
                        $all_batch_ids[] = (int)$row->uid;
                    }
                }
            }
            if (empty($all_batch_ids)) {
                $all_batch_ids = [$parent_uid];
            }
            $placeholders = implode(',', array_fill(0, count($all_batch_ids), '%d'));
            $where .= $wpdb->prepare(" AND job_id IN ($placeholders)", $all_batch_ids);
            $where .= $wpdb->prepare(' ORDER BY id ASC LIMIT %d OFFSET %d;', [
                $limit,
                $limit_start
            ]);

            $csv_helper = new Csv();
            $select = "user_email, referral_code, points";
            $csv_helper->titles = ['email', 'referral_code', 'points'];

            $query = "SELECT {$select} FROM {$table} WHERE {$where}";
	    $file_data = $wpdb->get_results($query, ARRAY_A); 

            if (!empty($file_data)) {
                foreach ($file_data as &$single_file_data) {
                    if (isset($single_file_data['user_email'])) {
                        $single_file_data['email'] = $single_file_data['user_email'];
                    }
                }

                if (!file_exists($file_path)) {
                    $csv_helper->save($file_path, [$csv_helper->titles], true);
                }

                $csv_helper->save($file_path, $file_data, true);
            }

            $limit_start += $limit;

            if ($limit_start >= $total_count) {
                wp_send_json_success([
                    'success' => 'completed',
                    'notification' => __('Export completed successfully.', 'wp-loyalty-migration'),
                    'redirect' => admin_url('admin.php?' . http_build_query([
                            'page' => WLRMG_PLUGIN_SLUG,
                            'view' => 'activity_details',
                            'job_id' => $post['job_id']
                        ]))
                ]);
            } else {
                wp_send_json_success([
                    'success' => 'incomplete',
                    'limit_start' => $limit_start,
                    'notification' => sprintf( /* translators: %s: limit  */__('Exported %s customer(s)', 'wp-loyalty-migration'), $limit_start)
                ]);
            }
        } else {
            wp_send_json_error(['message' => __('No data to export', 'wp-loyalty-migration')]);
        }
    }

    /**
     * Retrieves export files based on specified parameters.
     *
     * @return void
     */
    public static function getExportFiles()
    {
        if (!WC::isSecurityValid('wlrmg_popup_nonce')) {
            wp_send_json_error(['message' => __('Basic check failed', 'wp-loyalty-migration')]);
        }
        $job_id = Input::get('job_id', 0);
        if (empty($job_id)) {
            wp_send_json_error(['message' => __('Invalid job id', 'wp-loyalty-migration')]);
        }
        $action_type = Input::get('action_type');


        $export_files = self::exportFileList([
            'category' => $action_type,
            'job_id' => $job_id
        ]);
        $page_details = [
            'job_id' => $job_id,
            'export_files' => $export_files,
        ];

        $file_path = get_theme_file_path('wp-loyalty-migration/Admin/export_log_files.php');
        if (!file_exists($file_path)) {
            $file_path = WLRMG_VIEW_PATH . '/Admin/export_log_files.php';
        }
        wp_send_json_success(['html' => WC::renderTemplate($file_path, $page_details, false)]);
    }

    /**
     * Retrieves export files based on the specified parameters.
     *
     * @param array $post An array containing job ID and category for exporting files.
     *
     * @return array An array of objects representing the export files with file name, path, and URL.
     */
    protected static function exportFileList($post)
    {

        if (empty($post) || !is_array($post)) {
            return [];
        }
        $path = WLRMG_PLUGIN_DIR . '/App/File/' . $post['job_id'];

        switch ($post['category']) {
            case 'woocommerce_migration' :
                $file_name = 'wc_customer_migration_export_*.*';
                break;// woocommerce export
            case 'wlpr_migration':
                $file_name = 'wlr_customer_migration_export_*.*'; //loyalty export
                break;
            case 'wp_swings_migration':
                $file_name = 'wpswing_customer_migration_export_*.*';
                break;
            default:
                $file_name = 'customer_migration_export_*.*';
                break;
        }

        $delete_file_path = trim($path . '/' . $file_name);
        $download_list = [];
        foreach (glob($delete_file_path) as $file_path) {
            if (file_exists($file_path)) {
                $file_detail = new stdClass();
                $file_detail->file_name = basename($file_path);
                $file_detail->file_path = $file_path;
                $file_detail->file_url = rtrim(WLRMG_PLUGIN_URL, '/') . '/App/File/' . $post['job_id'] . '/' . $file_detail->file_name;
                $download_list[] = $file_detail;
            }
        }

        return $download_list;
    }

}
