<?php
namespace WPSSC\Admin\Tables;

use WP_List_Table;
use WPSSC\DB;

if (!defined('ABSPATH')) { exit; }

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class VariantsListTable extends WP_List_Table {

    public function get_columns(): array {
        return [
            'sku'         => 'SKU',
            'model'       => 'Model',
            'color'       => 'Color',
            'size'        => 'Size',
            'price'       => 'Price',
            'stock_total' => 'Stock',
            'is_active'   => 'Active',
        ];
    }

    public function prepare_items(): void {
        global $wpdb;

        $table = DB::table('variants');

        $items = $wpdb->get_results(
            "SELECT sku, model, color, size, price, stock_total, is_active
             FROM {$table}
             ORDER BY sku ASC",
            ARRAY_A
        );

        $this->items = $items ?: [];

        $this->_column_headers = [
            $this->get_columns(),
            [],
            [],
        ];
    }

    protected function column_default($item, $column_name) {
        return esc_html((string)($item[$column_name] ?? ''));
    }

    protected function column_is_active($item): string {
        return $item['is_active']
            ? '<span style="color:green;font-weight:bold;">Yes</span>'
            : '<span style="color:#999;">No</span>';
    }

    protected function column_price($item): string {
        return number_format((float)$item['price'], 2) . ' €';
    }
}
