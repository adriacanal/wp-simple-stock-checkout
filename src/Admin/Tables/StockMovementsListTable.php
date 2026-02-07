<?php

namespace WPSSC\Admin\Tables;

use WPSSC\Repositories\StockMovementRepository;

if (!defined('ABSPATH')) { exit; }

if (!class_exists('\WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class StockMovementsListTable extends \WP_List_Table
{
    private StockMovementRepository $repo;

    public function __construct()
    {
        parent::__construct([
            'singular' => 'stock_movement',
            'plural'   => 'stock_movements',
            'ajax'     => false,
        ]);

        $this->repo = new StockMovementRepository();
    }

    public function get_columns(): array
    {
        return [
            'id'            => 'ID',
            'created_at'    => __('Date', 'wp-simple-stock-checkout'),
            'variant_id'    => __('Variant', 'wp-simple-stock-checkout'),
            'movement_type' => __('Type', 'wp-simple-stock-checkout'),
            'qty'           => __('Qty', 'wp-simple-stock-checkout'),
            'note'          => __('Note', 'wp-simple-stock-checkout'),
            'created_by'    => __('User', 'wp-simple-stock-checkout'),
        ];
    }

    public function prepare_items(): void
    {
        $per_page = 20;
        $page = $this->get_pagenum();

        $filters = [];

        $search = isset($_REQUEST['s']) ? sanitize_text_field((string) $_REQUEST['s']) : '';
        if ($search !== '' && ctype_digit($search)) {
            $filters['variant_id'] = (int) $search;
        }

        if (isset($_GET['variant_id']) && ctype_digit((string) $_GET['variant_id'])) {
            $filters['variant_id'] = (int) $_GET['variant_id'];
        }
        if (!empty($_GET['movement_type'])) {
            $filters['movement_type'] = sanitize_key((string) $_GET['movement_type']);
        }

        $total = $this->repo->count($filters);
        $rows = $this->repo->list($filters, $page, $per_page);

        $this->items = $rows;

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        ]);
    }

    public function column_default($item, $column_name)
    {
        $val = $item[$column_name] ?? '';
        return esc_html((string) $val);
    }

    protected function get_table_classes()
    {
        $classes = parent::get_table_classes();
        $classes[] = 'widefat';
        $classes[] = 'striped';
        return $classes;
    }
}
