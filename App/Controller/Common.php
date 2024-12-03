<?php

namespace Wlrm\App\Controller;


defined( 'ABSPATH' ) or die();

use Wlrm\App\Controller\Compatibles\WLPRPointsRewards;
use Wlrm\App\Controller\Compatibles\WooPointsRewards;
use Wlrm\App\Controller\Compatibles\WPSwings;
use Wlrm\App\Helper\Input;
use Wlrm\App\Helper\Pagination;
use Wlrm\App\Helper\WC;
use Wlrm\App\Models\MigrationLog;
use Wlrm\App\Models\ScheduledJobs;
use function Crontrol\Schedule\get;

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
			$result['job_data'] = self::handleJobData( $job_data );
			$result['activity'] = self::getActivityLogsData( $job_id );
		}

		return apply_filters( 'wlrm_before_acitivity_view_details_data', $result );
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
		$activity_list    = $bulk_action_log->getActivityList( $job_id, $current_page );
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

		wp_enqueue_script( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Js/alertify' . $suffix . '.js', [], WLR_PLUGIN_VERSION . '&t=' . time() );
		wp_enqueue_script( WLRMG_PLUGIN_SLUG . '-main-script', WLRMG_PLUGIN_URL . 'Assets/Admin/Js/wlrmg-main' . $suffix . ".js", [
			'jquery',
			'select2'
		], WLRMG_PLUGIN_VERSION . '&t=' . time() );
		$localize_data = apply_filters( 'wlrmg_before_localize_data', [
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'migrate_users' => WC::createNonce( 'wlrmg_migrate_users_nonce' ),
			'save_settings' => WC::createNonce( 'wlrmg_save_settings_nonce' ),
			'popup_nonce'   => WC::createNonce( 'wlrmg_popup_nonce' ),
		] );
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
			$scheduled_time = strtotime( '+1 hour', current_time( 'timestamp' ) );
			wp_schedule_event( $scheduled_time, 'hourly', $hook );
		}
	}

	public static function triggerMigrations() {
		$data               = ScheduledJobs::getAvailableJob();
		$process_identifier = 'wlrmg_cron_job_running';
		if ( get_transient( $process_identifier ) ) {
			return;
		}
		set_transient( $process_identifier, true, 60 );
		if ( is_object( $data ) && ! empty( $data ) ) {
			//process
			$category = ! empty( $data->category ) ? $data->category : "";
			switch ( $category ) {
				case 'wp_swings_migration':
					$wp_swings = new WPSwings();
					$wp_swings->migrateToLoyalty( $data );
					break;
				case 'wlpr_migration':
					$wlpr_point_reward = new WLPRPointsRewards();
					$wlpr_point_reward->migrateToLoyalty( $data );
					break;
				case 'woocommerce_migration':
					$woo_point_reward = new WooPointsRewards();
					$woo_point_reward->migrateToLoyalty( $data );
					break;
				case 'yith_migration':
				default:
					return;
			}
		}
		delete_transient( $process_identifier );
	}
}