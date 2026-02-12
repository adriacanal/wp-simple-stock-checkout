<?php
namespace WPSSC;

if (!defined('ABSPATH')) { exit; }

final class DB {

    public static function wpdb(): \wpdb {
        global $wpdb;
        return $wpdb;
    }

    public static function table(string $name): string {
        return self::wpdb()->prefix . 'wpssc_' . $name;
    }

    public static function noMysqlUtc(): string {
        return gmdate('Y-m-d H:i:s');
    }

    public static function create_or_update_schema(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();

        $variants = self::table('variants');
        $orders   = self::table('orders');
        $res      = self::table('reservations');
        $moves    = self::table('stock_movements');

        // Variants (products / SKUs)
        $sql1 = "CREATE TABLE {$variants} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sku VARCHAR(32) NOT NULL,
            model VARCHAR(16) NOT NULL,
            color VARCHAR(32) NOT NULL,
            size VARCHAR(16) NOT NULL,
            price DECIMAL(6,2) NOT NULL,
            stock_total INT NOT NULL DEFAULT 0,
            stock_sold INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY sku (sku),
            KEY model (model),
            KEY is_active (is_active)
        ) {$charset};";

        // Orders (purchase intents)
        $sql2 = "CREATE TABLE {$orders} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_token CHAR(12) NOT NULL,
            email VARCHAR(190) NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'pending',
            total DECIMAL(8,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_token (order_token),
            KEY status (status)
        ) {$charset};";

        // Reservations (temporary stock holds)
        $sql3 = "CREATE TABLE {$res} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            variant_id BIGINT UNSIGNED NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            reserved_until DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY variant_id (variant_id),
            KEY reserved_until (reserved_until)
        ) {$charset};";

        // Stock movements (manual sales, adjustments, returns)
        $sql4 = "CREATE TABLE {$moves} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            variant_id BIGINT UNSIGNED NULL,
            sku VARCHAR(32) NULL,
            quantity_delta INT NOT NULL,
            reason VARCHAR(64) NOT NULL,
            note VARCHAR(255) NULL,
            user_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY variant_id (variant_id),
            KEY sku (sku),
            KEY created_at (created_at)
        ) {$charset};";

        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        dbDelta($sql4);
    }
}
