<?php

namespace WPSSC\Repositories;

use WPSSC\DB;

if (!defined('ABSPATH')) { exit; }

final class ReservationRepository
{
    public const STATUS_RESERVED  = 'reserved';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_PAID      = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    private \wpdb $db;
    private string $tVariants;
    private string $tOrders;
    private string $tItems;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;

        $this->tVariants = DB::table('variants');
        $this->tOrders   = DB::table('orders');
        $this->tItems    = DB::table('order_items');
    }

    public function create_reservation(int $variant_id, int $qty, string $email, int $ttl_minutes, ?string $checkout_url = null): string
    {
        $variant_id = (int)$variant_id;
        $qty = (int)$qty;
        $email = strtolower(trim($email));

        if ($variant_id < 1) throw new \InvalidArgumentException('Invalid variant.');
        if ($qty < 1) throw new \InvalidArgumentException('Invalid quantity.');
        if (!is_email($email)) throw new \InvalidArgumentException('Invalid email.');

        $ttl_minutes = max(1, min(1440, (int)$ttl_minutes));
        $now = gmdate('Y-m-d H:i:s');
        $reserved_until = gmdate('Y-m-d H:i:s', time() + ($ttl_minutes * 60));

        $token = wp_generate_uuid4();

        $this->db->query('START TRANSACTION');

        try {
            $sqlReserve = "
                UPDATE {$this->tVariants}
                SET stock_reserved = stock_reserved + %d
                WHERE id = %d
                  AND (stock_total - stock_sold - stock_reserved) >= %d
                  AND is_active = 1
            ";
            $affected = $this->db->query($this->db->prepare($sqlReserve, $qty, $variant_id, $qty));

            if ($affected !== 1) {
                $exists = (int)$this->db->get_var(
                    $this->db->prepare("SELECT COUNT(*) FROM {$this->tVariants} WHERE id=%d", $variant_id)
                );
                if ($exists === 0) throw new \RuntimeException('Variant not found.');
                throw new \RuntimeException('Not enough stock available.');
            }

            $inserted = $this->db->insert(
                $this->tOrders,
                [
                    'token'          => $token,
                    'email'          => $email,
                    'status'         => self::STATUS_RESERVED,
                    'reserved_until' => $reserved_until,
                    'created_at'     => $now,
                    'checkout_url'   => $checkout_url,
                    'meta'           => null,
                ],
                ['%s','%s','%s','%s','%s','%s','%s']
            );

            if ($inserted !== 1) {
                throw new \RuntimeException('Failed to create order.');
            }

            $order_id = (int)$this->db->insert_id;

            $price = $this->db->get_var(
                $this->db->prepare("SELECT price FROM {$this->tVariants} WHERE id=%d", $variant_id)
            );

            $itemInserted = $this->db->insert(
                $this->tItems,
                [
                    'order_id'   => $order_id,
                    'variant_id' => $variant_id,
                    'qty'        => $qty,
                    'unit_price' => $price !== null ? (float)$price : null,
                ],
                ['%d','%d','%d','%f']
            );

            if ($itemInserted !== 1) {
                throw new \RuntimeException('Failed to create order item.');
            }

            $this->db->query('COMMIT');
            return $token;

        } catch (\Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    public function get_order_by_token(string $token): ?array
    {
        $token = trim($token);
        if (!$this->is_valid_token($token)) return null;

        $order = $this->db->get_row(
            $this->db->prepare("SELECT * FROM {$this->tOrders} WHERE token=%s LIMIT 1", $token),
            ARRAY_A
        );

        if (!$order) return null;

        $items = (array)$this->db->get_results(
            $this->db->prepare("SELECT * FROM {$this->tItems} WHERE order_id=%d", (int)$order['id']),
            ARRAY_A
        );

        $order['items'] = $items;
        return $order;
    }

    /**
     * Expira una ordre si encara està reserved i ja ha passat reserved_until.
     * Deleguem a expire_order_by_id() per no duplicar lògica.
     */
    public function expire_if_needed(string $token): bool
    {
        $token = trim($token);
        if (!$this->is_valid_token($token)) return false;

        // llegim l’ID (sense lock aquí; el lock es fa dins expire_order_by_id)
        $order_id = (int)$this->db->get_var(
            $this->db->prepare("SELECT id FROM {$this->tOrders} WHERE token=%s LIMIT 1", $token)
        );

        if ($order_id < 1) return false;

        return $this->expire_order_by_id($order_id);
    }

    public function find_expired_reserved_order_ids(int $limit = 50): array
    {
        $limit = max(1, min(500, (int)$limit));

        $sql = "
            SELECT id
            FROM {$this->tOrders}
            WHERE status = %s
              AND reserved_until < UTC_TIMESTAMP()
            ORDER BY reserved_until ASC, id ASC
            LIMIT %d
        ";

        $rows = (array) $this->db->get_col(
            $this->db->prepare($sql, self::STATUS_RESERVED, $limit)
        );

        return array_map('intval', $rows);
    }

    public function expire_order_by_id(int $order_id): bool
    {
        $order_id = (int)$order_id;
        if ($order_id < 1) return false;

        $this->db->query('START TRANSACTION');

        try {
            $order = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->tOrders} WHERE id=%d LIMIT 1 FOR UPDATE", $order_id),
                ARRAY_A
            );

            if (!$order) {
                $this->db->query('ROLLBACK');
                return false;
            }

            if ((string)$order['status'] !== self::STATUS_RESERVED) {
                $this->db->query('ROLLBACK');
                return false;
            }

            $now = gmdate('Y-m-d H:i:s');
            if ($now <= (string)$order['reserved_until']) {
                $this->db->query('ROLLBACK');
                return false;
            }

            $this->release_reserved_for_order($order_id);

            $this->db->update(
                $this->tOrders,
                ['status' => self::STATUS_EXPIRED],
                ['id' => $order_id],
                ['%s'],
                ['%d']
            );

            $this->db->query('COMMIT');
            return true;

        } catch (\Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    private function release_reserved_for_order(int $order_id): void
    {
        $items = (array) $this->db->get_results(
            $this->db->prepare("SELECT variant_id, qty FROM {$this->tItems} WHERE order_id=%d", $order_id),
            ARRAY_A
        );

        foreach ($items as $it) {
            $variant_id = (int)$it['variant_id'];
            $qty = (int)$it['qty'];

            $sqlRelease = "
                UPDATE {$this->tVariants}
                SET stock_reserved = GREATEST(stock_reserved - %d, 0)
                WHERE id = %d
            ";
            $this->db->query($this->db->prepare($sqlRelease, $qty, $variant_id));
        }
    }

    private function is_valid_token(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9\-]{36}$/i', $token);
    }

    /**
     * Marca una ordre com a pagada fent matching per token.
     * Retorna un status string per a la conciliació:
     * - ok_paid
     * - already_paid
     * - not_found
     * - expired_needs_review
     * - not_reserved
     */
    public function mark_paid_by_token(string $token): string
    {
        $token = strtolower(trim($token));
        if (!$this->is_valid_token($token)) return 'not_found';

        $this->db->query('START TRANSACTION');

        try {
            $order = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->tOrders} WHERE token=%s LIMIT 1 FOR UPDATE", $token),
                ARRAY_A
            );

            if (!$order) {
                $this->db->query('ROLLBACK');
                return 'not_found';
            }

            $status = (string)$order['status'];

            if ($status === self::STATUS_PAID) {
                $this->db->query('ROLLBACK');
                return 'already_paid';
            }

            // Per defecte NO convertim expirades a paid automàticament (requereix revisió)
            if ($status === self::STATUS_EXPIRED) {
                $this->db->query('ROLLBACK');
                return 'expired_needs_review';
            }

            if ($status !== self::STATUS_RESERVED) {
                $this->db->query('ROLLBACK');
                return 'not_reserved';
            }

            $order_id = (int)$order['id'];

            // Mou estoc: reserved -> sold
            $items = (array) $this->db->get_results(
                $this->db->prepare("SELECT variant_id, qty FROM {$this->tItems} WHERE order_id=%d", $order_id),
                ARRAY_A
            );

            foreach ($items as $it) {
                $variant_id = (int)$it['variant_id'];
                $qty = (int)$it['qty'];

                // allibera reserved
                $sql1 = "
                    UPDATE {$this->tVariants}
                    SET stock_reserved = GREATEST(stock_reserved - %d, 0)
                    WHERE id = %d
                ";
                $this->db->query($this->db->prepare($sql1, $qty, $variant_id));

                // incrementa sold
                $sql2 = "
                    UPDATE {$this->tVariants}
                    SET stock_sold = stock_sold + %d
                    WHERE id = %d
                ";
                $this->db->query($this->db->prepare($sql2, $qty, $variant_id));
            }

            $this->db->update(
                $this->tOrders,
                ['status' => self::STATUS_PAID],
                ['id' => $order_id],
                ['%s'],
                ['%d']
            );

            $this->db->query('COMMIT');
            return 'ok_paid';

        } catch (\Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

}
