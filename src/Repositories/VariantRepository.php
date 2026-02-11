<?php
namespace WPSSC\Repositories;

use WPSSC\DB;

if (!defined('ABSPATH')) { exit; }

final class VariantRepository {

    public function upsert_by_sku(array $row): void {
        global $wpdb;
        $t = DB::table('variants');

        $sku = $row['sku'];

        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t} WHERE sku = %s LIMIT 1",
            $sku
        ));

        $data = [
            'sku'         => $row['sku'],
            'model'       => $row['model'],
            'color'       => $row['color'],
            'size'        => $row['size'],
            'price'       => $row['price'],
            'stock_total' => $row['stock_total'],
            'is_active'   => $row['is_active'],
        ];

        $formats = ['%s','%s','%s','%s','%f','%d','%d'];

        if ($existing_id) {
            $wpdb->update($t, $data, ['id' => (int)$existing_id], $formats, ['%d']);
        } else {
            $wpdb->insert($t, $data, $formats);
        }
    }

    public function set_active(int $variant_id, bool $is_active): bool
    {
        global $wpdb;
        $t = DB::table('variants');

        $updated = $wpdb->update(
            $t,
            ['is_active' => $is_active ? 1 : 0],
            ['id' => (int)$variant_id],
            ['%d'],
            ['%d']
        );

        return $updated === 1;
    }

}
