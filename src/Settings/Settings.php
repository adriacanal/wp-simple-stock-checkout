<?php
namespace WPSSC\Settings;

if (!defined('ABSPATH')) { exit; }

final class Settings {
    public const OPTION_KEY = 'wpssc_settings';

    public static function add_defaults(): void {
        if (get_option(self::OPTION_KEY) !== false) return;

        add_option(self::OPTION_KEY, [
            'reserve_minutes' => 15,
            'checkout_url_child' => '',
            'checkout_url_adult' => '',
            'require_email' => 1,
            'checkout_url' => '',
        ]);
    }

    public static function all(): array {
        $v = get_option(self::OPTION_KEY, []);
        return is_array($v) ? $v : [];
    }

    public static function get(string $key, $default = null) {
        $all = self::all();
        return $all[$key] ?? $default;
    }

    public static function update(array $new): void {
        update_option(self::OPTION_KEY, $new, false);
    }

    /** Clamp 1..1440. Default 15. */
    public static function reservation_minutes(): int {
        $mins = (int) self::get('reserve_minutes', 15);
        if ($mins < 1) return 15;
        if ($mins > 1440) return 1440;
        return $mins;
    }

    /**
     * Retorna la URL del checkout.
     * - Si existeix 'checkout_url' (nova) i és vàlida, la usa.
     * - Si no, retorna child/adult segons $type.
     * $type: 'child' | 'adult'
     */
    public static function checkout_url(string $type = 'child'): string {
        $single = (string) self::get('checkout_url', '');
        $single = self::sanitize_checkout_url($single);
        if ($single !== '') return $single;

        if ($type === 'adult') {
            return self::sanitize_checkout_url((string) self::get('checkout_url_adult', ''));
        }
        return self::sanitize_checkout_url((string) self::get('checkout_url_child', ''));
    }

    public static function require_email(): bool {
        return (int) self::get('require_email', 1) === 1;
    }

    private static function sanitize_checkout_url(string $url): string {
        $url = trim($url);
        if ($url === '') return '';
        $url = esc_url_raw($url);
        if ($url !== '' && !preg_match('#^https?://#i', $url)) return '';
        return $url;
    }
}
