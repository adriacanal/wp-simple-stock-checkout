<?php

namespace WPSSC\Admin\Pages;

use WPSSC\Domain\StockMovement;
use WPSSC\Repositories\StockMovementRepository;

if (!defined('ABSPATH')) { exit; }

final class StockMovementsPage
{
    public const PAGE_SLUG = 'wpssc-stock-movement-new';
    private const NONCE_ACTION = 'wpssc_save_stock_movement';
    private const NONCE_NAME = '_wpssc_nonce';

    public function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('No tens permisos per accedir aquí.', 'wp-simple-stock-checkout'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $result = $this->handle_post();
            if ($result === true) {
                add_settings_error('wpssc_stock', 'saved', __('Movement saved', 'wp-simple-stock-checkout'), 'updated');
            } else {
                add_settings_error('wpssc_stock', 'error', $result, 'error');
            }
        }

        settings_errors('wpssc_stock');

        $url_log = admin_url('admin.php?page=' . \WPSSC\Admin\Pages\StockMovementsListPage::PAGE_SLUG);
        $url_new = admin_url('admin.php?page=' . \WPSSC\Admin\Pages\StockMovementsPage::PAGE_SLUG);


        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Manual sale / Stock movement', 'wp-simple-stock-checkout'); ?></h1>

            <p>
                <a class="button" href="<?php echo esc_url($url_log); ?>"><?php echo esc_html__('View log', 'wp-simple-stock-checkout'); ?></a>
                <a class="button button-primary" href="<?php echo esc_url($url_new); ?>"><?php echo esc_html__('New movement', 'wp-simple-stock-checkout'); ?></a>
            </p>

            <form method="post">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="variant_id"><?php echo esc_html__('Variant ID', 'wp-simple-stock-checkout'); ?></label></th>
                        <td>
                            <input name="variant_id" id="variant_id" type="number" min="1" required class="regular-text" />
                            <p class="description"><?php echo esc_html__('ID de la fila a la taula variants.', 'wp-simple-stock-checkout'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="movement_type"><?php echo esc_html__('Movement type', 'wp-simple-stock-checkout'); ?></label></th>
                        <td>
                            <select name="movement_type" id="movement_type" required>
                                <option value="<?php echo esc_attr(StockMovement::TYPE_MANUAL_SALE); ?>">
                                    <?php echo esc_html__('Manual sale (parada) → stock_sold + qty', 'wp-simple-stock-checkout'); ?>
                                </option>
                                <option value="<?php echo esc_attr(StockMovement::TYPE_ADJUSTMENT); ?>">
                                    <?php echo esc_html__('Adjustment → stock_total +/- qty', 'wp-simple-stock-checkout'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="qty"><?php echo esc_html__('Quantity', 'wp-simple-stock-checkout'); ?></label></th>
                        <td>
                            <input name="qty" id="qty" type="number" required class="regular-text" />
                            <p class="description">
                                <?php echo esc_html__('Manual sale: qty > 0. Adjustment: pot ser + o -.', 'wp-simple-stock-checkout'); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="note"><?php echo esc_html__('Note', 'wp-simple-stock-checkout'); ?></label></th>
                        <td>
                            <textarea name="note" id="note" rows="3" class="large-text"></textarea>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save movement', 'wp-simple-stock-checkout')); ?>
            </form>
        </div>
        <?php
    }

    private function handle_post()
    {
        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce((string) $_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return __('Security check failed.', 'wp-simple-stock-checkout');
        }

        $variant_id = isset($_POST['variant_id']) ? (int) $_POST['variant_id'] : 0;
        $movement_type = isset($_POST['movement_type']) ? sanitize_key((string) $_POST['movement_type']) : '';
        $qty = isset($_POST['qty']) ? (int) $_POST['qty'] : 0;
        $note = isset($_POST['note']) ? sanitize_textarea_field((string) $_POST['note']) : null;

        if ($variant_id < 1) return __('Invalid variant ID.', 'wp-simple-stock-checkout');
        if ($qty === 0) return __('Quantity cannot be 0.', 'wp-simple-stock-checkout');

        try {
            $movement = new StockMovement($variant_id, $movement_type, $qty, $note, get_current_user_id());
            (new StockMovementRepository())->create_and_apply($movement);
            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }
}
