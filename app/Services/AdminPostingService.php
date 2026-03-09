<?php

namespace App\Services;

use App\Models\BinModel;
use App\Models\InventoryLocationModel;
use App\Models\KarigarModel;
use App\Models\WarehouseModel;
use RuntimeException;

class AdminPostingService
{
    private PostingService $postingService;
    private InventoryLocationModel $locationModel;
    private WarehouseModel $warehouseModel;
    private BinModel $binModel;
    private KarigarModel $karigarModel;

    public function __construct(?PostingService $postingService = null)
    {
        $this->postingService = $postingService ?? new PostingService();
        $this->locationModel = new InventoryLocationModel();
        $this->warehouseModel = new WarehouseModel();
        $this->binModel = new BinModel();
        $this->karigarModel = new KarigarModel();
    }

    /**
     * @param array<string,mixed> $line
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    public function postKarigarMaterialVoucher(
        string $direction,
        string $voucherType,
        int $locationId,
        int $karigarId,
        array $line,
        array $meta = []
    ): array {
        if (! in_array($direction, ['issue', 'return'], true)) {
            throw new RuntimeException('Invalid voucher direction.');
        }
        if ($locationId <= 0) {
            throw new RuntimeException('Location is required for voucher posting.');
        }
        if ($karigarId <= 0) {
            throw new RuntimeException('Karigar is required for voucher posting.');
        }

        $warehouse = $this->resolveWarehouseBinByLocation($locationId);
        $karigar = $this->karigarModel->find($karigarId);
        if (! $karigar) {
            throw new RuntimeException('Karigar not found for voucher posting.');
        }

        $warehouseAccountId = $this->postingService->ensureAccount(
            'WAREHOUSE',
            'WH-' . $warehouse['warehouse_id'],
            (string) $warehouse['warehouse_name'] . ' Warehouse',
            'warehouses',
            (int) $warehouse['warehouse_id']
        );

        $karigarAccountId = $this->postingService->ensureAccount(
            'KARIGAR',
            'KARIGAR-' . $karigarId,
            'Karigar - ' . (string) $karigar['name'],
            'karigars',
            $karigarId
        );

        $header = [
            'voucher_type' => strtoupper(trim($voucherType)),
            'voucher_date' => (string) ($meta['voucher_date'] ?? date('Y-m-d')),
            'order_id' => isset($meta['order_id']) ? (int) $meta['order_id'] : null,
            'job_card_id' => isset($meta['job_card_id']) ? (int) $meta['job_card_id'] : null,
            'party_id' => $karigarId,
            'remarks' => (string) ($meta['remarks'] ?? ''),
            'created_by' => (int) ($meta['created_by'] ?? (session('admin_id') ?: 0)),
        ];

        if ($direction === 'issue') {
            $header['from_warehouse_id'] = (int) $warehouse['warehouse_id'];
            $header['from_bin_id'] = (int) $warehouse['bin_id'];
            $header['debit_account_id'] = $karigarAccountId;
            $header['credit_account_id'] = $warehouseAccountId;
        } else {
            $header['to_warehouse_id'] = (int) $warehouse['warehouse_id'];
            $header['to_bin_id'] = (int) $warehouse['bin_id'];
            $header['debit_account_id'] = $warehouseAccountId;
            $header['credit_account_id'] = $karigarAccountId;
        }

        return $this->postingService->postVoucher($header, [$line]);
    }

    /**
     * @return array{warehouse_id:int,bin_id:int,warehouse_name:string,warehouse_code:string}
     */
    public function resolveWarehouseBinByLocation(int $locationId): array
    {
        $location = $this->locationModel->find($locationId);
        if (! $location) {
            throw new RuntimeException('Inventory location not found.');
        }

        $warehouseCode = $this->warehouseCodeFromLocation((string) ($location['location_type'] ?? ''), $locationId);
        $warehouse = $this->warehouseModel->where('warehouse_code', $warehouseCode)->first();
        if (! $warehouse) {
            $warehouseId = (int) $this->warehouseModel->insert([
                'warehouse_code' => $warehouseCode,
                'name' => (string) ($location['name'] ?? ('Location ' . $locationId)),
                'warehouse_type' => $warehouseCode,
                'address' => $location['address'] ?? null,
                'is_active' => 1,
            ], true);
            $warehouse = $this->warehouseModel->find($warehouseId);
        }

        if (! $warehouse) {
            throw new RuntimeException('Unable to resolve warehouse for location.');
        }

        $bin = $this->binModel
            ->where('warehouse_id', (int) $warehouse['id'])
            ->where('bin_code', 'MAIN')
            ->first();

        if (! $bin) {
            $binId = (int) $this->binModel->insert([
                'warehouse_id' => (int) $warehouse['id'],
                'bin_code' => 'MAIN',
                'name' => 'Main Bin',
                'is_active' => 1,
            ], true);
            $bin = $this->binModel->find($binId);
        }

        if (! $bin) {
            throw new RuntimeException('Unable to resolve bin for warehouse.');
        }

        return [
            'warehouse_id' => (int) $warehouse['id'],
            'bin_id' => (int) $bin['id'],
            'warehouse_name' => (string) $warehouse['name'],
            'warehouse_code' => (string) $warehouse['warehouse_code'],
        ];
    }

    private function warehouseCodeFromLocation(string $locationType, int $locationId): string
    {
        $type = strtoupper(trim($locationType));
        if ($type === 'VAULT') {
            return 'VAULT';
        }
        if ($type === 'STORE') {
            return 'STORE';
        }
        if ($type === 'WIP') {
            return 'WIP_STORE';
        }
        if ($type === 'FG') {
            return 'FG_STORE';
        }
        if ($type === 'SHOWROOM') {
            return 'SHOWROOM';
        }
        if ($type === 'BRANCH') {
            return 'BRANCH_STORE';
        }

        return 'LOC-' . $locationId;
    }

    /**
     * @return array{item_key:string,material_name:string,fine_gold:float}
     */
    public function buildGoldLineMeta(?int $goldPurityId, float $weightGm, ?float $pureGoldWeight = null): array
    {
        $materialName = 'GOLD';
        $itemKey = 'GOLD-0-GOLD';
        if ($goldPurityId !== null && $goldPurityId > 0) {
            $db = db_connect();
            $purity = $db->table('gold_purities')->where('id', $goldPurityId)->get()->getRowArray();
            $colorName = strtoupper(trim((string) ($purity['color_name'] ?? '')));
            if ($colorName === 'YELLOW') {
                $colorName = 'YG';
            } elseif ($colorName === 'WHITE') {
                $colorName = 'WG';
            } elseif ($colorName === 'ROSE') {
                $colorName = 'RG';
            }
            if ($colorName === '') {
                $colorName = 'YG';
            }
            $materialName = $colorName . '-BAR';
            $itemKey = 'GOLD-' . $goldPurityId . '-' . $materialName;
        }

        $fine = $pureGoldWeight ?? $weightGm;
        if ($fine < 0) {
            $fine = 0;
        }

        return [
            'item_key' => $itemKey,
            'material_name' => $materialName,
            'fine_gold' => round($fine, 3),
        ];
    }

    public function buildStoneItemKey(string $stoneType, string $size, string $quality): string
    {
        $base = strtoupper(trim($stoneType));
        if ($base === '') {
            $base = 'STONE';
        }
        $suffix = strtoupper(trim($size . '|' . $quality));
        if ($suffix === '|') {
            $suffix = 'GEN';
        }
        return 'STONE-' . $base . '|' . $suffix;
    }
}
