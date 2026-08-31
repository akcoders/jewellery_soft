<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class KarigarMaterialAccountingService
{
    private PostingService $postingService;
    private AdminPostingService $adminPostingService;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
        $this->postingService = new PostingService($this->db);
        $this->adminPostingService = new AdminPostingService($this->postingService);
    }

    public function postInventoryHeader(string $material, string $direction, int $headerId): int
    {
        $config = $this->movementConfig($material, $direction);
        $header = $this->db->table($config['header_table'])->where('id', $headerId)->get()->getRowArray();
        if (! $header) {
            throw new RuntimeException('Material transaction header was not found.');
        }

        $karigarId = (int) ($header['karigar_id'] ?? 0);
        $locationId = (int) ($header['location_id'] ?? 0);
        if ($locationId <= 0 && $direction === 'return' && (int) ($header['issue_id'] ?? 0) > 0) {
            $issueHeaderTable = [
                'gold' => 'gold_inventory_issue_headers',
                'diamond' => 'issue_headers',
                'stone' => 'stone_inventory_issue_headers',
            ][$material] ?? '';
            if ($issueHeaderTable !== '') {
                $issueHeader = $this->db->table($issueHeaderTable)
                    ->select('location_id')
                    ->where('id', (int) $header['issue_id'])
                    ->get()
                    ->getRowArray();
                $locationId = (int) ($issueHeader['location_id'] ?? 0);
            }
        }
        if ($karigarId <= 0 || $locationId <= 0) {
            throw new RuntimeException('Karigar and location are required for material accounting.');
        }

        $line = $this->aggregateLine($material, $config, $headerId);
        $voucherId = $this->postAccountMovement(
            $direction,
            strtoupper($material . '_' . $direction),
            $karigarId,
            $locationId,
            [$line],
            [
                'voucher_date' => (string) ($header[$config['date_field']] ?? date('Y-m-d')),
                'remarks' => sprintf(
                    '%s %s %s',
                    ucfirst($material),
                    $direction,
                    trim((string) ($header['voucher_no'] ?? ('#' . $headerId)))
                ),
                'created_by' => (int) ($header['created_by'] ?? (session('admin_id') ?: 0)),
            ]
        );

        $this->db->table($config['header_table'])->where('id', $headerId)->update([
            'account_voucher_id' => $voucherId,
        ]);

        return $voucherId;
    }

    /**
     * Refresh the accounting effect of an edited material transaction in place.
     * This deliberately keeps one voucher and one set of ledger rows per source.
     */
    public function refreshInventoryHeaderVoucher(string $material, string $direction, int $headerId): int
    {
        $config = $this->movementConfig($material, $direction);
        $header = $this->db->table($config['header_table'])->where('id', $headerId)->get()->getRowArray();
        if (! $header) {
            throw new RuntimeException('Material transaction header was not found.');
        }

        $voucherId = (int) ($header['account_voucher_id'] ?? 0);
        if ($voucherId <= 0) {
            return $this->postInventoryHeader($material, $direction, $headerId);
        }

        $karigarId = (int) ($header['karigar_id'] ?? 0);
        $locationId = (int) ($header['location_id'] ?? 0);
        if ($karigarId <= 0 || $locationId <= 0) {
            throw new RuntimeException('Karigar and location are required for material accounting.');
        }

        $line = $this->aggregateLine($material, $config, $headerId);
        return $this->postAccountMovement(
            $direction,
            strtoupper($material . '_' . $direction),
            $karigarId,
            $locationId,
            [$line],
            [
                'voucher_date' => (string) ($header[$config['date_field']] ?? date('Y-m-d')),
                'remarks' => sprintf(
                    '%s %s %s',
                    ucfirst($material),
                    $direction,
                    trim((string) ($header['voucher_no'] ?? ('#' . $headerId)))
                ),
                'created_by' => (int) ($header['created_by'] ?? (session('admin_id') ?: 0)),
            ],
            $voucherId
        );
    }

    public function reverseHeaderVoucher(string $headerTable, int $headerId, string $reason, int $createdBy = 0): void
    {
        if (! $this->db->fieldExists('account_voucher_id', $headerTable)) {
            return;
        }
        $header = $this->db->table($headerTable)->select('account_voucher_id')->where('id', $headerId)->get()->getRowArray();
        $voucherId = (int) ($header['account_voucher_id'] ?? 0);
        if ($voucherId <= 0) {
            return;
        }

        $this->postingService->reverseVoucher($voucherId, $reason, $createdBy, true, true);
        $this->db->table($headerTable)->where('id', $headerId)->update(['account_voucher_id' => null]);
    }

    public function postFinishedJewelleryReceipt(
        int $orderId,
        int $karigarId,
        int $locationId,
        float $pureGoldGm,
        float $diamondPcs,
        float $diamondCts,
        string $remarks,
        int $createdBy = 0,
        ?string $voucherDate = null,
        float $stoneCts = 0.0
    ): int {
        if ($karigarId <= 0) {
            throw new RuntimeException('An assigned karigar is required before receiving jewellery.');
        }

        $lines = [];
        if ($pureGoldGm > 0) {
            $lines[] = $this->goldLine($pureGoldGm);
        }
        if ($diamondCts > 0 || $diamondPcs > 0) {
            $lines[] = $this->diamondLine($diamondPcs, $diamondCts);
        }
        if ($stoneCts > 0) {
            $lines[] = $this->stoneLine($stoneCts);
        }
        if ($lines === []) {
            throw new RuntimeException('Pure gold, diamond, or stone detail is required for karigar settlement.');
        }

        $karigarAccountId = $this->karigarAccountId($karigarId);
        foreach ($lines as $line) {
            $this->assertAvailableBalance($karigarAccountId, $line);
        }

        $finishedAccountId = $this->postingService->ensureAccount(
            'FINISHED_JEWELLERY',
            'FG-MATERIAL',
            'Finished Jewellery Material',
            'system',
            1
        );
        $warehouse = $this->adminPostingService->resolveWarehouseBinByLocation($locationId);

        $result = $this->postingService->postVoucher([
            'voucher_type' => 'JEWELLERY_RECEIVE',
            'voucher_date' => $voucherDate ?: date('Y-m-d'),
            'order_id' => $orderId,
            'party_id' => $karigarId,
            'to_warehouse_id' => $warehouse['warehouse_id'],
            'to_bin_id' => $warehouse['bin_id'],
            'debit_account_id' => $finishedAccountId,
            'credit_account_id' => $karigarAccountId,
            'skip_inventory_movement' => true,
            'remarks' => $remarks,
            'created_by' => $createdBy,
        ], $lines);

        return (int) $result['voucher_id'];
    }

    public function stoneReceiptShortfall(int $karigarId, float $stoneCts): float
    {
        $stoneCts = round(max(0, $stoneCts), 3);
        if ($stoneCts <= 0) {
            return 0.0;
        }

        $accountId = $this->karigarAccountId($karigarId);
        $balance = $this->db->table('account_balances')
            ->select('qty_cts')
            ->where('account_id', $accountId)
            ->where('item_type', 'STONE')
            ->where('item_key', 'STONE-POOL')
            ->get()
            ->getRowArray();
        $available = max(0, (float) ($balance['qty_cts'] ?? 0));

        return round(max(0, $stoneCts - $available), 3);
    }

    /**
     * @param list<array<string,mixed>> $lines
     * @param array<string,mixed> $meta
     */
    private function postAccountMovement(
        string $direction,
        string $voucherType,
        int $karigarId,
        int $locationId,
        array $lines,
        array $meta,
        ?int $replaceVoucherId = null
    ): int {
        $warehouse = $this->adminPostingService->resolveWarehouseBinByLocation($locationId);
        $warehouseAccountId = $this->postingService->ensureAccount(
            'WAREHOUSE',
            'WH-' . $warehouse['warehouse_id'],
            $warehouse['warehouse_name'] . ' Warehouse',
            'warehouses',
            $warehouse['warehouse_id']
        );
        $karigarAccountId = $this->karigarAccountId($karigarId);

        if ($direction === 'return') {
            foreach ($lines as $line) {
                $this->assertAvailableBalance($karigarAccountId, $line);
            }
        }

        $header = [
            'voucher_type' => $voucherType,
            'voucher_date' => (string) ($meta['voucher_date'] ?? date('Y-m-d')),
            'party_id' => $karigarId,
            'skip_inventory_movement' => true,
            'remarks' => (string) ($meta['remarks'] ?? ''),
            'created_by' => (int) ($meta['created_by'] ?? 0),
        ];
        if ($direction === 'issue') {
            $header += [
                'from_warehouse_id' => $warehouse['warehouse_id'],
                'from_bin_id' => $warehouse['bin_id'],
                'debit_account_id' => $karigarAccountId,
                'credit_account_id' => $warehouseAccountId,
            ];
        } else {
            $header += [
                'to_warehouse_id' => $warehouse['warehouse_id'],
                'to_bin_id' => $warehouse['bin_id'],
                'debit_account_id' => $warehouseAccountId,
                'credit_account_id' => $karigarAccountId,
            ];
        }

        $result = $replaceVoucherId !== null && $replaceVoucherId > 0
            ? $this->postingService->replaceVoucher($replaceVoucherId, $header, $lines)
            : $this->postingService->postVoucher($header, $lines);
        return (int) $result['voucher_id'];
    }

    private function karigarAccountId(int $karigarId): int
    {
        $karigar = $this->db->table('karigars')->where('id', $karigarId)->get()->getRowArray();
        if (! $karigar) {
            throw new RuntimeException('Karigar was not found for material accounting.');
        }

        return $this->postingService->ensureAccount(
            'KARIGAR',
            'KARIGAR-' . $karigarId,
            'Karigar - ' . (string) $karigar['name'],
            'karigars',
            $karigarId
        );
    }

    /** @param array<string,mixed> $line */
    private function assertAvailableBalance(int $accountId, array $line): void
    {
        $balance = $this->db->table('account_balances')
            ->where('account_id', $accountId)
            ->where('item_type', (string) $line['item_type'])
            ->where('item_key', (string) $line['item_key'])
            ->get()
            ->getRowArray() ?? [];

        foreach (['qty_pcs', 'qty_cts', 'qty_weight', 'fine_gold_qty'] as $field) {
            $lineField = $field === 'fine_gold_qty' ? 'fine_gold' : $field;
            $required = (float) ($line[$lineField] ?? 0);
            if ($required > ((float) ($balance[$field] ?? 0) + 0.0005)) {
                throw new RuntimeException('Karigar ' . strtolower(str_replace('_', ' ', $field)) . ' balance is insufficient.');
            }
        }
    }

    /** @return array<string,mixed> */
    private function aggregateLine(string $material, array $config, int $headerId): array
    {
        $row = $this->db->table($config['line_table'])
            ->select($config['sum_sql'], false)
            ->where($config['foreign_key'], $headerId)
            ->get()
            ->getRowArray() ?? [];

        if ($material === 'gold') {
            $fine = round((float) ($row['fine_weight'] ?? 0), 3);
            return $this->goldLine($fine);
        }
        if ($material === 'diamond') {
            return $this->diamondLine((float) ($row['pcs'] ?? 0), (float) ($row['weight'] ?? 0));
        }

        $pcs = round((float) ($row['pcs'] ?? 0), 3);
        $weight = round((float) ($row['weight'] ?? 0), 3);
        if ($pcs <= 0 && $weight <= 0) {
            throw new RuntimeException('Stone transaction does not contain any quantity.');
        }
        return [
            'item_type' => 'STONE',
            'item_key' => 'STONE-POOL',
            'material_name' => 'Stone',
            'qty_pcs' => $pcs,
            'qty_cts' => $weight,
            'qty_weight' => 0,
            'fine_gold' => 0,
        ];
    }

    /** @return array<string,mixed> */
    private function goldLine(float $fineGoldGm): array
    {
        $fineGoldGm = round($fineGoldGm, 3);
        if ($fineGoldGm <= 0) {
            throw new RuntimeException('Pure gold weight must be greater than zero.');
        }
        return [
            'item_type' => 'GOLD',
            'item_key' => 'GOLD-FINE',
            'material_name' => 'Pure Gold',
            'qty_pcs' => 0,
            'qty_cts' => 0,
            'qty_weight' => $fineGoldGm,
            'fine_gold' => $fineGoldGm,
        ];
    }

    /** @return array<string,mixed> */
    private function diamondLine(float $pcs, float $cts): array
    {
        $pcs = round($pcs, 3);
        $cts = round($cts, 3);
        if ($pcs <= 0 && $cts <= 0) {
            throw new RuntimeException('Diamond quantity must be greater than zero.');
        }
        return [
            'item_type' => 'DIAMOND',
            'item_key' => 'DIAMOND-POOL',
            'material_name' => 'Diamond',
            'qty_pcs' => $pcs,
            'qty_cts' => $cts,
            'qty_weight' => 0,
            'fine_gold' => 0,
        ];
    }

    /** @return array<string,mixed> */
    private function stoneLine(float $cts): array
    {
        $cts = round($cts, 3);
        if ($cts <= 0) {
            throw new RuntimeException('Stone quantity must be greater than zero.');
        }

        return [
            'item_type' => 'STONE',
            'item_key' => 'STONE-POOL',
            'material_name' => 'Stone',
            // Stone accounting is maintained in the inventory unit (cts/qty).
            'qty_pcs' => 0,
            'qty_cts' => $cts,
            'qty_weight' => 0,
            'fine_gold' => 0,
        ];
    }

    /** @return array<string,string> */
    private function movementConfig(string $material, string $direction): array
    {
        $configs = [
            'gold:issue' => ['header_table' => 'gold_inventory_issue_headers', 'line_table' => 'gold_inventory_issue_lines', 'foreign_key' => 'issue_id', 'date_field' => 'issue_date', 'sum_sql' => 'COALESCE(SUM(fine_weight_gm),0) fine_weight'],
            'gold:return' => ['header_table' => 'gold_inventory_return_headers', 'line_table' => 'gold_inventory_return_lines', 'foreign_key' => 'return_id', 'date_field' => 'return_date', 'sum_sql' => 'COALESCE(SUM(fine_weight_gm),0) fine_weight'],
            'diamond:issue' => ['header_table' => 'issue_headers', 'line_table' => 'issue_lines', 'foreign_key' => 'issue_id', 'date_field' => 'issue_date', 'sum_sql' => 'COALESCE(SUM(pcs),0) pcs, COALESCE(SUM(carat),0) weight'],
            'diamond:return' => ['header_table' => 'return_headers', 'line_table' => 'return_lines', 'foreign_key' => 'return_id', 'date_field' => 'return_date', 'sum_sql' => 'COALESCE(SUM(pcs),0) pcs, COALESCE(SUM(carat),0) weight'],
            // Stone inventory is balanced by qty. PCS remains a voucher-line detail, not a second account balance unit.
            'stone:issue' => ['header_table' => 'stone_inventory_issue_headers', 'line_table' => 'stone_inventory_issue_lines', 'foreign_key' => 'issue_id', 'date_field' => 'issue_date', 'sum_sql' => '0 pcs, COALESCE(SUM(qty),0) weight'],
            // Stone return lines store quantity only; unlike issue lines they do not have a pcs column.
            'stone:return' => ['header_table' => 'stone_inventory_return_headers', 'line_table' => 'stone_inventory_return_lines', 'foreign_key' => 'return_id', 'date_field' => 'return_date', 'sum_sql' => '0 pcs, COALESCE(SUM(qty),0) weight'],
        ];
        $key = strtolower($material . ':' . $direction);
        if (! isset($configs[$key])) {
            throw new RuntimeException('Unsupported karigar material movement.');
        }
        return $configs[$key];
    }
}
