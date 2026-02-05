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

// Core required files
require_once WPSSC_PLUGIN_DIR . 'includes/class-plugin.php';
require_once WPSSC_PLUGIN_DIR . 'includes/class-activator.php';
require_once WPSSC_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once WPSSC_PLUGIN_DIR . 'includes/class-db.php';

// Optional (safe) includes — add as you create them
$optional = [
    'includes/class-settings.php',
    'includes/class-capabilities.php',

    // Admin
    'includes/admin/class-admin.php',
    'includes/admin/class-admin-menu.php',
];

foreach ($optional as $rel) {
    $path = WPSSC_PLUGIN_DIR . $rel;
    if (file_exists($path)) {
        require_once $path;
    }
}


add_action('plugins_loaded', function () {
    \WPSSC\Plugin::instance()->init();
});

register_activation_hook(__FILE__, function () {
    \WPSSC\Activator::activate();
});

register_deactivation_hook(__FILE__, function () {
    \WPSSC\Deactivator::deactivate();
});
