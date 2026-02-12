<?php
namespace WPSSC;

use WPSSC\Settings\Settings;
use WPSSC\Security\Capabilities;
use WPSSC\Cron\Cron;

if (!defined('ABSPATH')) { exit; }

final class Activator {
    public static function activate(): void {
        DB::create_or_update_schema();

        if (class_exists('\WPSSC\Cron\Cron')) {
            Cron::ensure_scheduled();
        }

        Settings::add_defaults();
        Capabilities::add_caps();
    }
}
