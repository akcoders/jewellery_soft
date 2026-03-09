<?php

namespace App\Controllers\Admin\StoneInventory;

use App\Controllers\BaseController;
use App\Models\InventoryLocationModel;
use App\Models\StoneInventoryAdjustmentHeaderModel;
use App\Models\StoneInventoryAdjustmentLineModel;
use App\Models\StoneInventoryItemModel;
use App\Services\StoneInventory\StockService;
use Throwable;

class AdjustmentsController extends BaseController
{
    private StoneInventoryAdjustmentHeaderModel $headerModel;
    private StoneInventoryAdjustmentLineModel $lineModel;
    private StoneInventoryItemModel $itemModel;
    private InventoryLocationModel $locationModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->headerModel = new StoneInventoryAdjustmentHeaderModel();
        $this->lineModel = new StoneInventoryAdjustmentLineModel();
        $this->itemModel = new StoneInventoryItemModel();
        $this->locationModel = new InventoryLocationModel();
    }

    public function index(): string
    {
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $builder = db_connect()->table('stone_inventory_adjustment_headers ah')
            ->select('ah.*, iloc.name as location_name, COUNT(al.id) as line_count, COALESCE(SUM(al.qty), 0) as total_qty, COALESCE(SUM(al.line_value), 0) as total_value', false)
            ->join('stone_inventory_adjustment_lines al', 'al.adjustment_id = ah.id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ah.location_id', 'left')
            ->groupBy('ah.id')
            ->orderBy('ah.id', 'DESC');

        if ($from !== '') {
            $builder->where('ah.adjustment_date >=', $from);
        }
        if ($to !== '') {
            $builder->where('ah.adjustment_date <=', $to);
        }

        return view('admin/stone_inventory/adjustments/index', [
            'title' => 'Stone Stock Adjustments',
            'adjustments' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): string
    {
        return view('admin/stone_inventory/adjustments/create', [
            'title' => 'Create Stone Adjustment',
            'items' => $this->itemOptions(),
            'locations' => $this->locationOptions(),
            'adjustment' => null,
            'lines' => [],
            'action' => site_url('admin/stone-inventory/adjustments'),
        ]);
    }

    public function store()
    {
        $validationError = $this->validateHeader();
        if ($validationError !== null) {
            return redirect()->back()->withInput()->with('error', $validationError);
        }

        $db = db_connect();
        $service = new StockService($db);
        $parsed = $this->collectLinesFromRequest();
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }
        if ($parsed['lines'] === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line is required.');
        }

        try {
            $db->transException(true)->transStart();

            $adjustmentType = strtolower(trim((string) $this->request->getPost('adjustment_type')));
            $adjustmentId = (int) $this->headerModel->insert([
                'adjustment_date' => (string) $this->request->getPost('adjustment_date'),
                'adjustment_type' => $adjustmentType,
                'location_id' => (int) $this->request->getPost('location_id'),
                'notes' => trim((string) $this->request->getPost('notes')) ?: null,
                'created_by' => (int) session('admin_id'),
            ], true);

            foreach ($parsed['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $service->upsertItemFromSignature((array) ($line['signature'] ?? []));
                }

                $this->lineModel->insert([
                    'adjustment_id' => $adjustmentId,
                    'item_id' => $itemId,
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'line_value' => $line['line_value'],
                    'reason' => $line['reason'],
                ]);
            }

            $service->applyAdjustment($adjustmentId, $adjustmentType);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/adjustments'))
            ->with('success', 'Stone adjustment saved and stock updated.');
    }

    public function view(int $id)
    {
        $adjustment = db_connect()->table('stone_inventory_adjustment_headers ah')
            ->select('ah.*, iloc.name as location_name')
            ->join('inventory_locations iloc', 'iloc.id = ah.location_id', 'left')
            ->where('ah.id', $id)
            ->get()
            ->getRowArray();

        if (! $adjustment) {
            return redirect()->to(site_url('admin/stone-inventory/adjustments'))->with('error', 'Adjustment not found.');
        }

        return view('admin/stone_inventory/adjustments/view', [
            'title' => 'View Stone Adjustment',
            'adjustment' => $adjustment,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('stone_inventory_adjustment_lines', 'adjustment_id', $id),
        ]);
    }

    public function edit(int $id)
    {
        $adjustment = $this->headerModel->find($id);
        if (! $adjustment) {
            return redirect()->to(site_url('admin/stone-inventory/adjustments'))->with('error', 'Adjustment not found.');
        }

        return view('admin/stone_inventory/adjustments/edit', [
            'title' => 'Edit Stone Adjustment',
            'items' => $this->itemOptions(),
            'locations' => $this->locationOptions(),
            'adjustment' => $adjustment,
            'lines' => $this->lineRows($id),
            'action' => site_url('admin/stone-inventory/adjustments/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $adjustment = $this->headerModel->find($id);
        if (! $adjustment) {
            return redirect()->to(site_url('admin/stone-inventory/adjustments'))->with('error', 'Adjustment not found.');
        }

        $validationError = $this->validateHeader();
        if ($validationError !== null) {
            return redirect()->back()->withInput()->with('error', $validationError);
        }

        $db = db_connect();
        $service = new StockService($db);
        $parsed = $this->collectLinesFromRequest();
        if ($parsed['error'] !== null) {
            return redirect()->back()->withInput()->with('error', $parsed['error']);
        }
        if ($parsed['lines'] === []) {
            return redirect()->back()->withInput()->with('error', 'At least one valid line is required.');
        }

        try {
            $db->transException(true)->transStart();
            $service->reverseAdjustment($id, (string) ($adjustment['adjustment_type'] ?? 'add'));

            $adjustmentType = strtolower(trim((string) $this->request->getPost('adjustment_type')));
            $this->headerModel->update($id, [
                'adjustment_date' => (string) $this->request->getPost('adjustment_date'),
                'adjustment_type' => $adjustmentType,
                'location_id' => (int) $this->request->getPost('location_id'),
                'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            ]);

            $this->lineModel->where('adjustment_id', $id)->delete();
            foreach ($parsed['lines'] as $line) {
                $itemId = (int) ($line['item_id'] ?? 0);
                if ($itemId <= 0) {
                    $itemId = $service->upsertItemFromSignature((array) ($line['signature'] ?? []));
                }

                $this->lineModel->insert([
                    'adjustment_id' => $id,
                    'item_id' => $itemId,
                    'qty' => $line['qty'],
                    'rate' => $line['rate'],
                    'line_value' => $line['line_value'],
                    'reason' => $line['reason'],
                ]);
            }

            $service->applyAdjustment($id, $adjustmentType);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/adjustments/view/' . $id))
            ->with('success', 'Stone adjustment updated and stock recalculated.');
    }

    public function delete(int $id)
    {
        $adjustment = $this->headerModel->find($id);
        if (! $adjustment) {
            return redirect()->to(site_url('admin/stone-inventory/adjustments'))->with('error', 'Adjustment not found.');
        }

        $db = db_connect();
        $service = new StockService($db);

        try {
            $db->transException(true)->transStart();
            $service->reverseAdjustment($id, (string) ($adjustment['adjustment_type'] ?? 'add'));
            $this->lineModel->where('adjustment_id', $id)->delete();
            $this->headerModel->delete($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->to(site_url('admin/stone-inventory/adjustments'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/stone-inventory/adjustments'))
            ->with('success', 'Stone adjustment deleted and stock restored.');
    }

    /**
     * @return array{lines:list<array<string,mixed>>,error:?string}
     */
    private function collectLinesFromRequest(): array
    {
        $itemIds = (array) $this->request->getPost('item_id');
        $productNames = (array) $this->request->getPost('product_name');
        $stoneTypes = (array) $this->request->getPost('stone_type');
        $qtys = (array) $this->request->getPost('qty');
        $rates = (array) $this->request->getPost('rate');
        $reasons = (array) $this->request->getPost('reason');

        $max = max(count($itemIds), count($productNames), count($stoneTypes), count($qtys), count($rates), count($reasons));
        $lines = [];

        for ($i = 0; $i < $max; $i++) {
            $itemId = (int) ($itemIds[$i] ?? 0);
            $productName = trim((string) ($productNames[$i] ?? ''));
            $stoneType = trim((string) ($stoneTypes[$i] ?? ''));
            $qtyValue = (float) ($qtys[$i] ?? 0);
            $rateRaw = trim((string) ($rates[$i] ?? ''));
            $reason = trim((string) ($reasons[$i] ?? ''));

            $isBlank = $itemId <= 0 && $productName === '' && $stoneType === '' && $qtyValue <= 0 && $rateRaw === '' && $reason === '';
            if ($isBlank) {
                continue;
            }
            if ($qtyValue <= 0) {
                return ['lines' => [], 'error' => 'Quantity must be greater than zero for each line.'];
            }
            $rateValue = $rateRaw === '' ? null : (float) $rateRaw;
            if ($rateValue !== null && $rateValue < 0) {
                return ['lines' => [], 'error' => 'Rate cannot be negative.'];
            }

            if ($itemId <= 0) {
                if ($productName === '') {
                    return ['lines' => [], 'error' => 'Product name is required when item is not selected.'];
                }
                $signature = [
                    'product_name' => $productName,
                    'stone_type' => $stoneType,
                    'default_rate' => $rateValue ?? 0,
                ];
            } else {
                if (! $this->itemModel->find($itemId)) {
                    return ['lines' => [], 'error' => 'Selected item does not exist.'];
                }
                $signature = [];
            }

            $lineValue = $rateValue === null ? null : round($qtyValue * $rateValue, 2);
            $lines[] = [
                'item_id' => $itemId,
                'qty' => round($qtyValue, 3),
                'rate' => $rateValue === null ? null : round($rateValue, 2),
                'line_value' => $lineValue,
                'reason' => $reason === '' ? null : $reason,
                'signature' => $signature,
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    private function validateHeader(): ?string
    {
        if (! $this->validate([
            'adjustment_date' => 'required|valid_date',
            'adjustment_type' => 'required|in_list[add,subtract]',
            'location_id' => 'required|integer|greater_than[0]',
            'notes' => 'permit_empty',
        ])) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
        }

        $locationId = (int) $this->request->getPost('location_id');
        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return 'Selected location was not found.';
        }
        return null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function itemOptions(): array
    {
        return $this->itemModel->orderBy('product_name', 'ASC')->orderBy('id', 'DESC')->findAll();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function locationOptions(): array
    {
        return $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function lineRows(int $adjustmentId): array
    {
        return db_connect()->table('stone_inventory_adjustment_lines al')
            ->select('al.*, i.product_name, i.stone_type')
            ->join('stone_inventory_items i', 'i.id = al.item_id', 'left')
            ->where('al.adjustment_id', $adjustmentId)
            ->orderBy('al.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{total_qty:float,total_value:float}
     */
    private function lineTotals(string $table, string $headerField, int $headerId): array
    {
        $row = db_connect()->table($table)
            ->select('COALESCE(SUM(qty),0) as total_qty, COALESCE(SUM(line_value),0) as total_value', false)
            ->where($headerField, $headerId)
            ->get()
            ->getRowArray();

        return [
            'total_qty' => (float) ($row['total_qty'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }
}

