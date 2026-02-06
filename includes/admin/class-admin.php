<?php
namespace WPSSC\Admin;

if (!defined('ABSPATH')) { exit; }

final class Admin {
    public function init(): void {
        if (class_exists('\\WPSSC\\Admin\\AdminMenu')) {
            (new AdminMenu())->init();
        }
        if (class_exists('\\WPSSC\\Admin\\SettingsPage')) {
            (new SettingsPage())->init();
        }
        if (class_exists('\\WPSSC\\Admin\\VariantsImportPage')) {
            (new VariantsImportPage())->init();
        }
    }
}
