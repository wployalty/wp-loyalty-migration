<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlrm\App\Controller\Compatibles;

use Wlr\App\Helpers\EarnCampaign;
use Wlr\App\Models\Users;
use Wlrm\App\Models\MigrationJob;
use Wlrm\App\Models\MigrationLog;
use Wlrm\App\Models\ScheduledJobs;

class WLPRPointsRewards implements Base {

	static function checkPluginIsActive() {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return in_array( 'loyalty-points-rewards/wp-loyalty-points-rewards.php', $active_plugins, false );
	}

	static function getMigrationJob() {
		$job_table = new ScheduledJobs();
		$job       = $job_table->getWhere( "category = 'wlpr_migration'" );

		return ( ! empty( $job ) && is_object( $job ) && isset( $job->uid ) ) ? $job : new \stdClass();
	}

	function migrateToLoyalty( $job_data ) {
		if ( empty( $job_data ) || ! is_object( $job_data ) ) {
			return;
		}
		$job_id            = (int) isset( $job_data->uid ) && ! empty( $job_data->uid ) ? $job_data->uid : 0;
		$admin_mail        = (string) isset( $job_data->admin_mail ) && ! empty( $job_data->admin_mail ) ? $job_data->admin_mail : '';
		$action_type       = (string) isset( $job_data->action_type ) && ! empty( $job_data->action_type ) ? $job_data->action_type : "migration_to_wployalty";
		$last_processed_id = (int) isset( $job_data->last_processed_id ) && ! empty( $job_data->last_processed_id ) ? $job_data->last_processed_id : 0;
		global $wpdb;
		$where        = $wpdb->prepare( " WHERE id > %d ", array( (int) $last_processed_id ) );
		$limit_offset = "";
		if ( isset( $job_data->limit ) && ( $job_data->limit > 0 ) ) {
			$limit_offset .= $wpdb->prepare( " LIMIT %d OFFSET %d ", array( (int) $job_data->limit, 0 ) );
		}
		$select = " SELECT * FROM " . $wpdb->prefix . "wlpr_points ";
		$query  = $select . $where . " ORDER BY id ASC " . $limit_offset;
		$users  = $wpdb->get_results( stripslashes( $query ) );
		$this->migrateUsers( $users, $job_id, $job_data, $admin_mail, $action_type );
	}

	function migrateUsers( $users, $job_id, $data, $admin_mail, $action_type ) {
		$migration_job_model = new ScheduledJobs();
		$migration_log_model = new MigrationLog();
		$data_logs           = array(
			'job_id' => $job_id,
		);
		if ( count( $users ) == 0 ) {
			$data_logs['action'] = 'wlpr_migration_completed';
			$data_logs['note']   = __( 'No available records for processing.', 'wp-loyalty-migration' );
			$migration_log_model->saveLogs( $data_logs, "wlpr_migration" );
			$update_data = array(
				"status" => "completed",
			);
			$migration_job_model->updateRow( $update_data, array( "uid" => $job_id, 'source_app' => 'wlr_migration' ) );

			return;
		}
		$loyalty_user_model = new Users();
		$campaign           = EarnCampaign::getInstance();
		$helper_base        = new \Wlr\App\Helpers\Base();
		$conditions         = isset( $data->conditions ) && ! empty( $data->conditions ) ? json_decode( $data->conditions, true ) : array();
		foreach ( $users as $user ) {
			$user_email = ! empty( $user ) && is_object( $user ) && isset( $user->user_email ) && ! empty( $user->user_email ) ? $user->user_email : "";
			if ( empty( $user_email ) ) {
				continue;
			}
			$user_id    = ! empty( $user ) && is_object( $user ) && isset( $user->id ) && ! empty( $user->id ) ? $user->id : 0;
			$new_points = (int) ( ! empty( $user->points ) ) ? $user->points : 0;
			//check user exist in loyalty
			$user_points = $loyalty_user_model->getQueryData( array(
				'user_email' => array(
					'operator' => '=',
					'value'    => sanitize_email( $user_email )
				)
			), '*', array(), false, true );
			$created_at  = strtotime( date( "Y-m-d h:i:s" ) );
			if ( is_object( $user_points ) &&
			     (
				     ( isset( $user_points->user_email ) && isset( $conditions['update_point'] ) && $conditions['update_point'] == 'skip' ) ||
				     ( isset( $user_points->is_banned_user ) && $user_points->is_banned_user == 1 && isset( $conditions['update_banned_user'] ) && $conditions['update_banned_user'] == 'skip' )
			     )
			) {
				$data->last_processed_id = $user_id;
				$update_status           = array(
					"status"            => "processing",
					"last_processed_id" => $data->last_processed_id,
					"updated_at"        => $created_at,
				);
				$migration_job_model->updateRow( $update_status, array(
					'uid'        => $job_id,
					'source_app' => 'wlr_migration'
				) );
				continue;
			}
			if ( is_object( $user_points ) && isset( $user_points->refer_code ) ) {
				$refer_code = $user_points->refer_code;
			} else {
				$refer_code = $helper_base->get_unique_refer_code( '', false, $user_email );
			}
			$action_data                = array(
				"user_email"          => $user_email,
				"customer_command"    => $data->comment,
				"points"              => $new_points,
				"referral_code"       => $refer_code,
				"action_process_type" => "earn_point",
				"customer_note"       => sprintf( __( "Added %d %s by site administrator(%s) via WPLoyalty migration", "wp-loyalty-migration" ), $new_points, $campaign->getPointLabel( $new_points ), $admin_mail ),
				"note"                => sprintf( __( "%s customer migrated from Woocommerce Loyalty points and rewards with %d %s by administrator(%s) via WPLoyalty migration", "wp-loyalty-migration" ), $user_email, $new_points, $campaign->getPointLabel( $new_points ), $admin_mail ),
			);
			$trans_type                 = 'credit';
			$wployalty_migration_status = $campaign->addExtraPointAction( $action_type, (int) $new_points, $action_data, $trans_type );
			$data_logs                  = array(
				'job_id'            => $job_id,
				'action'            => 'wlpr_migration',
				'user_email'        => $user_email,
				'referral_code'     => $refer_code,
				'points'            => $new_points,
				'earn_total_points' => $new_points,
				'created_at'        => strtotime( date( "Y-m-d h:i:s" ) ),
			);
			if ( ! $wployalty_migration_status ) {
				$data_logs['action'] = 'wlpr_migration_failed';
			}
			if ( $migration_log_model->saveLogs( $data_logs, "wlpr_migration" ) > 0 ) {
				$data->offset            = $data->offset + 1;
				$data->last_processed_id = $user_id;
				$update_status           = array(
					"status"            => "processing",
					"offset"            => $data->offset,
					"last_processed_id" => $data->last_processed_id,
					"updated_at"        => strtotime( date( "Y-m-d h:i:s" ) ),
				);
				$migration_job_model->updateRow( $update_status, array(
					'uid'        => $job_id,
					'source_app' => 'wlr_migration'
				) );
			}
		}

	}
}