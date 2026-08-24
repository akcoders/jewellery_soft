<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class FinishedJewelleryService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function createForCompletedOrder(int $orderId, int $createdBy = 0): ?int
    {
        if ($orderId <= 0 || ! $this->db->tableExists('fg_items') || ! $this->db->fieldExists('studded_details_json', 'fg_items')) {
            return null;
        }
        $order = $this->db->table('orders')->where('id', $orderId)->get()->getRowArray();
        if (! $order || (string) ($order['status'] ?? '') !== 'Completed') {
            return null;
        }
        $existing = $this->db->table('fg_items')->select('id')->where('order_id', $orderId)->get()->getRowArray();
        if ($existing) {
            $this->syncFinishedPhoto((int) $existing['id'], $orderId);
            $this->syncDesignForCompletedOrder($orderId, $createdBy);
            return (int) $existing['id'];
        }
        $summary = $this->db->table('order_receive_summaries')->where('order_id', $orderId)->orderBy('id', 'DESC')->get()->getRowArray() ?? [];
        if ($summary === []) {
            $movement = $this->db->table('order_material_movements')
                ->where('order_id', $orderId)
                ->where('movement_type', 'receive')
                ->orderBy('id', 'DESC')->get()->getRowArray() ?? [];
            if ($movement !== []) {
                $summary = [
                    'movement_id' => (int) ($movement['id'] ?? 0),
                    'gross_weight_gm' => (float) (($movement['gross_weight_gm'] ?? 0) ?: ($movement['gold_gm'] ?? 0)),
                    'net_gold_weight_gm' => (float) (($movement['net_gold_weight_gm'] ?? 0) ?: ($movement['gold_gm'] ?? 0)),
                    'pure_gold_weight_gm' => (float) ($movement['pure_gold_weight_gm'] ?? 0),
                    'diamond_weight_cts' => (float) ($movement['diamond_cts'] ?? 0),
                    'stone_weight_cts' => 0,
                ];
            }
        }
        $movementId = (int) ($summary['movement_id'] ?? 0);
        $details = $movementId > 0
            ? $this->db->table('order_receive_details')->where('movement_id', $movementId)->orderBy('id', 'ASC')->get()->getResultArray()
            : [];
        $studded = [];
        foreach ($details as $detail) {
            if (! in_array(strtolower((string) ($detail['component_type'] ?? '')), ['diamond', 'stone', 'other'], true)) {
                continue;
            }
            $studded[] = [
                'type' => (string) ($detail['component_type'] ?? ''),
                'name' => (string) ($detail['component_name'] ?? ''),
                'pcs' => (float) ($detail['pcs'] ?? 0),
                'weight_cts' => (float) ($detail['weight_cts'] ?? 0),
                'weight_gm' => (float) ($detail['weight_gm'] ?? 0),
                'rate' => (float) ($detail['rate'] ?? 0),
                'amount' => (float) ($detail['line_total'] ?? 0),
            ];
        }
        $orderItem = $this->db->table('order_items')->where('order_id', $orderId)->orderBy('id', 'ASC')->get()->getRowArray() ?? [];
        $jobCard = $this->db->table('job_cards')->select('id')->where('order_id', $orderId)->orderBy('id', 'ASC')->get()->getRowArray();
        $warehouse = $this->db->table('warehouses')->whereIn('warehouse_code', ['FG_STORE', 'MAIN'])->orderBy('id', 'ASC')->get()->getRowArray()
            ?? $this->db->table('warehouses')->orderBy('id', 'ASC')->get()->getRowArray();
        $warehouseId = (int) ($warehouse['id'] ?? 0);
        $bin = $warehouseId > 0 ? $this->db->table('bins')->where('warehouse_id', $warehouseId)->orderBy('id', 'ASC')->get()->getRowArray() : null;
        $tagNo = 'FG-' . preg_replace('/[^A-Z0-9]+/', '-', strtoupper((string) $order['order_no']));
        if ($this->db->table('fg_items')->where('tag_no', $tagNo)->countAllResults() > 0) {
            $tagNo .= '-' . $orderId;
        }
        $now = date('Y-m-d H:i:s');
        $finishPhoto = $this->latestFinishPhoto($orderId);
        $this->db->table('fg_items')->insert([
            'tag_no' => substr($tagNo, 0, 80),
            'order_id' => $orderId,
            'job_card_id' => $jobCard ? (int) $jobCard['id'] : null,
            'production_ready_item_id' => null,
            'design_name' => (string) (($orderItem['item_description'] ?? '') ?: $order['order_no']),
            'purity_label' => null,
            'qty' => max(1, (int) ($orderItem['qty'] ?? 1)),
            'gross_wt' => (float) ($summary['gross_weight_gm'] ?? 0),
            'net_gold_wt' => (float) (($summary['net_gold_weight_gm'] ?? 0) ?: ($orderItem['gold_required_gm'] ?? 0)),
            'diamond_cts' => (float) (($summary['diamond_weight_cts'] ?? 0) ?: ($orderItem['diamond_required_cts'] ?? 0)),
            'stone_wt' => (float) ($summary['stone_weight_cts'] ?? 0),
            'studded_details_json' => json_encode($studded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source_image_path' => $finishPhoto['file_path'] ?? null,
            'status' => 'AVAILABLE',
            'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
            'bin_id' => $bin ? (int) $bin['id'] : null,
            'showroom_stock_status' => 'FG_STORE',
            'inventory_remarks' => $summary === [] ? 'Created on order completion; receive details were not supplied' : 'Created automatically from completed order receive details',
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $fgItemId = (int) $this->db->insertID();
        $this->db->table('showroom_fg_movements')->insert([
            'fg_item_id' => $fgItemId,
            'movement_type' => 'ORDER_COMPLETED_TO_FG',
            'reference_type' => 'orders',
            'reference_id' => $orderId,
            'remarks' => 'Finished jewellery created automatically when order was completed',
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->syncDesignForCompletedOrder($orderId, $createdBy);
        return $fgItemId;
    }

    /**
     * A fresh completed design is saved only after a finish photo exists. Repeat orders
     * already carry a design_id; matching photo hashes also reuse the original design.
     */
    public function syncDesignForCompletedOrder(int $orderId, int $createdBy = 0): ?int
    {
        if ($orderId <= 0
            || ! $this->db->tableExists('design_masters')
            || ! $this->db->fieldExists('source_image_sha256', 'design_masters')) {
            return null;
        }
        $order = $this->db->table('orders')->where('id', $orderId)->get()->getRowArray();
        if (! $order || (string) ($order['status'] ?? '') !== 'Completed') {
            return null;
        }
        $items = $this->db->table('order_items')->where('order_id', $orderId)->orderBy('id', 'ASC')->get()->getResultArray();
        if ($items === []) {
            return null;
        }
        $summary = $this->db->table('order_receive_summaries')->where('order_id', $orderId)->orderBy('id', 'DESC')->get()->getRowArray() ?? [];
        $movementId = (int) ($summary['movement_id'] ?? 0);
        $details = $movementId > 0
            ? $this->db->table('order_receive_details')->where('movement_id', $movementId)->orderBy('id', 'ASC')->get()->getResultArray()
            : [];
        $studded = array_map(static fn(array $row): array => [
            'type' => (string) ($row['component_type'] ?? ''),
            'name' => (string) ($row['component_name'] ?? ''),
            'pcs' => (float) ($row['pcs'] ?? 0),
            'weight_cts' => (float) ($row['weight_cts'] ?? 0),
            'weight_gm' => (float) ($row['weight_gm'] ?? 0),
            'rate' => (float) ($row['rate'] ?? 0),
            'amount' => (float) ($row['line_total'] ?? 0),
        ], $details);

        $firstDesignId = null;
        $generalPhotoUsed = false;
        foreach ($items as $item) {
            if ((int) ($item['design_id'] ?? 0) > 0) {
                $firstDesignId ??= (int) $item['design_id'];
                continue;
            }
            $photo = $this->latestFinishPhoto($orderId, (int) $item['id']);
            if (! $photo && ! $generalPhotoUsed) {
                $photo = $this->latestFinishPhoto($orderId);
                $generalPhotoUsed = $photo !== null;
            }
            if (! $photo) {
                continue;
            }
            $absolutePath = FCPATH . ltrim((string) $photo['file_path'], '/');
            $imageHash = is_file($absolutePath) ? hash_file('sha256', $absolutePath) : false;
            if (! is_string($imageHash) || $imageHash === '') {
                continue;
            }
            $sameDesign = $this->db->table('design_masters')->select('id')
                ->where('source_image_sha256', $imageHash)->get()->getRowArray();
            if ($sameDesign) {
                $designId = (int) $sameDesign['id'];
                $this->db->table('order_items')->where('id', (int) $item['id'])->update([
                    'design_id' => $designId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $firstDesignId ??= $designId;
                continue;
            }

            $purity = null;
            if ((int) ($item['gold_purity_id'] ?? 0) > 0) {
                $purityRow = $this->db->table('gold_purities')->select('purity_code')
                    ->where('id', (int) $item['gold_purity_id'])->get()->getRowArray();
                $purity = (string) ($purityRow['purity_code'] ?? '');
            }
            $diamondCts = (float) (($summary['diamond_weight_cts'] ?? 0) ?: ($item['diamond_required_cts'] ?? 0));
            $category = stripos((string) $purity, 'silver') !== false ? 'Silver' : ($diamondCts > 0 ? 'Diamond' : 'Gold');
            $name = trim((string) ($item['item_description'] ?? '')) ?: (string) $order['order_no'];
            $subcategory = $this->inferSubcategory($name);
            $baseCode = 'AUTO-' . preg_replace('/[^A-Z0-9]+/', '-', strtoupper((string) $order['order_no']));
            $code = substr($baseCode . (count($items) > 1 ? '-' . (int) $item['id'] : ''), 0, 40);
            if ($this->db->table('design_masters')->where('design_code', $code)->countAllResults() > 0) {
                $code = substr($code, 0, 32) . '-' . (int) $item['id'];
            }
            $now = date('Y-m-d H:i:s');
            $this->db->table('design_masters')->insert([
                'design_code' => $code,
                'name' => $name,
                'category' => $category,
                'subcategory' => $subcategory,
                'image_path' => (string) $photo['file_path'],
                'source_order_id' => $orderId,
                'source_order_item_id' => (int) $item['id'],
                'source_karigar_id' => (int) ($order['assigned_karigar_id'] ?? 0) ?: null,
                'purity_label' => $purity ?: null,
                'gross_weight_gm' => (float) ($summary['gross_weight_gm'] ?? 0),
                'net_gold_weight_gm' => (float) (($summary['net_gold_weight_gm'] ?? 0) ?: ($item['gold_required_gm'] ?? 0)),
                'pure_gold_weight_gm' => (float) ($summary['pure_gold_weight_gm'] ?? 0),
                'diamond_weight_cts' => $diamondCts,
                'stone_weight_cts' => (float) ($summary['stone_weight_cts'] ?? 0),
                'studded_details_json' => json_encode($studded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'source_image_sha256' => $imageHash,
                'source_type' => 'completed_order',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $designId = (int) $this->db->insertID();
            $this->db->table('order_items')->where('id', (int) $item['id'])->update([
                'design_id' => $designId,
                'updated_at' => $now,
            ]);
            $firstDesignId ??= $designId;
        }

        return $firstDesignId;
    }

    private function syncFinishedPhoto(int $fgItemId, int $orderId): void
    {
        $photo = $this->latestFinishPhoto($orderId);
        if (! $photo) {
            return;
        }
        $this->db->table('fg_items')->where('id', $fgItemId)->update([
            'source_image_path' => (string) $photo['file_path'],
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string,mixed>|null */
    private function latestFinishPhoto(int $orderId, int $orderItemId = 0): ?array
    {
        if (! $this->db->tableExists('order_attachments')) {
            return null;
        }
        $builder = $this->db->table('order_attachments')
            ->where('order_id', $orderId)
            ->where('LOWER(file_type)', 'finish_photo');
        if ($orderItemId > 0) {
            $builder->where('order_item_id', $orderItemId);
        }
        $row = $builder->orderBy('id', 'DESC')->get()->getRowArray();
        return $row ?: null;
    }

    private function inferSubcategory(string $name): string
    {
        $normalized = strtoupper($name);
        $map = [
            'WAIST BELT' => 'Waist Belt', 'BELT' => 'Waist Belt', 'JHUMKI' => 'Jhumki',
            'JHUMKA' => 'Jhumki', 'HAARAM' => 'Haaram', 'HARAM' => 'Haaram',
            'CHOKER' => 'Choker', 'NECKLACE' => 'Necklace', 'BANGLE' => 'Bangle',
            'BRACELET' => 'Bracelet', 'RING' => 'Ring', 'EARRING' => 'Earrings',
            'STUD' => 'Stud Earrings', 'CHAIN' => 'Chain', 'PENDANT' => 'Pendant',
            'MANG' => 'Maang Tikka', 'TIKKA' => 'Maang Tikka',
        ];
        foreach ($map as $needle => $label) {
            if (str_contains($normalized, $needle)) {
                return $label;
            }
        }
        return 'Jewellery';
    }
}
