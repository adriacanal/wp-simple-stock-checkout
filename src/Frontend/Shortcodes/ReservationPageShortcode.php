<?php

namespace WPSSC\Frontend\Shortcodes;

use WPSSC\DB;
use WPSSC\Settings\Settings;
use WPSSC\Repositories\ReservationRepository;

if (!defined('ABSPATH')) { exit; }

final class ReservationPageShortcode
{
    public function render($atts = []): string
    {
        $token = isset($_GET['token']) ? sanitize_text_field((string)$_GET['token']) : '';
        if ($token === '') {
            return '<div class="wpssc-notice wpssc-notice-error">' . esc_html__('Missing token.', 'wp-simple-stock-checkout') . '</div>';
        }

        $repo = new ReservationRepository();

        // Expira si cal (allibera stock_reserved)
        try {
            $repo->expire_if_needed($token);
        } catch (\Throwable $e) {
            return '<div class="wpssc-notice wpssc-notice-error">' . esc_html($e->getMessage()) . '</div>';
        }

        $order = $repo->get_order_by_token($token);
        if (!$order) {
            return '<div class="wpssc-notice wpssc-notice-error">' . esc_html__('Order not found.', 'wp-simple-stock-checkout') . '</div>';
        }

        $status = (string)$order['status'];
        $reserved_until = (string)$order['reserved_until'];

        $base_checkout = isset($order['checkout_url']) ? esc_url_raw((string)$order['checkout_url']) : '';

        if ($base_checkout === '') {
            $base_checkout = Settings::checkout_url('child');
        }

        $checkout_link = '';
        if ($base_checkout !== '') {
            $checkout_link = add_query_arg(['token' => $token], $base_checkout);
        }

        $items = isset($order['items']) && is_array($order['items']) ? $order['items'] : [];

        $variant = null;
        if (!empty($items)) {
            $variant_id = (int)$items[0]['variant_id'];
            $variant = $this->get_variant($variant_id);
        }

        ob_start();
        ?>
        <div class="wpssc-reservation">
            <h2><?php echo esc_html__('Reservation', 'wp-simple-stock-checkout'); ?></h2>

            <p><strong><?php echo esc_html__('Status:', 'wp-simple-stock-checkout'); ?></strong> <?php echo esc_html($status); ?></p>

            <?php if ($status === ReservationRepository::STATUS_RESERVED): ?>
                <p>
                    <strong><?php echo esc_html__('Reserved until:', 'wp-simple-stock-checkout'); ?></strong>
                    <?php echo esc_html($reserved_until); ?> (UTC)
                </p>
            <?php endif; ?>

            <?php if ($variant): ?>
                <h3><?php echo esc_html__('Order summary', 'wp-simple-stock-checkout'); ?></h3>
                <ul>
                    <li><?php echo esc_html($variant['sku'] . ' — ' . $variant['model'] . ' ' . $variant['color'] . ' ' . $variant['size']); ?></li>
                    <li><?php echo esc_html__('Quantity:', 'wp-simple-stock-checkout'); ?> <?php echo (int)$items[0]['qty']; ?></li>
                    <?php if ($items[0]['unit_price'] !== null): ?>
                        <li><?php echo esc_html__('Unit price:', 'wp-simple-stock-checkout'); ?> <?php echo esc_html(number_format((float)$items[0]['unit_price'], 2)); ?>€</li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>

            <?php if ($status === ReservationRepository::STATUS_RESERVED): ?>
                <?php if ($checkout_link !== ''): ?>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url($checkout_link); ?>" rel="noopener noreferrer">
                            <?php echo esc_html__('Proceed to payment', 'wp-simple-stock-checkout'); ?>
                        </a>
                    </p>
                    <p class="description">
                        <?php echo esc_html__('You will be redirected to the external checkout.', 'wp-simple-stock-checkout'); ?>
                    </p>
                <?php else: ?>
                    <div class="wpssc-notice wpssc-notice-warning">
                        <?php echo esc_html__('Checkout URL is not configured. Please contact the AFA.', 'wp-simple-stock-checkout'); ?>
                    </div>
                <?php endif; ?>
            <?php elseif ($status === ReservationRepository::STATUS_EXPIRED): ?>
                <div class="wpssc-notice wpssc-notice-error">
                    <?php echo esc_html__('This reservation has expired. Please start again.', 'wp-simple-stock-checkout'); ?>
                </div>
            <?php elseif ($status === ReservationRepository::STATUS_PAID): ?>
                <div class="wpssc-notice wpssc-notice-success">
                    <?php echo esc_html__('Payment confirmed. Thank you!', 'wp-simple-stock-checkout'); ?>
                </div>
            <?php else: ?>
                <div class="wpssc-notice wpssc-notice-warning">
                    <?php echo esc_html__('This order is not available for payment.', 'wp-simple-stock-checkout'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string)ob_get_clean();
    }

    private function get_variant(int $variant_id): ?array
    {
        global $wpdb;
        $t = DB::table('variants');

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT id, sku, model, color, size, price FROM {$t} WHERE id=%d LIMIT 1", $variant_id),
            ARRAY_A
        );

        return $row ?: null;
    }
}
