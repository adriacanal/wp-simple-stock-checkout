<?php

namespace WPSSC\Repositories;

use WPSSC\DB;
use WPSSC\Domain\StockMovement;

if (!defined('ABSPATH')) { exit; }

final class StockMovementRepository
{
    private \wpdb $db;
    private string $movementsTable;
    private string $variantsTable;

    // Camps de variants (segons el que ja tens)
    private string $fieldVariantId = 'id';
    private string $fieldStockTotal = 'stock_total';
    private string $fieldStockSold  = 'stock_sold';

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;

        $this->movementsTable = DB::table('stock_movements');
        $this->variantsTable  = DB::table('variants');
    }

    public function create_and_apply(StockMovement $m): int
    {
        if ($m->qty === 0) {
            throw new \InvalidArgumentException('Quantity cannot be 0.');
        }

        if (!in_array($m->movement_type, [StockMovement::TYPE_MANUAL_SALE, StockMovement::TYPE_ADJUSTMENT], true)) {
            throw new \InvalidArgumentException('Invalid movement type.');
        }

        // Transacció (InnoDB): aplica estoc + log atòmic
        $this->db->query('START TRANSACTION');

        try {
            $this->apply_to_stock($m);

            $inserted = $this->db->insert(
                $this->movementsTable,
                [
                    'variant_id'    => $m->variant_id,
                    'movement_type' => $m->movement_type,
                    'qty'           => $m->qty,
                    'note'          => $m->note,
                    'created_by'    => $m->created_by,
                    'created_at'    => gmdate('Y-m-d H:i:s'),
                    'applied'       => 1,
                ],
                ['%d','%s','%d','%s','%d','%s','%d']
            );

            if ($inserted !== 1) {
                throw new \RuntimeException('Failed to insert movement log.');
            }

            $id = (int) $this->db->insert_id;

            $this->db->query('COMMIT');
            return $id;
        } catch (\Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Regles:
     * - manual_sale: stock_sold += qty (qty > 0) i (stock_sold + qty <= stock_total)
     * - adjustment: stock_total += qty (qty pot ser +/-) i (stock_total + qty >= stock_sold)
     */
    private function apply_to_stock(StockMovement $m): void
    {
        $t = $this->variantsTable;
        $idF = $this->fieldVariantId;
        $totalF = $this->fieldStockTotal;
        $soldF = $this->fieldStockSold;

        if ($m->movement_type === StockMovement::TYPE_MANUAL_SALE) {
            if ($m->qty < 1) {
                throw new \InvalidArgumentException('Manual sale qty must be > 0.');
            }

            $sql = "
                UPDATE {$t}
                SET {$soldF} = {$soldF} + %d
                WHERE {$idF} = %d
                  AND ({$soldF} + %d) <= {$totalF}
            ";
            $res = $this->db->query($this->db->prepare($sql, $m->qty, $m->variant_id, $m->qty));

            if ($res !== 1) {
                $exists = (int)$this->db->get_var(
                    $this->db->prepare("SELECT COUNT(*) FROM {$t} WHERE {$idF}=%d", $m->variant_id)
                );
                if ($exists === 0) {
                    throw new \RuntimeException('Variant not found.');
                }
                throw new \RuntimeException('Not enough stock available.');
            }
            return;
        }

        if ($m->movement_type === StockMovement::TYPE_ADJUSTMENT) {
            $sql = "
                UPDATE {$t}
                SET {$totalF} = {$totalF} + %d
                WHERE {$idF} = %d
                  AND ({$totalF} + %d) >= {$soldF}
            ";
            $res = $this->db->query($this->db->prepare($sql, $m->qty, $m->variant_id, $m->qty));

            if ($res !== 1) {
                $exists = (int)$this->db->get_var(
                    $this->db->prepare("SELECT COUNT(*) FROM {$t} WHERE {$idF}=%d", $m->variant_id)
                );
                if ($exists === 0) {
                    throw new \RuntimeException('Variant not found.');
                }
                throw new \RuntimeException('Invalid adjustment: stock_total would be < stock_sold.');
            }
            return;
        }

        throw new \RuntimeException('Unhandled movement type.');
    }

    public function list(array $filters, int $page, int $per_page): array
    {
        $page = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        $offset = ($page - 1) * $per_page;

        $where = ['1=1'];
        $args = [];

        if (!empty($filters['variant_id'])) {
            $where[] = 'variant_id = %d';
            $args[] = (int) $filters['variant_id'];
        }
        if (!empty($filters['movement_type'])) {
            $where[] = 'movement_type = %s';
            $args[] = (string) $filters['movement_type'];
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT * FROM {$this->movementsTable} WHERE {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
        $args[] = $per_page;
        $args[] = $offset;

        return (array) $this->db->get_results($this->db->prepare($sql, ...$args), ARRAY_A);
    }

    public function count(array $filters): int
    {
        $where = ['1=1'];
        $args = [];

        if (!empty($filters['variant_id'])) {
            $where[] = 'variant_id = %d';
            $args[] = (int) $filters['variant_id'];
        }
        if (!empty($filters['movement_type'])) {
            $where[] = 'movement_type = %s';
            $args[] = (string) $filters['movement_type'];
        }

        $where_sql = implode(' AND ', $where);
        $sql = "SELECT COUNT(*) FROM {$this->movementsTable} WHERE {$where_sql}";

        if (!empty($args)) {
            $sql = $this->db->prepare($sql, ...$args);
        }

        return (int) $this->db->get_var($sql);
    }
}
