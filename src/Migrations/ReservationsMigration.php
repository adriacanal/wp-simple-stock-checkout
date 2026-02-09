<?php

namespace WPSSC\Migrations;

use WPSSC\DB;

if (!defined('ABSPATH')) { exit; }

final class ReservationsMigration
{
    public static function install(): void
    {
        self::ensureVariantsReservedColumn();
        self::createOrdersTable();
        self::createOrderItemsTable();
    }

    private static function ensureVariantsReservedColumn(): void
    {
        global $wpdb;
        $variants = DB::table('variants');

        $col = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$variants} LIKE %s", 'stock_reserved')
        );

        if ($col) {
            return;
        }

        // Afegim stock_reserved amb default 0 (després de stock_sold si existeix; sinó al final)
        $wpdb->query("ALTER TABLE {$variants} ADD COLUMN stock_reserved INT NOT NULL DEFAULT 0");

        // Sanity: no deixem valors negatius
        $wpdb->query("UPDATE {$variants} SET stock_reserved = 0 WHERE stock_reserved < 0");
    }

    private static function createOrdersTable(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t = DB::table('orders');
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$t} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token CHAR(36) NOT NULL,
            email VARCHAR(190) NOT NULL,
            status VARCHAR(32) NOT NULL,
            reserved_until DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            checkout_url TEXT NULL,
            meta LONGTEXT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token_unique (token),
            KEY status_created (status, created_at),
            KEY reserved_until (reserved_until)
        ) {$charset};";

        dbDelta($sql);
    }

    private static function createOrderItemsTable(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $t = DB::table('order_items');
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$t} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            variant_id BIGINT UNSIGNED NOT NULL,
            qty INT NOT NULL,
            unit_price DECIMAL(10,2) NULL,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY variant_id (variant_id)
        ) {$charset};";

        dbDelta($sql);
    }
}
