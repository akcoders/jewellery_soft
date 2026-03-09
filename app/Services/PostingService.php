<?php

namespace App\Services;

use App\Models\AccountBalanceModel;
use App\Models\AccountModel;
use App\Models\InventoryBalanceModel;
use App\Models\LedgerEntryModel;
use App\Models\VoucherLineModel;
use App\Models\VoucherModel;
use CodeIgniter\Database\ConnectionInterface;
use RuntimeException;

class PostingService
{
    private ConnectionInterface $db;
    private VoucherModel $voucherModel;
    private VoucherLineModel $voucherLineModel;
    private InventoryBalanceModel $inventoryBalanceModel;
    private AccountModel $accountModel;
    private AccountBalanceModel $accountBalanceModel;
    private LedgerEntryModel $ledgerEntryModel;

    public function __construct(?ConnectionInterface $db = null)
    {
        $this->db = $db ?? db_connect();
        $this->voucherModel = new VoucherModel($this->db);
        $this->voucherLineModel = new VoucherLineModel($this->db);
        $this->inventoryBalanceModel = new InventoryBalanceModel($this->db);
        $this->accountModel = new AccountModel($this->db);
        $this->accountBalanceModel = new AccountBalanceModel($this->db);
        $this->ledgerEntryModel = new LedgerEntryModel($this->db);
    }

    /**
     * @param array<string,mixed> $header
     * @param array<int,array<string,mixed>> $lines
     * @return array<string,mixed>
     */
    public function postVoucher(array $header, array $lines): array
    {
        if ($lines === []) {
            throw new RuntimeException('Voucher lines are required.');
        }

        $voucherType = strtoupper(trim((string) ($header['voucher_type'] ?? 'GENERAL')));
        $voucherDate = trim((string) ($header['voucher_date'] ?? date('Y-m-d')));
        $voucherNo = trim((string) ($header['voucher_no'] ?? ''));
        if ($voucherNo === '') {
            $voucherNo = $this->generateVoucherNo($voucherType, $voucherDate);
        }

        $createdBy = (int) ($header['created_by'] ?? 0);
        $createdIp = trim((string) ($header['created_ip'] ?? ''));
        if ($createdIp === '') {
            $createdIp = service('request')->getIPAddress() ?? null;
        }

        $fromWarehouseId = (int) ($header['from_warehouse_id'] ?? 0);
        $fromBinId = (int) ($header['from_bin_id'] ?? 0);
        $toWarehouseId = (int) ($header['to_warehouse_id'] ?? 0);
        $toBinId = (int) ($header['to_bin_id'] ?? 0);

        $debitAccountId = (int) ($header['debit_account_id'] ?? 0);
        $creditAccountId = (int) ($header['credit_account_id'] ?? 0);

        if ($debitAccountId <= 0 || $creditAccountId <= 0) {
            throw new RuntimeException('Both debit_account_id and credit_account_id are required.');
        }

        $this->db->transException(true)->transStart();

        if ($this->voucherModel->where('voucher_no', $voucherNo)->first()) {
            throw new RuntimeException('Voucher number already exists: ' . $voucherNo);
        }

        $voucherId = (int) $this->voucherModel->insert([
            'voucher_no' => $voucherNo,
            'voucher_type' => $voucherType,
            'voucher_date' => $voucherDate,
            'voucher_datetime' => date('Y-m-d H:i:s'),
            'from_warehouse_id' => $fromWarehouseId > 0 ? $fromWarehouseId : null,
            'from_bin_id' => $fromBinId > 0 ? $fromBinId : null,
            'to_warehouse_id' => $toWarehouseId > 0 ? $toWarehouseId : null,
            'to_bin_id' => $toBinId > 0 ? $toBinId : null,
            'order_id' => isset($header['order_id']) ? (int) $header['order_id'] : null,
            'job_card_id' => isset($header['job_card_id']) ? (int) $header['job_card_id'] : null,
            'party_id' => isset($header['party_id']) ? (int) $header['party_id'] : null,
            'debit_account_id' => $debitAccountId,
            'credit_account_id' => $creditAccountId,
            'status' => 'Posted',
            'remarks' => trim((string) ($header['remarks'] ?? '')),
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_ip' => $createdIp,
        ], true);

        $lineNo = 0;
        foreach ($lines as $line) {
            $lineNo++;
            $normalized = $this->normalizeLine($line);
            $normalized['voucher_id'] = $voucherId;
            $normalized['line_no'] = $lineNo;
            $this->voucherLineModel->insert($normalized);

            $this->applyInventoryMovement(
                $fromWarehouseId,
                $fromBinId,
                $toWarehouseId,
                $toBinId,
                $normalized
            );

            $this->applyAccountMovement($debitAccountId, $creditAccountId, $normalized);

            $this->ledgerEntryModel->insert([
                'voucher_id' => $voucherId,
                'line_no' => $lineNo,
                'debit_account_id' => $debitAccountId,
                'credit_account_id' => $creditAccountId,
                'item_type' => $normalized['item_type'],
                'item_key' => $normalized['item_key'],
                'qty_pcs' => $normalized['qty_pcs'],
                'qty_cts' => $normalized['qty_cts'],
                'qty_weight' => $normalized['qty_weight'],
                'fine_gold_qty' => $normalized['fine_gold'],
                'order_id' => isset($header['order_id']) ? (int) $header['order_id'] : null,
                'job_card_id' => isset($header['job_card_id']) ? (int) $header['job_card_id'] : null,
            ]);
        }

        $this->logAudit('voucher', $voucherId, 'POST', null, ['header' => $header, 'lines' => $lines], $createdBy, $createdIp);

        $this->db->transComplete();

        return [
            'voucher_id' => $voucherId,
            'voucher_no' => $voucherNo,
            'status' => 'Posted',
        ];
    }

    /**
     * @param array<string,mixed> $newHeader
     * @param array<int,array<string,mixed>> $newLines
     * @return array<string,mixed>
     */
    public function reverseAndRepost(int $voucherId, string $reason, array $newHeader, array $newLines): array
    {
        $this->db->transException(true)->transStart();

        $reverseResult = $this->reverseVoucher($voucherId, $reason, (int) ($newHeader['created_by'] ?? 0), true);
        $postResult = $this->postVoucher($newHeader, $newLines);

        $this->db->transComplete();

        return [
            'reversal' => $reverseResult,
            'new_voucher' => $postResult,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function reverseVoucher(int $voucherId, string $reason, int $userId = 0, bool $inTransaction = false): array
    {
        if (! $inTransaction) {
            $this->db->transException(true)->transStart();
        }

        $voucher = $this->voucherModel->find($voucherId);
        if (! $voucher) {
            throw new RuntimeException('Voucher not found.');
        }
        if ((int) ($voucher['is_reversal'] ?? 0) === 1) {
            throw new RuntimeException('Reversal voucher cannot be reversed again.');
        }
        if ((string) ($voucher['status'] ?? '') === 'Reversed') {
            throw new RuntimeException('Voucher already reversed.');
        }

        $lines = $this->voucherLineModel->where('voucher_id', $voucherId)->orderBy('line_no', 'ASC')->findAll();
        if ($lines === []) {
            throw new RuntimeException('Voucher lines not found.');
        }

        $reverseHeader = [
            'voucher_type' => 'REVERSAL_' . (string) $voucher['voucher_type'],
            'voucher_date' => date('Y-m-d'),
            'from_warehouse_id' => $voucher['to_warehouse_id'] ?? null,
            'from_bin_id' => $voucher['to_bin_id'] ?? null,
            'to_warehouse_id' => $voucher['from_warehouse_id'] ?? null,
            'to_bin_id' => $voucher['from_bin_id'] ?? null,
            'order_id' => $voucher['order_id'] ?? null,
            'job_card_id' => $voucher['job_card_id'] ?? null,
            'party_id' => $voucher['party_id'] ?? null,
            'debit_account_id' => $voucher['credit_account_id'] ?? null,
            'credit_account_id' => $voucher['debit_account_id'] ?? null,
            'remarks' => 'Reversal of ' . $voucher['voucher_no'] . ' | Reason: ' . $reason,
            'created_by' => $userId,
            'created_ip' => service('request')->getIPAddress() ?? null,
        ];

        $reverseLines = [];
        foreach ($lines as $line) {
            $reverseLines[] = [
                'item_type' => $line['item_type'],
                'item_key' => $line['item_key'],
                'material_name' => $line['material_name'] ?? null,
                'bag_id' => $line['bag_id'] ?? null,
                'tag_no' => $line['tag_no'] ?? null,
                'gold_purity_id' => $line['gold_purity_id'] ?? null,
                'shape' => $line['shape'] ?? null,
                'chalni_size' => $line['chalni_size'] ?? null,
                'color' => $line['color'] ?? null,
                'clarity' => $line['clarity'] ?? null,
                'stone_type' => $line['stone_type'] ?? null,
                'qty_pcs' => (float) $line['qty_pcs'],
                'qty_cts' => (float) $line['qty_cts'],
                'qty_weight' => (float) $line['qty_weight'],
                'fine_gold' => (float) ($line['fine_gold'] ?? 0),
                'rate' => (float) ($line['rate'] ?? 0),
                'amount' => (float) ($line['amount'] ?? 0),
                'remarks' => 'Reversal line for voucher #' . $voucher['voucher_no'],
            ];
        }

        $result = $this->postVoucher($reverseHeader, $reverseLines);
        $reversalVoucherId = (int) $result['voucher_id'];

        $this->voucherModel->update($voucherId, ['status' => 'Reversed']);
        $this->voucherModel->update($reversalVoucherId, [
            'is_reversal' => 1,
            'reversal_of_id' => $voucherId,
        ]);

        $this->db->table('voucher_reversals')->insert([
            'original_voucher_id' => $voucherId,
            'reversal_voucher_id' => $reversalVoucherId,
            'reason' => $reason,
            'created_by' => $userId > 0 ? $userId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logAudit('voucher', $voucherId, 'REVERSE', $voucher, ['reversal_voucher_id' => $reversalVoucherId, 'reason' => $reason], $userId, service('request')->getIPAddress() ?? null);

        if (! $inTransaction) {
            $this->db->transComplete();
        }

        return [
            'original_voucher_id' => $voucherId,
            'reversal_voucher_id' => $reversalVoucherId,
            'reversal_voucher_no' => $result['voucher_no'],
        ];
    }

    private function generateVoucherNo(string $voucherType, string $voucherDate): string
    {
        $dateToken = str_replace('-', '', $voucherDate);
        $prefix = substr(preg_replace('/[^A-Z]/', '', $voucherType), 0, 4);
        if ($prefix === '') {
            $prefix = 'VCH';
        }

        $like = $prefix . '-' . $dateToken . '-%';
        $last = $this->voucherModel->like('voucher_no', $like, 'after')->orderBy('id', 'DESC')->first();
        $n = 1;
        if ($last && preg_match('/-(\d+)$/', (string) $last['voucher_no'], $m)) {
            $n = ((int) $m[1]) + 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $dateToken, $n);
    }

    /**
     * @param array<string,mixed> $line
     * @return array<string,mixed>
     */
    private function normalizeLine(array $line): array
    {
        $itemType = strtoupper(trim((string) ($line['item_type'] ?? '')));
        if ($itemType === '') {
            throw new RuntimeException('Line item_type is required.');
        }

        $itemKey = trim((string) ($line['item_key'] ?? ''));
        if ($itemKey === '') {
            $itemKey = $this->buildItemKey($itemType, $line);
        }

        $qtyPcs = round((float) ($line['qty_pcs'] ?? 0), 3);
        $qtyCts = round((float) ($line['qty_cts'] ?? 0), 3);
        $qtyWeight = round((float) ($line['qty_weight'] ?? 0), 3);

        if ($qtyPcs <= 0 && $qtyCts <= 0 && $qtyWeight <= 0) {
            throw new RuntimeException('Voucher line quantity must be greater than zero.');
        }

        return [
            'item_type' => $itemType,
            'item_key' => $itemKey,
            'material_name' => $line['material_name'] ?? null,
            'bag_id' => isset($line['bag_id']) ? (int) $line['bag_id'] : null,
            'tag_no' => $line['tag_no'] ?? null,
            'gold_purity_id' => isset($line['gold_purity_id']) ? (int) $line['gold_purity_id'] : null,
            'shape' => $line['shape'] ?? null,
            'chalni_size' => $line['chalni_size'] ?? null,
            'color' => $line['color'] ?? null,
            'clarity' => $line['clarity'] ?? null,
            'stone_type' => $line['stone_type'] ?? null,
            'qty_pcs' => $qtyPcs,
            'qty_cts' => $qtyCts,
            'qty_weight' => $qtyWeight,
            'fine_gold' => round((float) ($line['fine_gold'] ?? 0), 3),
            'rate' => round((float) ($line['rate'] ?? 0), 3),
            'amount' => round((float) ($line['amount'] ?? 0), 2),
            'remarks' => $line['remarks'] ?? null,
        ];
    }

    /**
     * @param array<string,mixed> $line
     */
    private function applyInventoryMovement(int $fromWarehouseId, int $fromBinId, int $toWarehouseId, int $toBinId, array $line): void
    {
        if ($fromWarehouseId > 0) {
            $this->updateInventoryBalance($fromWarehouseId, $fromBinId, $line, -1);
        }

        if ($toWarehouseId > 0) {
            $this->updateInventoryBalance($toWarehouseId, $toBinId, $line, 1);
        }
    }

    /**
     * @param array<string,mixed> $line
     */
    private function updateInventoryBalance(int $warehouseId, int $binId, array $line, int $direction): void
    {
        $row = $this->lockInventoryBalanceRow($warehouseId, $binId, (string) $line['item_type'], (string) $line['item_key']);

        $newPcs = round((float) ($row['qty_pcs'] ?? 0) + ($direction * (float) $line['qty_pcs']), 3);
        $newCts = round((float) ($row['qty_cts'] ?? 0) + ($direction * (float) $line['qty_cts']), 3);
        $newWeight = round((float) ($row['qty_weight'] ?? 0) + ($direction * (float) $line['qty_weight']), 3);
        $newFineGold = round((float) ($row['fine_gold_qty'] ?? 0) + ($direction * (float) $line['fine_gold']), 3);

        if ($newPcs < -0.0001 || $newCts < -0.0001 || $newWeight < -0.0001 || $newFineGold < -0.0001) {
            throw new RuntimeException('Insufficient stock for item ' . $line['item_key']);
        }

        $this->inventoryBalanceModel->update((int) $row['id'], [
            'item_key' => $line['item_key'],
            'qty_pcs' => $newPcs,
            'qty_cts' => $newCts,
            'qty_weight' => $newWeight,
            'fine_gold_qty' => $newFineGold,
            'pcs_balance' => $newPcs,
            'cts_balance' => $newCts,
            'weight_gm_balance' => $newWeight,
            'fine_gold_balance' => $newFineGold,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function lockInventoryBalanceRow(int $warehouseId, int $binId, string $itemType, string $itemKey): array
    {
        $query = $this->db->query(
            'SELECT * FROM inventory_balances WHERE warehouse_id = ? AND bin_id = ? AND item_type = ? AND item_key = ? FOR UPDATE',
            [$warehouseId, $binId, $itemType, $itemKey]
        );
        $row = $query->getRowArray();

        if ($row) {
            return $row;
        }

        $id = (int) $this->inventoryBalanceModel->insert([
            'warehouse_id' => $warehouseId,
            'bin_id' => $binId,
            'item_type' => $itemType,
            'item_key' => $itemKey,
            'material_name' => $itemKey,
            'qty_pcs' => 0,
            'qty_cts' => 0,
            'qty_weight' => 0,
            'fine_gold_qty' => 0,
            'pcs_balance' => 0,
            'cts_balance' => 0,
            'weight_gm_balance' => 0,
            'fine_gold_balance' => 0,
            'balance_key' => sha1($warehouseId . '|' . $binId . '|' . $itemType . '|' . $itemKey),
        ], true);

        $row = $this->db->query('SELECT * FROM inventory_balances WHERE id = ? FOR UPDATE', [$id])->getRowArray();
        return $row ?: [];
    }

    /**
     * @param array<string,mixed> $line
     */
    private function applyAccountMovement(int $debitAccountId, int $creditAccountId, array $line): void
    {
        $this->updateAccountBalance($debitAccountId, $line, 1);
        $this->updateAccountBalance($creditAccountId, $line, -1);
    }

    /**
     * @param array<string,mixed> $line
     */
    private function updateAccountBalance(int $accountId, array $line, int $direction): void
    {
        if ($accountId <= 0) {
            throw new RuntimeException('Invalid account id in posting.');
        }

        $query = $this->db->query(
            'SELECT * FROM account_balances WHERE account_id = ? AND item_type = ? AND item_key = ? FOR UPDATE',
            [$accountId, $line['item_type'], $line['item_key']]
        );
        $row = $query->getRowArray();

        if (! $row) {
            $id = (int) $this->accountBalanceModel->insert([
                'account_id' => $accountId,
                'item_type' => $line['item_type'],
                'item_key' => $line['item_key'],
                'qty_pcs' => 0,
                'qty_cts' => 0,
                'qty_weight' => 0,
                'fine_gold_qty' => 0,
            ], true);
            $row = $this->db->query('SELECT * FROM account_balances WHERE id = ? FOR UPDATE', [$id])->getRowArray();
        }

        $this->accountBalanceModel->update((int) $row['id'], [
            'qty_pcs' => round((float) $row['qty_pcs'] + $direction * (float) $line['qty_pcs'], 3),
            'qty_cts' => round((float) $row['qty_cts'] + $direction * (float) $line['qty_cts'], 3),
            'qty_weight' => round((float) $row['qty_weight'] + $direction * (float) $line['qty_weight'], 3),
            'fine_gold_qty' => round((float) $row['fine_gold_qty'] + $direction * (float) $line['fine_gold'], 3),
        ]);
    }

    /**
     * @param array<string,mixed> $line
     */
    private function buildItemKey(string $itemType, array $line): string
    {
        if ($itemType === 'DIAMOND_BAG' || $itemType === 'DIAMOND') {
            if (! empty($line['bag_id'])) {
                return 'BAG-' . (int) $line['bag_id'];
            }
            return strtoupper(trim((string) (($line['shape'] ?? 'NA') . '|' . ($line['chalni_size'] ?? 'NA') . '|' . ($line['color'] ?? 'NA') . '|' . ($line['clarity'] ?? 'NA'))));
        }

        if ($itemType === 'FG') {
            return 'TAG-' . strtoupper(trim((string) ($line['tag_no'] ?? 'UNSET')));
        }

        if ($itemType === 'GOLD') {
            return 'GOLD-' . (string) ((int) ($line['gold_purity_id'] ?? 0)) . '-' . strtoupper(trim((string) ($line['material_name'] ?? 'BASIC')));
        }

        if ($itemType === 'STONE') {
            return 'STONE-' . strtoupper(trim((string) (($line['stone_type'] ?? 'NA') . '|' . ($line['material_name'] ?? 'NA'))));
        }

        return strtoupper(trim((string) ($line['material_name'] ?? $itemType)));
    }

    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    private function logAudit(string $entityType, int $entityId, string $action, ?array $before, ?array $after, int $userId, ?string $ip): void
    {
        if (! $this->db->tableExists('audit_logs')) {
            return;
        }

        $this->db->table('audit_logs')->insert([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'before_json' => $before ? json_encode($before) : null,
            'after_json' => $after ? json_encode($after) : null,
            'ip_address' => $ip,
            'created_by' => $userId > 0 ? $userId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function ensureAccount(string $type, string $code, string $name, ?string $referenceTable = null, ?int $referenceId = null): int
    {
        $exists = $this->accountModel->where('account_code', $code)->first();
        if ($exists) {
            return (int) $exists['id'];
        }

        return (int) $this->accountModel->insert([
            'account_code' => $code,
            'account_name' => $name,
            'account_type' => strtoupper($type),
            'reference_table' => $referenceTable,
            'reference_id' => $referenceId,
            'is_active' => 1,
        ], true);
    }
}
