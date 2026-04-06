<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CustomerModel;
use App\Models\FgItemModel;
use App\Models\OrderModel;
use App\Models\ShowroomCounterModel;
use App\Models\ShowroomFgMovementModel;
use App\Models\ShowroomModel;
use App\Models\ShowroomReservationModel;

class ShowroomStockController extends BaseController
{
    private FgItemModel $fgItemModel;
    private ShowroomModel $showroomModel;
    private ShowroomCounterModel $counterModel;
    private ShowroomFgMovementModel $movementModel;
    private ShowroomReservationModel $reservationModel;
    private CustomerModel $customerModel;
    private OrderModel $orderModel;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->fgItemModel = new FgItemModel();
        $this->showroomModel = new ShowroomModel();
        $this->counterModel = new ShowroomCounterModel();
        $this->movementModel = new ShowroomFgMovementModel();
        $this->reservationModel = new ShowroomReservationModel();
        $this->customerModel = new CustomerModel();
        $this->orderModel = new OrderModel();
    }

    public function index(): string
    {
        $cards = $this->summaryCards();
        $rows = db_connect()->table('fg_items fg')
            ->select('fg.*, s.name as showroom_name, c.counter_name, o.order_no, r.id as reservation_id, r.reserved_for_name, r.reservation_status, cust.name as customer_name')
            ->join('showrooms s', 's.id = fg.showroom_id', 'left')
            ->join('showroom_counters c', 'c.id = fg.showroom_counter_id', 'left')
            ->join('orders o', 'o.id = fg.order_id', 'left')
            ->join('showroom_reservations r', "r.fg_item_id = fg.id AND r.reservation_status = 'Reserved'", 'left', false)
            ->join('customers cust', 'cust.id = r.customer_id', 'left')
            ->where('fg.showroom_id IS NOT NULL', null, false)
            ->orderBy('fg.updated_at', 'DESC')
            ->get()
            ->getResultArray();

        $movements = db_connect()->table('showroom_fg_movements m')
            ->select('m.*, fg.tag_no, fs.name as from_showroom_name, ts.name as to_showroom_name, fc.counter_name as from_counter_name, tc.counter_name as to_counter_name')
            ->join('fg_items fg', 'fg.id = m.fg_item_id', 'left')
            ->join('showrooms fs', 'fs.id = m.from_showroom_id', 'left')
            ->join('showrooms ts', 'ts.id = m.to_showroom_id', 'left')
            ->join('showroom_counters fc', 'fc.id = m.from_counter_id', 'left')
            ->join('showroom_counters tc', 'tc.id = m.to_counter_id', 'left')
            ->orderBy('m.id', 'DESC')
            ->get(200)
            ->getResultArray();

        $reservations = db_connect()->table('showroom_reservations r')
            ->select('r.*, fg.tag_no, s.name as showroom_name, cust.name as customer_name, o.order_no')
            ->join('fg_items fg', 'fg.id = r.fg_item_id', 'left')
            ->join('showrooms s', 's.id = r.showroom_id', 'left')
            ->join('customers cust', 'cust.id = r.customer_id', 'left')
            ->join('orders o', 'o.id = r.order_id', 'left')
            ->orderBy('r.id', 'DESC')
            ->get(200)
            ->getResultArray();

        return view('admin/showroom_stock/index', [
            'title' => 'Showroom Stock',
            'cards' => $cards,
            'rows' => $rows,
            'movements' => $movements,
            'reservations' => $reservations,
        ]);
    }

    public function transferForm(): string
    {
        return view('admin/showroom_stock/transfer', [
            'title' => 'Transfer FG To Showroom',
            'showrooms' => $this->activeShowrooms(),
            'fgItems' => $this->fgStoreItems(),
            'formAction' => site_url('admin/showroom-stock/transfer'),
        ]);
    }

    public function transfer()
    {
        $rules = [
            'showroom_id' => 'required|integer|greater_than[0]',
            'fg_item_ids' => 'required',
            'remarks' => 'permit_empty',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $showroomId = (int) $this->request->getPost('showroom_id');
        $showroom = $this->showroomModel->find($showroomId);
        if (! $showroom) {
            return redirect()->back()->withInput()->with('error', 'Showroom not found.');
        }

        $fgItemIds = array_values(array_unique(array_map('intval', (array) $this->request->getPost('fg_item_ids'))));
        $fgItemIds = array_values(array_filter($fgItemIds, static fn(int $id): bool => $id > 0));
        if ($fgItemIds === []) {
            return redirect()->back()->withInput()->with('error', 'Select at least one FG item.');
        }

        $db = db_connect();
        $db->transStart();

        $fgRows = $this->fgItemModel->whereIn('id', $fgItemIds)->findAll();
        foreach ($fgRows as $fg) {
            $currentStatus = strtoupper(trim((string) ($fg['showroom_stock_status'] ?? 'FG_STORE')));
            if (! in_array($currentStatus, ['FG_STORE', 'AVAILABLE', ''], true) || (int) ($fg['showroom_id'] ?? 0) > 0) {
                continue;
            }

            $this->fgItemModel->update((int) $fg['id'], [
                'showroom_id' => $showroomId,
                'showroom_counter_id' => null,
                'showroom_stock_status' => 'SHOWROOM_AVAILABLE',
                'warehouse_id' => $showroom['warehouse_location_id'] ?? $fg['warehouse_id'],
            ]);

            $this->movementModel->insert([
                'fg_item_id' => (int) $fg['id'],
                'movement_type' => 'FG_TO_SHOWROOM',
                'from_showroom_id' => null,
                'to_showroom_id' => $showroomId,
                'remarks' => trim((string) $this->request->getPost('remarks')) ?: 'FG moved to showroom',
                'created_by' => (int) (session('admin_id') ?? 0),
            ]);
        }

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to transfer FG items right now.');
        }

        return redirect()->to(site_url('admin/showroom-stock'))->with('success', 'FG items transferred to showroom.');
    }

    public function allocationForm(): string
    {
        return view('admin/showroom_stock/allocation', [
            'title' => 'Allocate Showroom Stock To Counter',
            'showrooms' => $this->activeShowrooms(),
            'counters' => $this->activeCounters(),
            'fgItems' => $this->showroomAvailableItems(),
            'formAction' => site_url('admin/showroom-stock/allocate'),
        ]);
    }

    public function counterReturnForm(): string
    {
        $prefillFgItemId = (int) ($this->request->getGet('fg_item_id') ?? 0);
        return view('admin/showroom_stock/counter_return', [
            'title' => 'Return Counter Stock To Showroom',
            'showrooms' => $this->activeShowrooms(),
            'counters' => $this->activeCounters(),
            'fgItems' => $this->counterAllocatedItems(),
            'formAction' => site_url('admin/showroom-stock/counter-return'),
            'prefillFgItemId' => $prefillFgItemId > 0 ? $prefillFgItemId : null,
        ]);
    }

    public function allocate()
    {
        $rules = [
            'showroom_counter_id' => 'required|integer|greater_than[0]',
            'fg_item_ids' => 'required',
            'remarks' => 'permit_empty',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $counterId = (int) $this->request->getPost('showroom_counter_id');
        $counter = $this->counterModel->find($counterId);
        if (! $counter) {
            return redirect()->back()->withInput()->with('error', 'Counter not found.');
        }

        $fgItemIds = array_values(array_unique(array_map('intval', (array) $this->request->getPost('fg_item_ids'))));
        $db = db_connect();
        $db->transStart();

        $fgRows = $this->fgItemModel->whereIn('id', $fgItemIds)->findAll();
        foreach ($fgRows as $fg) {
            if ((int) ($fg['showroom_id'] ?? 0) !== (int) ($counter['showroom_id'] ?? 0)) {
                continue;
            }
            if (strtoupper(trim((string) ($fg['showroom_stock_status'] ?? ''))) === 'RESERVED') {
                continue;
            }

            $this->fgItemModel->update((int) $fg['id'], [
                'showroom_counter_id' => $counterId,
                'showroom_stock_status' => 'COUNTER_AVAILABLE',
            ]);

            $this->movementModel->insert([
                'fg_item_id' => (int) $fg['id'],
                'movement_type' => 'SHOWROOM_TO_COUNTER',
                'from_showroom_id' => (int) ($fg['showroom_id'] ?? 0),
                'to_showroom_id' => (int) ($fg['showroom_id'] ?? 0),
                'from_counter_id' => $fg['showroom_counter_id'] ? (int) $fg['showroom_counter_id'] : null,
                'to_counter_id' => $counterId,
                'remarks' => trim((string) $this->request->getPost('remarks')) ?: 'FG moved to counter',
                'created_by' => (int) (session('admin_id') ?? 0),
            ]);
        }

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to allocate showroom stock.');
        }

        return redirect()->to(site_url('admin/showroom-stock'))->with('success', 'Counter allocation saved.');
    }

    public function reservationForm(): string
    {
        return view('admin/showroom_stock/reservation', [
            'title' => 'Reserve Showroom Stock',
            'showrooms' => $this->activeShowrooms(),
            'fgItems' => $this->reservableItems(),
            'customers' => $this->customerModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'orders' => $this->orderModel->orderBy('id', 'DESC')->findAll(200),
            'formAction' => site_url('admin/showroom-stock/reserve'),
        ]);
    }

    public function reserve()
    {
        $rules = [
            'fg_item_id' => 'required|integer|greater_than[0]',
            'expires_on' => 'permit_empty|valid_date',
            'customer_id' => 'permit_empty|integer',
            'order_id' => 'permit_empty|integer',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $fgItemId = (int) $this->request->getPost('fg_item_id');
        $fg = $this->fgItemModel->find($fgItemId);
        if (! $fg || (int) ($fg['showroom_id'] ?? 0) <= 0) {
            return redirect()->back()->withInput()->with('error', 'Selected FG item is not in showroom stock.');
        }

        $db = db_connect();
        $db->transStart();

        $this->reservationModel->insert([
            'fg_item_id' => $fgItemId,
            'showroom_id' => (int) ($fg['showroom_id'] ?? 0),
            'customer_id' => $this->nullableInt($this->request->getPost('customer_id')),
            'order_id' => $this->nullableInt($this->request->getPost('order_id')),
            'reserved_for_name' => trim((string) $this->request->getPost('reserved_for_name')) ?: null,
            'reserved_for_phone' => trim((string) $this->request->getPost('reserved_for_phone')) ?: null,
            'reservation_status' => 'Reserved',
            'reserved_on' => date('Y-m-d H:i:s'),
            'expires_on' => $this->nullableDateTime((string) $this->request->getPost('expires_on')),
            'notes' => trim((string) $this->request->getPost('notes')) ?: null,
            'created_by' => (int) (session('admin_id') ?? 0),
        ]);

        $this->fgItemModel->update($fgItemId, [
            'reserved_order_id' => $this->nullableInt($this->request->getPost('order_id')),
            'showroom_stock_status' => 'RESERVED',
        ]);

        $this->movementModel->insert([
            'fg_item_id' => $fgItemId,
            'movement_type' => 'RESERVED',
            'to_showroom_id' => (int) ($fg['showroom_id'] ?? 0),
            'to_counter_id' => $fg['showroom_counter_id'] ? (int) $fg['showroom_counter_id'] : null,
            'remarks' => trim((string) $this->request->getPost('notes')) ?: 'FG reserved',
            'created_by' => (int) (session('admin_id') ?? 0),
        ]);

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to reserve FG item.');
        }

        return redirect()->to(site_url('admin/showroom-stock'))->with('success', 'FG item reserved.');
    }

    public function counterReturn()
    {
        $rules = [
            'fg_item_ids' => 'required',
            'remarks' => 'permit_empty',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $fgItemIds = array_values(array_unique(array_map('intval', (array) $this->request->getPost('fg_item_ids'))));
        $fgItemIds = array_values(array_filter($fgItemIds, static fn(int $id): bool => $id > 0));
        if ($fgItemIds === []) {
            return redirect()->back()->withInput()->with('error', 'Select at least one counter item.');
        }

        $db = db_connect();
        $db->transStart();

        $fgRows = $this->fgItemModel->whereIn('id', $fgItemIds)->findAll();
        foreach ($fgRows as $fg) {
            $currentStatus = strtoupper(trim((string) ($fg['showroom_stock_status'] ?? '')));
            if ((int) ($fg['showroom_id'] ?? 0) <= 0 || (int) ($fg['showroom_counter_id'] ?? 0) <= 0) {
                continue;
            }
            if (in_array($currentStatus, ['RESERVED', 'SOLD'], true)) {
                continue;
            }

            $this->fgItemModel->update((int) $fg['id'], [
                'showroom_counter_id' => null,
                'showroom_stock_status' => 'SHOWROOM_AVAILABLE',
            ]);

            $this->movementModel->insert([
                'fg_item_id' => (int) $fg['id'],
                'movement_type' => 'COUNTER_TO_SHOWROOM',
                'from_showroom_id' => (int) ($fg['showroom_id'] ?? 0),
                'to_showroom_id' => (int) ($fg['showroom_id'] ?? 0),
                'from_counter_id' => (int) ($fg['showroom_counter_id'] ?? 0),
                'to_counter_id' => null,
                'remarks' => trim((string) $this->request->getPost('remarks')) ?: 'Counter stock returned to showroom',
                'created_by' => (int) (session('admin_id') ?? 0),
            ]);
        }

        $db->transComplete();
        if (! $db->transStatus()) {
            return redirect()->back()->withInput()->with('error', 'Unable to return counter stock right now.');
        }

        return redirect()->to(site_url('admin/showroom-stock'))->with('success', 'Selected counter items returned to showroom.');
    }

    public function releaseReservation(int $id)
    {
        $reservation = $this->reservationModel->find($id);
        if (! $reservation) {
            return redirect()->to(site_url('admin/showroom-stock'))->with('error', 'Reservation not found.');
        }
        if ((string) ($reservation['reservation_status'] ?? '') !== 'Reserved') {
            return redirect()->to(site_url('admin/showroom-stock'))->with('warning', 'Reservation is already closed.');
        }

        $db = db_connect();
        $db->transStart();

        $this->reservationModel->update($id, [
            'reservation_status' => 'Released',
            'released_on' => date('Y-m-d H:i:s'),
        ]);

        $fg = $this->fgItemModel->find((int) $reservation['fg_item_id']);
        if ($fg) {
            $newStatus = (int) ($fg['showroom_counter_id'] ?? 0) > 0 ? 'COUNTER_AVAILABLE' : 'SHOWROOM_AVAILABLE';
            $this->fgItemModel->update((int) $reservation['fg_item_id'], [
                'reserved_order_id' => null,
                'showroom_stock_status' => $newStatus,
            ]);

            $this->movementModel->insert([
                'fg_item_id' => (int) $reservation['fg_item_id'],
                'movement_type' => 'RESERVATION_RELEASED',
                'to_showroom_id' => (int) ($fg['showroom_id'] ?? 0),
                'to_counter_id' => $fg['showroom_counter_id'] ? (int) $fg['showroom_counter_id'] : null,
                'remarks' => 'Showroom reservation released',
                'created_by' => (int) (session('admin_id') ?? 0),
            ]);
        }

        $db->transComplete();

        return redirect()->to(site_url('admin/showroom-stock'))->with('success', 'Reservation released.');
    }

    private function summaryCards(): array
    {
        $rows = db_connect()->table('fg_items')
            ->select("COUNT(*) as total_items, COALESCE(SUM(gross_wt),0) as gross_wt, COALESCE(SUM(net_gold_wt),0) as net_gold_wt, COALESCE(SUM(diamond_cts),0) as diamond_cts", false)
            ->where('showroom_id IS NOT NULL', null, false)
            ->get()
            ->getRowArray() ?? [];

        $reserved = db_connect()->table('fg_items')
            ->where('showroom_stock_status', 'RESERVED')
            ->countAllResults();

        $counters = db_connect()->table('fg_items')
            ->where('showroom_counter_id IS NOT NULL', null, false)
            ->countAllResults();

        return [
            'total_items' => (int) ($rows['total_items'] ?? 0),
            'gross_wt' => (float) ($rows['gross_wt'] ?? 0),
            'net_gold_wt' => (float) ($rows['net_gold_wt'] ?? 0),
            'diamond_cts' => (float) ($rows['diamond_cts'] ?? 0),
            'reserved_items' => $reserved,
            'counter_items' => $counters,
        ];
    }

    private function activeShowrooms(): array
    {
        return db_connect()->table('showrooms')
            ->select('id, name, showroom_code, warehouse_location_id')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function activeCounters(): array
    {
        return db_connect()->table('showroom_counters c')
            ->select('c.*, s.name as showroom_name')
            ->join('showrooms s', 's.id = c.showroom_id', 'left')
            ->where('c.is_active', 1)
            ->orderBy('s.name', 'ASC')
            ->orderBy('c.counter_name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function fgStoreItems(): array
    {
        return db_connect()->table('fg_items fg')
            ->select('fg.id, fg.tag_no, fg.gross_wt, fg.net_gold_wt, fg.diamond_cts, o.order_no')
            ->join('orders o', 'o.id = fg.order_id', 'left')
            ->where('fg.showroom_id IS NULL', null, false)
            ->whereIn('fg.status', ['AVAILABLE', 'Available', 'Completed', 'Ready'])
            ->orderBy('fg.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    private function showroomAvailableItems(): array
    {
        return db_connect()->table('fg_items fg')
            ->select('fg.id, fg.tag_no, fg.showroom_id, fg.showroom_counter_id, fg.gross_wt, fg.net_gold_wt, fg.diamond_cts, fg.showroom_stock_status, s.name as showroom_name')
            ->join('showrooms s', 's.id = fg.showroom_id', 'left')
            ->where('fg.showroom_id IS NOT NULL', null, false)
            ->whereNotIn('fg.showroom_stock_status', ['RESERVED'])
            ->orderBy('s.name', 'ASC')
            ->orderBy('fg.tag_no', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function reservableItems(): array
    {
        return db_connect()->table('fg_items fg')
            ->select('fg.id, fg.tag_no, fg.showroom_id, fg.showroom_counter_id, fg.gross_wt, fg.net_gold_wt, s.name as showroom_name, c.counter_name')
            ->join('showrooms s', 's.id = fg.showroom_id', 'left')
            ->join('showroom_counters c', 'c.id = fg.showroom_counter_id', 'left')
            ->where('fg.showroom_id IS NOT NULL', null, false)
            ->whereIn('fg.showroom_stock_status', ['SHOWROOM_AVAILABLE', 'COUNTER_AVAILABLE'])
            ->orderBy('s.name', 'ASC')
            ->orderBy('fg.tag_no', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function counterAllocatedItems(): array
    {
        return db_connect()->table('fg_items fg')
            ->select('fg.id, fg.tag_no, fg.showroom_id, fg.showroom_counter_id, fg.gross_wt, fg.net_gold_wt, fg.diamond_cts, fg.showroom_stock_status, s.name as showroom_name, c.counter_name')
            ->join('showrooms s', 's.id = fg.showroom_id', 'left')
            ->join('showroom_counters c', 'c.id = fg.showroom_counter_id', 'left')
            ->where('fg.showroom_id IS NOT NULL', null, false)
            ->where('fg.showroom_counter_id IS NOT NULL', null, false)
            ->whereIn('fg.showroom_stock_status', ['COUNTER_AVAILABLE'])
            ->orderBy('s.name', 'ASC')
            ->orderBy('c.counter_name', 'ASC')
            ->orderBy('fg.tag_no', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    private function nullableDateTime(string $value): ?string
    {
        if (trim($value) === '') {
            return null;
        }
        return date('Y-m-d H:i:s', strtotime($value));
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }
}
