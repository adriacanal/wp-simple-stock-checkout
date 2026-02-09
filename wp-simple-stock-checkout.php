<?php
/**
 * Plugin Name: WP Simple Stock Checkout
 * Description: Generic limited-stock & reservation system with external checkout redirection (no WooCommerce).
 * Version: 0.1.0
 * Author: Adrià Canal
 * License: GPLv2 or later
 * Text Domain: wp-simple-stock-checkout
 */

if (!defined('ABSPATH')) { exit; }

define('WPSSC_VERSION', '0.1.0');
define('WPSSC_PLUGIN_FILE', __FILE__);
define('WPSSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPSSC_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WPSSC_PLUGIN_DIR . 'vendor/autoload.php';

add_action('plugins_loaded', function () {
    \WPSSC\Plugin::instance()->init();
});

register_activation_hook(__FILE__, function () {
    \WPSSC\Activator::activate();

    // Ensure DB schema for stock movements (safe no-op if already exists)
    if (class_exists('\WPSSC\Migrations\StockMovementsMigration')) {
        \WPSSC\Migrations\StockMovementsMigration::install();
    }

    if (class_exists('\WPSSC\Migrations\ReservationsMigration')) {
        \WPSSC\Migrations\ReservationsMigration::install();
    }

});

register_deactivation_hook(__FILE__, function () {
    \WPSSC\Deactivator::deactivate();
});
