<?php

namespace WPSSC\Frontend\Shortcodes;

use WPSSC\DB;
use WPSSC\Repositories\ReservationRepository;
use WPSSC\Settings\Settings;

if (!defined('ABSPATH')) { exit; }

final class ReserveFormShortcode
{
    private const NONCE_ACTION = 'wpssc_reserve_submit';
    private const NONCE_NAME   = '_wpssc_nonce';

    /**
     * Heurística per detectar "adult" segons model/sku.
     * Pots ampliar aquestes paraules quan vulguis.
     */
    private const ADULT_KEYWORDS = [
        'adult', 'adults', 'adulta', 'adulto',
        'man', 'men', 'woman', 'women',
        'unisex adult', 'adult unisex',
    ];

    public function render($atts = []): string
    {
        $atts = shortcode_atts([
            // On redirigir després de reservar (recomanat: pàgina on tens [wpssc_reservation])
            // Ex: success_page="/reserve/"
            'success_page' => '',
        ], $atts);

        $error = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpssc_reserve_submit'])) {
            $result = $this->handle_post($atts);
            if ($result !== true) $error = $result;
        }

        $variants = $this->get_active_variants();

        ob_start();

        if ($error) {
            echo '<div class="wpssc-notice wpssc-notice-error">' . esc_html($error) . '</div>';
        }

        ?>
        <form method="post">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <input type="hidden" name="wpssc_reserve_submit" value="1" />

            <p>
                <label>
                    <?php echo esc_html__('Variant', 'wp-simple-stock-checkout'); ?><br/>
                    <select name="variant_id" required>
                        <option value=""><?php echo esc_html__('Choose…', 'wp-simple-stock-checkout'); ?></option>
                        <?php foreach ($variants as $v): ?>
                            <?php
                            $label = sprintf(
                                '%s — %s %s (%s) — %s€ — %d disponibles',
                                $v['sku'],
                                $v['model'],
                                $v['color'],
                                $v['size'],
                                number_format((float)$v['price'], 2),
                                (int)$v['available']
                            );
                            ?>
                            <option value="<?php echo (int)$v['id']; ?>">
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </p>

            <p>
                <label>
                    <?php echo esc_html__('Quantity', 'wp-simple-stock-checkout'); ?><br/>
                    <input type="number" name="qty" min="1" value="1" required />
                </label>
            </p>

            <?php if (Settings::require_email()): ?>
                <p>
                    <label>
                        <?php echo esc_html__('Email', 'wp-simple-stock-checkout'); ?><br/>
                        <input type="email" name="email" required />
                    </label>
                </p>
            <?php else: ?>
                <!-- si no requereixes email, el passem buit -->
                <input type="hidden" name="email" value="" />
            <?php endif; ?>

            <p>
                <button type="submit"><?php echo esc_html__('Reserve', 'wp-simple-stock-checkout'); ?></button>
            </p>
        </form>
        <?php

        return (string) ob_get_clean();
    }

    private function handle_post(array $atts)
    {
        $nonce = isset($_POST[self::NONCE_NAME]) ? (string)$_POST[self::NONCE_NAME] : '';
        if (!$nonce || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return __('Security check failed.', 'wp-simple-stock-checkout');
        }

        // Rate limit bàsic per IP (evita bots que bloquegin stock)
        if (!$this->rate_limit_ok()) {
            return __('Too many attempts. Please try again in a few minutes.', 'wp-simple-stock-checkout');
        }

        $variant_id = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $qty        = isset($_POST['qty']) ? (int)$_POST['qty'] : 0;

        // Email pot ser opcional segons settings
        $email_raw  = isset($_POST['email']) ? (string)$_POST['email'] : '';
        $email      = sanitize_email($email_raw);

        if ($variant_id < 1) return __('Invalid variant.', 'wp-simple-stock-checkout');
        if ($qty < 1) return __('Invalid quantity.', 'wp-simple-stock-checkout');

        if (Settings::require_email()) {
            if (!is_email($email)) return __('Invalid email.', 'wp-simple-stock-checkout');
        } else {
            // si no cal, fem servir un placeholder intern (opcional) o buit
            $email = $email !== '' && is_email($email) ? $email : 'no-email@local';
        }

        $ttl = Settings::reservation_minutes();

        // IMPORTANT: el tipus (child/adult) el deduïm de la variant real a DB (no del POST)
        $variant = $this->get_variant($variant_id);
        if (!$variant) {
            return __('Variant not found.', 'wp-simple-stock-checkout');
        }

        $type = $this->infer_checkout_type($variant); // 'child' | 'adult'

        // Si tens una URL única 'checkout_url' (compat), Settings::checkout_url() ja la prioritzarà.
        $checkout_url = Settings::checkout_url($type);
        if ($checkout_url === '') {
            $checkout_url = null;
        }

        try {
            $repo = new ReservationRepository();
            $token = $repo->create_reservation($variant_id, $qty, $email, $ttl, $checkout_url);

            $target = $this->get_reservation_page_url($atts);
            $target = add_query_arg(['token' => $token], $target);

            wp_safe_redirect($target);
            exit;

        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    private function get_reservation_page_url(array $atts): string
    {
        $success = isset($atts['success_page']) ? trim((string)$atts['success_page']) : '';
        if ($success !== '') {
            if (filter_var($success, FILTER_VALIDATE_URL)) return $success;
            return home_url($success);
        }

        // Per defecte, pàgina /reserve/
        return home_url('/reserve/');
    }

    private function rate_limit_ok(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'wpssc_reserve_rl_' . md5($ip);

        $count = (int) get_transient($key);
        if ($count >= 8) {
            return false;
        }

        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    private function get_active_variants(): array
    {
        global $wpdb;
        $t = DB::table('variants');

        $sql = "
            SELECT
              id, sku, model, color, size, price,
              stock_total, stock_sold, stock_reserved,
              (stock_total - stock_sold - stock_reserved) AS available
            FROM {$t}
            WHERE is_active = 1
            ORDER BY model ASC, color ASC, size ASC
        ";

        $rows = (array) $wpdb->get_results($sql, ARRAY_A);

        return array_values(array_filter($rows, function ($r) {
            return (int)$r['available'] > 0;
        }));
    }

    /**
     * Carrega la variant real per validar i inferir tipus checkout.
     */
    private function get_variant(int $variant_id): ?array
    {
        global $wpdb;
        $t = DB::table('variants');

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT id, sku, model, is_active FROM {$t} WHERE id=%d LIMIT 1", $variant_id),
            ARRAY_A
        );

        if (!$row) return null;
        if ((int)$row['is_active'] !== 1) return null;

        return $row;
    }

    /**
     * Heurística:
     * - si model o sku conté paraules d'adult → 'adult'
     * - sinó → 'child'
     *
     * Ajusta ADULT_KEYWORDS si el vostre CSV fa servir un altre vocabulari (ex: "Adult", "Adults", "Unisex adult", etc.)
     */
    private function infer_checkout_type(array $variant): string
    {
        $haystack = strtolower(trim(($variant['model'] ?? '') . ' ' . ($variant['sku'] ?? '')));

        foreach (self::ADULT_KEYWORDS as $kw) {
            if ($kw !== '' && str_contains($haystack, strtolower($kw))) {
                return 'adult';
            }
        }

        return 'child';
    }
}
