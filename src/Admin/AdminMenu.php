<?php
namespace WPSSC\Admin;

use WPSSC\Admin\Pages\StockMovementsListPage;
use WPSSC\Admin\Pages\StockMovementsPage;
use WPSSC\Admin\Pages\VariantsImportPage;
use WPSSC\Admin\Pages\VariantsListPage;
use WPSSC\Admin\Pages\SettingsPage;
use WPSSC\Security\Capabilities;

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

        // NEW: Stock Movements pages
        $movementsListPage = new StockMovementsListPage();
        $movementsNewPage  = new StockMovementsPage();

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

        // NEW: Stock movements log
        add_submenu_page(
            'wpssc',
            'Stock movements',
            'Stock movements',
            Capabilities::CAP_MANAGE,
            StockMovementsListPage::PAGE_SLUG,
            [$movementsListPage, 'render']
        );

        // NEW: New movement form
        add_submenu_page(
            'wpssc',
            'New stock movement',
            'New movement',
            Capabilities::CAP_MANAGE,
            StockMovementsPage::PAGE_SLUG,
            [$movementsNewPage, 'render']
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

    public function save_settings(): void
    {
        if (!current_user_can(Capabilities::CAP_MANAGE)) {
            wp_die(__('No tens permisos per fer aquesta acció.', 'wp-simple-stock-checkout'));
        }

        $nonce = isset($_POST['_wpssc_nonce']) ? (string) $_POST['_wpssc_nonce'] : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'wpssc_save_settings')) {
            wp_die(__('Security check failed (invalid nonce).', 'wp-simple-stock-checkout'));
        }

        $raw = isset($_POST['wpssc_settings']) && is_array($_POST['wpssc_settings'])
            ? (array) $_POST['wpssc_settings']
            : [];

        $allowed_keys = [
            'reserve_minutes',
            'checkout_url_child',
            'checkout_url_adult',
            'require_email',
            'checkout_url', // compat opcional
        ];

        $clean = [];

        foreach ($raw as $key => $value) {
            $key = sanitize_key((string) $key);

            if (!in_array($key, $allowed_keys, true)) {
                continue;
            }

            if ($key === 'reserve_minutes') {
                $mins = (int) $value;
                $mins = max(1, min(1440, $mins));
                $clean[$key] = $mins;
                continue;
            }

            if ($key === 'require_email') {
                $clean[$key] = ((string)$value === '1' || (string)$value === 'on') ? 1 : 0;
                continue;
            }

            if ($key === 'checkout_url' || $key === 'checkout_url_child' || $key === 'checkout_url_adult') {
                $url = esc_url_raw((string)$value);
                if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                    $url = '';
                }
                $clean[$key] = $url;
                continue;
            }

            $clean[$key] = sanitize_text_field((string)$value);
        }

        // Conserva claus existents que no estiguin al formulari (per no “esborrar” config antiga)
        $existing = \WPSSC\Settings\Settings::all();
        $merged = array_merge($existing, $clean);

        update_option('wpssc_settings', $merged, false);

        $redirect = add_query_arg(
            [
                'page' => SettingsPage::PAGE_SLUG,
                'settings-updated' => '1',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

}
