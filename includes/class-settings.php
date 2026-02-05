<?php
namespace WPSSC;

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
        update_option(self::OPTION_KEY, $new);
    }
}
