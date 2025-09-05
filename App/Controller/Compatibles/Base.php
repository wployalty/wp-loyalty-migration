<?php
/**
 * @author      Wployalty (Ilaiyaraja)
 * @license     http://www.gnu.org/licenses/gpl-2.0.html
 * @link        https://www.wployalty.net
 * */

namespace Wlrmg\App\Controller\Compatibles;

defined( 'ABSPATH' ) or die();

interface Base {
	static function checkPluginIsActive();

	static function getMigrationJob();

	function migrateToLoyalty( $job_data );

}