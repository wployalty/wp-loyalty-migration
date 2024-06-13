<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlrm\App\Helper;

use Valitron\Validator;

defined( 'ABSPATH' ) or die();

class Validation {
	static function validateInputAlpha( $input ) {
		return preg_replace( "/[^A-Za-z0-9_\-]/", "", $input );
	}

	function validateMigrationData( $post ) {
		$rule_validator = new Validator( $post );
		$labels         = array();
		$labels_fields  = array(
			'migration_action',
			'update_point'
		);
		$this_field     = __( "This field", "wp-loyalty-migration" );
		foreach ( $labels_fields as $label ) {
			$labels[ $label ] = sprintf( __( "%s", "wp-loyalty-migration" ), $this_field );
		}
		$rule_validator->labels( $labels );
		$rule_validator->stopOnFirstFail( false );
		$rule_validator->rule( "required", array(
			'migration_action',
			'update_point'
		) )->message( __( "{field} is required", "wp-loyalty-migration" ) );
		if ( $rule_validator->validate() ) {
			return true;
		} else {
			return $rule_validator->errors();
		}
	}

	function validateSettingsData( $post ) {
		$rule_validator = new Validator( $post );
		$labels         = array();
		$labels_fields  = array(
			'batch_limit',
			'pagination_limit'
		);
		$this_field     = __( "This field", "wp-loyalty-migration" );
		foreach ( $labels_fields as $label ) {
			$labels[ $label ] = sprintf( __( "%s", "wp-loyalty-migration" ), $this_field );
		}
		$rule_validator->labels( $labels );
		$rule_validator->stopOnFirstFail( false );
		$rule_validator->rule( "required", array( 'batch_limit' ) )->message( __( "{field} is required", "wp-loyalty-migration" ) );
		if ( $rule_validator->validate() ) {
			return true;
		} else {
			return $rule_validator->errors();
		}
	}
}