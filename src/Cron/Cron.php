<?php

namespace WPSSC\Cron;

if (!defined('ABSPATH')) { exit; }

final class Cron
{
    public const HOOK_EXPIRE = 'wpssc_cron_expire_reservations';
    private const SCHEDULE_KEY = 'wpssc_every_5min';

    public static function init(): void
    {
        // Defineix interval custom (5 min)
        add_filter('cron_schedules', [self::class, 'add_schedules']);

        // Registra el handler
        add_action(self::HOOK_EXPIRE, [self::class, 'run_expire_job']);

        // Assegura que està programat (safe, no duplica)
        self::ensure_scheduled();
    }

    public static function add_schedules(array $schedules): array
    {
        if (!isset($schedules[self::SCHEDULE_KEY])) {
            $schedules[self::SCHEDULE_KEY] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __('Every 5 minutes (WPSSC)', 'wp-simple-stock-checkout'),
            ];
        }
        return $schedules;
    }

    public static function ensure_scheduled(): void
    {
        if (!wp_next_scheduled(self::HOOK_EXPIRE)) {
            // Primer run: ara + 60s per evitar arrencades immediates pesades
            wp_schedule_event(time() + 60, self::SCHEDULE_KEY, self::HOOK_EXPIRE);
        }
    }

    public static function clear_scheduled(): void
    {
        $timestamp = wp_next_scheduled(self::HOOK_EXPIRE);
        while ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK_EXPIRE);
            $timestamp = wp_next_scheduled(self::HOOK_EXPIRE);
        }
    }

    public static function run_expire_job(): void
    {
        // Evita overlapping si WP-Cron es dispara en paral·lel
        if (get_transient('wpssc_cron_expire_lock')) {
            return;
        }
        set_transient('wpssc_cron_expire_lock', 1, 2 * MINUTE_IN_SECONDS);

        try {
            $job = new ExpireReservationsJob();
            $job->run(50); // batch size
        } catch (\Throwable $e) {
            // Logging mínim (si vols, ho portes a debug mode)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[WPSSC] Cron expire job error: ' . $e->getMessage());
            }
        } finally {
            delete_transient('wpssc_cron_expire_lock');
        }
    }
}
