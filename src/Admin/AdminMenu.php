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

        // Nonce
        $nonce = isset($_POST['_wpssc_nonce']) ? (string) $_POST['_wpssc_nonce'] : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'wpssc_save_settings')) {
            wp_die(__('Security check failed (invalid nonce).', 'wp-simple-stock-checkout'));
        }

        // Recollim dades del formulari.
        // Recomanació: al SettingsPage, agrupa els camps sota name="wpssc_settings[...]" per evitar col·lisions.
        $raw = isset($_POST['wpssc_settings']) && is_array($_POST['wpssc_settings'])
            ? (array) $_POST['wpssc_settings']
            : [];

        // Permet només claus conegudes (whitelist).
        // TODO: omple aquesta llista amb les teves keys reals quan les tinguis tancades.
        // Mentrestant, pots deixar-la buida i acceptar totes les claus (no recomanat).
        $allowed_keys = [
            // Exemples típics (substitueix/afegeix les teves):
            'checkout_url',
            'reservation_minutes',
            'cron_interval_minutes',
            'admin_notice_email',
            'is_debug',
        ];

        $clean = [];

        foreach ($raw as $key => $value) {
            $key = sanitize_key((string) $key);

            // Si vols fer-ho estricte: ignora claus no permeses
            if (!empty($allowed_keys) && !in_array($key, $allowed_keys, true)) {
                continue;
            }

            // Sanitització per tipus (heurística segura)
            if (is_array($value)) {
                // Evitem arrays profunds per seguretat; si en vols, ho especifiquem explícitament
                $clean[$key] = array_map('sanitize_text_field', $value);
                continue;
            }

            $value = (string) $value;

            // Heurístiques per key
            if (str_contains($key, 'url')) {
                $clean[$key] = esc_url_raw($value);
            } elseif (str_contains($key, 'email')) {
                $clean[$key] = sanitize_email($value);
            } elseif (str_contains($key, 'minutes') || str_contains($key, 'qty') || str_contains($key, 'limit')) {
                $clean[$key] = (int) $value;
            } elseif (str_starts_with($key, 'is_') || str_starts_with($key, 'enable_')) {
                // checkboxes: si no ve, normalment és false; aquí només processem els que han vingut
                $clean[$key] = $value === '1' || $value === 'on' ? 1 : 0;
            } else {
                $clean[$key] = sanitize_text_field($value);
            }
        }

        // Checkboxes “off”: si al formulari tens checkboxes dins wpssc_settings, quan no es marquen no venen al POST.
        // Si tens flags, és millor posar hidden 0 + checkbox 1. Si no ho tens, pots forçar defaults aquí.
        // Exemple:
        // if (!isset($raw['is_debug'])) $clean['is_debug'] = 0;

        update_option('wpssc_settings', $clean, false);

        // Redirecció a la pàgina Settings
        $settings_slug = defined('\WPSSC\Admin\SettingsPage::PAGE_SLUG')
            ? SettingsPage::PAGE_SLUG
            : 'wpssc-settings';

        $redirect = add_query_arg(
            [
                'page' => $settings_slug,
                'settings-updated' => '1',
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($redirect);
        exit;
    }

}
