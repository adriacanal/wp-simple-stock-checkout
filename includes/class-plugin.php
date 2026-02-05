<?php
namespace WPSSC;

if (!defined('ABSPATH')) { exit; }

final class Plugin {
    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function init(): void {
        if (is_admin() && class_exists('\\WPSSC\\Admin\\Admin')) {
            (new \WPSSC\Admin\Admin())->init();
        }
    }
}
