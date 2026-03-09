<?php

namespace App\Controllers\Admin\GoldInventory;

use App\Controllers\BaseController;
use App\Models\GoldInventoryAdjustmentHeaderModel;
use App\Models\GoldInventoryAdjustmentLineModel;
use App\Models\GoldInventoryItemModel;
use App\Models\GoldPurityModel;
use App\Models\InventoryLocationModel;
use App\Services\GoldInventory\StockService;
use Throwable;

class AdjustmentsController extends BaseController
{
    private GoldInventoryAdjustmentHeaderModel $headerModel;
    private GoldInventoryAdjustmentLineModel $lineModel;
    private GoldInventoryItemModel $itemModel;
    private GoldPurityModel $purityModel;
    private InventoryLocationModel $locationModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->headerModel = new GoldInventoryAdjustmentHeaderModel();
        $this->lineModel = new GoldInventoryAdjustmentLineModel();
        $this->itemModel = new GoldInventoryItemModel();
        $this->purityModel = new GoldPurityModel();
        $this->locationModel = new InventoryLocationModel();
    }

    public function index(): string
    {
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $builder = db_connect()->table('gold_inventory_adjustment_headers ah')
            ->select('ah.*, iloc.name as location_name, COUNT(al.id) as line_count, COALESCE(SUM(al.weight_gm), 0) as total_weight, COALESCE(SUM(al.line_value), 0) as total_value', false)
            ->join('gold_inventory_adjustment_lines al', 'al.adjustment_id = ah.id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ah.location_id', 'left')
            ->groupBy('ah.id')
            ->orderBy('ah.id', 'DESC');

        if ($from !== '') {
            $builder->where('ah.adjustment_date >=', $from);
        }
        if ($to !== '') {
            $builder->where('ah.adjustment_date <=', $to);
        }

        return view('admin/gold_inventory/adjustments/index', [
            'title' => 'Gold Stock Adjustments',
            'adjustments' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): string
    {
        return view('admin/gold_inventory/adjustments/create', [
            'title' => 'Create Gold Adjustment',
            'items' => $this->itemOptions(),
            'purities' => $this->purityOptions(),
            'locations' => $this->locationOptions(),
            'adjustment' => null,
            'lines' => [],
            'action' => site_url('admin/gold-inventory/adjustments'),
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

            $adjustmentDate = (string) $this->request->getPost('adjustment_date');
            $adjustmentType = strtolower(trim((string) $this->request->getPost('adjustment_type')));
            if (! in_array($adjustmentType, ['add', 'subtract'], true)) {
                throw new \RuntimeException('Invalid adjustment type.');
            }
            $locationId = (int) $this->request->getPost('location_id');

            $adjustmentId = (int) $this->headerModel->insert([
                'adjustment_date' => $adjustmentDate,
                'adjustment_type' => $adjustmentType,
                'location_id' => $locationId,
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
                    'weight_gm' => $line['weight_gm'],
                    'fine_weight_gm' => $service->calculateFineWeightForItem($itemId, (float) $line['weight_gm']),
                    'rate_per_gm' => $line['rate_per_gm'],
                    'line_value' => $line['line_value'],
                    'reason' => $line['reason'],
                ]);
            }

            $service->applyAdjustment($adjustmentId, $adjustmentType, [
                'txn_date' => $adjustmentDate,
                'location_id' => $locationId,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Gold adjustment posting',
            ]);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/adjustments'))
            ->with('success', 'Adjustment saved and stock updated.');
    }

    public function view(int $id)
    {
        $adjustment = db_connect()->table('gold_inventory_adjustment_headers ah')
            ->select('ah.*, iloc.name as location_name')
            ->join('inventory_locations iloc', 'iloc.id = ah.location_id', 'left')
            ->where('ah.id', $id)
            ->get()
            ->getRowArray();

        if (! $adjustment) {
            return redirect()->to(site_url('admin/gold-inventory/adjustments'))->with('error', 'Adjustment not found.');
        }

        return view('admin/gold_inventory/adjustments/view', [
            'title' => 'View Gold Adjustment',
            'adjustment' => $adjustment,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('gold_inventory_adjustment_lines', 'adjustment_id', $id),
        ]);
    }

    public function edit(int $id)
    {
        $adjustment = $this->headerModel->find($id);
        if (! $adjustment) {
            return redirect()->to(site_url('admin/gold-inventory/adjustments'))->with('error', 'Adjustment not found.');
        }

        return view('admin/gold_inventory/adjustments/edit', [
            'title' => 'Edit Gold Adjustment',
            'items' => $this->itemOptions(),
            'purities' => $this->purityOptions(),
            'locations' => $this->locationOptions(),
            'adjustment' => $adjustment,
            'lines' => $this->lineRows($id),
            'action' => site_url('admin/gold-inventory/adjustments/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $adjustment = $this->headerModel->find($id);
        if (! $adjustment) {
            return redirect()->to(site_url('admin/gold-inventory/adjustments'))->with('error', 'Adjustment not found.');
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
            $service->reverseAdjustment($id, (string) ($adjustment['adjustment_type'] ?? 'add'), [
                'txn_date' => (string) ($adjustment['adjustment_date'] ?? ''),
                'location_id' => isset($adjustment['location_id']) ? (int) $adjustment['location_id'] : null,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Adjustment reversal for edit',
            ]);

            $adjustmentDate = (string) $this->request->getPost('adjustment_date');
            $adjustmentType = strtolower(trim((string) $this->request->getPost('adjustment_type')));
            if (! in_array($adjustmentType, ['add', 'subtract'], true)) {
                throw new \RuntimeException('Invalid adjustment type.');
            }
            $locationId = (int) $this->request->getPost('location_id');

            $this->headerModel->update($id, [
                'adjustment_date' => $adjustmentDate,
                'adjustment_type' => $adjustmentType,
                'location_id' => $locationId,
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
                    'weight_gm' => $line['weight_gm'],
                    'fine_weight_gm' => $service->calculateFineWeightForItem($itemId, (float) $line['weight_gm']),
                    'rate_per_gm' => $line['rate_per_gm'],
                    'line_value' => $line['line_value'],
                    'reason' => $line['reason'],
                ]);
            }

            $service->applyAdjustment($id, $adjustmentType, [
                'txn_date' => $adjustmentDate,
                'location_id' => $locationId,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Gold adjustment posting',
            ]);

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/adjustments/view/' . $id))
            ->with('success', 'Adjustment updated and stock recalculated.');
    }

    public function delete(int $id)
    {
        $adjustment = $this->headerModel->find($id);
        if (! $adjustment) {
            return redirect()->to(site_url('admin/gold-inventory/adjustments'))->with('error', 'Adjustment not found.');
        }

        $db = db_connect();
        $service = new StockService($db);

        try {
            $db->transException(true)->transStart();
            $service->reverseAdjustment($id, (string) ($adjustment['adjustment_type'] ?? 'add'), [
                'txn_date' => (string) ($adjustment['adjustment_date'] ?? ''),
                'location_id' => isset($adjustment['location_id']) ? (int) $adjustment['location_id'] : null,
                'created_by' => (int) session('admin_id'),
                'notes' => 'Adjustment deleted reversal',
            ]);
            $this->lineModel->where('adjustment_id', $id)->delete();
            $this->headerModel->delete($id);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->to(site_url('admin/gold-inventory/adjustments'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/gold-inventory/adjustments'))
            ->with('success', 'Adjustment deleted and stock restored.');
    }

    /**
     * @return array{lines:list<array<string,mixed>>,error:?string}
     */
    private function collectLinesFromRequest(): array
    {
        $itemIds = (array) $this->request->getPost('item_id');
        $purityIds = (array) $this->request->getPost('gold_purity_id');
        $colors = (array) $this->request->getPost('color_name');
        $forms = (array) $this->request->getPost('form_type');
        $weights = (array) $this->request->getPost('weight_gm');
        $rates = (array) $this->request->getPost('rate_per_gm');
        $reasons = (array) $this->request->getPost('reason');

        $max = max(count($itemIds), count($purityIds), count($colors), count($forms), count($weights), count($rates), count($reasons));
        $lines = [];

        for ($i = 0; $i < $max; $i++) {
            $itemId = (int) ($itemIds[$i] ?? 0);
            $purityId = (int) ($purityIds[$i] ?? 0);
            $colorName = trim((string) ($colors[$i] ?? ''));
            $formType = trim((string) ($forms[$i] ?? ''));
            $weight = (float) ($weights[$i] ?? 0);
            $rateRaw = trim((string) ($rates[$i] ?? ''));
            $reason = trim((string) ($reasons[$i] ?? ''));

            $isBlank = $itemId <= 0
                && $purityId <= 0
                && $colorName === ''
                && $formType === ''
                && $weight <= 0
                && $rateRaw === ''
                && $reason === '';
            if ($isBlank) {
                continue;
            }

            if ($weight <= 0) {
                return ['lines' => [], 'error' => 'Weight must be greater than zero for each line.'];
            }

            $rate = $rateRaw === '' ? null : (float) $rateRaw;
            if ($rate !== null && $rate < 0) {
                return ['lines' => [], 'error' => 'Rate per gram cannot be negative.'];
            }

            if ($itemId <= 0) {
                if ($purityId <= 0) {
                    return ['lines' => [], 'error' => 'Select gold purity when existing item is not selected.'];
                }
                $signature = [
                    'gold_purity_id' => $purityId,
                    'color_name' => $colorName,
                    'form_type' => $formType,
                ];
            } else {
                if (! $this->itemModel->find($itemId)) {
                    return ['lines' => [], 'error' => 'Selected gold item does not exist.'];
                }
                $signature = [];
            }

            $lineValue = $rate === null ? null : round($weight * $rate, 2);
            $lines[] = [
                'item_id' => $itemId,
                'weight_gm' => round($weight, 3),
                'rate_per_gm' => $rate === null ? null : round($rate, 2),
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
            'location_id' => 'required|integer',
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
        return db_connect()->table('gold_inventory_items gi')
            ->select('gi.*, gp.purity_code as master_purity_code, gp.color_name as master_color_name')
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->orderBy('gi.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function purityOptions(): array
    {
        return $this->purityModel
            ->where('is_active', 1)
            ->orderBy('purity_percent', 'DESC')
            ->findAll();
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
        return db_connect()->table('gold_inventory_adjustment_lines al')
            ->select('al.*, gi.gold_purity_id, gi.purity_code, gi.purity_percent, gi.color_name, gi.form_type, gp.purity_code as master_purity_code')
            ->join('gold_inventory_items gi', 'gi.id = al.item_id', 'left')
            ->join('gold_purities gp', 'gp.id = gi.gold_purity_id', 'left')
            ->where('al.adjustment_id', $adjustmentId)
            ->orderBy('al.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{total_weight:float,total_fine:float,total_value:float}
     */
    private function lineTotals(string $table, string $headerField, int $headerId): array
    {
        $row = db_connect()->table($table)
            ->select('COALESCE(SUM(weight_gm),0) as total_weight, COALESCE(SUM(fine_weight_gm),0) as total_fine, COALESCE(SUM(line_value),0) as total_value', false)
            ->where($headerField, $headerId)
            ->get()
            ->getRowArray();

        return [
            'total_weight' => (float) ($row['total_weight'] ?? 0),
            'total_fine' => (float) ($row['total_fine'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }
}
