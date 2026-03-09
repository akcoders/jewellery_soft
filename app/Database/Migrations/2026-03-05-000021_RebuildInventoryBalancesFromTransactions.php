<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RebuildInventoryBalancesFromTransactions extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('inventory_transactions') || ! $this->db->tableExists('inventory_balances')) {
            return;
        }

        $this->db->query('TRUNCATE TABLE inventory_balances');

        $now = date('Y-m-d H:i:s');
        $warehouseExpr = "CASE WHEN COALESCE(qty_sign, 1) < 0 THEN COALESCE(from_warehouse_id, location_id) ELSE COALESCE(to_warehouse_id, location_id) END";
        $binExpr = "CASE WHEN COALESCE(qty_sign, 1) < 0 THEN from_bin_id ELSE to_bin_id END";

        $sql = "
            INSERT INTO inventory_balances (
                balance_key,
                item_type,
                material_name,
                gold_purity_id,
                diamond_shape,
                diamond_sieve,
                diamond_sieve_min,
                diamond_sieve_max,
                diamond_color,
                diamond_clarity,
                diamond_cut,
                diamond_quality,
                diamond_fluorescence,
                diamond_lab,
                certificate_no,
                packet_no,
                lot_no,
                stone_type,
                stone_size,
                stone_color_shade,
                stone_quality_grade,
                warehouse_id,
                bin_id,
                pcs_balance,
                weight_gm_balance,
                cts_balance,
                fine_gold_balance,
                created_at,
                updated_at
            )
            SELECT
                SHA1(CONCAT_WS('|',
                    COALESCE(item_type, ''),
                    COALESCE(material_name, ''),
                    COALESCE(gold_purity_id, ''),
                    COALESCE(diamond_shape, ''),
                    COALESCE(diamond_sieve, ''),
                    COALESCE(diamond_sieve_min, ''),
                    COALESCE(diamond_sieve_max, ''),
                    COALESCE(diamond_color, ''),
                    COALESCE(diamond_clarity, ''),
                    COALESCE(diamond_cut, ''),
                    COALESCE(diamond_quality, ''),
                    COALESCE(diamond_fluorescence, ''),
                    COALESCE(diamond_lab, ''),
                    COALESCE(certificate_no, ''),
                    COALESCE(packet_no, ''),
                    COALESCE(lot_no, ''),
                    COALESCE(stone_type, ''),
                    COALESCE(stone_size, ''),
                    COALESCE(stone_color_shade, ''),
                    COALESCE(stone_quality_grade, ''),
                    COALESCE({$warehouseExpr}, ''),
                    COALESCE({$binExpr}, '')
                )) AS balance_key,
                item_type,
                material_name,
                gold_purity_id,
                diamond_shape,
                diamond_sieve,
                diamond_sieve_min,
                diamond_sieve_max,
                diamond_color,
                diamond_clarity,
                diamond_cut,
                diamond_quality,
                diamond_fluorescence,
                diamond_lab,
                certificate_no,
                packet_no,
                lot_no,
                stone_type,
                stone_size,
                stone_color_shade,
                stone_quality_grade,
                {$warehouseExpr} AS warehouse_id,
                {$binExpr} AS bin_id,
                ROUND(SUM(COALESCE(qty_sign, 1) * COALESCE(pcs, 0)), 3) AS pcs_balance,
                ROUND(SUM(COALESCE(qty_sign, 1) * COALESCE(weight_gm, 0)), 3) AS weight_gm_balance,
                ROUND(SUM(COALESCE(qty_sign, 1) * COALESCE(cts, 0)), 3) AS cts_balance,
                ROUND(SUM(COALESCE(qty_sign, 1) * COALESCE(fine_gold_gm, 0)), 3) AS fine_gold_balance,
                '{$now}' AS created_at,
                '{$now}' AS updated_at
            FROM inventory_transactions
            WHERE COALESCE(is_void, 0) = 0
              AND COALESCE(status, 'posted') = 'posted'
            GROUP BY
                item_type,
                material_name,
                gold_purity_id,
                diamond_shape,
                diamond_sieve,
                diamond_sieve_min,
                diamond_sieve_max,
                diamond_color,
                diamond_clarity,
                diamond_cut,
                diamond_quality,
                diamond_fluorescence,
                diamond_lab,
                certificate_no,
                packet_no,
                lot_no,
                stone_type,
                stone_size,
                stone_color_shade,
                stone_quality_grade,
                {$warehouseExpr},
                {$binExpr}
            HAVING
                ABS(ROUND(SUM(COALESCE(qty_sign, 1) * COALESCE(pcs, 0)), 3)) > 0.0001
                OR ABS(ROUND(SUM(COALESCE(qty_sign, 1) * COALESCE(weight_gm, 0)), 3)) > 0.0001
                OR ABS(ROUND(SUM(COALESCE(qty_sign, 1) * COALESCE(cts, 0)), 3)) > 0.0001
                OR ABS(ROUND(SUM(COALESCE(qty_sign, 1) * COALESCE(fine_gold_gm, 0)), 3)) > 0.0001
        ";

        $this->db->query($sql);
    }

    public function down()
    {
        if ($this->db->tableExists('inventory_balances')) {
            $this->db->query('TRUNCATE TABLE inventory_balances');
        }
    }
}
