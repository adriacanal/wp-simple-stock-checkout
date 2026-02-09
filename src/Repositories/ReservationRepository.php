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

    /**
     * Crea una reserva atòmica:
     * - incrementa stock_reserved si hi ha disponibilitat
     * - crea order + order_item
     * Retorna token.
     */
    public function create_reservation(int $variant_id, int $qty, string $email, int $ttl_minutes, ?string $checkout_url = null): string
    {
        $variant_id = (int)$variant_id;
        $qty = (int)$qty;
        $email = strtolower(trim($email));

        if ($variant_id < 1) throw new \InvalidArgumentException('Invalid variant.');
        if ($qty < 1) throw new \InvalidArgumentException('Invalid quantity.');
        if (!is_email($email)) throw new \InvalidArgumentException('Invalid email.');

        $ttl_minutes = max(1, min(1440, (int)$ttl_minutes)); // 1 min a 24h
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
                    'token'         => $token,
                    'email'         => $email,
                    'status'        => self::STATUS_RESERVED,
                    'reserved_until'=> $reserved_until,
                    'created_at'    => $now,
                    'checkout_url'  => $checkout_url,
                    'meta'          => null,
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
     * Allibera stock_reserved en conseqüència.
     */
    public function expire_if_needed(string $token): bool
    {
        $token = trim($token);
        if (!$this->is_valid_token($token)) return false;

        $this->db->query('START TRANSACTION');

        try {
            $order = $this->db->get_row(
                $this->db->prepare("SELECT * FROM {$this->tOrders} WHERE token=%s LIMIT 1 FOR UPDATE", $token),
                ARRAY_A
            );

            if (!$order) {
                $this->db->query('ROLLBACK');
                return false;
            }

            if ($order['status'] !== self::STATUS_RESERVED) {
                $this->db->query('ROLLBACK');
                return false;
            }

            $now = gmdate('Y-m-d H:i:s');
            if ($now <= $order['reserved_until']) {
                $this->db->query('ROLLBACK');
                return false;
            }

            $items = (array)$this->db->get_results(
                $this->db->prepare("SELECT variant_id, qty FROM {$this->tItems} WHERE order_id=%d", (int)$order['id']),
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

            $this->db->update(
                $this->tOrders,
                ['status' => self::STATUS_EXPIRED],
                ['id' => (int)$order['id']],
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

    private function is_valid_token(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9\-]{36}$/i', $token);
    }
}
