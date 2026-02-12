<?php
namespace WPSSC;

use WPSSC\Cron\Cron;

if (!defined('ABSPATH')) { exit; }

final class Deactivator {
    public static function deactivate(): void {
        if (class_exists('\WPSSC\Cron\Cron')) {
            Cron::clear_scheduled();
        }
    }
}
