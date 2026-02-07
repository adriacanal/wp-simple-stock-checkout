<?php
namespace WPSSC;

use WPSSC\Settings\Settings;
use WPSSC\Security\Capabilities;

if (!defined('ABSPATH')) { exit; }

final class Activator {
    public static function activate(): void {
        DB::create_or_update_schema();
        Settings::add_defaults();
        Capabilities::add_caps();
    }
}
