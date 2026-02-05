<?php
/**
 * Plugin Name: WP Simple Stock Checkout
 * Description: Generic limited-stock & reservation system with external checkout redirection (no WooCommerce).
 * Version: 0.1.0
 * Author: Open Source Community
 * License: GPLv2 or later
 * Text Domain: wp-simple-stock-checkout
 */

if (!defined('ABSPATH')) { exit; }

define('WPSSC_VERSION', '0.1.0');
define('WPSSC_PLUGIN_FILE', __FILE__);
define('WPSSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPSSC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Simple autoloader for WPSSC namespace
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'WPSSC\\') !== 0) {
        return;
    }

    $relative = str_replace('WPSSC\\', '', $class);
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);

    $file = WPSSC_PLUGIN_DIR . 'includes/class-' . strtolower($relative) . '.php';

    if (!file_exists($file)) {
        $file = WPSSC_PLUGIN_DIR . 'includes/' . strtolower($relative) . '.php';
    }

    if (file_exists($file)) {
        require_once $file;
    }
});

require_once WPSSC_PLUGIN_DIR . 'includes/class-plugin.php';
require_once WPSSC_PLUGIN_DIR . 'includes/class-activator.php';
require_once WPSSC_PLUGIN_DIR . 'includes/class-deactivator.php';

add_action('plugins_loaded', function () {
    \WPSSC\Plugin::instance()->init();
});

register_activation_hook(__FILE__, function () {
    \WPSSC\Activator::activate();
});

register_deactivation_hook(__FILE__, function () {
    \WPSSC\Deactivator::deactivate();
});
