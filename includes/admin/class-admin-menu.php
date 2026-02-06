<?php
namespace WPSSC\Admin;

use WPSSC\Capabilities;
use WPSSC\Settings;

if (!defined('ABSPATH')) { exit; }

final class AdminMenu {

    public function init(): void {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_wpssc_save_settings', [$this, 'save_settings']);
    }

    public function register_menu(): void {
        add_menu_page(
            'WP Simple Stock Checkout',
            'Stock Checkout',
            Capabilities::CAP_MANAGE,
            'wpssc',
            [$this, 'render_settings'],
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'wpssc',
            'Settings',
            'Settings',
            Capabilities::CAP_MANAGE,
            SettingsPage::PAGE_SLUG,
            [new SettingsPage(), 'render']
        );

        add_submenu_page(
            'wpssc',
            'Import Variants',
            'Import Variants',
            Capabilities::CAP_MANAGE,
            VariantsImportPage::PAGE_SLUG,
            [new VariantsImportPage(), 'render']
        );

    }
}
