<?php

namespace Wlrm\App\Controller\Compatibles;

defined('ABSPATH') or die();

interface Base
{
    static function checkPluginIsActive();

    static function getMigrationJob();

    function migrateToLoyalty($job_data);

}