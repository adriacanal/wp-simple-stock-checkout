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
        $variantsPage = new VariantsListPage();
        $importPage   = new VariantsImportPage();
        $settingsPage = new SettingsPage();

        add_menu_page(
            'WP Simple Stock Checkout',
            'Stock Checkout',
            Capabilities::CAP_MANAGE,
            'wpssc',
            [$variantsPage, 'render'],
            'dashicons-cart',
            56
        );

        add_submenu_page(
            'wpssc',
            'Variants',
            'Variants',
            Capabilities::CAP_MANAGE,
            VariantsListPage::PAGE_SLUG,
            [$variantsPage, 'render']
        );

        add_submenu_page(
            'wpssc',
            'Import Variants',
            'Import Variants',
            Capabilities::CAP_MANAGE,
            VariantsImportPage::PAGE_SLUG,
            [$importPage, 'render']
        );

        add_submenu_page(
            'wpssc',
            'Settings',
            'Settings',
            Capabilities::CAP_MANAGE,
            SettingsPage::PAGE_SLUG,
            [$settingsPage, 'render']
        );
    }
}
