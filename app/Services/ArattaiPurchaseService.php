<?php

namespace App\Services;

use App\Models\PurchaseItemModel;
use App\Models\PurchaseModel;
use RuntimeException;
use Throwable;

/** Creates the same general purchase/GRN entry used by the admin purchase form. */
class ArattaiPurchaseService
{
    /** @param array<string,mixed> $draft @return array{id:int,purchase_no:string,bill_path:string} */
    public function create(array $draft, int $createdBy): array
    {
        $type = ucfirst(strtolower(trim((string) ($draft['purchase_type'] ?? ''))));
        if (! in_array($type, ['Gold', 'Diamond', 'Stone'], true)) {
            throw new RuntimeException('Select Gold, Diamond or Stone purchase.');
        }
        $vendorId = (int) ($draft['vendor_id'] ?? 0);
        $locationId = (int) ($draft['location_id'] ?? 0);
        $date = trim((string) ($draft['purchase_date'] ?? ''));
        $invoiceNo = trim((string) ($draft['invoice_no'] ?? ''));
        $taxableAmount = round((float) ($draft['taxable_amount'] ?? 0), 2);
        $bill = is_array($draft['purchase_bill'] ?? null) ? $draft['purchase_bill'] : [];
        $items = array_values(array_filter((array) ($draft['items'] ?? []), 'is_array'));
        if ($vendorId <= 0 || $locationId <= 0 || $date === '' || strtotime($date) === false) {
            throw new RuntimeException('Vendor, purchase date and inventory location are required.');
        }
        if ($invoiceNo === '' || $taxableAmount <= 0 || $items === []) {
            throw new RuntimeException('Invoice number, taxable amount and at least one purchase item are required.');
        }
        if (! $this->validBill($bill)) {
            throw new RuntimeException('Purchase bill is compulsory. Upload a valid image or PDF bill.');
        }
        $db = db_connect();
        $vendor = $db->table('vendors')->where('id', $vendorId)->where('is_active', 1)->get()->getRowArray();
        $location = $db->table('inventory_locations')->where('id', $locationId)->where('is_active', 1)->get()->getRowArray();
        if (! is_array($vendor) || ! is_array($location)) {
            throw new RuntimeException('Selected vendor or inventory location is no longer active.');
        }
        $dueDate = trim((string) ($draft['payment_due_date'] ?? ''));
        if ($type !== 'Gold' && ($dueDate === '' || strtotime($dueDate) === false)) {
            throw new RuntimeException('Payment due date is required for Diamond and Stone purchases.');
        }
        $items = $this->validateItems($type, $items);
        $tax = (new TaxMasterService())->calculate(
            (int) ($draft['gst_master_id'] ?? 0),
            $taxableAmount,
            (float) ($draft['round_off_amount'] ?? 0)
        );

        $storedBill = $this->storeBill($bill);
        $purchaseId = 0;
        $purchaseNo = '';
        try {
            $db->transException(true)->transStart();
            $purchaseNo = 'PO' . date('ymdHis') . random_int(10, 99);
            $purchaseId = (int) (new PurchaseModel())->insert([
                'purchase_no' => $purchaseNo,
                'purchase_type' => $type,
                'vendor_id' => $vendorId,
                'purchase_date' => date('Y-m-d', strtotime($date)),
                'invoice_no' => $invoiceNo,
                'invoice_amount' => (float) $tax['invoice_total'],
                'taxable_amount' => (float) $tax['taxable_amount'],
                'gst_master_id' => (int) $tax['gst_master_id'],
                'tax_breakup_json' => (string) $tax['tax_breakup_json'],
                'gst_amount' => (float) $tax['gst_amount'],
                'round_off_amount' => (float) $tax['round_off_amount'],
                'payment_due_date' => $type === 'Gold' ? null : date('Y-m-d', strtotime($dueDate)),
                'payment_status' => $type === 'Gold' ? 'Paid' : 'Pending',
                'location_id' => $locationId,
                'notes' => trim((string) ($draft['notes'] ?? '')) ?: null,
                'bill_file_name' => $storedBill['file_name'],
                'bill_file_path' => $storedBill['relative'],
                'bill_mime_type' => $storedBill['mime_type'],
                'bill_file_size' => $storedBill['file_size'],
                'created_by' => $createdBy > 0 ? $createdBy : null,
            ], true);
            if ($purchaseId <= 0) throw new RuntimeException('Purchase header could not be saved.');

            $postingLines = [];
            foreach ($items as $item) {
                $itemId = (int) (new PurchaseItemModel())->insert([
                    'purchase_id' => $purchaseId,
                    'item_type' => $item['item_type'],
                    'material_name' => $item['material_name'],
                    'gold_purity_id' => $item['gold_purity_id'],
                    'diamond_shape' => $item['diamond_shape'],
                    'diamond_sieve' => $item['diamond_sieve'],
                    'diamond_color' => $item['diamond_color'],
                    'diamond_clarity' => $item['diamond_clarity'],
                    'pcs' => $item['pcs'],
                    'weight_gm' => $item['weight_gm'],
                    'cts' => $item['cts'],
                ], true);
                if ($itemId <= 0) throw new RuntimeException('Purchase item could not be saved.');
                $db->table('inventory_transactions')->insert([
                    'txn_date' => date('Y-m-d', strtotime($date)),
                    'transaction_type' => 'purchase',
                    'location_id' => $locationId,
                    'counter_location_id' => null,
                    'item_type' => $item['item_type'],
                    'material_name' => $item['material_name'],
                    'gold_purity_id' => $item['gold_purity_id'],
                    'diamond_shape' => $item['diamond_shape'],
                    'diamond_sieve' => $item['diamond_sieve'],
                    'diamond_color' => $item['diamond_color'],
                    'diamond_clarity' => $item['diamond_clarity'],
                    'pcs' => $item['pcs'],
                    'weight_gm' => $item['weight_gm'],
                    'cts' => $item['cts'],
                    'reference_type' => 'purchase_item',
                    'reference_id' => $itemId,
                    'document_type' => 'Material Receipt (GRN)',
                    'document_no' => $invoiceNo,
                    'notes' => trim((string) ($draft['notes'] ?? '')),
                    'created_by' => $createdBy > 0 ? $createdBy : null,
                ]);
                $postingLines[] = $this->postingLine($item);
            }

            $warehouse = (new AdminPostingService())->resolveWarehouseBinByLocation($locationId);
            $posting = new PostingService($db);
            $warehouseAccountId = $posting->ensureAccount(
                'WAREHOUSE', 'WH-' . (int) $warehouse['warehouse_id'],
                (string) $warehouse['warehouse_name'] . ' Warehouse', 'warehouses', (int) $warehouse['warehouse_id']
            );
            $vendorAccountId = $posting->ensureAccount(
                'VENDOR', 'VENDOR-' . $vendorId, 'Vendor - ' . (string) ($vendor['name'] ?? ''), 'vendors', $vendorId
            );
            $posting->postVoucher([
                'voucher_type' => 'GRN',
                'voucher_date' => date('Y-m-d', strtotime($date)),
                'to_warehouse_id' => (int) $warehouse['warehouse_id'],
                'to_bin_id' => (int) $warehouse['bin_id'],
                'party_id' => $vendorId,
                'debit_account_id' => $warehouseAccountId,
                'credit_account_id' => $vendorAccountId,
                'remarks' => 'Purchase ' . $purchaseNo . ' | Invoice ' . $invoiceNo,
                'created_by' => $createdBy > 0 ? $createdBy : null,
            ], $postingLines);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            if (is_file($storedBill['absolute'])) @unlink($storedBill['absolute']);
            throw $e;
        }
        $source = (string) ($bill['temp_path'] ?? '');
        if ($source !== '' && is_file($source)) @unlink($source);
        try {
            (new MobileNotificationEventService())->notifyInventoryTransactionCreated(
                'purchase', $type, 'purchases', $purchaseId, 'arattai'
            );
        } catch (Throwable $e) {
            log_message('error', 'Arattai purchase notification failed: {message}', ['message' => $e->getMessage()]);
        }
        return ['id' => $purchaseId, 'purchase_no' => $purchaseNo, 'bill_path' => $storedBill['relative']];
    }

    /** @param array<string,mixed> $bill */
    public function discardDraftBill(array $bill): void
    {
        $path = (string) ($bill['temp_path'] ?? '');
        if ($path !== '' && str_starts_with($path, WRITEPATH . 'uploads/arattai-purchase-drafts') && is_file($path)) {
            @unlink($path);
        }
    }

    /** @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    private function validateItems(string $type, array $items): array
    {
        $validated = [];
        foreach ($items as $index => $item) {
            $material = trim((string) ($item['material_name'] ?? ''));
            $purityId = (int) ($item['gold_purity_id'] ?? 0);
            $pcs = max(0, (int) ($item['pcs'] ?? 0));
            $weight = round(max(0, (float) ($item['weight_gm'] ?? 0)), 3);
            $cts = round(max(0, (float) ($item['cts'] ?? 0)), 3);
            $shape = trim((string) ($item['diamond_shape'] ?? ''));
            if ($type === 'Gold' && ($material === '' || $purityId <= 0 || $weight <= 0)) {
                throw new RuntimeException('Gold line ' . ($index + 1) . ': material, purity and weight are required.');
            }
            if ($type === 'Diamond' && ($shape === '' || $cts <= 0)) {
                throw new RuntimeException('Diamond line ' . ($index + 1) . ': shape and carat weight are required.');
            }
            if ($type === 'Stone' && ($material === '' || $cts <= 0)) {
                throw new RuntimeException('Stone line ' . ($index + 1) . ': material and weight are required.');
            }
            $validated[] = [
                'item_type' => $type,
                'material_name' => $material !== '' ? $material : $shape,
                'gold_purity_id' => $purityId > 0 ? $purityId : null,
                'diamond_shape' => $shape !== '' ? $shape : null,
                'diamond_sieve' => trim((string) ($item['diamond_sieve'] ?? '')) ?: null,
                'diamond_color' => trim((string) ($item['diamond_color'] ?? '')) ?: null,
                'diamond_clarity' => trim((string) ($item['diamond_clarity'] ?? '')) ?: null,
                'pcs' => $pcs,
                'weight_gm' => $weight,
                'cts' => $cts,
            ];
        }
        return $validated;
    }

    /** @param array<string,mixed> $item @return array<string,mixed> */
    private function postingLine(array $item): array
    {
        $type = strtoupper((string) $item['item_type']);
        $adminPosting = new AdminPostingService();
        if ($type === 'GOLD') {
            $weight = (float) $item['weight_gm'];
            $purityId = (int) ($item['gold_purity_id'] ?? 0);
            $purity = db_connect()->table('gold_purities')->where('id', $purityId)->get()->getRowArray();
            $fine = round($weight * ((float) ($purity['purity_percent'] ?? 100) / 100), 3);
            $meta = $adminPosting->buildGoldLineMeta($purityId, $weight, $fine);
            return [
                'item_type' => 'GOLD', 'item_key' => $meta['item_key'], 'material_name' => $meta['material_name'],
                'gold_purity_id' => $purityId, 'qty_weight' => $weight, 'qty_pcs' => 0, 'qty_cts' => 0,
                'fine_gold' => $meta['fine_gold'], 'remarks' => (string) $item['material_name'],
            ];
        }
        if ($type === 'DIAMOND') {
            $shape = trim((string) ($item['diamond_shape'] ?? ''));
            $size = trim((string) ($item['diamond_sieve'] ?? ''));
            $color = trim((string) ($item['diamond_color'] ?? ''));
            $clarity = trim((string) ($item['diamond_clarity'] ?? ''));
            return [
                'item_type' => 'DIAMOND', 'item_key' => strtoupper(trim($shape . '|' . $size . '|' . $color . '|' . $clarity)),
                'material_name' => (string) $item['material_name'], 'shape' => $shape ?: null,
                'chalni_size' => $size ?: null, 'color' => $color ?: null, 'clarity' => $clarity ?: null,
                'qty_pcs' => (float) $item['pcs'], 'qty_cts' => (float) $item['cts'], 'qty_weight' => 0,
                'fine_gold' => 0, 'remarks' => 'Diamond Purchase',
            ];
        }
        $stone = (string) $item['material_name'];
        $size = trim((string) ($item['diamond_sieve'] ?? ''));
        $quality = trim((string) ($item['diamond_clarity'] ?? ''));
        return [
            'item_type' => 'STONE', 'item_key' => $adminPosting->buildStoneItemKey($stone, $size, $quality),
            'material_name' => $stone, 'stone_type' => $stone, 'qty_pcs' => (float) $item['pcs'],
            'qty_cts' => (float) $item['cts'], 'qty_weight' => 0, 'fine_gold' => 0, 'remarks' => 'Stone Purchase',
        ];
    }

    /** @param array<string,mixed> $bill @return array{absolute:string,relative:string,file_name:string,mime_type:string,file_size:int} */
    private function storeBill(array $bill): array
    {
        $source = (string) ($bill['temp_path'] ?? '');
        if (! $this->validBill($bill)) throw new RuntimeException('Purchase bill is missing.');
        $extension = strtolower((string) ($bill['extension'] ?? pathinfo($source, PATHINFO_EXTENSION)));
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new RuntimeException('Purchase bill must be PDF, JPG, PNG or WEBP.');
        }
        $directory = FCPATH . 'uploads/purchases/bills';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not create purchase bill storage.');
        }
        $name = 'arattai_bill_' . date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
        $absolute = $directory . DIRECTORY_SEPARATOR . $name;
        if (! copy($source, $absolute)) throw new RuntimeException('Could not store the purchase bill.');
        return [
            'absolute' => $absolute,
            'relative' => 'uploads/purchases/bills/' . $name,
            'file_name' => trim((string) ($bill['original_name'] ?? '')) ?: $name,
            'mime_type' => trim((string) ($bill['mime_type'] ?? '')) ?: ($extension === 'pdf' ? 'application/pdf' : 'image/' . $extension),
            'file_size' => (int) filesize($absolute),
        ];
    }

    /** @param array<string,mixed> $bill */
    private function validBill(array $bill): bool
    {
        $path = (string) ($bill['temp_path'] ?? '');
        return $path !== '' && is_file($path) && filesize($path) > 0 && filesize($path) <= 10 * 1024 * 1024;
    }
}
