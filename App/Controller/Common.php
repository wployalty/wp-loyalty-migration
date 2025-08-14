<?php

namespace Wlrm\App\Controller;

use Exception;
use stdClass;
use Wlrm\App\Controller\Compatibles\WLPRPointsRewards;
use Wlrm\App\Controller\Compatibles\WooPointsRewards;
use Wlrm\App\Controller\Compatibles\WPSwings;
use Wlrm\App\Helper\Input;
use Wlrm\App\Helper\Pagination;
use Wlrm\App\Helper\WC;
use Wlrm\App\Models\MigrationLog;
use Wlrm\App\Models\ScheduledJobs;


defined( 'ABSPATH' ) or die();


class Common {
	/**
	 * Adds a menu page for WPLoyalty plugin migration if the current user has admin privilege.
	 *
	 * This method checks if the user has admin privilege using WC::hasAdminPrivilege() method.
	 * If the user is an admin, it adds a menu page with the title 'WPLoyalty: Migration' to the WordPress admin menu.
	 * The menu page is accessible to users with the 'manage_woocommerce' capability.
	 * The menu page callback function is set to self::addMigrationPage().
	 *
	 * @return void
	 */
	public static function addMenu() {
		if ( WC::hasAdminPrivilege() ) {
			add_menu_page( __( 'WPLoyalty: Migration', 'wp-loyalty-migration' ), __( 'WPLoyalty: Migration', 'wp-loyalty-migration' ), 'manage_woocommerce', WLRMG_PLUGIN_SLUG, [
				self::class,
				'addMigrationPage'
			], 'dashicons-megaphone', 58 );
		}
	}

	/**
	 * Adds a migration page based on the current view.
	 *
	 * This method checks for admin privilege before proceeding to create the migration page.
	 * It retrieves the current view from the Input class and initializes parameters for rendering the page.
	 * Based on the view, it sets the main page content to include actions, settings, or activity details.
	 * It then determines the file path for the template file to render the migration page.
	 * If the specified file path does not exist, a fallback path is used instead.
	 * Finally, it renders the template with the specified parameters.
	 *
	 * @return void
	 */
	public static function addMigrationPage() {
		if ( ! WC::hasAdminPrivilege() ) {
			return;
		}
		$view   = Input::get( 'view', 'actions' );
		$params = [
			'current_page' => $view,
			'main_page'    => [],
		];
		switch ( $view ) {
			case 'actions':
				$params['main_page']['actions'] = self::getActionsPage();
				break;
			case 'settings':
				$params['main_page']['settings'] = self::getSettingsPage();
				break;
			case 'activity_details':
				$params['main_page']['activity_details'] = self::getActivityDetailsPage();
				break;
		}
		$file_path = get_theme_file_path( 'wp-loyalty-migration/Admin/main.php' );
		if ( ! file_exists( $file_path ) ) {
			$file_path = WLRMG_VIEW_PATH . '/Admin/main.php';
		}
		WC::renderTemplate( $file_path, $params );
	}

	/**
	 * Retrieves the actions page data for migration.
	 *
	 * This method constructs an array of arguments containing details for different migration cards.
	 * Each migration card includes information such as type, title, description, activation status, job data, and button visibility.
	 * It then determines the file path for the template file to render the actions page.
	 * If the specified file path does not exist, a fallback path is used instead.
	 * Finally, it renders the template with the provided arguments.
	 *
	 * @return string|null
	 */
	public static function getActionsPage() {
		$args      = [
			'current_page'     => 'actions',
			'back_to_apps_url' => admin_url( 'admin.php?' . http_build_query( [ 'page' => WLR_PLUGIN_SLUG ] ) ) . '#/apps',
			'previous'         => WLRMG_PLUGIN_URL . "Assets/svg/previous.svg",
			'migration_cards'  => [
				[
					'type'                   => 'wp_swings_migration',
					'title'                  => __( 'WPSwings points and rewards', 'wp-loyalty-migration' ),
					'description'            => __( 'Migrate users with points', 'wp-loyalty-migration' ),
					'is_active'              => WPSwings::checkPluginIsActive(),
					'job_data'               => WPSwings::getMigrationJob(),
					'is_show_migrate_button' => true,
				],
				[
					'type'                   => 'wlpr_migration',
					'title'                  => __( 'WooCommerce Loyalty Points and Rewards', 'wp-loyalty-migration' ),
					'description'            => __( 'Migrate users with points', 'wp-loyalty-migration' ),
					'is_active'              => WLPRPointsRewards::checkPluginIsActive(),
					'job_data'               => WLPRPointsRewards::getMigrationJob(),
					'is_show_migrate_button' => true,
				],
				[
					'type'                   => 'woocommerce_migration',
					'title'                  => __( 'Woocommerce points and rewards', 'wp-loyalty-migration' ),
					'description'            => __( 'Migrate users with points', 'wp-loyalty-migration' ),
					'is_active'              => WooPointsRewards::checkPluginIsActive(),
					'job_data'               => WooPointsRewards::getMigrationJob(),
					'is_show_migrate_button' => true,
				],
			]
		];
		$file_path = get_theme_file_path( 'wp-loyalty-migration/Admin/actions.php' );
		if ( ! file_exists( $file_path ) ) {
			$file_path = WLRMG_VIEW_PATH . '/Admin/actions.php';
		}

		return WC::renderTemplate( $file_path, $args, false );
	}

	/**
	 * Retrieves the settings page content.
	 *
	 * This method initializes the arguments for the settings page, including batch limits, pagination limits,
	 * current page identifier, back to apps URL, icons for back and previous buttons, and option settings.
	 * It determines the file path for the settings template file, falling back to a default path if necessary.
	 * It then renders the settings page template with the specified arguments.
	 *
	 * @return string|null
	 */
	public static function getSettingsPage() {
		$args      = [
			'batch_limit'      => [
				'10' => '10',
				'20' => '20',
				'30' => '30',
				'40' => '40',
				'50' => '50',
			],
			'pagination_limit' => [
				'5'   => '5',
				'10'  => '10',
				'20'  => '20',
				'50'  => '50',
				'100' => '100',
			],
			'current_page'     => 'settings',
			'back_to_apps_url' => admin_url( 'admin.php?' . http_build_query( [ 'page' => WLR_PLUGIN_SLUG ] ) ) . '#/apps',
			'back'             => WLRMG_PLUGIN_URL . "Assets/svg/back_button.svg",
			'previous'         => WLRMG_PLUGIN_URL . "Assets/svg/previous.svg",
			'option_settings'  => get_option( 'wlrmg_settings', [] ),
		];
		$file_path = get_theme_file_path( 'wp-loyalty-migration/Admin/settings.php' );
		if ( ! file_exists( $file_path ) ) {
			$file_path = WLRMG_VIEW_PATH . '/Admin/settings.php';
		}

		return WC::renderTemplate( $file_path, $args, false );
	}

	/**
	 * Retrieves the activity details page content based on the provided job ID.
	 *
	 * This method retrieves the job ID from the Input class and initializes arguments for building the activity details page.
	 * It sets the current page to 'activity' and includes the job ID, search term, action type, activity data, back button SVG path, and no activity icon SVG path in the arguments.
	 * Before rendering the activity details page, it allows filtering of the arguments through the 'wlrm_before_activity_details_page' hook.
	 * The method determines the file path for the activity details template file. If the specified path does not exist, a fallback path is used.
	 * Finally, it renders the activity details template with the specified arguments.
	 *
	 * @return string Rendered content of the activity details page.
	 */
	public static function getActivityDetailsPage() {
		$job_id    = (int) Input::get( 'job_id', 0 );
		$args      = array(
			"current_page"     => 'activity',
			"job_id"           => $job_id,
			"search"           => (string) Input::get( 'search', '' ),
			"action"           => (string) Input::get( 'type', '' ),
			"activity"         => self::getActivityDetailsData( $job_id ),
			"back"             => WLRMG_PLUGIN_URL . "Assets/svg/back_button.svg",
			"no_activity_icon" => WLRMG_PLUGIN_URL . "Assets/svg/no_activity_list.svg",
		);
		$args      = apply_filters( 'wlrm_before_activity_details_page', $args );
		$file_path = get_theme_file_path( 'wp-loyalty-migration/Admin/activity_details.php' );
		if ( ! file_exists( $file_path ) ) {
			$file_path = WLRMG_VIEW_PATH . '/Admin/activity_details.php';
		}

		return WC::renderTemplate( $file_path, $args, false );
	}

	/**
	 * Retrieves activity details data for a specific job ID.
	 *
	 * This method fetches activity details data for a specified job ID. If the job ID is empty or invalid, an empty array is returned.
	 * It interacts with the 'ScheduledJobs' table to retrieve job data based on the provided job ID and source application.
	 * The retrieved job data is then processed to include additional information and activity logs related to the job.
	 * The final result is passed through a filter hook for customization before being returned.
	 *
	 * @param int $job_id The ID of the job to retrieve activity details for.
	 *
	 * @return array Contains the job ID and additional data related to the job's activity details.
	 */
	protected static function getActivityDetailsData( $job_id ) {
		if ( empty( $job_id ) || $job_id <= 0 ) {
			return [];
		}
		$job_table = new ScheduledJobs();
		global $wpdb;
		$where    = $wpdb->prepare( " uid = %d AND source_app =%s", [ $job_id, 'wlr_migration' ] );
		$job_data = $job_table->getWhere( $where );

		$result = [
			'job_id' => $job_id,
		];
		if ( ! empty( $job_data ) && is_object( $job_data ) ) {
            $job_info = self::handleJobData( $job_data );

            // Batch stats across all batches of the same parent
            // Determine parent uid: job without parent_job_id acts as parent
            $parent_uid = $job_data->uid;
            $decoded = !empty($job_data->conditions) ? json_decode($job_data->conditions, true) : [];
            if (isset($decoded['batch_info']['parent_job_id']) && (int)$decoded['batch_info']['parent_job_id'] > 0) {
                $parent_uid = (int)$decoded['batch_info']['parent_job_id'];
            }
            $all_batches = ScheduledJobs::getBatchesByParent($parent_uid);
            $total_batches = is_array($all_batches) ? count($all_batches) : 0;
            $status_counts = [
                'pending' => 0,
                'processing' => 0,
                'completed' => 0,
                'failed' => 0,
            ];
            if (!empty($all_batches)) {
                foreach ($all_batches as $single_job) {
                    $st = isset($single_job->status) ? (string)$single_job->status : '';
                    if (isset($status_counts[$st])) {
                        $status_counts[$st]++;
                    } else {
                        // Treat unknown statuses as pending for display purposes
                        $status_counts['pending']++;
                    }
                }
            }

            // Compute aggregated processed items across all batches (sum of offsets)
            $processed_items = 0;
            if (!empty($all_batches)) {
                foreach ($all_batches as $single_job) {
                    $processed_items += (int)($single_job->offset ?? 0);
                }
            }
            $job_info['processed_items'] = $processed_items;

            // Derive overall status for the badge from batch statuses instead of the parent-only status
            $overall_status = 'pending';
            if ($total_batches > 0 && $status_counts['completed'] === $total_batches) {
                $overall_status = 'completed';
            } elseif ($status_counts['processing'] > 0) {
                $overall_status = 'processing';
            } elseif ($status_counts['failed'] > 0 && $status_counts['completed'] === 0) {
                $overall_status = 'failed';
            }
            $job_info['status'] = $overall_status;

            $result['job_data'] = $job_info;
			$result['activity'] = self::getActivityLogsData( $job_id );
		}

		return apply_filters( 'wlrmg_before_acitivity_view_details_data', $result );
	}

	/**
	 * Handles the job data to process and return relevant information.
	 *
	 * This method takes in the job data object and processes it to extract necessary information.
	 * It checks for empty or non-object job data and returns an empty array if found.
	 * It extracts specific attributes from the job_data object to populate the result array.
	 * The result array includes details such as creation date, offset, admin email, status, action, and conditions.
	 * It also sets an action label based on the job category.
	 * Conditionally, it adjusts how to display the update points condition.
	 *
	 * @param object $job_data The job data object containing necessary information.
	 *
	 * @return array Processed data extracted from the job data object.
	 */
	protected static function handleJobData( $job_data ) {
		if ( empty( $job_data ) || ! is_object( $job_data ) ) {
			return [];
		}
		$result = [
			'created_at' => ! empty( $job_data->created_at ) ? WC::beforeDisplayDate( $job_data->created_at ) : '',
			'offset'     => ! empty( $job_data->offset ) ? $job_data->offset : 0,
			'admin_mail' => ! empty( $job_data->admin_mail ) ? $job_data->admin_mail : '',
			'status'     => ! empty( $job_data->status ) ? $job_data->status : '',
			'action'     => ! empty( $job_data->category ) ? $job_data->category : '',
            'conditions' => ! empty( $job_data->conditions ) ? json_decode( $job_data->conditions, true ) : [],
		];
		if ( ! empty( $job_data->category ) ) {
			switch ( $job_data->category ) {
				case 'wp_swings_migration':
					$result['action_label'] = __( 'WP Swings Migration', 'wp-loyalty-migration' );
					break;
				case 'wlpr_migration':
					$result['action_label'] = __( 'Woocommerce Loyalty Points and Rewards Migration', 'wp-loyalty-migration' );
					break;
				case 'woocommerce_migration':
					$result['action_label'] = __( 'Woocommerce Point and Rewards Migration', 'wp-loyalty-migration' );
					break;
				case 'yith_migration':
				default:
					break;
			}
		}
		if ( ! empty( $result['conditions']['update_point'] ) ) {
			$result['conditions']['update_point'] = ( $result['conditions']['update_point'] == 'add' ) ? __( 'Add points to customer', 'wp-loyalty-migration' )
				: __( 'Skip customer', 'wp-loyalty-migration' );
		}

		return $result;
	}

	/**
	 * Retrieves activity logs data for a specified job ID.
	 *
	 * This method fetches activity logs data related to a specific job ID.
	 * It creates pagination information based on the retrieved data for easy navigation.
	 * It assembles and returns an array containing the relevant details for displaying the activity logs.
	 *
	 * @param int $job_id The ID of the job for which activity logs data is requested.
	 *
	 * @return array Fetched activity logs data including pagination and other details.
	 */
	protected static function getActivityLogsData( $job_id ) {
		if ( empty( $job_id ) || $job_id <= 0 ) {
			return [];
		}
		$bulk_action_log  = new MigrationLog();
		$url              = admin_url( 'admin.php?' . http_build_query( [
				'page'   => WLRMG_PLUGIN_SLUG,
				'view'   => 'activity_details',
				'job_id' => $job_id,
			] ) );
		$current_page     = (int) Input::get( 'migration_page', 1 );
        // Aggregate logs across all batches belonging to the same parent
        $parent_uid = $job_id;
        $job_table = new ScheduledJobs();
        global $wpdb;
        $parent_or_child = $job_table->getWhere( $wpdb->prepare( " uid = %d AND source_app =%s", [ $job_id, 'wlr_migration' ] ) );
        if ( ! empty( $parent_or_child ) && is_object( $parent_or_child ) && ! empty( $parent_or_child->conditions ) ) {
            $decoded = json_decode( $parent_or_child->conditions, true );
            if ( isset( $decoded['batch_info']['parent_job_id'] ) && (int) $decoded['batch_info']['parent_job_id'] > 0 ) {
                $parent_uid = (int) $decoded['batch_info']['parent_job_id'];
            }
        }
        $all_batches = ScheduledJobs::getBatchesByParent( $parent_uid );
        $all_batch_ids = [];
        if ( ! empty( $all_batches ) && is_array( $all_batches ) ) {
            foreach ( $all_batches as $row ) {
                if ( isset( $row->uid ) ) {
                    $all_batch_ids[] = (int) $row->uid;
                }
            }
        }
        // Fallback to current job id if something goes wrong
        if ( empty( $all_batch_ids ) ) {
            $all_batch_ids = [ (int) $job_id ];
        }

        $activity_list    = $bulk_action_log->getActivityList( $all_batch_ids, $current_page );
		$per_page         = (int) is_array( $activity_list ) && isset( $activity_list['per_page'] ) ? $activity_list['per_page'] : 0;
		$current_page     = (int) is_array( $activity_list ) && isset( $activity_list['current_page'] ) ? $activity_list['current_page'] : 0;
		$pagination_param = [
			'totalRows'          => (int) is_array( $activity_list ) && isset( $activity_list['total_rows'] ) ? $activity_list['total_rows'] : 0,
			'perPage'            => $per_page,
			'baseURL'            => $url,
			'currentPage'        => $current_page,
			'queryStringSegment' => 'migration_page',
		];
		$pagination       = new Pagination( $pagination_param );

		return apply_filters( 'wlrmg_activity_details', [
			'base_url'                  => $url,
			'pagination'                => $pagination,
			'per_page'                  => $per_page,
			'page_number'               => $current_page,
			'activity_list'             => (array) is_array( $activity_list ) && isset( $activity_list['data'] ) ? $activity_list['data'] : [],
			'show_export_file_download' => (int) is_array( $activity_list ) && isset( $activity_list['export_file_list'] ) ? count( $activity_list['export_file_list'] ) : 0,
			'export_csv_file_list'      => is_array( $activity_list ) && isset( $activity_list['export_file_list'] ) ? $activity_list['export_file_list'] : [],
		] );
	}

	/**
	 * Hides the WordPress admin menu item for the WP Loyalty Migration plugin.
	 *
	 * @return void
	 */
	public static function hideMenu() {
		?>
        <style>
            #toplevel_page_wp-loyalty-migration {
                display: none !important;
            }
        </style>
		<?php
	}

	/**
	 * Adds assets including stylesheets and scripts for the WLRMG plugin.
	 *
	 * This method checks if the current page matches the WLRMG plugin slug before enqueuing assets.
	 * It determines the suffix for asset filenames based on whether SCRIPT_DEBUG is defined and its value.
	 * Removes all actions hooked to 'admin_notices' action.
	 * Enqueues necessary stylesheets and scripts with versioning and cache-busting parameters.
	 * Localizes script data for use in JavaScript code.
	 *
	 * @return void
	 */
	public static function addAssets() {
		if ( Input::get( 'page' ) != WLRMG_PLUGIN_SLUG ) {
			return;
		}

		$suffix = ".min";
		if ( defined( "SCRIPT_DEBUG" ) ) {
			$suffix = SCRIPT_DEBUG ? "" : ".min";
		}
		remove_all_actions( "admin_notices" );
		wp_enqueue_style( WLR_PLUGIN_SLUG . '-wlr-font', WLR_PLUGIN_URL . 'Assets/Site/Css/wlr-fonts' . $suffix . '.css', [], WLR_PLUGIN_VERSION . '&t=' . time() );
		wp_enqueue_style( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Css/alertify' . $suffix . '.css', [], WLR_PLUGIN_VERSION . '&t=' . time() );
		wp_enqueue_style( WLRMG_PLUGIN_SLUG . '-main-style', WLRMG_PLUGIN_URL . 'Assets/Admin/Css/wlrmg-main.css', [ 'woocommerce_admin_styles' ], WLRMG_PLUGIN_VERSION . '&t=' . time() );
		//phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
		wp_enqueue_script( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Js/alertify' . $suffix . '.js', [], WLR_PLUGIN_VERSION . '&t=' . time() );
		//phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
        wp_enqueue_script( WLRMG_PLUGIN_SLUG . '-main-script', WLRMG_PLUGIN_URL . 'Assets/Admin/Js/wlrmg-main.js', [
			'jquery',
			'select2'
		], WLRMG_PLUGIN_VERSION . '&t=' . time() );
		$localize_data = apply_filters( 'wlrmg_before_localize_data', [
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'migrate_users' => WC::createNonce( 'wlrmg_migrate_users_nonce' ),
			'save_settings' => WC::createNonce( 'wlrmg_save_settings_nonce' ),
			'popup_nonce'   => WC::createNonce( 'wlrmg_popup_nonce' ),
            'yes'           => __( 'Yes, Proceed', 'wp-loyalty-migration' ),
			'cancel'        => __( 'No, Cancel', 'wp-loyalty-migration' ),
			'export_warning' => __( 'Are you sure you want to leave?', 'wp-loyalty-migration' ),
			'migration_warning' => __( 'Migration Warning', 'wp-loyalty-migration' ),
			'cancel_export_warning' => __( 'Export is in progress. Do you really want to close?', 'wp-loyalty-migration' ),
			'cancel_warning' => __( 'Make sure you want to close?', 'wp-loyalty-migration' ),
			'migration_notice' => wp_kses_post( '
                    <h3>' . __( 'Important Note : Please read before starting migration,Do not deactivate or delete your old point system/program during migration.' ,'wp-loyalty-migration') . '</h3>
                    <ul>
                        <li>' . __( 'Before starting the migration in WPLoyalty, ensure that earning and redeeming points is paused in your existing system/program. Its lead to give extra/low points in migration.' ,'wp-loyalty-migration') . '</li>
                        <li>' . __( 'The default batch limit is set to 50. You can adjust this in the settings.For example, if the batch limit is 50, then 50 customers will be migrated every 3 minutes — resulting in 1000 customers per hour, 24,400 customers per day.','wp-loyalty-migration' ) . '</li>
                        <li>' . __( 'Once a migration job is started, it cannot be paused or stopped midway. Please double-check all configurations before initiating the process.' ,'wp-loyalty-migration') . '</li>
                    </ul>
                ' ),
		],true );
		wp_localize_script( WLRMG_PLUGIN_SLUG . '-main-script', 'wlrmg_localize_data', $localize_data );
	}

	/**
	 * Adds an extra action to the provided action list.
	 *
	 * This method adds an extra action key-value pair to the given action list array.
	 * If the provided action list is empty or not an array, the original list is returned unchanged.
	 * The added action key is 'migration_to_wployalty' with the corresponding label retrieved from the translation function.
	 *
	 * @param array $action_list The array of actions to which the extra action will be added.
	 *
	 * @return array The updated action list with the extra action added.
	 */
	public static function addExtraAction( $action_list ) {
		if ( empty( $action_list ) || ! is_array( $action_list ) ) {
			return $action_list;
		}
		$action_list['migration_to_wployalty'] = __( 'User migrated via WPLoyalty Migration', 'wp-loyalty-migration' );

		return $action_list;
	}

	/**
	 * Adds 'migration_to_wployalty' action to the given action list.
	 *
	 * This method takes an array of action names and adds 'migration_to_wployalty' to it.
	 * If the provided action list is empty or not an array, the method returns the original action list without modification.
	 * Otherwise, the 'migration_to_wployalty' action is appended to the end of the action list.
	 *
	 * @param array $action_list An array containing the list of actions to which 'migration_to_wployalty' will be added.
	 *
	 * @return array The updated action list with 'migration_to_wployalty' added, or the original action list if it was empty or not an array.
	 */
	public static function addExtraPointLedgerAction( $action_list ) {
		if ( empty( $action_list ) || ! is_array( $action_list ) ) {
			return $action_list;
		}
		$action_list[] = 'migration_to_wployalty';

		return $action_list;
	}

	/**
	 * Initializes the migration schedule.
	 *
	 * This method checks if the migration job hook is already scheduled in WordPress.
	 * If the hook is not scheduled, it calculates the scheduled time to be one hour from the current time.
	 * It then schedules an hourly event using the calculated time and the migration job hook.
	 *
	 * @return void
	 */
	public static function initSchedule() {
		$hook      = 'wlrmg_migration_jobs';
		$timestamp = wp_next_scheduled( $hook );
		if ( false === $timestamp ) {
			$scheduled_time = strtotime( '+3 minutes' );
			wp_schedule_event( $scheduled_time, 'every_3_minutes', $hook );
		}
	}

	/**
	 * Adds minutes to the input data array.
	 *
	 * This method takes an input data array and checks if it is an array.
	 * If the input is not an array, it returns the input data as is.
	 * If the input is an array, it adds a 'minutes' array to the data with keys 'interval' and 'display'.
	 * The 'interval' key is assigned the value of MINUTE_IN_SECONDS constant.
	 * The 'display' key is assigned the localized translation of 'Minutes'.
	 *
	 * @param array $data The input data array to add minutes to.
	 *
	 * @return array The modified input data array with minutes added if it was an array.
	 */
	public static function addMinutes( $data ) {
		if ( ! is_array( $data ) || isset($data['every_3_minutes']) ) {
			return $data;
		}

		$data['every_3_minutes'] = [
			'interval' => 180,
			'display'  => __( 'Every 3 Minutes', 'wp-loyalty-migration' ),
		];

		return $data;
	}

	public static function triggerMigrations() {
		// Produce child batches for the active category first
		self::produceChildBatches();

		// Only queue pending child jobs for the active category (option-backed)
		$active_opt = get_option('wlrmg_active_category', array());
		$active_category = is_array($active_opt) && ! empty($active_opt['category']) ? (string) $active_opt['category'] : '';
        if (empty($active_category)) {
            return; // No active category selected yet
        }

        $pending_jobs = ScheduledJobs::getPendingJobsByCategory($active_category);
        if (empty($pending_jobs)) {
			return;
		}
		
		$job_table = new ScheduledJobs();
		$max_in_flight = (int) apply_filters('wlrmg_max_in_flight_batches', 3);
		$in_flight = 0;
		
        foreach ($pending_jobs as $job) {
			if (!function_exists('as_schedule_single_action')) {
				continue;
			}
			if ($in_flight >= $max_in_flight) {
                break;
            }
            $time = time() + 10;
            $uid = isset($job->uid) ? (int)$job->uid : 0;
            if ($uid <= 0) {
                continue;
            }
            // Deduplicate scheduling by checking existing action for this uid in our queue group
            if (false === as_next_scheduled_action('wlrmg_process_migration_job', [[ 'uid' => $uid ]], 'wlrmg_migration_queue')) {
                as_schedule_single_action($time, 'wlrmg_process_migration_job', [[ 'uid' => $uid ]], 'wlrmg_migration_queue');
            }
            $in_flight++;
        }
	}

    /**
     * Produce child range batches for the active migration category.
     * Ensures only one category runs at a time and per-parent production is serialized by transient lock.
     */
    private static function produceChildBatches()
    {
		// Resolve active category via option instead of transient
		$active_opt = get_option('wlrmg_active_category', array());
		$active_category = is_array($active_opt) && ! empty($active_opt['category']) ? (string) $active_opt['category'] : '';
		$parent_job = null;

        if (empty($active_category)) {
            // Pick the oldest parent job across all categories
            $parents = ScheduledJobs::getParentJobsPendingOrActive();
            if (empty($parents)) {
                return;
            }
            $parent_job = is_array($parents) ? reset($parents) : $parents;
            if (empty($parent_job) || !is_object($parent_job)) {
                return;
            }
			$active_category = isset($parent_job->category) ? $parent_job->category : '';
            if (empty($active_category)) {
                return;
            }
			// Persist active category as a durable option
			update_option('wlrmg_active_category', array(
				'category' => $active_category,
				'set_at' => time(),
			));
        } else {
            // Resolve the active category's parent
			$parent_job = ScheduledJobs::getParentJobByCategory($active_category);
            if (empty($parent_job) || !is_object($parent_job)) {
				// No more parent for this category; release lock
				delete_option('wlrmg_active_category');
                return;
            }
        }

        // Parse cursor and limit from parent conditions
        $conditions = !empty($parent_job->conditions) ? json_decode($parent_job->conditions, true) : [];
        $batch_limit = (int) ($conditions['batch_limit'] ?? (int)$parent_job->limit ?? 50);
        if ($batch_limit <= 0) {
            $batch_limit = 50;
        }
        $last_enqueued_id = (int) ($conditions['last_enqueued_id'] ?? 0);

        // Per-parent lock (option-backed with stale recovery)
		$parent_uid = (int) $parent_job->uid;
		$got_lock = self::acquireProducerLock($parent_uid);
		if (!$got_lock) {
			return;
		}

        try {
            $current_max_id = Migration::getCurrentMaxId($active_category);
            if ($current_max_id <= 0 || $last_enqueued_id >= $current_max_id) {
                // Nothing to enqueue now. If no child jobs are active for this parent, finalize parent and release category.
                $children = ScheduledJobs::getBatchesByParent((int)$parent_job->uid);
                $has_active_children = false;
                if (!empty($children) && is_array($children)) {
                    foreach ($children as $child) {
                        if ((int)$child->uid === (int)$parent_job->uid) {
                            // Skip the parent row itself
				continue;
			}
                        if (in_array((string)$child->status, ['pending','processing'], true)) {
                            $has_active_children = true;
                            break;
                        }
                    }
                }
				if (!$has_active_children) {
					// Mark parent as completed
					$job_table = new ScheduledJobs();
					$job_table->updateRow([
						'status' => 'completed',
						'updated_at' => strtotime(gmdate('Y-m-d h:i:s'))
					], [
						'uid' => (int)$parent_job->uid,
						'source_app' => 'wlr_migration'
					]);
					delete_option('wlrmg_active_category');
				}
                return;
            }

            // Create up to N child jobs per tick
            $max_batches_per_tick = (int) apply_filters('wlrmg_max_batches_per_tick', 5);
            for ($i = 0; $i < $max_batches_per_tick; $i++) {
                $ids = Migration::getIdsWindow($active_category, $last_enqueued_id, $batch_limit, $current_max_id);
                if (empty($ids)) {
                    break;
                }
                $end_id = (int) end($ids);
                $insert_id = ScheduledJobs::insertChildRangeJob($parent_job, $last_enqueued_id, $end_id, $batch_limit);
                if ($insert_id > 0) {
                    ScheduledJobs::updateParentEnqueuedCursor((int)$parent_job->uid, $end_id);
                    $last_enqueued_id = $end_id;
                } else {
                    // Failed to insert; stop to avoid loop
                    break;
                }
                if ($last_enqueued_id >= $current_max_id) {
                    break;
                }
            }

            // If we've enqueued all up to current max and there are no active children, complete parent and release category
            if ($last_enqueued_id >= $current_max_id) {
                $children = ScheduledJobs::getBatchesByParent((int)$parent_job->uid);
                $has_active_children = false;
                if (!empty($children) && is_array($children)) {
                    foreach ($children as $child) {
                        if ((int)$child->uid === (int)$parent_job->uid) {
                            continue;
                        }
                        if (in_array((string)$child->status, ['pending','processing'], true)) {
                            $has_active_children = true;
                            break;
                        }
                    }
                }
				if (!$has_active_children) {
					$job_table = new ScheduledJobs();
					$job_table->updateRow([
						'status' => 'completed',
				'updated_at' => strtotime(gmdate('Y-m-d h:i:s'))
					], [
						'uid' => (int)$parent_job->uid,
				'source_app' => 'wlr_migration'
			]);
					delete_option('wlrmg_active_category');
				}
            }
		} finally {
			self::releaseProducerLock($parent_uid);
		}
    }

    /**
     * Acquire per-parent producer lock using options API (no token variant).
     * Returns true if we acquired the lock, false otherwise.
     */
    private static function acquireProducerLock($parent_uid)
    {
        $option_name = 'wlrmg_producer_lock_' . (int) $parent_uid;
        $now = time();
        // Atomic acquire
        if (add_option($option_name, array('locked_at' => $now))) {
            return true;
        }
        // Stale detection: older than 10 minutes → recover lock
        $existing = get_option($option_name, array());
        $locked_at = (int) ($existing['locked_at'] ?? 0);
        if ($locked_at > 0 && ($now - $locked_at) > (10 * MINUTE_IN_SECONDS)) {
            // Recover by deleting and reacquiring atomically
            delete_option($option_name);
            return add_option($option_name, array('locked_at' => $now));
        }
        return false;
    }

    /**
     * Release producer lock (no owner check; we assume short critical sections).
     */
    private static function releaseProducerLock($parent_uid)
    {
        $option_name = 'wlrmg_producer_lock_' . (int) $parent_uid;
        delete_option($option_name);
    }

	/**
	 * Process a single migration job
	 * This method handles both batch jobs and single jobs
	 *
	 * @param object $job_data Job data object
	 * @return void
	 */
    private static function processJob($job_data) {
        // Support being invoked with array args { uid: X } from Action Scheduler
        if (is_array($job_data) && isset($job_data['uid'])) {
            $job_table = new ScheduledJobs();
            global $wpdb;
            $loaded = $job_table->getWhere($wpdb->prepare("uid = %d AND source_app = %s", [(int)$job_data['uid'], 'wlr_migration']));
            if (empty($loaded) || !is_object($loaded)) {
                return;
            }
            $job_data = $loaded;
        }

        // Batch jobs: fetch users using batch_info (offset/limit)
        $batch_info = ScheduledJobs::getBatchInfo($job_data);
        $category = is_object($job_data) ? $job_data->category : $job_data['category'];
        if ($batch_info && isset($batch_info['start_id']) && isset($batch_info['end_id'])) {
            $users = Migration::getUsersForRange(
                $category,
                (int)$batch_info['start_id'],
                (int)$batch_info['end_id']
            );
        } else {
            // Fallback: let classes fetch
            $users = null;
        }

        // Route to appropriate migration class
        switch ($category) {
				case 'wp_swings_migration':
					$wp_swings = new WPSwings();
				$wp_swings->migrateToLoyalty($job_data, $users);
					break;
				case 'wlpr_migration':
					$wlpr_point_reward = new WLPRPointsRewards();
				$wlpr_point_reward->migrateToLoyalty($job_data, $users);
					break;
				case 'woocommerce_migration':
					$woo_point_reward = new WooPointsRewards();
				$woo_point_reward->migrateToLoyalty($job_data, $users);
					break;
				case 'yith_migration':
				default:
				wc_get_logger()->add('wlrmg_migration', 'Unknown migration category: ' . $job_data->category);
					return;
		}

	}

	/**
	 * Process a single migration job using Action Scheduler
	 * This method handles both batch jobs and single jobs
	 *
	 * @param object $job_data Job data object
	 * @return void
	 */
	public static function processMigrationJob($job_data) {
        // Resolve minimal AS args to full job row before any status updates
        if (is_array($job_data) && isset($job_data['uid'])) {
            $job_table = new ScheduledJobs();
            global $wpdb;
            $loaded = $job_table->getWhere($wpdb->prepare("uid = %d AND source_app = %s", [(int)$job_data['uid'], 'wlr_migration']));
            if (empty($loaded) || !is_object($loaded)) {
                return;
            }
            $job_data = $loaded;
        } elseif (is_object($job_data) && (!isset($job_data->category) || empty($job_data->category))) {
            // Handle object with only uid
            $job_table = new ScheduledJobs();
            global $wpdb;
            $loaded = $job_table->getWhere($wpdb->prepare("uid = %d AND source_app = %s", [(int)$job_data->uid, 'wlr_migration']));
            if (empty($loaded) || !is_object($loaded)) {
                return;
            }
            $job_data = $loaded;
        }

        $process_identifier = 'wlrmg_job_' . (isset($job_data->uid) ? $job_data->uid : 0);
		
		try {
			// Update job status to 'processing' when starting
			$job_table = new ScheduledJobs();
			$update_data = [
				'status' => 'processing',
				'updated_at' => strtotime(gmdate('Y-m-d h:i:s'))
			];
			$job_table->updateRow($update_data, [
				'uid' => $job_data->uid,
				'source_app' => 'wlr_migration'
			]);
			
            self::processJob($job_data);

            // After processing, if this is a batch job, mark this batch row as completed.
            // The migration classes process the provided $users entirely in one run.
            global $wpdb;
            $latest = $job_table->getWhere($wpdb->prepare("uid = %d AND source_app = %s", [$job_data->uid, 'wlr_migration']));
            $batch_info = ScheduledJobs::getBatchInfo($latest);
            if (!empty($latest) && is_object($latest) && $latest->status !== 'completed') {
                $update_data = [
                    'status' => $batch_info ? 'completed' : 'pending',
                    'updated_at' => strtotime(gmdate('Y-m-d h:i:s'))
                ];
                $job_table->updateRow($update_data, [
                    'uid' => $job_data->uid,
                    'source_app' => 'wlr_migration'
                ]);
            }
			
		} catch (Exception $e) {
			// Update job status to 'failed' on error
			$job_table = new ScheduledJobs();
			$update_data = [
				'status' => 'failed',
				'updated_at' => strtotime(gmdate('Y-m-d h:i:s'))
			];
			$job_table->updateRow($update_data, [
				'uid' => $job_data->uid,
				'source_app' => 'wlr_migration'
			]);
			
			// Re-throw to let Action Scheduler handle retry logic
			throw $e;
		}
	}


}