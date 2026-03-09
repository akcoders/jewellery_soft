<?php

namespace App\Controllers\Admin\DiamondInventory;

use App\Controllers\BaseController;
use App\Models\DiamondInventoryAdjustmentHeaderModel;
use App\Models\DiamondInventoryAdjustmentLineModel;
use App\Models\InventoryLocationModel;
use App\Models\ItemModel;
use App\Services\DiamondInventory\StockService;
use Throwable;

class AdjustmentsController extends BaseController
{
    private $headerModel;
    private $lineModel;
    private $itemModel;
    private $locationModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->headerModel = new DiamondInventoryAdjustmentHeaderModel();
        $this->lineModel = new DiamondInventoryAdjustmentLineModel();
        $this->itemModel = new ItemModel();
        $this->locationModel = new InventoryLocationModel();
    }

    public function index(): string
    {
        $from = trim((string) $this->request->getGet('from'));
        $to = trim((string) $this->request->getGet('to'));

        $builder = db_connect()->table('diamond_inventory_adjustment_headers ah')
            ->select('ah.*, iloc.name as location_name, COUNT(al.id) as line_count, COALESCE(SUM(al.carat), 0) as total_carat, COALESCE(SUM(al.line_value), 0) as total_value', false)
            ->join('diamond_inventory_adjustment_lines al', 'al.adjustment_id = ah.id', 'left')
            ->join('inventory_locations iloc', 'iloc.id = ah.location_id', 'left')
            ->groupBy('ah.id')
            ->orderBy('ah.id', 'DESC');

        if ($from !== '') {
            $builder->where('ah.adjustment_date >=', $from);
        }
        if ($to !== '') {
            $builder->where('ah.adjustment_date <=', $to);
        }

        return view('admin/diamond_inventory/adjustments/index', [
            'title' => 'Diamond Stock Adjustments',
            'adjustments' => $builder->get()->getResultArray(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): string
    {
        return view('admin/diamond_inventory/adjustments/create', [
            'title' => 'Create Diamond Adjustment',
            'items' => $this->itemOptions(),
            'locations' => $this->locationOptions(),
            'adjustment' => null,
            'lines' => [],
            'action' => site_url('admin/diamond-inventory/adjustments'),
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
                    'pcs' => $line['pcs'],
                    'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
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

        return redirect()->to(site_url('admin/diamond-inventory/adjustments'))
            ->with('success', 'Diamond adjustment saved and stock updated.');
    }

    public function view(int $id)
    {
        $adjustment = db_connect()->table('diamond_inventory_adjustment_headers ah')
            ->select('ah.*, iloc.name as location_name')
            ->join('inventory_locations iloc', 'iloc.id = ah.location_id', 'left')
            ->where('ah.id', $id)
            ->get()
            ->getRowArray();

        if (! $adjustment) {
            return redirect()->to(site_url('admin/diamond-inventory/adjustments'))->with('error', 'Adjustment not found.');
        }

        return view('admin/diamond_inventory/adjustments/view', [
            'title' => 'View Diamond Adjustment',
            'adjustment' => $adjustment,
            'lines' => $this->lineRows($id),
            'totals' => $this->lineTotals('diamond_inventory_adjustment_lines', 'adjustment_id', $id),
        ]);
    }

    public function edit(int $id)
    {
        $adjustment = $this->headerModel->find($id);
        if (! $adjustment) {
            return redirect()->to(site_url('admin/diamond-inventory/adjustments'))->with('error', 'Adjustment not found.');
        }

        return view('admin/diamond_inventory/adjustments/edit', [
            'title' => 'Edit Diamond Adjustment',
            'items' => $this->itemOptions(),
            'locations' => $this->locationOptions(),
            'adjustment' => $adjustment,
            'lines' => $this->lineRows($id),
            'action' => site_url('admin/diamond-inventory/adjustments/' . $id . '/update'),
        ]);
    }

    public function update(int $id)
    {
        $adjustment = $this->headerModel->find($id);
        if (! $adjustment) {
            return redirect()->to(site_url('admin/diamond-inventory/adjustments'))->with('error', 'Adjustment not found.');
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
                    'pcs' => $line['pcs'],
                    'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'],
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

        return redirect()->to(site_url('admin/diamond-inventory/adjustments/view/' . $id))
            ->with('success', 'Diamond adjustment updated and stock recalculated.');
    }

    public function delete(int $id)
    {
        $adjustment = $this->headerModel->find($id);
        if (! $adjustment) {
            return redirect()->to(site_url('admin/diamond-inventory/adjustments'))->with('error', 'Adjustment not found.');
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
            return redirect()->to(site_url('admin/diamond-inventory/adjustments'))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('admin/diamond-inventory/adjustments'))
            ->with('success', 'Diamond adjustment deleted and stock restored.');
    }

    /**
     * @return array{lines:list<array<string,mixed>>,error:?string}
     */
    private function collectLinesFromRequest(): array
    {
        $itemIds = (array) $this->request->getPost('item_id');
        $diamondTypes = (array) $this->request->getPost('diamond_type');
        $shapes = (array) $this->request->getPost('shape');
        $chalniFroms = (array) $this->request->getPost('chalni_from');
        $chalniTos = (array) $this->request->getPost('chalni_to');
        $colors = (array) $this->request->getPost('color');
        $clarities = (array) $this->request->getPost('clarity');
        $cuts = (array) $this->request->getPost('cut');
        $pcs = (array) $this->request->getPost('pcs');
        $carats = (array) $this->request->getPost('carat');
        $rates = (array) $this->request->getPost('rate_per_carat');
        $reasons = (array) $this->request->getPost('reason');

        $max = max(
            count($itemIds),
            count($diamondTypes),
            count($shapes),
            count($chalniFroms),
            count($chalniTos),
            count($colors),
            count($clarities),
            count($cuts),
            count($pcs),
            count($carats),
            count($rates),
            count($reasons)
        );

        $lines = [];
        for ($i = 0; $i < $max; $i++) {
            $itemId = (int) ($itemIds[$i] ?? 0);
            $diamondType = trim((string) ($diamondTypes[$i] ?? ''));
            $shape = trim((string) ($shapes[$i] ?? ''));
            $chalniFromRaw = trim((string) ($chalniFroms[$i] ?? ''));
            $chalniToRaw = trim((string) ($chalniTos[$i] ?? ''));
            $color = trim((string) ($colors[$i] ?? ''));
            $clarity = trim((string) ($clarities[$i] ?? ''));
            $cut = trim((string) ($cuts[$i] ?? ''));
            $pcsValue = (float) ($pcs[$i] ?? 0);
            $caratValue = (float) ($carats[$i] ?? 0);
            $rateRaw = trim((string) ($rates[$i] ?? ''));
            $reason = trim((string) ($reasons[$i] ?? ''));

            $isBlank = $itemId <= 0
                && $diamondType === ''
                && $shape === ''
                && $chalniFromRaw === ''
                && $chalniToRaw === ''
                && $color === ''
                && $clarity === ''
                && $cut === ''
                && $pcsValue <= 0
                && $caratValue <= 0
                && $rateRaw === ''
                && $reason === '';
            if ($isBlank) {
                continue;
            }

            if ($caratValue <= 0) {
                return ['lines' => [], 'error' => 'Carat must be greater than zero for each line.'];
            }
            if ($pcsValue < 0) {
                return ['lines' => [], 'error' => 'PCS cannot be negative.'];
            }

            $rateValue = $rateRaw === '' ? null : (float) $rateRaw;
            if ($rateValue !== null && $rateValue < 0) {
                return ['lines' => [], 'error' => 'Rate per carat cannot be negative.'];
            }

            if ($itemId <= 0) {
                if ($diamondType === '') {
                    return ['lines' => [], 'error' => 'Diamond type is required when item is not selected.'];
                }
                $from = $chalniFromRaw === '' ? null : $chalniFromRaw;
                $to = $chalniToRaw === '' ? null : $chalniToRaw;
                if (($from === null && $to !== null) || ($from !== null && $to === null)) {
                    return ['lines' => [], 'error' => 'Both chalni from and chalni to are required when chalni is used.'];
                }
                if ($from !== null && ! ctype_digit($from)) {
                    return ['lines' => [], 'error' => 'Chalni from must contain digits only.'];
                }
                if ($to !== null && ! ctype_digit($to)) {
                    return ['lines' => [], 'error' => 'Chalni to must contain digits only.'];
                }
                if ($from !== null && $to !== null && ((int) ltrim($from, '0')) > ((int) ltrim($to, '0'))) {
                    return ['lines' => [], 'error' => 'Chalni from must be less than or equal to chalni to.'];
                }

                $signature = [
                    'diamond_type' => $diamondType,
                    'shape' => $shape,
                    'chalni_from' => $from,
                    'chalni_to' => $to,
                    'color' => $color,
                    'clarity' => $clarity,
                    'cut' => $cut,
                ];
            } else {
                if (! $this->itemModel->find($itemId)) {
                    return ['lines' => [], 'error' => 'Selected item does not exist.'];
                }
                $signature = [];
            }

            $lineValue = $rateValue === null ? null : round($caratValue * $rateValue, 2);
            $lines[] = [
                'item_id' => $itemId,
                'pcs' => round($pcsValue, 3),
                'carat' => round($caratValue, 3),
                'rate_per_carat' => $rateValue === null ? null : round($rateValue, 2),
                'line_value' => $lineValue,
                'reason' => $reason === '' ? null : $reason,
                'signature' => $signature,
            ];
        }

        return ['lines' => $lines, 'error' => null];
    }

    private function validateHeader()
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
        return $this->itemModel->orderBy('diamond_type', 'ASC')->orderBy('id', 'DESC')->findAll();
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
        return db_connect()->table('diamond_inventory_adjustment_lines al')
            ->select('al.*, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut')
            ->join('items i', 'i.id = al.item_id', 'left')
            ->where('al.adjustment_id', $adjustmentId)
            ->orderBy('al.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{total_pcs:float,total_carat:float,total_value:float}
     */
    private function lineTotals(string $table, string $headerField, int $headerId): array
    {
        $row = db_connect()->table($table)
            ->select('COALESCE(SUM(pcs),0) as total_pcs, COALESCE(SUM(carat),0) as total_carat, COALESCE(SUM(line_value),0) as total_value', false)
            ->where($headerField, $headerId)
            ->get()
            ->getRowArray();

        return [
            'total_pcs' => (float) ($row['total_pcs'] ?? 0),
            'total_carat' => (float) ($row['total_carat'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }
}
