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

add_action('plugins_loaded', function () {
    // Només per confirmar que el plugin carrega.
});
