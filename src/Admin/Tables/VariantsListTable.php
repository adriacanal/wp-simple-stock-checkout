<?php
namespace WPSSC\Admin\Tables;

use WP_List_Table;
use WPSSC\DB;

if (!defined('ABSPATH')) { exit; }

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class VariantsListTable extends WP_List_Table
{
    public function get_columns(): array
    {
        return [
            'sku'         => __('SKU', 'wp-simple-stock-checkout'),
            'model'       => __('Model', 'wp-simple-stock-checkout'),
            'color'       => __('Color', 'wp-simple-stock-checkout'),
            'size'        => __('Size', 'wp-simple-stock-checkout'),
            'price'       => __('Price', 'wp-simple-stock-checkout'),
            'stock_total' => __('Stock total', 'wp-simple-stock-checkout'),
            'is_active'   => __('Status', 'wp-simple-stock-checkout'),
        ];
    }

    protected function get_default_primary_column_name(): string
    {
        return 'sku';
    }

    public function prepare_items(): void
    {
        global $wpdb;

        $table = DB::table('variants');

        // IMPORTANT: seleccionem id i is_active
        $items = $wpdb->get_results(
            "SELECT id, sku, model, color, size, price, stock_total, is_active
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

    public function column_sku($item): string
    {
        $sku = esc_html((string)($item['sku'] ?? ''));
        $id  = (int)($item['id'] ?? 0);

        $is_active = (int)($item['is_active'] ?? 0) === 1;

        if ($id < 1) {
            return $sku; // fallback
        }

        $to = $is_active ? '0' : '1';
        $label = $is_active ? __('Deactivate', 'wp-simple-stock-checkout') : __('Activate', 'wp-simple-stock-checkout');

        $url = add_query_arg(
            [
                'action'      => 'wpssc_toggle_variant',
                'variant_id'  => $id,
                'to'          => $to,
                '_wpssc_nonce'=> wp_create_nonce('wpssc_toggle_variant'),
            ],
            admin_url('admin-post.php')
        );

        $actions = [
            'toggle' => '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>',
        ];

        return $sku . $this->row_actions($actions);
    }

    public function column_is_active($item): string
    {
        $is_active = (int)($item['is_active'] ?? 0) === 1;

        return $is_active
            ? '<span style="color:#1d7f1d;">' . esc_html__('Active', 'wp-simple-stock-checkout') . '</span>'
            : '<span style="color:#a00;">' . esc_html__('Inactive', 'wp-simple-stock-checkout') . '</span>';
    }

    protected function column_price($item): string
    {
        $price = isset($item['price']) ? (float)$item['price'] : 0.0;
        return number_format($price, 2) . ' €';
    }

    // Per les columnes que no tenen mètode column_{name}, WP_List_Table farà servir això
    public function column_default($item, $column_name)
    {
        $value = $item[$column_name] ?? '';
        return esc_html((string)$value);
    }
}
