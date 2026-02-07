<?php

namespace WPSSC\Admin\Pages;

use WPSSC\Admin\Tables\StockMovementsListTable;

if (!defined('ABSPATH')) { exit; }

final class StockMovementsListPage
{
    public const PAGE_SLUG = 'wpssc-stock-movements';

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tens permisos per accedir aquí.', 'wp-simple-stock-checkout'));
        }

        $url_new = admin_url('admin.php?page=' . \WPSSC\Admin\Pages\StockMovementsPage::PAGE_SLUG);

        $table = new StockMovementsListTable();
        $table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Stock movements log', 'wp-simple-stock-checkout'); ?></h1>

            <p>
                <a class="button button-primary" href="<?php echo esc_url($url_new); ?>">
                    <?php echo esc_html__('New movement', 'wp-simple-stock-checkout'); ?>
                </a>
            </p>

            <form method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr(\WPSSC\Admin\Pages\StockMovementsListPage::PAGE_SLUG); ?>" />
                <input type="hidden" name="view" value="log" />
                <?php
                $table->search_box(__('Search (variant id)', 'wp-simple-stock-checkout'), 'wpssc-stock-search');
                $table->display();
                ?>
            </form>
        </div>
        <?php
    }
}
