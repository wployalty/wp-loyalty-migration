<?php

namespace Wlrm\App\Controller\Compatibles;

use Wlrm\App\Models\MigrationJob;

class WLPRPointsRewards implements Base
{

    static function checkPluginIsActive()
    {
        $active_plugins = apply_filters('active_plugins', get_option('active_plugins', array()));
        if (is_multisite()) {
            $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
        }
        return in_array('loyalty-points-rewards/wp-loyalty-points-rewards.php', $active_plugins, false);
    }

    static function getMigrationJob()
    {
        $job_table = new MigrationJob();
        $job = $job_table->getWhere("action = 'wlpr_migration'");
        return (!empty($job) && is_object($job) && isset($job->uid)) ? $job : new \stdClass();
    }

    function migrateToLoyalty($job_data)
    {
        // TODO: Implement migrateToLoyalty() method.
    }
}