<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\FgItemModel;
use App\Models\ShowroomFgMovementModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class JewelleryInventoryController extends BaseController
{
    private const TERMINAL_STATUSES = ['TRANSFERRED', 'DELIVERED', 'SOLD', 'DISPATCHED'];

    private FgItemModel $fgItemModel;
    private ShowroomFgMovementModel $movementModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->fgItemModel = new FgItemModel();
        $this->movementModel = new ShowroomFgMovementModel();
    }

    public function index(): string
    {
        $db = db_connect();
        $karigarId = (int) ($this->request->getGet('karigar_id') ?? 0);
        $builder = $db->table('fg_items fg')
            ->select('fg.*, p.ready_group, p.ready_date, p.serial_no, p.reference_no, p.gold_amount, p.labour_charges, p.total_value, p.payment_status, p.source_sheet, p.source_row, k.id as karigar_id, k.name as karigar_name, o.order_no', false)
            ->join('production_ready_items p', 'p.id = fg.production_ready_item_id', 'left')
            ->join('karigars k', 'k.id = p.karigar_id', 'left')
            ->join('orders o', 'o.id = fg.order_id', 'left');
        if ($karigarId > 0) {
            $builder->where('p.karigar_id', $karigarId);
        }
        $allRows = $builder->orderBy('fg.id', 'DESC')->get()->getResultArray();
        $activeRows = [];
        $closedRows = [];
        foreach ($allRows as $row) {
            $status = strtoupper(trim((string) (($row['showroom_stock_status'] ?? '') ?: ($row['status'] ?? ''))));
            if (in_array($status, self::TERMINAL_STATUSES, true)) {
                $closedRows[] = $row;
            } else {
                $activeRows[] = $row;
            }
        }

        $historyBuilder = $db->table('showroom_fg_movements m')
            ->select('m.*, fg.tag_no, fg.design_name, o.order_no', false)
            ->join('fg_items fg', 'fg.id = m.fg_item_id', 'left')
            ->join('orders o', 'o.id = fg.order_id', 'left')
            ->whereIn('m.movement_type', ['INVENTORY_TRANSFERRED', 'INVENTORY_DELIVERED', 'SOLD', 'DISPATCHED']);
        $history = $historyBuilder->orderBy('m.id', 'DESC')->get()->getResultArray();

        return view('admin/jewellery_inventory/index', [
            'title' => 'Jewellery Inventory',
            'activeRows' => $activeRows,
            'closedRows' => $closedRows,
            'history' => $history,
            'karigars' => $db->table('karigars')->select('id, name')->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray(),
            'karigarId' => $karigarId,
            'summary' => $this->summary($activeRows, $closedRows),
        ]);
    }

    public function close(int $id)
    {
        $rules = [
            'inventory_status' => 'required|in_list[TRANSFERRED,DELIVERED]',
            'remark' => 'required|max_length[1000]',
        ];
        if (! $this->validate($rules)) {
            $errors = $this->validator ? $this->validator->getErrors() : [];
            return redirect()->back()->with('error', $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0]);
        }
        $item = $this->fgItemModel->find($id);
        if (! $item) {
            throw PageNotFoundException::forPageNotFound('Jewellery item not found.');
        }
        $currentStatus = strtoupper(trim((string) (($item['showroom_stock_status'] ?? '') ?: ($item['status'] ?? ''))));
        if (in_array($currentStatus, self::TERMINAL_STATUSES, true)) {
            return redirect()->back()->with('warning', 'This jewellery item is already out of active inventory.');
        }

        $status = strtoupper((string) $this->request->getPost('inventory_status'));
        $remark = trim((string) $this->request->getPost('remark'));
        $db = db_connect();
        $db->transStart();
        $this->fgItemModel->update($id, [
            'status' => $status,
            'showroom_stock_status' => $status,
            'inventory_remarks' => $remark,
            'terminal_at' => date('Y-m-d H:i:s'),
        ]);
        $this->movementModel->insert([
            'fg_item_id' => $id,
            'movement_type' => 'INVENTORY_' . $status,
            'from_showroom_id' => $item['showroom_id'] ?: null,
            'from_counter_id' => $item['showroom_counter_id'] ?: null,
            'reference_type' => 'finished_jewellery',
            'reference_id' => $id,
            'remarks' => $remark,
            'created_by' => (int) (session('admin_id') ?? 0),
        ]);
        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->with('error', 'Unable to update jewellery inventory.');
        }
        return redirect()->to(site_url('admin/jewellery-inventory'))->with('success', 'Jewellery marked ' . strtolower($status) . '; the history and remark were retained.');
    }

    public function image(int $id)
    {
        $item = db_connect()->table('production_ready_items')->select('image_path, design_name')->where('id', $id)->get()->getRowArray();
        if (! $item || trim((string) ($item['image_path'] ?? '')) === '') {
            throw PageNotFoundException::forPageNotFound();
        }
        $relativePath = ltrim(str_replace(['\\', '..'], ['/', ''], (string) $item['image_path']), '/');
        $fullPath = null;
        foreach ([
            [FCPATH . $relativePath, realpath(FCPATH . 'uploads')],
            [WRITEPATH . $relativePath, realpath(WRITEPATH . 'uploads')],
        ] as [$candidate, $allowedRoot]) {
            $resolved = realpath($candidate);
            if ($resolved && $allowedRoot && str_starts_with($resolved, $allowedRoot . DIRECTORY_SEPARATOR) && is_file($resolved)) {
                $fullPath = $resolved;
                break;
            }
        }
        if ($fullPath === null) {
            throw PageNotFoundException::forPageNotFound();
        }
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';
        if (! str_starts_with($mime, 'image/')) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->response->setHeader('Content-Type', $mime)->setHeader('Cache-Control', 'private, max-age=3600')->setBody((string) file_get_contents($fullPath));
    }

    /** @return array<string,float|int> */
    private function summary(array $activeRows, array $closedRows): array
    {
        return [
            'active_items' => count($activeRows),
            'closed_items' => count($closedRows),
            'gross_weight' => array_sum(array_map(static fn(array $row): float => (float) ($row['gross_wt'] ?? 0), $activeRows)),
            'net_gold_weight' => array_sum(array_map(static fn(array $row): float => (float) ($row['net_gold_wt'] ?? 0), $activeRows)),
            'diamond_cts' => array_sum(array_map(static fn(array $row): float => (float) ($row['diamond_cts'] ?? 0), $activeRows)),
        ];
    }
}
