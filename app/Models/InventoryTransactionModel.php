<?php

namespace App\Models;

use CodeIgniter\Model;
use RuntimeException;

class InventoryTransactionModel extends Model
{
    protected $table         = 'inventory_transactions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'voucher_no',
        'voucher_group',
        'txn_date',
        'txn_datetime',
        'transaction_type',
        'location_id',
        'counter_location_id',
        'from_warehouse_id',
        'from_bin_id',
        'to_warehouse_id',
        'to_bin_id',
        'item_type',
        'material_name',
        'gold_purity_id',
        'fine_gold_gm',
        'diamond_shape',
        'diamond_sieve',
        'diamond_sieve_min',
        'diamond_sieve_max',
        'diamond_color',
        'diamond_clarity',
        'diamond_cut',
        'diamond_quality',
        'diamond_fluorescence',
        'diamond_lab',
        'certificate_no',
        'packet_no',
        'lot_no',
        'stone_type',
        'stone_size',
        'stone_color_shade',
        'stone_quality_grade',
        'pcs',
        'weight_gm',
        'cts',
        'qty_sign',
        'reference_type',
        'reference_id',
        'document_type',
        'document_no',
        'reversal_of_id',
        'reversed_by_id',
        'reversed_at',
        'reversal_reason',
        'immutable_hash',
        'is_reversal',
        'is_void',
        'status',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';

    protected $allowCallbacks = true;
    protected $beforeInsert   = ['prepareInsertPayload'];
    protected $afterInsert    = ['syncBalances'];

    public function update($id = null, $row = null): bool
    {
        throw new RuntimeException('Inventory transactions are immutable. Use reversal and new voucher entry.');
    }

    public function delete($id = null, bool $purge = false)
    {
        throw new RuntimeException('Inventory transactions cannot be deleted.');
    }

    protected function prepareInsertPayload(array $payload): array
    {
        if (! isset($payload['data']) || ! is_array($payload['data'])) {
            return $payload;
        }

        $row = $payload['data'];

        $txnDate = trim((string) ($row['txn_date'] ?? ''));
        if ($txnDate === '') {
            $txnDate = date('Y-m-d');
            $row['txn_date'] = $txnDate;
        }

        if (trim((string) ($row['txn_datetime'] ?? '')) === '') {
            $row['txn_datetime'] = $txnDate . ' ' . date('H:i:s');
        }

        if (trim((string) ($row['voucher_no'] ?? '')) === '') {
            $row['voucher_no'] = $this->nextVoucherNo($txnDate);
        }

        if (trim((string) ($row['voucher_group'] ?? '')) === '') {
            $row['voucher_group'] = (string) $row['voucher_no'];
        }

        $transactionType = strtolower(trim((string) ($row['transaction_type'] ?? '')));
        $qtySign = (int) ($row['qty_sign'] ?? 0);
        if ($qtySign === 0) {
            $qtySign = $this->resolveSign($transactionType);
            $row['qty_sign'] = $qtySign;
        }

        $locationId = $this->nullableInt($row['location_id'] ?? null);
        $counterLocationId = $this->nullableInt($row['counter_location_id'] ?? null);

        $fromWarehouseId = $this->nullableInt($row['from_warehouse_id'] ?? null);
        $toWarehouseId = $this->nullableInt($row['to_warehouse_id'] ?? null);

        if ($fromWarehouseId === null || $toWarehouseId === null) {
            if ($transactionType === 'transfer_out') {
                $fromWarehouseId = $fromWarehouseId ?? $locationId;
                $toWarehouseId = $toWarehouseId ?? $counterLocationId;
            } elseif ($transactionType === 'transfer_in') {
                $fromWarehouseId = $fromWarehouseId ?? $counterLocationId;
                $toWarehouseId = $toWarehouseId ?? $locationId;
            } elseif ($this->isOutType($transactionType)) {
                $fromWarehouseId = $fromWarehouseId ?? $locationId;
            } elseif ($this->isInType($transactionType)) {
                $toWarehouseId = $toWarehouseId ?? $locationId;
            } elseif ($qtySign < 0) {
                $fromWarehouseId = $fromWarehouseId ?? $locationId;
            } else {
                $toWarehouseId = $toWarehouseId ?? $locationId;
            }
        }

        $row['from_warehouse_id'] = $fromWarehouseId;
        $row['to_warehouse_id'] = $toWarehouseId;

        $fromBinId = $this->nullableInt($row['from_bin_id'] ?? null);
        $toBinId = $this->nullableInt($row['to_bin_id'] ?? null);

        if ($fromBinId === null && $fromWarehouseId !== null) {
            $fromBinId = $this->resolveDefaultBinId($fromWarehouseId);
        }
        if ($toBinId === null && $toWarehouseId !== null) {
            $toBinId = $this->resolveDefaultBinId($toWarehouseId);
        }

        $row['from_bin_id'] = $fromBinId;
        $row['to_bin_id'] = $toBinId;

        if (! isset($row['fine_gold_gm']) || (float) $row['fine_gold_gm'] <= 0) {
            $row['fine_gold_gm'] = $this->calculateFineGold($row);
        }

        if (trim((string) ($row['status'] ?? '')) === '') {
            $row['status'] = 'posted';
        }

        if (! isset($row['is_reversal'])) {
            $row['is_reversal'] = 0;
        }

        if (! isset($row['is_void'])) {
            $row['is_void'] = 0;
        }

        if (trim((string) ($row['immutable_hash'] ?? '')) === '') {
            $row['immutable_hash'] = hash(
                'sha256',
                implode('|', [
                    (string) $row['voucher_no'],
                    (string) $row['txn_datetime'],
                    (string) ($row['transaction_type'] ?? ''),
                    (string) ($row['item_type'] ?? ''),
                    (string) ($row['material_name'] ?? ''),
                    microtime(true),
                ])
            );
        }

        $payload['data'] = $row;

        return $payload;
    }

    protected function syncBalances(array $payload): array
    {
        if (! $this->db->tableExists('inventory_balances')) {
            return $payload;
        }

        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return $payload;
        }

        $row = $this->where('id', $id)->first();
        if (! is_array($row)) {
            return $payload;
        }

        if ((int) ($row['is_void'] ?? 0) === 1 || (string) ($row['status'] ?? 'posted') !== 'posted') {
            return $payload;
        }

        $this->applyBalanceDelta($row);

        return $payload;
    }

    private function applyBalanceDelta(array $row): void
    {
        $sign = (int) ($row['qty_sign'] ?? 0);
        if ($sign === 0) {
            $sign = $this->resolveSign((string) ($row['transaction_type'] ?? ''));
        }
        if ($sign === 0) {
            return;
        }

        $warehouseId = $sign < 0
            ? $this->nullableInt($row['from_warehouse_id'] ?? ($row['location_id'] ?? null))
            : $this->nullableInt($row['to_warehouse_id'] ?? ($row['location_id'] ?? null));

        $binId = $sign < 0
            ? $this->nullableInt($row['from_bin_id'] ?? null)
            : $this->nullableInt($row['to_bin_id'] ?? null);

        if ($binId === null && $warehouseId !== null) {
            $binId = $this->resolveDefaultBinId($warehouseId);
        }

        $dimensions = [
            'item_type' => trim((string) ($row['item_type'] ?? '')),
            'material_name' => trim((string) ($row['material_name'] ?? '')),
            'gold_purity_id' => $this->nullableInt($row['gold_purity_id'] ?? null),
            'diamond_shape' => $this->normalizeString($row['diamond_shape'] ?? null),
            'diamond_sieve' => $this->normalizeString($row['diamond_sieve'] ?? null),
            'diamond_sieve_min' => $this->nullableDecimal($row['diamond_sieve_min'] ?? null),
            'diamond_sieve_max' => $this->nullableDecimal($row['diamond_sieve_max'] ?? null),
            'diamond_color' => $this->normalizeString($row['diamond_color'] ?? null),
            'diamond_clarity' => $this->normalizeString($row['diamond_clarity'] ?? null),
            'diamond_cut' => $this->normalizeString($row['diamond_cut'] ?? null),
            'diamond_quality' => $this->normalizeString($row['diamond_quality'] ?? null),
            'diamond_fluorescence' => $this->normalizeString($row['diamond_fluorescence'] ?? null),
            'diamond_lab' => $this->normalizeString($row['diamond_lab'] ?? null),
            'certificate_no' => $this->normalizeString($row['certificate_no'] ?? null),
            'packet_no' => $this->normalizeString($row['packet_no'] ?? null),
            'lot_no' => $this->normalizeString($row['lot_no'] ?? null),
            'stone_type' => $this->normalizeString($row['stone_type'] ?? null),
            'stone_size' => $this->normalizeString($row['stone_size'] ?? null),
            'stone_color_shade' => $this->normalizeString($row['stone_color_shade'] ?? null),
            'stone_quality_grade' => $this->normalizeString($row['stone_quality_grade'] ?? null),
            'warehouse_id' => $warehouseId,
            'bin_id' => $binId,
        ];

        $balanceKey = sha1(json_encode($dimensions));
        $deltaPcs = round($sign * (float) ($row['pcs'] ?? 0), 3);
        $deltaGm = round($sign * (float) ($row['weight_gm'] ?? 0), 3);
        $deltaCts = round($sign * (float) ($row['cts'] ?? 0), 3);
        $deltaFineGold = round($sign * (float) ($row['fine_gold_gm'] ?? 0), 3);

        $table = $this->db->table('inventory_balances');
        $existing = $table->where('balance_key', $balanceKey)->get()->getRowArray();

        if ($existing) {
            $table->where('id', (int) $existing['id'])->update([
                'pcs_balance' => round((float) ($existing['pcs_balance'] ?? 0) + $deltaPcs, 3),
                'weight_gm_balance' => round((float) ($existing['weight_gm_balance'] ?? 0) + $deltaGm, 3),
                'cts_balance' => round((float) ($existing['cts_balance'] ?? 0) + $deltaCts, 3),
                'fine_gold_balance' => round((float) ($existing['fine_gold_balance'] ?? 0) + $deltaFineGold, 3),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return;
        }

        $table->insert($dimensions + [
            'balance_key' => $balanceKey,
            'pcs_balance' => $deltaPcs,
            'weight_gm_balance' => $deltaGm,
            'cts_balance' => $deltaCts,
            'fine_gold_balance' => $deltaFineGold,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function calculateFineGold(array $row): float
    {
        if (strtolower(trim((string) ($row['item_type'] ?? ''))) !== 'gold') {
            return 0.0;
        }

        $weight = (float) ($row['weight_gm'] ?? 0);
        if ($weight <= 0) {
            return 0.0;
        }

        $purityId = $this->nullableInt($row['gold_purity_id'] ?? null);
        if ($purityId === null || ! $this->db->tableExists('gold_purities')) {
            return round($weight, 3);
        }

        $purity = $this->db->table('gold_purities')
            ->select('purity_percent')
            ->where('id', $purityId)
            ->get()
            ->getRowArray();

        if (! $purity) {
            return round($weight, 3);
        }

        $percent = (float) ($purity['purity_percent'] ?? 0);
        if ($percent <= 0) {
            return round($weight, 3);
        }

        return round($weight * ($percent / 100), 3);
    }

    private function resolveSign(string $transactionType): int
    {
        $type = strtolower(trim($transactionType));
        if ($this->isOutType($type)) {
            return -1;
        }
        return 1;
    }

    private function isInType(string $transactionType): bool
    {
        return in_array($transactionType, [
            'purchase',
            'receive',
            'adjustment_plus',
            'transfer_in',
            'production_receive',
            'return_in',
        ], true);
    }

    private function isOutType(string $transactionType): bool
    {
        return in_array($transactionType, [
            'issue',
            'transfer_out',
            'adjustment_minus',
            'dispatch',
            'consume',
            'loss',
            'breakage',
            'return_out',
        ], true);
    }

    private function resolveDefaultBinId(int $warehouseId): ?int
    {
        if ($warehouseId <= 0 || ! $this->db->tableExists('inventory_bins')) {
            return null;
        }

        $row = $this->db->table('inventory_bins')
            ->select('id')
            ->where('location_id', $warehouseId)
            ->where('is_active', 1)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();

        return $row ? (int) $row['id'] : null;
    }

    private function nextVoucherNo(string $txnDate): string
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $txnDate) ? $txnDate : date('Y-m-d');
        $dateToken = str_replace('-', '', $date);

        if (! $this->db->tableExists('inventory_voucher_counters')) {
            return 'INV-' . $dateToken . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }

        $counterTable = $this->db->table('inventory_voucher_counters');
        $this->db->transStart();

        $row = $counterTable->where('counter_date', $date)->get()->getRowArray();
        if (! $row) {
            $next = 1;
            $counterTable->insert([
                'counter_date' => $date,
                'last_number'  => $next,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        } else {
            $next = ((int) ($row['last_number'] ?? 0)) + 1;
            $counterTable->where('id', (int) $row['id'])->update([
                'last_number' => $next,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        }

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return 'INV-' . $dateToken . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }

        return sprintf('INV-%s-%05d', $dateToken, $next);
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableDecimal($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function normalizeString($value): ?string
    {
        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }
}
