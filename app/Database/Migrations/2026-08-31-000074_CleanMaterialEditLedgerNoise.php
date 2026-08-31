<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CleanMaterialEditLedgerNoise extends Migration
{
    public function up()
    {
        $this->cleanEditReversalVouchers();
        $this->rebuildCurrentGoldSourceEntries();
    }

    private function cleanEditReversalVouchers(): void
    {
        foreach (['voucher_reversals', 'vouchers', 'voucher_lines', 'ledger_entries'] as $table) {
            if (! $this->db->tableExists($table)) {
                return;
            }
        }

        $this->db->query('DROP TEMPORARY TABLE IF EXISTS cleanup_edit_voucher_ids');
        $this->db->query('CREATE TEMPORARY TABLE cleanup_edit_voucher_ids (id BIGINT UNSIGNED PRIMARY KEY)');

        $liveReferences = [];
        foreach (['gold_inventory_issue_headers', 'issue_headers', 'stone_inventory_issue_headers'] as $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('account_voucher_id', $table)) {
                $liveReferences[] = "SELECT account_voucher_id AS id FROM {$table} WHERE account_voucher_id IS NOT NULL";
            }
        }
        $liveSql = $liveReferences === [] ? 'SELECT NULL AS id WHERE 1 = 0' : implode(' UNION ALL ', $liveReferences);

        $this->db->query(
            "INSERT IGNORE INTO cleanup_edit_voucher_ids (id)
             SELECT vr.original_voucher_id
             FROM voucher_reversals vr
             WHERE vr.reason IN ('Gold issue edited', 'Diamond issue updated', 'Stone issue updated')
               AND vr.original_voucher_id NOT IN ({$liveSql})
               AND vr.reversal_voucher_id NOT IN ({$liveSql})
             UNION
             SELECT vr.reversal_voucher_id
             FROM voucher_reversals vr
             WHERE vr.reason IN ('Gold issue edited', 'Diamond issue updated', 'Stone issue updated')
               AND vr.original_voucher_id NOT IN ({$liveSql})
               AND vr.reversal_voucher_id NOT IN ({$liveSql})"
        );

        $this->db->query('DELETE le FROM ledger_entries le INNER JOIN cleanup_edit_voucher_ids c ON c.id = le.voucher_id');
        $this->db->query('DELETE vl FROM voucher_lines vl INNER JOIN cleanup_edit_voucher_ids c ON c.id = vl.voucher_id');
        $this->db->query('DELETE vr FROM voucher_reversals vr INNER JOIN cleanup_edit_voucher_ids a ON a.id = vr.original_voucher_id INNER JOIN cleanup_edit_voucher_ids b ON b.id = vr.reversal_voucher_id');
        $this->db->query('DELETE v FROM vouchers v INNER JOIN cleanup_edit_voucher_ids c ON c.id = v.id');
        $this->db->query('DROP TEMPORARY TABLE IF EXISTS cleanup_edit_voucher_ids');
    }

    private function rebuildCurrentGoldSourceEntries(): void
    {
        $required = [
            'gold_inventory_ledger_entries',
            'gold_inventory_purchase_headers',
            'gold_inventory_purchase_lines',
            'gold_inventory_issue_headers',
            'gold_inventory_issue_lines',
        ];
        foreach ($required as $table) {
            if (! $this->db->tableExists($table)) {
                return;
            }
        }

        $ledger = $this->db->table('gold_inventory_ledger_entries');
        $ledger->whereIn('reference_table', [
            'gold_inventory_purchase_headers',
            'gold_inventory_issue_headers',
        ])->delete();

        $this->db->query(
            "INSERT INTO gold_inventory_ledger_entries
                (txn_date, txn_type, reference_table, reference_id, karigar_id, location_id, item_id,
                 debit_weight_gm, credit_weight_gm, debit_fine_gm, credit_fine_gm,
                 balance_weight_gm, balance_fine_gm, rate_per_gm, line_value, notes, created_by, created_at)
             SELECT h.purchase_date, 'purchase', 'gold_inventory_purchase_headers', h.id, NULL, h.location_id, l.item_id,
                    ROUND(SUM(l.weight_gm), 3), 0, ROUND(SUM(l.fine_weight_gm), 3), 0,
                    0, 0,
                    CASE WHEN SUM(l.weight_gm) > 0 THEN ROUND(SUM(l.line_value) / SUM(l.weight_gm), 2) ELSE NULL END,
                    ROUND(SUM(l.line_value), 2), 'Gold purchase posting', h.created_by, COALESCE(h.updated_at, h.created_at, NOW())
             FROM gold_inventory_purchase_headers h
             INNER JOIN gold_inventory_purchase_lines l ON l.purchase_id = h.id
             GROUP BY h.id, h.purchase_date, h.location_id, h.created_by, h.updated_at, h.created_at, l.item_id"
        );

        $this->db->query(
            "INSERT INTO gold_inventory_ledger_entries
                (txn_date, txn_type, reference_table, reference_id, order_id, karigar_id, location_id, item_id,
                 debit_weight_gm, credit_weight_gm, debit_fine_gm, credit_fine_gm,
                 balance_weight_gm, balance_fine_gm, rate_per_gm, line_value, notes, created_by, created_at)
             SELECT h.issue_date, 'issue', 'gold_inventory_issue_headers', h.id, h.order_id, h.karigar_id, h.location_id, l.item_id,
                    0, ROUND(SUM(l.weight_gm), 3), 0, ROUND(SUM(l.fine_weight_gm), 3),
                    0, 0,
                    CASE WHEN SUM(l.weight_gm) > 0 THEN ROUND(SUM(COALESCE(l.line_value, 0)) / SUM(l.weight_gm), 2) ELSE NULL END,
                    ROUND(SUM(COALESCE(l.line_value, 0)), 2), 'Gold issue posting', h.created_by, COALESCE(h.updated_at, h.created_at, NOW())
             FROM gold_inventory_issue_headers h
             INNER JOIN gold_inventory_issue_lines l ON l.issue_id = h.id
             GROUP BY h.id, h.issue_date, h.order_id, h.karigar_id, h.location_id, h.created_by, h.updated_at, h.created_at, l.item_id"
        );

        $balances = [];
        $rows = $this->db->table('gold_inventory_ledger_entries')
            ->select('id, item_id, debit_weight_gm, credit_weight_gm, debit_fine_gm, credit_fine_gm')
            ->orderBy('txn_date', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
        foreach ($rows as $row) {
            $itemId = (int) $row['item_id'];
            $balances[$itemId] ??= ['weight' => 0.0, 'fine' => 0.0];
            $balances[$itemId]['weight'] += (float) $row['debit_weight_gm'] - (float) $row['credit_weight_gm'];
            $balances[$itemId]['fine'] += (float) $row['debit_fine_gm'] - (float) $row['credit_fine_gm'];
            $ledger->where('id', (int) $row['id'])->update([
                'balance_weight_gm' => round($balances[$itemId]['weight'], 3),
                'balance_fine_gm' => round($balances[$itemId]['fine'], 3),
            ]);
        }
    }

    public function down()
    {
        // Data cleanup is intentionally non-reversible. Source transactions remain intact.
    }
}
