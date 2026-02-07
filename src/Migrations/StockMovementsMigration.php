<?php

namespace WPSSC\Migrations;

use WPSSC\DB;

if (!defined('ABSPATH')) { exit; }

final class StockMovementsMigration
{
    public static function install(): void
    {
        self::ensureStockSoldColumn();
        self::createMovementsTable();
    }

    private static function ensureStockSoldColumn(): void
    {
        global $wpdb;

        $variants = DB::table('variants');

        // Comprovem si existeix la columna stock_sold
        $col = $wpdb->get_var(
            $wpdb->prepare("SHOW COLUMNS FROM {$variants} LIKE %s", 'stock_sold')
        );

        if ($col) {
            return;
        }

        // Afegim stock_sold amb default 0
        $wpdb->query("ALTER TABLE {$variants} ADD COLUMN stock_sold INT NOT NULL DEFAULT 0 AFTER stock_total");

        // (Opcional) sanity: stock_sold no pot superar stock_total
        $wpdb->query("UPDATE {$variants} SET stock_sold = stock_total WHERE stock_sold > stock_total");
    }

    private static function createMovementsTable(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = DB::table('stock_movements'); // taula: {prefix}stock_movements
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            variant_id BIGINT UNSIGNED NOT NULL,
            movement_type VARCHAR(32) NOT NULL,
            qty INT NOT NULL,
            note TEXT NULL,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            applied TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY variant_created (variant_id, created_at),
            KEY type_created (movement_type, created_at)
        ) {$charset};";

        dbDelta($sql);
    }
}
