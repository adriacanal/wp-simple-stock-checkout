<?php
namespace WPSSC;

if (!defined('ABSPATH')) { exit; }

final class Activator {
    public static function activate(): void {
        DB::create_or_update_schema();
        Settings::add_defaults();
        Capabilities::add_caps();
    }
}
