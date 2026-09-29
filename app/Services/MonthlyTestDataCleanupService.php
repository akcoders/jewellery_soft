<?php

namespace App\Services;

use App\Services\DiamondInventory\StockService as DiamondStockService;
use App\Services\GoldInventory\StockService as GoldStockService;
use App\Services\StoneInventory\StockService as StoneStockService;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Removes the newest month of operational/test transactions while preserving
 * users, permissions, company configuration, and master catalogues.
 */
class MonthlyTestDataCleanupService
{
    private const FLOWS = [
        ['material' => 'diamond', 'action' => 'purchase', 'table' => 'purchase_headers', 'line' => 'purchase_lines', 'fk' => 'purchase_id', 'date' => 'purchase_date'],
        ['material' => 'diamond', 'action' => 'issue', 'table' => 'issue_headers', 'line' => 'issue_lines', 'fk' => 'issue_id', 'date' => 'issue_date'],
        ['material' => 'diamond', 'action' => 'return', 'table' => 'return_headers', 'line' => 'return_lines', 'fk' => 'return_id', 'date' => 'return_date'],
        ['material' => 'diamond', 'action' => 'adjustment', 'table' => 'diamond_inventory_adjustment_headers', 'line' => 'diamond_inventory_adjustment_lines', 'fk' => 'adjustment_id', 'date' => 'adjustment_date'],
        ['material' => 'gold', 'action' => 'purchase', 'table' => 'gold_inventory_purchase_headers', 'line' => 'gold_inventory_purchase_lines', 'fk' => 'purchase_id', 'date' => 'purchase_date'],
        ['material' => 'gold', 'action' => 'issue', 'table' => 'gold_inventory_issue_headers', 'line' => 'gold_inventory_issue_lines', 'fk' => 'issue_id', 'date' => 'issue_date'],
        ['material' => 'gold', 'action' => 'return', 'table' => 'gold_inventory_return_headers', 'line' => 'gold_inventory_return_lines', 'fk' => 'return_id', 'date' => 'return_date'],
        ['material' => 'gold', 'action' => 'adjustment', 'table' => 'gold_inventory_adjustment_headers', 'line' => 'gold_inventory_adjustment_lines', 'fk' => 'adjustment_id', 'date' => 'adjustment_date'],
        ['material' => 'stone', 'action' => 'purchase', 'table' => 'stone_inventory_purchase_headers', 'line' => 'stone_inventory_purchase_lines', 'fk' => 'purchase_id', 'date' => 'purchase_date'],
        ['material' => 'stone', 'action' => 'issue', 'table' => 'stone_inventory_issue_headers', 'line' => 'stone_inventory_issue_lines', 'fk' => 'issue_id', 'date' => 'issue_date'],
        ['material' => 'stone', 'action' => 'return', 'table' => 'stone_inventory_return_headers', 'line' => 'stone_inventory_return_lines', 'fk' => 'return_id', 'date' => 'return_date'],
        ['material' => 'stone', 'action' => 'adjustment', 'table' => 'stone_inventory_adjustment_headers', 'line' => 'stone_inventory_adjustment_lines', 'fk' => 'adjustment_id', 'date' => 'adjustment_date'],
    ];

    /** Child and operational tables only; access/configuration/master tables are intentionally absent. */
    private const MONTHLY_TABLES = [
        'mobile_push_notifications', 'mobile_tasks',
        'whatsapp_sender_queue', 'whatsapp_message_logs',
        'lead_followups', 'lead_notes', 'lead_images', 'leads',
        'staff_kpi_achievements', 'approvals',
        'order_followup_schedules', 'order_followups', 'order_diamond_size_details',
        'order_receive_details', 'order_receive_summaries', 'order_material_movements',
        'order_attachments', 'order_status_history', 'delivery_challans',
        'diamond_requirements', 'diamond_bag_history', 'diamond_bag_movements', 'diamond_issues',
        'gold_ledger_entries', 'diamond_ledger_entries', 'stone_ledger_entries',
        'karigar_payment_ledgers', 'production_ready_items',
        'job_card_operations', 'job_card_timeline', 'job_card_items', 'job_card_stages', 'job_cards',
        'packing_list_items', 'packing_lists', 'qc_checks',
        'showroom_fg_movements', 'showroom_reservations', 'showroom_sale_items', 'showroom_sales',
        'invoice_items', 'invoices', 'fg_items',
        'labour_bill_payments', 'labour_bill_jobworks', 'labour_bill_items', 'labour_bills',
        'purchase_bill_payments', 'purchase_bills', 'vendor_payments', 'customer_receipts',
        'grn_items', 'purchase_invoices', 'grns',
        'account_payments', 'account_journal_vouchers', 'credit_notes', 'debit_notes', 'audit_logs',
        'ledger_entries', 'voucher_lines', 'voucher_reversals', 'vouchers',
        'inventory_transactions', 'inventory_voucher_counters',
        'quotation_items', 'quotation_versions', 'quotations',
        'purchase_items', 'purchases',
        'order_items', 'orders',
    ];

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /** @return array<string,mixed> */
    public function preview(string $month): array
    {
        [$start, $end] = $this->monthRange($month);
        $categories = [
            'orders' => $this->countInRange('orders', $this->dateField('orders', ['created_at', 'order_received_date']), $start, $end),
            'purchases' => 0,
            'issues' => 0,
            'returns' => 0,
            'adjustments' => 0,
            'notifications' => $this->countInRange('mobile_push_notifications', 'created_at', $start, $end),
            'tasks' => $this->countInRange('mobile_tasks', 'created_at', $start, $end),
            'accounting_and_sales' => 0,
            'other_operational_rows' => 0,
        ];

        $laterRows = 0;
        foreach (self::FLOWS as $flow) {
            $count = $this->countInRange($flow['table'], $flow['date'], $start, $end);
            $categories[$flow['action'] . 's'] += $count;
            $laterRows += $this->countAfter($flow['table'], $flow['date'], $end);
        }

        // The combined web/PWA purchase form writes to the unified purchases
        // table in addition to the material-specific inventory flows above.
        $unifiedPurchaseDate = $this->dateField('purchases', ['created_at', 'purchase_date']);
        $categories['purchases'] += $this->countInRange('purchases', $unifiedPurchaseDate, $start, $end);
        $laterRows += $this->countAfter('purchases', $unifiedPurchaseDate, $end);
        $laterRows += $this->countAfter(
            'inventory_transactions',
            $this->dateField('inventory_transactions', ['created_at', 'txn_datetime', 'txn_date']),
            $end
        );

        foreach (['vouchers', 'account_payments', 'invoices', 'showroom_sales', 'labour_bills', 'purchase_bills'] as $table) {
            $field = $this->dateField($table, ['created_at', 'voucher_date', 'payment_date', 'invoice_date', 'sale_date', 'bill_date']);
            $categories['accounting_and_sales'] += $this->countInRange($table, $field, $start, $end);
            $laterRows += $this->countAfter($table, $field, $end);
        }

        foreach (self::MONTHLY_TABLES as $table) {
            if (in_array($table, ['orders', 'purchases', 'mobile_push_notifications', 'mobile_tasks', 'vouchers', 'account_payments', 'invoices', 'showroom_sales', 'labour_bills', 'purchase_bills'], true)) {
                continue;
            }
            $categories['other_operational_rows'] += $this->countInRange($table, 'created_at', $start, $end);
        }
        $laterRows += $this->countAfter('orders', $this->dateField('orders', ['created_at', 'order_received_date']), $end);

        return [
            'month' => $month,
            'start_at' => $start,
            'end_at' => $end,
            'categories' => $categories,
            'total_rows' => array_sum($categories),
            'later_operational_rows' => $laterRows,
            'can_cleanup' => $laterRows === 0,
            'safety_message' => $laterRows === 0
                ? 'This is the newest operational month. Stock rollback can run safely.'
                : 'Newer operational data exists. Clear newer months first so stock and accounting history stay valid.',
        ];
    }

    /** @return array<string,mixed> */
    public function cleanup(string $month, int $actorId, ?string $ipAddress = null): array
    {
        if ($actorId <= 0) {
            throw new RuntimeException('A logged-in administrator is required.');
        }
        $preview = $this->preview($month);
        if (! ($preview['can_cleanup'] ?? false)) {
            throw new RuntimeException((string) $preview['safety_message']);
        }
        if (! $this->db->tableExists('monthly_data_cleanup_audits')) {
            throw new RuntimeException('Run Database Update before using monthly cleanup.');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('monthly_data_cleanup_audits')->insert([
            'month_key' => $month,
            'status' => 'started',
            'preview_json' => json_encode($preview, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'requested_by' => $actorId,
            'ip_address' => $ipAddress,
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $auditId = (int) $this->db->insertID();
        [$start, $end] = $this->monthRange($month);
        $counts = [];

        try {
            $this->db->transException(true)->transStart();
            $flowRows = $this->inventoryFlowRows($start, $end);
            usort($flowRows, static function (array $a, array $b): int {
                $timeCompare = strcmp((string) $b['sort_at'], (string) $a['sort_at']);
                return $timeCompare !== 0 ? $timeCompare : ((int) $b['id'] <=> (int) $a['id']);
            });
            foreach ($flowRows as $event) {
                $this->reverseInventoryEvent($event);
            }
            $this->deleteVoucherRecords(array_map(
                static fn(array $event): int => (int) ($event['account_voucher_id'] ?? 0),
                $flowRows
            ), $counts);
            $this->deleteInventoryFlows($flowRows, $counts);
            $this->deleteMonthlyOperationalRows($start, $end, $counts);
            $this->recalculateGoldLedgerBalances();
            $this->rebuildAccountBalances();
            $this->rebuildInventoryBalances();
            $this->db->transComplete();

            $result = [
                'month' => $month,
                'records_deleted' => array_sum($counts),
                'table_counts' => $counts,
                'stock_rollback' => 'completed',
                'masters_preserved' => true,
            ];
            $this->db->table('monthly_data_cleanup_audits')->where('id', $auditId)->update([
                'status' => 'completed',
                'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'completed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return $result;
        } catch (Throwable $e) {
            $this->db->transRollback();
            $this->db->table('monthly_data_cleanup_audits')->where('id', $auditId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    private function inventoryFlowRows(string $start, string $end): array
    {
        $events = [];
        foreach (self::FLOWS as $flow) {
            if (! $this->hasFields($flow['table'], ['id', $flow['date']])) {
                continue;
            }
            $select = 'id, ' . $flow['date'];
            if ($this->db->fieldExists('created_at', $flow['table'])) {
                $select .= ', created_at';
            }
            if ($flow['action'] === 'adjustment' && $this->db->fieldExists('adjustment_type', $flow['table'])) {
                $select .= ', adjustment_type';
            }
            if ($this->db->fieldExists('account_voucher_id', $flow['table'])) {
                $select .= ', account_voucher_id';
            }
            $rows = $this->db->table($flow['table'])->select($select)
                ->where($flow['date'] . ' >=', substr($start, 0, 10))
                ->where($flow['date'] . ' <', substr($end, 0, 10))
                ->get()->getResultArray();
            foreach ($rows as $row) {
                $events[] = $flow + [
                    'id' => (int) $row['id'],
                    'adjustment_type' => (string) ($row['adjustment_type'] ?? ''),
                    'account_voucher_id' => (int) ($row['account_voucher_id'] ?? 0),
                    'sort_at' => (string) (($row['created_at'] ?? '') ?: ($row[$flow['date']] . ' 23:59:59')),
                ];
            }
        }
        return $events;
    }

    private function reverseInventoryEvent(array $event): void
    {
        $id = (int) $event['id'];
        $action = (string) $event['action'];
        $material = (string) $event['material'];
        if ($material === 'gold') {
            $service = new GoldStockService($this->db);
            if ($action === 'adjustment') {
                $service->reverseAdjustment($id, (string) $event['adjustment_type'], ['record_ledger' => false]);
            } else {
                $method = 'reverse' . ucfirst($action);
                $service->{$method}($id, ['record_ledger' => false]);
            }
            $service->clearLedgerEntriesForReference((string) $event['table'], $id);
            return;
        }
        $service = $material === 'diamond'
            ? new DiamondStockService($this->db)
            : new StoneStockService($this->db);
        if ($action === 'adjustment') {
            $service->reverseAdjustment($id, (string) $event['adjustment_type']);
            return;
        }
        if ($material === 'diamond') {
            if ($action === 'purchase') {
                (new DiamondChalniStockService($this->db))->reversePurchase($id);
            }
        }
        $method = 'reverse' . ucfirst($action);
        $service->{$method}($id);
        if ($material === 'diamond' && $action === 'issue') {
            (new DiamondBagTraceService($this->db))->reverseIssue($id);
        } elseif ($material === 'diamond' && $action === 'return') {
            (new DiamondBagTraceService($this->db))->reverseReturn($id);
        }
    }

    /** @param list<array<string,mixed>> $events @param array<string,int> $counts */
    private function deleteInventoryFlows(array $events, array &$counts): void
    {
        $idsByTable = [];
        foreach ($events as $event) {
            $idsByTable[(string) $event['table']][] = (int) $event['id'];
        }
        foreach (array_reverse(self::FLOWS) as $flow) {
            $ids = array_values(array_unique($idsByTable[$flow['table']] ?? []));
            if ($ids === []) {
                continue;
            }
            $this->deleteNotificationReferences((string) $flow['table'], $ids, $counts);
            if ($flow['table'] === 'purchase_headers') {
                $this->deleteWhereIn('diamond_purchase_attachments', 'purchase_id', $ids, $counts);
            } elseif ($flow['table'] === 'stone_inventory_purchase_headers') {
                $this->deleteWhereIn('stone_purchase_attachments', 'purchase_id', $ids, $counts);
            }
            $this->deleteWhereIn((string) $flow['line'], (string) $flow['fk'], $ids, $counts);
            $this->deleteWhereIn((string) $flow['table'], 'id', $ids, $counts);
        }
    }

    /** @param array<string,int> $counts */
    private function deleteMonthlyOperationalRows(string $start, string $end, array &$counts): void
    {
        foreach (self::MONTHLY_TABLES as $table) {
            if (! $this->db->tableExists($table) || ! $this->db->fieldExists('created_at', $table)) {
                continue;
            }
            $builder = $this->db->table($table)
                ->where('created_at >=', $start)
                ->where('created_at <', $end);
            $count = $builder->countAllResults();
            if ($count <= 0) {
                continue;
            }
            $this->db->table($table)->where('created_at >=', $start)->where('created_at <', $end)->delete();
            $counts[$table] = ($counts[$table] ?? 0) + $count;
        }
    }

    private function recalculateGoldLedgerBalances(): void
    {
        if ($this->db->tableExists('gold_inventory_ledger_entries')) {
            (new GoldStockService($this->db))->recalculateLedgerBalances();
        }
    }

    /** @param list<int> $voucherIds @param array<string,int> $counts */
    private function deleteVoucherRecords(array $voucherIds, array &$counts): void
    {
        $voucherIds = array_values(array_unique(array_filter(array_map('intval', $voucherIds), static fn(int $id): bool => $id > 0)));
        if ($voucherIds === [] || ! $this->db->tableExists('vouchers')) {
            return;
        }

        if ($this->hasFields('voucher_reversals', ['original_voucher_id', 'reversal_voucher_id'])) {
            $pairs = $this->db->table('voucher_reversals')
                ->groupStart()
                    ->whereIn('original_voucher_id', $voucherIds)
                    ->orWhereIn('reversal_voucher_id', $voucherIds)
                ->groupEnd()
                ->get()
                ->getResultArray();
            foreach ($pairs as $pair) {
                $voucherIds[] = (int) ($pair['original_voucher_id'] ?? 0);
                $voucherIds[] = (int) ($pair['reversal_voucher_id'] ?? 0);
            }
            $voucherIds = array_values(array_unique(array_filter($voucherIds, static fn(int $id): bool => $id > 0)));
            $this->deleteWhereIn('voucher_reversals', 'original_voucher_id', $voucherIds, $counts);
            $this->deleteWhereIn('voucher_reversals', 'reversal_voucher_id', $voucherIds, $counts);
        }

        $this->deleteWhereIn('ledger_entries', 'voucher_id', $voucherIds, $counts);
        $this->deleteWhereIn('voucher_lines', 'voucher_id', $voucherIds, $counts);
        if ($this->hasFields('audit_logs', ['entity_type', 'entity_id'])) {
            $count = $this->db->table('audit_logs')
                ->where('entity_type', 'voucher')
                ->whereIn('entity_id', $voucherIds)
                ->countAllResults();
            if ($count > 0) {
                $this->db->table('audit_logs')
                    ->where('entity_type', 'voucher')
                    ->whereIn('entity_id', $voucherIds)
                    ->delete();
                $counts['audit_logs'] = ($counts['audit_logs'] ?? 0) + $count;
            }
        }
        $this->deleteWhereIn('vouchers', 'id', $voucherIds, $counts);
    }

    private function rebuildAccountBalances(): void
    {
        if (! $this->db->tableExists('account_balances') || ! $this->db->tableExists('ledger_entries')) {
            return;
        }
        // DELETE remains transactional on InnoDB; TRUNCATE/emptyTable would
        // implicitly commit and could leave a half-finished cleanup behind.
        $this->db->query('DELETE FROM account_balances');
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
                 FROM ledger_entries le INNER JOIN vouchers v ON v.id = le.voucher_id WHERE v.status = 'Posted'
                 UNION ALL
                 SELECT le.credit_account_id account_id, le.item_type, le.item_key,
                        -le.qty_pcs, -le.qty_cts, -le.qty_weight, -le.fine_gold_qty
                 FROM ledger_entries le INNER JOIN vouchers v ON v.id = le.voucher_id WHERE v.status = 'Posted'
             ) balances
             WHERE account_id IS NOT NULL AND account_id > 0
             GROUP BY account_id, item_type, item_key"
        );
    }

    private function rebuildInventoryBalances(): void
    {
        if (! $this->db->tableExists('inventory_transactions') || ! $this->db->tableExists('inventory_balances')) {
            return;
        }
        $this->db->query('DELETE FROM inventory_balances');
        $now = $this->db->escape(date('Y-m-d H:i:s'));
        $warehouse = "CASE WHEN COALESCE(qty_sign, 1) < 0 THEN COALESCE(from_warehouse_id, location_id) ELSE COALESCE(to_warehouse_id, location_id) END";
        $bin = "CASE WHEN COALESCE(qty_sign, 1) < 0 THEN from_bin_id ELSE to_bin_id END";
        $this->db->query(
            "INSERT INTO inventory_balances
                (balance_key,item_type,material_name,gold_purity_id,diamond_shape,diamond_sieve,diamond_sieve_min,diamond_sieve_max,diamond_color,diamond_clarity,diamond_cut,diamond_quality,diamond_fluorescence,diamond_lab,certificate_no,packet_no,lot_no,stone_type,stone_size,stone_color_shade,stone_quality_grade,warehouse_id,bin_id,pcs_balance,weight_gm_balance,cts_balance,fine_gold_balance,created_at,updated_at)
             SELECT SHA1(CONCAT_WS('|',COALESCE(item_type,''),COALESCE(material_name,''),COALESCE(gold_purity_id,''),COALESCE(diamond_shape,''),COALESCE(diamond_sieve,''),COALESCE(diamond_sieve_min,''),COALESCE(diamond_sieve_max,''),COALESCE(diamond_color,''),COALESCE(diamond_clarity,''),COALESCE(diamond_cut,''),COALESCE(diamond_quality,''),COALESCE(diamond_fluorescence,''),COALESCE(diamond_lab,''),COALESCE(certificate_no,''),COALESCE(packet_no,''),COALESCE(lot_no,''),COALESCE(stone_type,''),COALESCE(stone_size,''),COALESCE(stone_color_shade,''),COALESCE(stone_quality_grade,''),COALESCE({$warehouse},''),COALESCE({$bin},''))),
                    item_type,material_name,gold_purity_id,diamond_shape,diamond_sieve,diamond_sieve_min,diamond_sieve_max,diamond_color,diamond_clarity,diamond_cut,diamond_quality,diamond_fluorescence,diamond_lab,certificate_no,packet_no,lot_no,stone_type,stone_size,stone_color_shade,stone_quality_grade,{$warehouse},{$bin},
                    ROUND(SUM(COALESCE(qty_sign,1)*COALESCE(pcs,0)),3),ROUND(SUM(COALESCE(qty_sign,1)*COALESCE(weight_gm,0)),3),ROUND(SUM(COALESCE(qty_sign,1)*COALESCE(cts,0)),3),ROUND(SUM(COALESCE(qty_sign,1)*COALESCE(fine_gold_gm,0)),3),{$now},{$now}
             FROM inventory_transactions
             WHERE COALESCE(is_void,0)=0 AND COALESCE(status,'posted')='posted'
             GROUP BY item_type,material_name,gold_purity_id,diamond_shape,diamond_sieve,diamond_sieve_min,diamond_sieve_max,diamond_color,diamond_clarity,diamond_cut,diamond_quality,diamond_fluorescence,diamond_lab,certificate_no,packet_no,lot_no,stone_type,stone_size,stone_color_shade,stone_quality_grade,{$warehouse},{$bin}
             HAVING ABS(ROUND(SUM(COALESCE(qty_sign,1)*COALESCE(pcs,0)),3)) > 0.0001
                 OR ABS(ROUND(SUM(COALESCE(qty_sign,1)*COALESCE(weight_gm,0)),3)) > 0.0001
                 OR ABS(ROUND(SUM(COALESCE(qty_sign,1)*COALESCE(cts,0)),3)) > 0.0001
                 OR ABS(ROUND(SUM(COALESCE(qty_sign,1)*COALESCE(fine_gold_gm,0)),3)) > 0.0001"
        );
    }

    /** @param list<int> $ids @param array<string,int> $counts */
    private function deleteNotificationReferences(string $table, array $ids, array &$counts): void
    {
        if (! $this->hasFields('mobile_push_notifications', ['reference_table', 'reference_id']) || $ids === []) {
            return;
        }
        $count = $this->db->table('mobile_push_notifications')->where('reference_table', $table)->whereIn('reference_id', $ids)->countAllResults();
        if ($count > 0) {
            $this->db->table('mobile_push_notifications')->where('reference_table', $table)->whereIn('reference_id', $ids)->delete();
            $counts['mobile_push_notifications'] = ($counts['mobile_push_notifications'] ?? 0) + $count;
        }
    }

    /** @param list<int> $ids @param array<string,int> $counts */
    private function deleteWhereIn(string $table, string $field, array $ids, array &$counts): void
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids)), static fn(int $id): bool => $id > 0));
        if ($ids === [] || ! $this->hasFields($table, [$field])) {
            return;
        }
        $count = $this->db->table($table)->whereIn($field, $ids)->countAllResults();
        if ($count > 0) {
            $this->db->table($table)->whereIn($field, $ids)->delete();
            $counts[$table] = ($counts[$table] ?? 0) + $count;
        }
    }

    private function countInRange(string $table, ?string $field, string $start, string $end): int
    {
        if ($field === null || ! $this->hasFields($table, [$field])) {
            return 0;
        }
        return (int) $this->db->table($table)->where($field . ' >=', $start)->where($field . ' <', $end)->countAllResults();
    }

    private function countAfter(string $table, ?string $field, string $end): int
    {
        if ($field === null || ! $this->hasFields($table, [$field])) {
            return 0;
        }
        return (int) $this->db->table($table)->where($field . ' >=', $end)->countAllResults();
    }

    /** @param list<string> $candidates */
    private function dateField(string $table, array $candidates): ?string
    {
        if (! $this->db->tableExists($table)) {
            return null;
        }
        foreach ($candidates as $field) {
            if ($this->db->fieldExists($field, $table)) {
                return $field;
            }
        }
        return null;
    }

    /** @param list<string> $fields */
    private function hasFields(string $table, array $fields): bool
    {
        if (! $this->db->tableExists($table)) {
            return false;
        }
        foreach ($fields as $field) {
            if (! $this->db->fieldExists($field, $table)) {
                return false;
            }
        }
        return true;
    }

    /** @return array{0:string,1:string} */
    private function monthRange(string $month): array
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new RuntimeException('Select a valid month.');
        }
        $start = DateTimeImmutable::createFromFormat('!Y-m', $month);
        if (! $start || $start->format('Y-m') !== $month) {
            throw new RuntimeException('Select a valid month.');
        }
        $end = $start->modify('first day of next month');
        if ($start > new DateTimeImmutable('first day of this month')) {
            throw new RuntimeException('Future months cannot be cleared.');
        }
        return [$start->format('Y-m-d 00:00:00'), $end->format('Y-m-d 00:00:00')];
    }
}
