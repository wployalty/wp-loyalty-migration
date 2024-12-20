<?php

namespace Wlrm\App\Controller;

defined('ABSPATH') or die();

use ParseCsv\Csv;
use stdClass;
use Wlrm\App\Helper\Input;
use Wlrm\App\Helper\Validation;
use Wlrm\App\Helper\WC;
use Wlrm\App\Models\MigrationLog;
use Wlrm\App\Models\ScheduledJobs;

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

        $cron_job_id = ScheduledJobs::insertData($post);
        if ($cron_job_id <= 0) {
            wp_send_json_error(['message' => __('Unable to create job', 'wp-loyalty-migration')]);
        }
        wp_send_json_success([
            'message' => __('Migration job created', 'wp-loyalty-migration'),
            'job_id' => $cron_job_id
        ]);
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

        if (!is_writable(WLRMG_PLUGIN_DIR . '/App/File/') && function_exists('chmod')) {
            $add_permission = @chmod(WLRMG_PLUGIN_DIR . '/App/File/', 0777);
            if (!$add_permission) {
                wp_send_json_error(['message' => __('Permission denied to write a file', 'wp-loyalty-bulk-action')]);
            }
        }
        $post = [
            'migration_action' => Input::get('migration_action'),
            'job_id' => Input::get('job_id'),
        ];

        $path = WLRMG_PLUGIN_DIR . '/App/File/' . $post['job_id'];
        $file_name = $post['migration_action'] . '_' . $post['job_id'] . '_export_*.*';
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

        $page_details = [
            'base_url' => $base_url,
            'total_count' => MigrationLog::getLogCount($post['migration_action'], $post['job_id']),
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

        $limit = 25; // Base size for each file
        $limit_start = (int)Input::get('limit_start', 0); // Offset
        $total_count = (int)Input::get('total_count', 0); // Total number of records

        $post = [
            'job_id' => Input::get('job_id'),
            'category' => Input::get('category')
        ];

        if ($total_count > $limit_start) {
            $path = WLRMG_PLUGIN_DIR . '/App/File/' . $post['job_id'];
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
                @chmod($path, 0777);
            }

            $file_name = $post['category'] . '_' . $post['job_id'] . '_export_';
            $file_count = (int)floor($limit_start / 500); // File number based on offset
            $file_path = trim($path . '/' . $file_name . $file_count . '.csv');

            $log_table = new MigrationLog();
            global $wpdb;
            $table = $log_table->getTableName();

            $where = "id > 0";
            $where .= $wpdb->prepare(" AND action = %s AND job_id = %d AND user_email !=''", [
                $post['category'],
                (int)$post['job_id']
            ]);
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

                // Write header if file doesn't exist
                if (!file_exists($file_path)) {
                    $csv_helper->save($file_path, [$csv_helper->titles], true);
                }

                // Append the data
                $csv_helper->save($file_path, $file_data, true);
            }

            $limit_start += $limit;

            // Check if completed
            if ($limit_start >= $total_count) {
                wp_send_json_success([
                    'success' => 'completed',
                    'notification' => __('Export completed successfully.', 'wp-loyalty-bulk-action'),
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
                    'notification' => sprintf(__('Exported %s customer(s)', 'wp-loyalty-bulk-action'), $limit_start)
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
        $file_name = 'customer_migration_export_*.csv';
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