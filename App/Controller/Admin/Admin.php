<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlrm\App\Controller\Admin;

use ParseCsv\Csv;
use Wlr\App\Helpers\Woocommerce;
use Wlrm\App\Controller\Base;
use Wlrm\App\Controller\Compatibles\WLPRPointsRewards;
use Wlrm\App\Controller\Compatibles\WooPointsRewards;
use Wlrm\App\Controller\Compatibles\WPSwings;
use Wlrm\App\Controller\Compatibles\Yith;
use Wlrm\App\Helper\CompatibleCheck;
use Wlrm\App\Helper\Pagination;
use Wlrm\App\Models\MigrationLog;
use Wlrm\App\Models\ScheduledJobs;

defined( "ABSPATH" ) or die();

class Admin extends Base {
	function pluginActivation() {
		$check = new CompatibleCheck();
		if ( $check->init_check( true ) ) {
			try {
				$this->createRequiredTable();
			} catch ( \Exception $e ) {
				exit( esc_html( WLRMG_PLUGIN_NAME . __( 'Plugin required table creation failed.', 'wp-loyalty-migration' ) ) );
			}
		}

		return true;
	}

	function createRequiredTable() {
		try {
			$job_table = new ScheduledJobs();
			$job_table->create();
			$log = new MigrationLog();
			$log->create();
		} catch ( \Exception $e ) {
			exit( WLRMG_PLUGIN_NAME . __( "Plugin required table creation failed.", "wp-loyalty-migration" ) );
		}
	}

	public function addAdminMenu() {
		if ( Woocommerce::hasAdminPrivilege() ) {
			add_menu_page( __( "WPLoyalty: Migration", "wp-loyalty-migration" ), __( "WPLoyalty: Migration", "wp-loyalty-migration" ), "manage_woocommerce", WLRMG_PLUGIN_SLUG, array(
				$this,
				"addMigrationPage"
			), 'dashicons-megaphone', 58 );
		}
	}

	/**
	 * @return string|void
	 */
	function addMigrationPage() {
		if ( ! Woocommerce::hasAdminPrivilege() ) {
			return "";
		}
		$view   = (string) self::$input->post_get( "view", "actions" );
		$params = array(
			'current_page' => $view,
			'main_page'    => array(),
		);
		switch ( $view ) {
			case 'actions':
				$params['main_page']['actions'] = $this->getActionsPage();
				break;
			case 'settings':
				$params['main_page']['settings'] = $this->getSettingsPage();
				break;
			case 'activity_details':
				$params['main_page']['activity_details'] = $this->getActivityDetailsPage();
				break;
		}
		self::$template->setData( WLRMG_VIEW_PATH . '/Admin/main.php', $params )->display();
	}

	function getActivityDetailsPage() {
		$view   = (string) self::$input->post_get( "view", "activity" );
		$type   = (string) self::$input->post_get( "type", "" );
		$search = (string) self::$input->post_get( "search", "" );
		$job_id = (int) self::$input->post_get( "job_id", 0 );
		$args   = array(
			"current_page"     => $view,
			"job_id"           => $job_id,
			"search"           => $search,
			"action"           => $type,
			"activity"         => $this->getActivityDetailsData( $job_id ),
			"back"             => WLRMG_PLUGIN_URL . "Assets/svg/back_button.svg",
			"no_activity_icon" => WLRMG_PLUGIN_URL . "Assets/svg/no_activity_list.svg",
		);
		$args   = apply_filters( 'wlba_before_activity_details_page', $args );

		return self::$template->setData( WLRMG_VIEW_PATH . "/Admin/activity_details.php", $args )->render();
	}

	function getActivityDetailsData( $job_id ) {
		if ( empty( $job_id ) || $job_id <= 0 ) {
			return array();
		}
		$job_table = new ScheduledJobs();
		$where     = self::$db->prepare( " uid = %d AND source_app =%s", array( $job_id, 'wlr_migration' ) );
		$job_data  = $job_table->getWhere( $where );

		$result = array(
			'job_id' => $job_id,
		);
		if ( ! empty( $job_data ) && is_object( $job_data ) ) {
			$result['job_data'] = $this->processJobData( $job_data );
			$result['activity'] = $this->getActivityLogsData( $job_id );
		}

		return apply_filters( 'wlba_before_acitivity_view_details_data', $result );
	}

	function processJobData( $job_data ) {
		if ( empty( $job_data ) || ! is_object( $job_data ) ) {
			return array();
		}
		$result = array(
			'created_at' => isset( $job_data->created_at ) && ! empty( $job_data->created_at ) ? self::$woocommerce->beforeDisplayDate( $job_data->created_at ) : '',
			'offset'     => isset( $job_data->offset ) && ! empty( $job_data->offset ) ? $job_data->offset : 0,
			'admin_mail' => isset( $job_data->admin_mail ) && ! empty( $job_data->admin_mail ) ? $job_data->admin_mail : '',
			'status'     => isset( $job_data->status ) && ! empty( $job_data->status ) ? $job_data->status : '',
			'action'     => isset( $job_data->category ) && ! empty( $job_data->category ) ? $job_data->category : '',
		);
		if ( is_object( $job_data ) && isset( $job_data->category ) ) {
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

		return $result;
	}

	function getActivityLogsData( $job_id ) {
		if ( empty( $job_id ) || $job_id <= 0 ) {
			return array();
		}
		$bulk_action_log  = new MigrationLog();
		$url              = admin_url( 'admin.php?' . http_build_query( array(
				'page'   => WLRMG_PLUGIN_SLUG,
				'view'   => 'activity_details',
				'job_id' => $job_id,
			) ) );
		$current_page     = (int) self::$input->post_get( "migration_page", 1 );
		$activity_list    = $bulk_action_log->getActivityList( $job_id, $current_page );
		$per_page         = (int) is_array( $activity_list ) && isset( $activity_list["per_page"] ) ? $activity_list["per_page"] : 0;
		$current_page     = (int) is_array( $activity_list ) && isset( $activity_list["current_page"] ) ? $activity_list["current_page"] : 0;
		$pagination_param = array(
			"totalRows"          => (int) is_array( $activity_list ) && isset( $activity_list["total_rows"] ) ? $activity_list["total_rows"] : 0,
			"perPage"            => $per_page,
			"baseURL"            => $url,
			"currentPage"        => $current_page,
			"queryStringSegment" => "migration_page",
		);
		$pagination       = new Pagination( $pagination_param );

		return apply_filters( 'wlrmg_activity_details', array(
			"base_url"                  => $url,
			"pagination"                => $pagination,
			"per_page"                  => $per_page,
			"page_number"               => $current_page,
			"activity_list"             => (array) is_array( $activity_list ) && isset( $activity_list["data"] ) ? $activity_list["data"] : array(),
			'show_export_file_download' => (int) is_array( $activity_list ) && isset( $activity_list["export_file_list"] ) ? count( $activity_list["export_file_list"] ) : 0,
			'export_csv_file_list'      => is_array( $activity_list ) && isset( $activity_list["export_file_list"] ) ? $activity_list["export_file_list"] : array(),
		) );
	}

	function getActionsPage() {
		$view = (string) self::$input->post_get( "view", "actions" );
		$args = array(
			'current_page'     => $view,
			"back_to_apps_url" => admin_url( 'admin.php?' . http_build_query( array( 'page' => WLR_PLUGIN_SLUG ) ) ) . '#/apps',
			"previous"         => WLRMG_PLUGIN_URL . "Assets/svg/previous.svg",
			'migration_cards'  => array(
				array(
					'type'                   => 'wp_swings_migration',
					'title'                  => __( 'WPSwings points and rewards', 'wp-loyalty-migration' ),
					'description'            => __( 'Migrate users with points', 'wp-loyalty-migration' ),
					'is_active'              => WPSwings::checkPluginIsActive(),
					'job_data'               => WPSwings::getMigrationJob(),
					'is_show_migrate_button' => true,
				),
				array(
					'type'                   => 'wlpr_migration',
					'title'                  => __( 'WooCommerce Loyalty Points and Rewards', 'wp-loyalty-migration' ),
					'description'            => __( 'Migrate users with points', 'wp-loyalty-migration' ),
					'is_active'              => WLPRPointsRewards::checkPluginIsActive(),
					'job_data'               => WLPRPointsRewards::getMigrationJob(),
					'is_show_migrate_button' => true,
				),
				array(
					'type'                   => 'woocommerce_migration',
					'title'                  => __( 'Woocommerce points and rewards', 'wp-loyalty-migration' ),
					'description'            => __( 'Migrate users with points', 'wp-loyalty-migration' ),
					'is_active'              => WooPointsRewards::checkPluginIsActive(),
					'job_data'               => WooPointsRewards::getMigrationJob(),
					'is_show_migrate_button' => true,
				),
			)
		);

		return self::$template->setData( WLRMG_VIEW_PATH . '/Admin/actions.php', $args )->render();
	}

	function getSettingsPage() {
		$view = (string) self::$input->post_get( "view", "settings" );
		$args = array(
			"batch_limit"      => $this->getBatchLimit(),
			"pagination_limit" => $this->getPaginationLimit(),
			"current_page"     => $view,
			"back_to_apps_url" => admin_url( 'admin.php?' . http_build_query( array( 'page' => WLR_PLUGIN_SLUG ) ) ) . '#/apps',
			"back"             => WLRMG_PLUGIN_URL . "Assets/svg/back_button.svg",
			"previous"         => WLRMG_PLUGIN_URL . "Assets/svg/previous.svg",
			"option_settings"  => get_option( 'wlrmg_settings', array() ),
		);

		return self::$template->setData( WLRMG_VIEW_PATH . '/Admin/settings.php', $args )->render();
	}

	public function getBatchLimit() {
		return array(
			"10" => "10",
			"20" => "20",
			"30" => "30",
			"40" => "40",
			"50" => "50",
		);
	}

	public function getPaginationLimit() {
		return array(
			"5"   => "5",
			"10"  => "10",
			"20"  => "20",
			"50"  => "50",
			"100" => "100",
		);
	}

	function getAppDetails( $plugins ) {
		if ( is_array( $plugins ) && ! empty( $plugins ) ) {
			foreach ( $plugins as &$plugin ) {
				if ( is_array( $plugin ) && isset( $plugin['plugin'] ) && $plugin['plugin'] == 'wp-loyalty-migration/wp-loyalty-migration.php' ) {
					$plugin['page_url'] = admin_url( 'admin.php?' . http_build_query( array( 'page' => WLRMG_PLUGIN_SLUG ) ) );
					break;
				}
			}
		}

		return $plugins;
	}

	function addAssets() {
		if ( self::$input->get( "page" ) != WLRMG_PLUGIN_SLUG ) {
			return;
		}
		$suffix = ".min";
		if ( defined( "SCRIPT_DEBUG" ) ) {
			$suffix = SCRIPT_DEBUG ? "" : ".min";
		}
		remove_all_actions( "admin_notices" );
		wp_enqueue_style( WLR_PLUGIN_SLUG . '-wlr-font', WLR_PLUGIN_URL . 'Assets/Site/Css/wlr-fonts' . $suffix . '.css', array(), WLR_PLUGIN_VERSION . '&t=' . time() );
		wp_enqueue_script( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Js/alertify' . $suffix . '.js', array(), WLR_PLUGIN_VERSION . '&t=' . time() );
		wp_enqueue_style( WLR_PLUGIN_SLUG . '-alertify', WLR_PLUGIN_URL . 'Assets/Admin/Css/alertify' . $suffix . '.css', array(), WLR_PLUGIN_VERSION . '&t=' . time() );
		wp_enqueue_style( WLRMG_PLUGIN_SLUG . "-main-style", WLRMG_PLUGIN_URL . "Assets/Admin/Css/wlrmg-main.css", array( "woocommerce_admin_styles" ), WLRMG_PLUGIN_VERSION . "&t=" . time() );
		wp_enqueue_script( WLRMG_PLUGIN_SLUG . "-main-script", WLRMG_PLUGIN_URL . "Assets/Admin/Js/wlrmg-main" . $suffix . ".js", array(
			"jquery",
			"select2"
		), WLRMG_PLUGIN_VERSION . "&t=" . time() );
		$localize_data = apply_filters( 'wlrmg_before_localize_data', array(
			"ajax_url"      => admin_url( "admin-ajax.php" ),
			"migrate_users" => Woocommerce::create_nonce( "wlrmg_migrate_users_nonce" ),
			"save_settings" => Woocommerce::create_nonce( "wlrmg_save_settings_nonce" ),
			"popup_nonce"   => Woocommerce::create_nonce( "wlrmg_popup_nonce" ),
		) );
		wp_localize_script( WLRMG_PLUGIN_SLUG . "-main-script", "wlrmg_localize_data", $localize_data );
	}

	function migrateUsersWithPointsJob() {
		$result = array(
			"success" => false,
			"data"    => array(
				"message" => __( "Security check failed", "wp-loyalty-migration" ),
			)
		);
		if ( ! $this->securityCheck( 'wlrmg_migrate_users_nonce' ) ) {
			wp_send_json( $result );
		}
		$post = self::$input->post();

		$validate_data = self::$validation->validateMigrationData( $post );
		if ( is_array( $validate_data ) && ! empty( $validate_data ) && count( $validate_data ) > 0 ) {
			foreach ( $validate_data as $key => $validate ) {
				$validate_data[ $key ] = current( $validate );
			}
			$result["data"]["field_error"] = $validate_data;
			$result["data"]["message"]     = __( "Records not saved", "wp-loyalty-migration" );
			wp_send_json( $result );
		}
		$cron_job_modal = new ScheduledJobs();
		/* check job exist or not */
		$where          = self::$db->prepare( 'id > %d AND source_app = %s AND category =%s', array(
			0,
			'wlr_migration',
			$post['migration_action']
		) );
		$check_data_job = $cron_job_modal->getWhere( $where, '*', true );
		if ( ! empty( $check_data_job ) ) {
			$result['data']['message'] = __( 'Migration job already created', 'wp-loyalty-migration' );
			wp_send_json( $result );
		}
		$wlrmg_setttings = get_option( 'wlrmg_settings' );
		$batch_limit     = (int) is_array( $wlrmg_setttings ) && isset( $wlrmg_setttings['batch_limit'] ) && ! empty( $wlrmg_setttings['batch_limit'] )
		                   && $wlrmg_setttings['batch_limit'] > 0 ? $wlrmg_setttings['batch_limit'] : 10;

		$max_uid = 1;
		if ( isset( $post['job_id'] ) && ! empty( $post['job_id'] ) ) {
			$max_uid = $post['job_id'];
		} else {
			$cron_job_modal = new ScheduledJobs();
			$where          = self::$db->prepare( ' id > %d', array( 0 ) );
			$data_job       = $cron_job_modal->getWhere( $where, 'MAX(uid) as max_uid', true );
			if ( ! empty( $data_job ) && is_object( $data_job ) && isset( $data_job->max_uid ) ) {
				$max_uid = $data_job->max_uid + 1;
			}
		}
		$admin_mail      = self::$woocommerce->get_login_user_email();
		$conditions      = array(
			'update_point' => isset( $post['update_point'] ) && ! empty( $post['update_point'] ) ? $post['update_point'] : 'skip',
		);
		$job_data        = array(
			"uid"               => $max_uid,
			"source_app"        => 'wlr_migration',
			"admin_mail"        => $admin_mail,
			"category"          => isset( $post['migration_action'] ) && ! empty( $post['migration_action'] ) ? $post['migration_action'] : "",
			"action_type"       => 'migration_to_wployalty',
			"conditions"        => json_encode( $conditions ),
			"status"            => 'pending',
			"limit"             => (int) isset( $batch_limit ) && $batch_limit > 0 ? $batch_limit : 0,
			"offset"            => 0,
			"last_processed_id" => 0,
			"created_at"        => strtotime( date( "Y-m-d h:i:s" ) ),
		);
		$job_table_model = new ScheduledJobs();
		$cron_job_id     = $job_table_model->insertRow( $job_data );
		if ( $cron_job_id <= 0 ) {
			$result['data']['message'] = __( "Migration job can't be created.", "wp-loyalty-migration" );
			wp_send_json( $result );
		}
		$result['success']         = true;
		$result['data']['message'] = __( "Migration job created successfully.", "wp-loyalty-migration" );
		$result['data']['job_id']  = $cron_job_id;
		wp_send_json( $result );
	}

	function getConfirmContent() {
		$result = array(
			"success" => false,
			"data"    => array(
				"message" => __( "Security check failed", "wp-loyalty-migration" ),
			)
		);
		if ( ! $this->securityCheck( 'wlrmg_migrate_users_nonce' ) ) {
			wp_send_json( $result );
		}
		$category      = (string) self::$input->post_get( 'category', '' );
		$validate_data = self::$validation->validateInputAlpha( $category );
		/*if (is_array($validate_data) && !empty($validate_data) && count($validate_data) > 0) {
			foreach ($validate_data as $key => $validate) {
				$validate_data[$key] = current($validate);
			}
			$result["data"]["field_error"] = $validate_data;
			$result["data"]["message"] = __("Cant able to ", "wp-loyalty-migration");
			wp_send_json($result);
		}*/
		$html = "";
		$args = array(
			'category' => $category,
		);
		switch ( $category ) {
			case 'wp_swings_migration':
			case 'wlpr_migration':
			case 'woocommerce_migration':
				$html = self::$template->setData( WLRMG_VIEW_PATH . '/Admin/popup.php', $args )->render();
				break;
		}
		$result['status']          = true;
		$result['data']['html']    = $html;
		$result['data']['message'] = __( 'Update points to customers', 'wp-loyalty-migration' );
		wp_send_json( $result );
	}

	function saveSettings() {
		$result = array(
			"success" => false,
			"data"    => array(
				"message" => __( "Security check failed", "wp-loyalty-migration" ),
			)
		);
		if ( ! $this->securityCheck( 'wlrmg_save_settings_nonce' ) ) {
			wp_send_json( $result );
		}
		$post          = self::$input->post();
		$validate_data = self::$validation->validateSettingsData( $post );
		if ( is_array( $validate_data ) && ! empty( $validate_data ) && count( $validate_data ) > 0 ) {
			foreach ( $validate_data as $key => $validate ) {
				$validate_data[ $key ] = current( $validate );
			}
			$data["data"]["field_error"] = $validate_data;
			$data["data"]["message"]     = __( "Records not saved", "wp-loyalty-migration" );
			wp_send_json( $data );
		}
		$need_to_remove_fields = array( 'action', 'wlrmg_nonce' );
		foreach ( $need_to_remove_fields as $field ) {
			unset( $post[ $field ] );
		}
		$data = apply_filters( 'wlrmg_before_save_settings', $post );
		update_option( 'wlrmg_settings', $data );
		$result['success']         = true;
		$result['data']['message'] = __( "Settings saved successfully.", "wp-loyalty-migration" );
		wp_send_json( $result );
	}

	function initSchedule() {
		$hook      = "wlrmg_migration_jobs";
		$timestamp = wp_next_scheduled( $hook );
		if ( false === $timestamp ) {
			$scheduled_time = strtotime( "+1 hour", current_time( "timestamp" ) );
			wp_schedule_event( $scheduled_time, "hourly", $hook );
		}
	}

	function removeSchedule() {
		$next_scheduled = wp_next_scheduled( "wlrmg_migration_jobs" );
		wp_unschedule_event( $next_scheduled, "wlrmg_migration_jobs" );
	}

	function triggerMigrations() {
		$job_table = new ScheduledJobs();
		$where     = self::$db->prepare( "  source_app = %s AND id > 0 AND status IN (%s,%s) ORDER BY id ASC", array(
			"wlr_migration",
			"pending",
			"processing"
		) );
		$data      = $job_table->getWhere( $where );
		$log       = wc_get_logger();
		$log->add( 'mig', json_encode( $data ) );
		$process_identifier = 'wlrmg_cron_job_running';
		if ( get_transient( $process_identifier ) ) {
			return;
		}
		set_transient( $process_identifier, true, 60 );
		if ( is_object( $data ) && ! empty( $data ) ) {
			//process
			$category = isset( $data->category ) && ! empty( $data->category ) ? $data->category : "";
			$log->add( 'mig', "category => " . json_encode( $category ) );
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

	function menuHide() {
		?>
        <style>
            #toplevel_page_wp-loyalty-migration {
                display: none !important;
            }
        </style>
		<?php
	}

	function addExtraAction( $action_list ) {
		if ( empty( $action_list ) || ! is_array( $action_list ) ) {
			return $action_list;
		}
		$action_list["migration_to_wployalty"] = __( "User migrated via WPLoyalty Migration", "wp-loyalty-migration" );

		return $action_list;
	}

	function addExtraPointLedgerAction( $action_list ) {
		if ( empty( $action_list ) || ! is_array( $action_list ) ) {
			return $action_list;
		}
		$action_list[] = "migration_to_wployalty";

		return $action_list;
	}

	function exportPopup() {
		$result = array(
			"success" => false,
			"data"    => array(),
		);
		if ( ! $this->securityCheck( 'wlrmg_popup_nonce' ) ) {
			$result['data']['message'] = __( "Security check failed", "wp-loyalty-bulk-action" );
			wp_send_json( $result );
		}
		if ( ! is_writable( WLRMG_PLUGIN_DIR . '/App/File/' ) && function_exists( 'chmod' ) ) {
			$add_permission = @chmod( WLRMG_PLUGIN_DIR . '/App/File/', 0777 );
			if ( ! $add_permission ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied to write a file', 'wp-loyalty-bulk-action' ) ) );
			}
		}
		$post             = self::$input->post();
		$path             = WLRMG_PLUGIN_DIR . '/App/File/' . $post['job_id'];
		$file_name        = $post['migration_action'] . '_' . $post['job_id'] . '_export_*.*';
		$delete_file_path = trim( $path . '/' . $file_name );
		foreach ( glob( $delete_file_path ) as $file_path ) {
			if ( file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}
		}
		$base_url                  = admin_url( 'admin.php?' . http_build_query( array(
				'page'   => WLRMG_PLUGIN_SLUG,
				'view'   => 'activity_details',
				'job_id' => $post['job_id']
			) ) );
		$log_table                 = new MigrationLog();
		$query                     = self::$db->prepare( " id > 0 AND action != %s AND job_id = %d AND user_email !='' ", array(
			$post['migration_action'] . "_completed",
			(int) $post["job_id"]
		) );
		$log_count                 = $log_table->getWhere( $query, "count(*) as total_count", true );
		$page_details              = array(
			'base_url'      => $base_url,
			'total_count'   => $log_count->total_count,
			'process_count' => 0,
			'limit_start'   => 0,
			'limit'         => 5,
			'wlrmg_nonce'   => Woocommerce::create_nonce( 'wlrmg_export_nonce' ),
			'job_id'        => $post['job_id'],
			'category'      => $post['migration_action'],
		);
		$template_path             = $this->getTemplatePath( "Admin/export_logs.php" );
		$html                      = self::$template->setData( $template_path, $page_details )->render();
		$result['success']         = true;
		$result['data']['success'] = 'completed';
		$result['data']['html']    = $html;
		wp_send_json( $result );
	}

	function handleExport() {
		$result = array(
			"success" => false,
			"data"    => array(),
		);
		if ( ! $this->securityCheck( 'wlrmg_export_nonce' ) ) {
			$result['data']['message'] = __( "Security check failed", "wp-loyalty-bulk-action" );
			wp_send_json( $result );
		}
		$post        = self::$input->post();
		$limit_start = (int) self::$input->post_get( 'limit_start', 0 );
		$limit       = (int) self::$input->post_get( 'limit', 5 );
		$total_count = (int) self::$input->post_get( 'total_count', 0 );
		if ( $total_count > $limit_start ) {
			$path = WLRMG_PLUGIN_DIR . '/App/File/' . $post['job_id'];
			if ( ! is_dir( $path ) ) {
				mkdir( $path, 0777, true );
				@chmod( $path, 0777 );
			}
			$file_name  = $post['category'] . '_' . $post['job_id'] . '_export_';
			$file_count = 0;
			if ( $limit_start >= 499 ) {
				$file_count = round( ( $limit_start / 499 ) );
			}
			$file_path = trim( $path . '/' . $file_name . $file_count . '.csv' );
			$log_table = new MigrationLog();
			global $wpdb;
			$table              = $log_table->getTableName();
			$where              = " id > 0 ";
			$where              .= $wpdb->prepare( "  AND action = %s AND job_id = %d AND user_email !='' ", array(
				$post["category"],
				(int) $post["job_id"]
			) );
			$where              .= $wpdb->prepare( ' ORDER BY id ASC LIMIT %d OFFSET %d;', array(
				$limit,
				$limit_start
			) );
			$csv_helper         = new Csv();
			$select             = "user_email,referral_code,points";
			$csv_helper->titles = array( 'email', 'referral_code', 'points' );

			$query     = "SELECT {$select} FROM {$table} WHERE {$where}";
			$file_data = $wpdb->get_results( $query, ARRAY_A );
			if ( ! empty( $file_data ) ) {
				foreach ( $file_data as &$single_file_data ) {
					if ( isset( $single_file_data['user_email'] ) ) {
						$single_file_data['email'] = $single_file_data['user_email'];
					}
				}
				$csv_helper->loadFile( $file_path );
				$count = $csv_helper->getTotalDataRowCount();
				if ( $count <= 0 ) {
					$header   = array();
					$header[] = $csv_helper->titles;
					$csv_helper->save( $file_path, $header, true );
				}
				$csv_helper->save( $file_path, $file_data, true );
			}
			$result['success']         = true;
			$result['data']['success'] = 'incomplete';
			$limit_start               = $limit_start + $limit;
			if ( $limit_start >= $total_count ) {
				$limit_start = $total_count;
			}
			$result['data']['limit_start']  = $limit_start;
			$result['data']['notification'] = sprintf( __( 'Exported %s customer', 'wp-loyalty-bulk-action' ), $limit_start );//__(sprintf('Insert/Update %s customer', count($file_data)), WLPR_TEXT_DOMAIN) . '<br>';
		} else {
			$result['success']              = true;
			$result['data']['success']      = 'completed';
			$result['data']['notification'] = sprintf( __( 'Completed', 'wp-loyalty-bulk-action' ), $limit_start );
			$result['data']['redirect']     = admin_url( 'admin.php?' . http_build_query( array(
					'page'   => WLRMG_PLUGIN_SLUG,
					'view'   => 'activity_details',
					'job_id' => $post['job_id']
				) ) );
		}
		wp_send_json( $result );
	}

	function exportFileList( $post ) {
		$path             = WLRMG_PLUGIN_DIR . '/App/File/' . $post['job_id'];
		$file_name        = $post['category'] . '_' . $post['job_id'] . '_export_*.*';
		$delete_file_path = trim( $path . '/' . $file_name );
		$download_list    = array();
		foreach ( glob( $delete_file_path ) as $file_path ) {
			if ( file_exists( $file_path ) ) {
				$file_detail            = new \stdClass();
				$file_detail->file_name = basename( $file_path );
				$file_detail->file_path = $file_path;
				$file_detail->file_url  = rtrim( WLRMG_PLUGIN_URL, '/' ) . '/App/File/' . $post['job_id'] . '/' . $file_detail->file_name;
				$download_list[]        = $file_detail;
			}
		}

		return $download_list;
	}

	function getExportFiles() {
		$result = array(
			"success" => false,
			"data"    => array(),
		);
		if ( ! $this->securityCheck( 'wlrmg_popup_nonce' ) ) {
			$result['data']['message'] = __( "Security check failed", "wp-loyalty-bulk-action" );
			wp_send_json( $result );
		}
		$post   = self::$input->post();
		$job_id = (int) self::$input->post_get( 'job_id', 0 );
		if ( empty( $job_id ) ) {
			$result['data']['message'] = __( "Job id not found", "wp-loyalty-bulk-action" );
			wp_send_json( $result );
		}
		$export_files           = $this->exportFileList( array(
			'category' => $post['action_type'],
			'job_id'   => $job_id
		) );
		$page_details           = array(
			'job_id'       => $job_id,
			'export_files' => $export_files,
		);
		$template_path          = $this->getTemplatePath( "Admin/export_log_files.php" );
		$html                   = self::$template->setData( $template_path, $page_details )->render();
		$result['success']      = true;
		$result['data']['html'] = $html;
		wp_send_json( $result );

	}
}