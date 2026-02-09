<?php

namespace WPSSC\Cron;

use WPSSC\Repositories\ReservationRepository;

if (!defined('ABSPATH')) { exit; }

final class ExpireReservationsJob
{
    public function run(int $limit = 50): int
    {
        $limit = max(1, min(500, (int)$limit));

        $repo = new ReservationRepository();

        // Busca ordres candidates (reserved + expired by time)
        $order_ids = $repo->find_expired_reserved_order_ids($limit);

        $expired_count = 0;

        foreach ($order_ids as $order_id) {
            try {
                if ($repo->expire_order_by_id((int)$order_id)) {
                    $expired_count++;
                }
            } catch (\Throwable $e) {
                // segueix amb la següent (no parem el batch)
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[WPSSC] Expire order error (order_id=' . (int)$order_id . '): ' . $e->getMessage());
                }
            }
        }

        return $expired_count;
    }
}
