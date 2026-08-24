<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CleanProductionTransactionsKeepKarigars extends Migration
{
    /**
     * One-time production cleanup requested before live order entry starts.
     * Karigar masters, vendor purchases, vendor payments and closing stock remain.
     */
    public function up()
    {
        $files = $this->transactionFiles();

        $this->db->transException(true)->transStart();
        $this->deleteOrderAndKarigarPaymentVouchers();
        $this->deleteKarigarPaymentsAndLabour();
        $this->deleteOrderTransactions();
        $this->deleteImportedProductionTransactions();
        $this->rebuildAccountBalances();
        $this->markImportSummaryClean();
        $this->db->transComplete();

        foreach ($files as $path) {
            $this->deleteTransactionFile($path);
        }
    }

    /**
     * The deleted rows are intentionally not recreated: this migration is an
     * irreversible one-time cleanup of unwanted demo/first-import transactions.
     */
    public function down()
    {
    }

    private function deleteOrderAndKarigarPaymentVouchers(): void
    {
        if (! $this->db->tableExists('vouchers')) {
            return;
        }
        $types = [
            'GOLD_ISSUE',
            'GOLD_RECEIVE',
            'DIAMOND_ISSUE',
            'DIAMOND_RETURN',
            'STONE_ISSUE',
            'STONE_RETURN',
            'FG_READY_RECEIPT',
            'ORDER_COMPLETION',
            'PRODUCTION_DETAIL_ISSUE',
            'PRODUCTION_SOURCE_ISSUE',
            'PRODUCTION_SOURCE_RETURN',
        ];
        $rows = $this->db->table('vouchers')
            ->select('id')
            ->groupStart()
                ->where('order_id IS NOT NULL', null, false)
                ->orWhereIn('voucher_type', $types)
            ->groupEnd()
            ->get()->getResultArray();
        $ids = array_values(array_filter(array_map('intval', array_column($rows, 'id'))));
        if ($ids === []) {
            return;
        }
        foreach (['ledger_entries', 'voucher_lines'] as $table) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->whereIn('voucher_id', $ids)->delete();
            }
        }
        if ($this->db->tableExists('voucher_reversals')) {
            $this->db->table('voucher_reversals')
                ->groupStart()->whereIn('original_voucher_id', $ids)->orWhereIn('reversal_voucher_id', $ids)->groupEnd()
                ->delete();
        }
        if ($this->db->tableExists('audit_logs')) {
            $this->db->table('audit_logs')->where('entity_type', 'voucher')->whereIn('entity_id', $ids)->delete();
        }
        $this->db->table('vouchers')->whereIn('id', $ids)->delete();
    }

    private function deleteKarigarPaymentsAndLabour(): void
    {
        if ($this->db->tableExists('account_payments')) {
            $this->db->table('account_payments')
                ->groupStart()
                    ->where('karigar_id IS NOT NULL', null, false)
                    ->orWhere('party_type', 'karigar')
                    ->orWhere('labour_bill_id IS NOT NULL', null, false)
                ->groupEnd()
                ->delete();
        }
        foreach (['labour_bill_payments', 'karigar_payment_ledgers', 'labour_bills'] as $table) {
            $this->clearTable($table);
        }
    }

    private function deleteOrderTransactions(): void
    {
        // Child/line tables must be cleared before their headers.
        foreach ([
            'gold_inventory_issue_lines',
            'gold_inventory_return_lines',
            'stone_inventory_issue_lines',
            'stone_inventory_return_lines',
            'issue_lines',
            'return_lines',
            'packing_list_items',
            'invoice_items',
            'job_card_items',
            'job_card_operations',
            'job_card_stages',
            'job_card_timeline',
            'qc_checks',
            'showroom_fg_movements',
            'showroom_reservations',
            'showroom_sale_items',
        ] as $table) {
            $this->clearTable($table);
        }

        foreach ([
            'gold_inventory_issue_headers',
            'gold_inventory_return_headers',
            'stone_inventory_issue_headers',
            'stone_inventory_return_headers',
            'issue_headers',
            'return_headers',
            'diamond_issues',
            'stone_issues',
            'diamond_ledger_entries',
            'gold_ledger_entries',
            'stone_ledger_entries',
            'order_receive_details',
            'order_receive_summaries',
            'order_material_movements',
            'order_followups',
            'order_attachments',
            'order_status_history',
            'delivery_challans',
            'packing_lists',
            'invoices',
            'credit_notes',
            'debit_notes',
            'whatsapp_message_logs',
            'whatsapp_sender_queue',
            'showroom_sales',
            'fg_items',
            'job_cards',
            'order_items',
        ] as $table) {
            $this->clearTable($table);
        }

        if ($this->db->tableExists('gold_inventory_ledger_entries')) {
            $this->db->table('gold_inventory_ledger_entries')
                ->groupStart()
                    ->where('order_id IS NOT NULL', null, false)
                    ->orWhereIn('txn_type', ['ISSUE', 'RECEIVE', 'RETURN'])
                ->groupEnd()
                ->delete();
        }
        $this->clearTable('orders');
    }

    private function deleteImportedProductionTransactions(): void
    {
        foreach ([
            'production_diamond_issue_lines',
            'production_diamond_movements',
            'production_gold_movements',
            'production_ready_items',
        ] as $table) {
            $this->clearTable($table);
        }
        if ($this->db->tableExists('production_source_rows')) {
            $this->db->table('production_source_rows')->whereIn('record_type', [
                'diamond_issuement_raw',
                'diamond_stock_ledger_raw',
                'gold_ledger_raw',
                'ready_job_raw',
            ])->delete();
        }
    }

    private function rebuildAccountBalances(): void
    {
        if (! $this->db->tableExists('account_balances') || ! $this->db->tableExists('ledger_entries')) {
            return;
        }
        $this->clearTable('account_balances');
        $now = $this->db->escape(date('Y-m-d H:i:s'));
        $this->db->query(
            "INSERT INTO account_balances
                (account_id, item_type, item_key, qty_pcs, qty_cts, qty_weight, fine_gold_qty, created_at, updated_at)
             SELECT account_id, item_type, item_key,
                    ROUND(SUM(qty_pcs),3), ROUND(SUM(qty_cts),3),
                    ROUND(SUM(qty_weight),3), ROUND(SUM(fine_gold_qty),3), {$now}, {$now}
             FROM (
                 SELECT le.debit_account_id account_id, le.item_type, le.item_key,
                        le.qty_pcs, le.qty_cts, le.qty_weight, le.fine_gold_qty
                 FROM ledger_entries le INNER JOIN vouchers v ON v.id = le.voucher_id
                 WHERE v.status = 'Posted'
                 UNION ALL
                 SELECT le.credit_account_id account_id, le.item_type, le.item_key,
                        -le.qty_pcs, -le.qty_cts, -le.qty_weight, -le.fine_gold_qty
                 FROM ledger_entries le INNER JOIN vouchers v ON v.id = le.voucher_id
                 WHERE v.status = 'Posted'
             ) balances
             WHERE account_id IS NOT NULL AND account_id > 0
             GROUP BY account_id, item_type, item_key"
        );
    }

    private function markImportSummaryClean(): void
    {
        if (! $this->db->tableExists('production_import_batches')) {
            return;
        }
        foreach ($this->db->table('production_import_batches')->select('id, summary_json')->get()->getResultArray() as $batch) {
            $summary = json_decode((string) ($batch['summary_json'] ?? '{}'), true);
            $summary = is_array($summary) ? $summary : [];
            foreach ([
                'source_rows',
                'gold_movements',
                'diamond_movements',
                'diamond_issue_lines',
                'ready_items',
                'ready_images',
                'finished_jewellery_items',
                'labour_bills',
                'karigar_payments',
                'orders',
            ] as $key) {
                $summary[$key] = 0;
            }
            $summary['transaction_cleanup'] = 'Orders, production movements and karigar payments removed before live entry';
            $summary['transaction_cleanup_at'] = date('Y-m-d H:i:s');
            $this->db->table('production_import_batches')->where('id', (int) $batch['id'])->update([
                'summary_json' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /** @return list<string> */
    private function transactionFiles(): array
    {
        $paths = [];

        // Ready-workbook images belong only to the imported order/finished-goods
        // transactions. Include every extracted image, even when an image was not
        // matched to a production_ready_items row during the original import.
        foreach (glob(WRITEPATH . 'uploads/production-imports/*/ready-images/*') ?: [] as $imagePath) {
            if (is_file($imagePath)) {
                $paths[ltrim(str_replace('\\', '/', substr($imagePath, strlen(WRITEPATH))), '/')] = true;
            }
        }

        foreach ([
            ['table' => 'production_ready_items', 'column' => 'image_path'],
            ['table' => 'order_attachments', 'column' => 'file_path'],
            ['table' => 'order_followups', 'column' => 'image_path'],
        ] as $source) {
            if (! $this->db->tableExists($source['table']) || ! $this->db->fieldExists($source['column'], $source['table'])) {
                continue;
            }
            foreach ($this->db->table($source['table'])->select($source['column'])->get()->getResultArray() as $row) {
                $value = trim((string) ($row[$source['column']] ?? ''));
                if ($value !== '') {
                    $paths[$value] = true;
                }
            }
        }
        if ($this->db->tableExists('account_payments') && $this->db->fieldExists('reference_file_path', 'account_payments')) {
            $rows = $this->db->table('account_payments')
                ->select('reference_file_path')
                ->groupStart()->where('karigar_id IS NOT NULL', null, false)->orWhere('party_type', 'karigar')->groupEnd()
                ->get()->getResultArray();
            foreach ($rows as $row) {
                $value = trim((string) ($row['reference_file_path'] ?? ''));
                if ($value !== '') {
                    $paths[$value] = true;
                }
            }
        }
        return array_keys($paths);
    }

    private function deleteTransactionFile(string $path): void
    {
        $relative = ltrim(str_replace(['\\', '..'], ['/', ''], $path), '/');
        foreach ([WRITEPATH . $relative, FCPATH . $relative, ROOTPATH . $relative] as $candidate) {
            $real = realpath($candidate);
            if (! $real || ! is_file($real)) {
                continue;
            }
            $allowed = false;
            foreach ([realpath(WRITEPATH), realpath(FCPATH . 'uploads')] as $root) {
                if ($root && ($real === $root || str_starts_with($real, $root . DIRECTORY_SEPARATOR))) {
                    $allowed = true;
                    break;
                }
            }
            if ($allowed) {
                @unlink($real);
            }
        }
    }

    private function clearTable(string $table): void
    {
        if ($this->db->tableExists($table)) {
            $this->db->query('DELETE FROM `' . str_replace('`', '', $table) . '`');
        }
    }
}
