<?php

namespace WPSSC;

use WPSSC\Frontend\Shortcodes\ReservationPageShortcode;
use WPSSC\Frontend\Shortcodes\ReserveFormShortcode;
use WPSSC\Cron\Cron;

if (!defined('ABSPATH')) { exit; }

final class Plugin
{
    private static ?Plugin $instance = null;

    public static function instance(): Plugin
    {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function init(): void
    {

        if (class_exists('\WPSSC\Cron\Cron')) {
            Cron::init();
        }

        if (is_admin() && class_exists('\\WPSSC\\Admin\\Admin')) {
            (new \WPSSC\Admin\Admin())->init();
        }

        if (!is_admin()) {
            add_shortcode('wpssc_reserve', function ($atts = []) {
                return (new ReserveFormShortcode())->render($atts);
            });

            add_shortcode('wpssc_reservation', function ($atts = []) {
                return (new ReservationPageShortcode())->render($atts);
            });
        }
    }
}
