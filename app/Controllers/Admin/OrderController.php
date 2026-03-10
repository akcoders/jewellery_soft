<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CustomerModel;
use App\Models\DiamondBagItemModel;
use App\Models\DiamondBagModel;
use App\Models\DiamondIssueModel;
use App\Models\DiamondLedgerEntryModel;
use App\Models\DesignMasterModel;
use App\Models\CompanySettingModel;
use App\Models\GoldLedgerEntryModel;
use App\Models\GoldPurityModel;
use App\Models\InventoryLocationModel;
use App\Models\InventoryTransactionModel;
use App\Models\JobCardModel;
use App\Models\KarigarModel;
use App\Models\LabourBillModel;
use App\Models\LeadModel;
use App\Models\OrderAttachmentModel;
use App\Models\OrderFollowupModel;
use App\Models\OrderItemModel;
use App\Models\OrderReceiveDetailModel;
use App\Models\OrderReceiveSummaryModel;
use App\Models\OrderModel;
use App\Models\OrderMaterialMovementModel;
use App\Models\OrderStatusHistoryModel;
use App\Models\StoneLedgerEntryModel;
use App\Models\DeliveryChallanModel;
use App\Services\AdminPostingService;
use App\Services\GoldInventory\StockService as GoldInventoryStockService;
use App\Services\PdfService;
use Config\Jewellery;
use Exception;
use Throwable;

class OrderController extends BaseController
{
    private OrderModel $orderModel;
    private OrderItemModel $orderItemModel;
    private OrderAttachmentModel $attachmentModel;
    private OrderFollowupModel $followupModel;
    private OrderStatusHistoryModel $historyModel;
    private OrderMaterialMovementModel $movementModel;
    private OrderReceiveDetailModel $receiveDetailModel;
    private OrderReceiveSummaryModel $receiveSummaryModel;
    private DiamondBagModel $diamondBagModel;
    private DiamondBagItemModel $diamondBagItemModel;
    private DiamondIssueModel $diamondIssueModel;
    private GoldLedgerEntryModel $goldLedgerModel;
    private DiamondLedgerEntryModel $diamondLedgerModel;
    private StoneLedgerEntryModel $stoneLedgerModel;
    private JobCardModel $jobCardModel;
    private KarigarModel $karigarModel;
    private LabourBillModel $labourBillModel;
    private CompanySettingModel $companySettingModel;
    private CustomerModel $customerModel;
    private LeadModel $leadModel;
    private DesignMasterModel $designModel;
    private GoldPurityModel $goldPurityModel;
    private InventoryLocationModel $locationModel;
    private InventoryTransactionModel $inventoryTxnModel;
    private DeliveryChallanModel $deliveryChallanModel;
    private AdminPostingService $adminPostingService;
    private PdfService $pdfService;
    private Jewellery $jewelleryConfig;

    public function __construct()
    {
        helper(['form', 'url']);
        $this->orderModel      = new OrderModel();
        $this->orderItemModel  = new OrderItemModel();
        $this->attachmentModel = new OrderAttachmentModel();
        $this->followupModel   = new OrderFollowupModel();
        $this->historyModel    = new OrderStatusHistoryModel();
        $this->movementModel   = new OrderMaterialMovementModel();
        $this->receiveDetailModel = new OrderReceiveDetailModel();
        $this->receiveSummaryModel = new OrderReceiveSummaryModel();
        $this->diamondBagModel = new DiamondBagModel();
        $this->diamondBagItemModel = new DiamondBagItemModel();
        $this->diamondIssueModel = new DiamondIssueModel();
        $this->goldLedgerModel = new GoldLedgerEntryModel();
        $this->diamondLedgerModel = new DiamondLedgerEntryModel();
        $this->stoneLedgerModel = new StoneLedgerEntryModel();
        $this->jobCardModel    = new JobCardModel();
        $this->karigarModel    = new KarigarModel();
        $this->labourBillModel = new LabourBillModel();
        $this->companySettingModel = new CompanySettingModel();
        $this->customerModel   = new CustomerModel();
        $this->leadModel       = new LeadModel();
        $this->designModel     = new DesignMasterModel();
        $this->goldPurityModel = new GoldPurityModel();
        $this->locationModel   = new InventoryLocationModel();
        $this->inventoryTxnModel = new InventoryTransactionModel();
        $this->deliveryChallanModel = new DeliveryChallanModel();
        $this->adminPostingService = new AdminPostingService();
        $this->pdfService = new PdfService();
        $this->jewelleryConfig = config(Jewellery::class);
    }

    public function index(): string
    {
        return $this->renderOrderList('all');
    }

    public function fresh(): string
    {
        return $this->renderOrderList('fresh');
    }

    public function repair(): string
    {
        return $this->renderOrderList('repair');
    }

    public function ready(): string
    {
        return $this->renderOrderList('ready');
    }

    public function followups(): string
    {
        $this->syncCompletedOrdersFromReceive();

        $orders = $this->orderModel
            ->select('orders.*, customers.name as customer_name, karigars.name as karigar_name')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->join('karigars', 'karigars.id = orders.assigned_karigar_id', 'left')
            ->whereNotIn('orders.status', ['Completed', 'Cancelled', 'Ready'])
            ->orderBy('orders.id', 'DESC')
            ->findAll();

        $latestByOrder = [];
        if ($orders !== []) {
            $orderIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $orders);
            $orderIds = array_values(array_filter($orderIds, static fn(int $id): bool => $id > 0));

            if ($orderIds !== []) {
                $sub = db_connect()->table('order_followups')
                    ->select('MAX(id) as id')
                    ->whereIn('order_id', $orderIds)
                    ->groupBy('order_id')
                    ->getCompiledSelect();

                $latestRows = db_connect()->table('order_followups ofu')
                    ->select('ofu.*, admin_users.name as taken_by_name')
                    ->join('(' . $sub . ') latest', 'latest.id = ofu.id', 'inner', false)
                    ->join('admin_users', 'admin_users.id = ofu.followup_taken_by', 'left')
                    ->get()
                    ->getResultArray();

                foreach ($latestRows as $row) {
                    $latestByOrder[(int) ($row['order_id'] ?? 0)] = $row;
                }
            }
        }

        $today = strtotime(date('Y-m-d'));
        foreach ($orders as &$order) {
            $orderId = (int) ($order['id'] ?? 0);
            $latest = $latestByOrder[$orderId] ?? null;

            $order['last_followup_stage'] = (string) ($latest['stage'] ?? '-');
            $order['last_followup_on'] = (string) ($latest['followup_taken_on'] ?? '-');
            $order['last_followup_by'] = (string) ($latest['taken_by_name'] ?? '-');
            $order['next_followup_date'] = (string) ($latest['next_followup_date'] ?? '');

            $statusLabel = 'Followup Pending';
            $statusClass = 'warning';
            $daysText = 'Not Set';

            if ($latest && ! empty($latest['next_followup_date'])) {
                $nextTs = strtotime((string) $latest['next_followup_date']);
                if ($nextTs !== false) {
                    $diffDays = (int) floor(($nextTs - $today) / 86400);
                    if ($diffDays < 0) {
                        $statusLabel = 'Followup Delay';
                        $statusClass = 'danger';
                        $daysText = abs($diffDays) . ' day delay';
                    } elseif ($diffDays === 0) {
                        $statusLabel = 'Followup Pending';
                        $statusClass = 'warning';
                        $daysText = 'Today';
                    } else {
                        $statusLabel = 'Followup Pending';
                        $statusClass = 'info';
                        $daysText = $diffDays . ' day left';
                    }
                }
            }

            $order['followup_status_label'] = $statusLabel;
            $order['followup_status_class'] = $statusClass;
            $order['followup_days_text'] = $daysText;
        }
        unset($order);

        return view('admin/orders/followups', [
            'title' => 'Order Followups',
            'orders' => $orders,
            'statuses' => $this->jewelleryConfig->orderStatuses,
        ]);
    }

    private function renderOrderList(string $mode): string
    {
        $this->syncCompletedOrdersFromReceive();

        $orders = $this->orderModel
            ->select('orders.*, customers.name as customer_name, karigars.name as karigar_name, karigars.rate_per_gm as karigar_rate_per_gm')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->join('karigars', 'karigars.id = orders.assigned_karigar_id', 'left');

        if ($mode === 'repair') {
            $orders->where('orders.order_type', 'Repair');
        } elseif ($mode === 'ready') {
            $orders->where('orders.status', 'Completed');
        } elseif ($mode === 'fresh') {
            $orders->where('orders.order_type !=', 'Repair');
        }

        $rows = $orders->orderBy('orders.id', 'DESC')->findAll();
        $purityMap = $this->buildOrderPurityPercentMap($rows);
        foreach ($rows as &$row) {
            $oid = (int) ($row['id'] ?? 0);
            $row['avg_purity_percent'] = (float) ($purityMap[$oid] ?? 100);
        }
        unset($row);

        $title = 'All Orders';
        if ($mode === 'fresh') {
            $title = 'Fresh Orders';
        } elseif ($mode === 'ready') {
            $title = 'Ready Orders';
        } elseif ($mode === 'repair') {
            $title = 'Repair Orders';
        }

        return view('admin/orders/index', [
            'title'    => $title,
            'orders'   => $rows,
            'orderMode'=> $mode,
            'karigars' => $this->karigarModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'locations'=> $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'statuses' => $this->jewelleryConfig->orderStatuses,
        ]);
    }

    public function create(): string
    {
        return $this->renderCreateForm(false);
    }

    public function createRepair(): string
    {
        return $this->renderCreateForm(true);
    }

    private function renderCreateForm(bool $repairMode): string
    {
        return view('admin/orders/create', [
            'title'       => $repairMode ? 'Create Repair Order' : 'Create Order',
            'customers'   => $this->customerModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'leads'       => $this->leadModel->where('status', 'Open')->orderBy('id', 'DESC')->findAll(),
            'designs'     => $this->designModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'goldPurities'=> $this->goldPurityModel->where('is_active', 1)->orderBy('purity_percent', 'DESC')->findAll(),
            'priorities'  => $this->jewelleryConfig->orderPriorities,
            'statuses'    => $this->jewelleryConfig->orderStatuses,
            'repairMode'  => $repairMode,
        ]);
    }

    public function store()
    {
        $orderType = trim((string) $this->request->getPost('order_type'));
        $isRepairOrder = $this->isRepairType($orderType);

        $rules = [
            'order_type'  => 'required|max_length[30]',
            'customer_id' => 'permit_empty|integer',
            'lead_id'     => 'permit_empty|integer',
            'priority'    => 'required',
            'due_date'    => 'permit_empty|valid_date',
            'status'      => 'required',
            'order_notes' => 'permit_empty',
        ];
        if ($isRepairOrder) {
            $rules = $rules + [
                'repair_ornament_details' => 'required',
                'repair_work_details' => 'required',
                'repair_receive_weight_gm' => 'required|decimal|greater_than[0]',
                'repair_received_at' => 'required|valid_date',
            ];
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $status = (string) $this->request->getPost('status');
        if (! in_array($status, $this->jewelleryConfig->orderStatuses, true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid order status.');
        }

        $priority = (string) $this->request->getPost('priority');
        if (! in_array($priority, $this->jewelleryConfig->orderPriorities, true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid order priority.');
        }

        $items = $this->collectItemsFromRequest();
        if ($items === [] && ! $isRepairOrder) {
            return redirect()->back()->withInput()->with('error', 'At least one order item is required.');
        }
        if ($items === [] && $isRepairOrder) {
            $items[] = [
                'design_id' => null,
                'gold_purity_id' => null,
                'item_description' => trim((string) $this->request->getPost('repair_work_details')),
                'size_label' => null,
                'qty' => 1,
                'gold_required_gm' => 0,
                'diamond_required_cts' => 0,
            ];
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $orderNo = 'OR' . date('ymdHis') . random_int(10, 99);
            $orderId = $this->orderModel->insert([
                'order_no'    => $orderNo,
                'order_type'  => $isRepairOrder ? 'Repair' : $orderType,
                'customer_id' => $this->nullableInt($this->request->getPost('customer_id')),
                'lead_id'     => $this->nullableInt($this->request->getPost('lead_id')),
                'status'      => $status,
                'priority'    => $priority,
                'due_date'    => $this->nullableDate((string) $this->request->getPost('due_date')),
                'order_notes' => trim((string) $this->request->getPost('order_notes')),
                'repair_ornament_details' => $isRepairOrder ? trim((string) $this->request->getPost('repair_ornament_details')) : null,
                'repair_work_details' => $isRepairOrder ? trim((string) $this->request->getPost('repair_work_details')) : null,
                'repair_receive_weight_gm' => $isRepairOrder ? (float) $this->request->getPost('repair_receive_weight_gm') : null,
                'repair_received_at' => $isRepairOrder ? $this->nullableDate((string) $this->request->getPost('repair_received_at')) : null,
                'created_by'  => (int) session('admin_id'),
            ], true);

            foreach ($items as $i => $item) {
                $itemId = $this->orderItemModel->insert([
                    'order_id'              => (int) $orderId,
                    'design_id'             => $item['design_id'],
                    'gold_purity_id'        => $item['gold_purity_id'],
                    'item_description'      => $item['item_description'],
                    'size_label'            => $item['size_label'],
                    'qty'                   => $item['qty'],
                    'gold_required_gm'      => $item['gold_required_gm'],
                    'diamond_required_cts'  => $item['diamond_required_cts'],
                    'item_status'           => $status,
                ], true);

                $this->jobCardModel->insert([
                    'job_card_no'  => 'JC' . date('ymdHis') . random_int(10, 99) . $i,
                    'order_id'     => (int) $orderId,
                    'order_item_id'=> (int) $itemId,
                    'status'       => 'Pending',
                    'priority'     => $priority,
                    'due_date'     => $this->nullableDate((string) $this->request->getPost('due_date')),
                    'qc_status'    => 'Pending',
                    'created_by'   => (int) session('admin_id'),
                ]);
            }

            $this->historyModel->insert([
                'order_id'    => (int) $orderId,
                'from_status' => null,
                'to_status'   => $status,
                'remarks'     => 'Order created.',
                'changed_by'  => (int) session('admin_id'),
            ]);

            $this->storeAttachments((int) $orderId);
        } catch (Exception $e) {
            $db->transRollback();

            return redirect()->back()->withInput()->with('error', 'Could not create order: ' . $e->getMessage());
        }

        $db->transComplete();

        return redirect()->to(site_url('admin/orders/' . $orderId))->with('success', 'Order created successfully.');
    }

    public function show(int $id): string
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel
            ->select('orders.*, customers.name as customer_name, leads.name as lead_name, karigars.name as karigar_name, karigars.rate_per_gm as karigar_rate_per_gm')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->join('leads', 'leads.id = orders.lead_id', 'left')
            ->join('karigars', 'karigars.id = orders.assigned_karigar_id', 'left')
            ->find($id);

        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }

        $items = $this->orderItemModel
            ->select('order_items.*, design_masters.design_code, design_masters.name as design_name, gold_purities.purity_code, gold_purities.color_name')
            ->join('design_masters', 'design_masters.id = order_items.design_id', 'left')
            ->join('gold_purities', 'gold_purities.id = order_items.gold_purity_id', 'left')
            ->where('order_id', $id)
            ->findAll();

        $summary = [
            'gold_required_gm'     => 0.0,
            'diamond_required_cts' => 0.0,
        ];

        foreach ($items as $item) {
            $summary['gold_required_gm'] += (float) $item['gold_required_gm'];
            $summary['diamond_required_cts'] += (float) $item['diamond_required_cts'];
        }
        $receivePurityInfo = $this->getOrderPurityInfo($id);

        $db = db_connect();
        $movements = [];
        $goldLedgers = [];

        if (
            $db->tableExists('gold_inventory_ledger_entries')
            && $db->tableExists('gold_inventory_items')
        ) {
            $goldLedgers = $db->table('gold_inventory_ledger_entries gle')
                ->select("
                    gle.txn_type as entry_type,
                    k.name as karigar_name,
                    iloc.name as location_name,
                    gi.purity_code,
                    gi.color_name,
                    CASE
                        WHEN COALESCE(gle.credit_weight_gm, 0) > 0 THEN gle.credit_weight_gm
                        ELSE gle.debit_weight_gm
                    END as weight_gm,
                    CASE
                        WHEN COALESCE(gle.credit_fine_gm, 0) > 0 THEN gle.credit_fine_gm
                        ELSE gle.debit_fine_gm
                    END as pure_gold_weight_gm,
                    gle.reference_table as reference_type,
                    gle.reference_id,
                    gle.notes,
                    gle.created_at
                ", false)
                ->join('karigars k', 'k.id = gle.karigar_id', 'left')
                ->join('inventory_locations iloc', 'iloc.id = gle.location_id', 'left')
                ->join('gold_inventory_items gi', 'gi.id = gle.item_id', 'left')
                ->where('gle.order_id', $id)
                ->orderBy('gle.id', 'DESC')
                ->get()
                ->getResultArray();
        }

        if ($goldLedgers === []) {
            $goldLedgers = $this->goldLedgerModel
                ->select('gold_ledger_entries.*, karigars.name as karigar_name, gold_purities.purity_code, gold_purities.color_name, inventory_locations.name as location_name')
                ->join('karigars', 'karigars.id = gold_ledger_entries.karigar_id', 'left')
                ->join('gold_purities', 'gold_purities.id = gold_ledger_entries.gold_purity_id', 'left')
                ->join('inventory_locations', 'inventory_locations.id = gold_ledger_entries.location_id', 'left')
                ->where('gold_ledger_entries.order_id', $id)
                ->orderBy('gold_ledger_entries.id', 'DESC')
                ->findAll();
        }

        if (
            $db->tableExists('gold_inventory_issue_headers')
            && $db->tableExists('gold_inventory_issue_lines')
            && $db->tableExists('gold_inventory_return_headers')
            && $db->tableExists('gold_inventory_return_lines')
        ) {
            $issueRows = $db->table('gold_inventory_issue_headers ih')
                ->select("
                    'issue' as movement_type,
                    k.name as karigar_name,
                    iloc.name as location_name,
                    gi.purity_code,
                    gi.color_name,
                    0 as gross_weight_gm,
                    0 as other_weight_gm,
                    0 as diamond_weight_gm,
                    COALESCE(SUM(il.weight_gm), 0) as gold_gm,
                    0 as diamond_cts,
                    COALESCE(SUM(il.fine_weight_gm), 0) as pure_gold_weight_gm,
                    ih.notes,
                    COALESCE(ih.created_at, CONCAT(ih.issue_date, ' 00:00:00')) as created_at
                ", false)
                ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->join('gold_inventory_items gi', 'gi.id = il.item_id', 'left')
                ->join('karigars k', 'k.id = ih.karigar_id', 'left')
                ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
                ->where('ih.order_id', $id)
                ->groupBy('ih.id')
                ->get()
                ->getResultArray();

            $returnRows = $db->table('gold_inventory_return_headers rh')
                ->select("
                    'receive' as movement_type,
                    k.name as karigar_name,
                    iloc.name as location_name,
                    gi.purity_code,
                    gi.color_name,
                    0 as gross_weight_gm,
                    0 as other_weight_gm,
                    0 as diamond_weight_gm,
                    COALESCE(SUM(rl.weight_gm), 0) as gold_gm,
                    0 as diamond_cts,
                    COALESCE(SUM(rl.fine_weight_gm), 0) as pure_gold_weight_gm,
                    rh.notes,
                    COALESCE(rh.created_at, CONCAT(rh.return_date, ' 00:00:00')) as created_at
                ", false)
                ->join('gold_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->join('gold_inventory_items gi', 'gi.id = rl.item_id', 'left')
                ->join('karigars k', 'k.id = rh.karigar_id', 'left')
                ->join('inventory_locations iloc', 'iloc.id = rh.location_id', 'left')
                ->where('rh.order_id', $id)
                ->groupBy('rh.id')
                ->get()
                ->getResultArray();

            $movements = array_merge($issueRows, $returnRows);
            usort($movements, static function (array $a, array $b): int {
                return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
            });
        }

        if ($movements === []) {
            $movements = $this->movementModel
                ->select('order_material_movements.*, karigars.name as karigar_name, gold_purities.purity_code, gold_purities.color_name, inventory_locations.name as location_name')
                ->join('karigars', 'karigars.id = order_material_movements.karigar_id', 'left')
                ->join('gold_purities', 'gold_purities.id = order_material_movements.gold_purity_id', 'left')
                ->join('inventory_locations', 'inventory_locations.id = order_material_movements.location_id', 'left')
                ->where('order_material_movements.order_id', $id)
                ->orderBy('order_material_movements.id', 'DESC')
                ->findAll();
        }

        $stoneLedgers = $this->stoneLedgerModel
            ->select('stone_ledger_entries.*, karigars.name as karigar_name, inventory_locations.name as location_name')
            ->join('karigars', 'karigars.id = stone_ledger_entries.karigar_id', 'left')
            ->join('inventory_locations', 'inventory_locations.id = stone_ledger_entries.location_id', 'left')
            ->where('stone_ledger_entries.order_id', $id)
            ->orderBy('stone_ledger_entries.id', 'DESC')
            ->findAll();

        if (
            $stoneLedgers === []
            && $db->tableExists('stone_inventory_issue_headers')
            && $db->tableExists('stone_inventory_issue_lines')
        ) {
            $issueLedgers = $db->table('stone_inventory_issue_headers ih')
                ->select("
                    'issue' as entry_type,
                    k.name as karigar_name,
                    iloc.name as location_name,
                    i.product_name as stone_type,
                    NULL as size,
                    NULL as stone_item_type,
                    NULL as color,
                    NULL as quality,
                    il.qty as pcs,
                    il.qty as weight_cts,
                    'stone_inventory_issue' as reference_type,
                    ih.id as reference_id,
                    ih.notes,
                    COALESCE(ih.created_at, CONCAT(ih.issue_date, ' 00:00:00')) as created_at
                ", false)
                ->join('stone_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->join('stone_inventory_items i', 'i.id = il.item_id', 'left')
                ->join('karigars k', 'k.id = ih.karigar_id', 'left')
                ->join('inventory_locations iloc', 'iloc.id = ih.location_id', 'left')
                ->where('ih.order_id', $id)
                ->get()
                ->getResultArray();

            $returnLedgers = [];
            if ($db->tableExists('stone_inventory_return_headers') && $db->tableExists('stone_inventory_return_lines')) {
                $returnLedgers = $db->table('stone_inventory_return_headers rh')
                    ->select("
                        'receive' as entry_type,
                        k.name as karigar_name,
                        iloc.name as location_name,
                        i.product_name as stone_type,
                        NULL as size,
                        NULL as stone_item_type,
                        NULL as color,
                        NULL as quality,
                        rl.qty as pcs,
                        rl.qty as weight_cts,
                        'stone_inventory_return' as reference_type,
                        rh.id as reference_id,
                        rh.notes,
                        COALESCE(rh.created_at, CONCAT(rh.return_date, ' 00:00:00')) as created_at
                    ", false)
                    ->join('stone_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                    ->join('stone_inventory_items i', 'i.id = rl.item_id', 'left')
                    ->join('karigars k', 'k.id = rh.karigar_id', 'left')
                    ->join('inventory_locations iloc', 'iloc.id = rh.location_id', 'left')
                    ->where('rh.order_id', $id)
                    ->get()
                    ->getResultArray();
            }

            $stoneLedgers = array_merge($issueLedgers, $returnLedgers);
            usort($stoneLedgers, static function (array $a, array $b): int {
                return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
            });
        }

        $issueTransactions = [];
        if (db_connect()->tableExists('issue_headers') && db_connect()->tableExists('issue_lines')) {
            $issueTransactions = db_connect()->table('issue_headers ih')
                ->select("ih.id as header_id, ih.issue_date as txn_date, ih.issue_to as party_name, ih.purpose, ih.notes, il.id as line_id, il.pcs, il.carat, il.rate_per_carat, il.line_value, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut, 'Issue' as txn_type", false)
                ->join('issue_lines il', 'il.issue_id = ih.id')
                ->join('items i', 'i.id = il.item_id', 'left')
                ->where('ih.order_id', $id)
                ->get()
                ->getResultArray();
        }

        $returnTransactions = [];
        if (db_connect()->tableExists('return_headers') && db_connect()->tableExists('return_lines')) {
            $returnTransactions = db_connect()->table('return_headers rh')
                ->select("rh.id as header_id, rh.return_date as txn_date, rh.return_from as party_name, rh.purpose, rh.notes, rl.id as line_id, rl.pcs, rl.carat, rl.rate_per_carat, rl.line_value, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut, 'Return' as txn_type", false)
                ->join('return_lines rl', 'rl.return_id = rh.id')
                ->join('items i', 'i.id = rl.item_id', 'left')
                ->where('rh.order_id', $id)
                ->get()
                ->getResultArray();
        }

        $diamondInventoryTransactions = array_merge($issueTransactions, $returnTransactions);
        usort($diamondInventoryTransactions, static function (array $a, array $b): int {
            $dateCompare = strcmp((string) ($b['txn_date'] ?? ''), (string) ($a['txn_date'] ?? ''));
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return (int) ($b['header_id'] ?? 0) <=> (int) ($a['header_id'] ?? 0);
        });

        $diamondInventorySummary = [
            'issue_pcs' => 0.0,
            'issue_carat' => 0.0,
            'return_pcs' => 0.0,
            'return_carat' => 0.0,
            'balance_pcs' => 0.0,
            'balance_carat' => 0.0,
        ];
        foreach ($diamondInventoryTransactions as $txn) {
            $pcs = (float) ($txn['pcs'] ?? 0);
            $carat = (float) ($txn['carat'] ?? 0);
            if ((string) ($txn['txn_type'] ?? '') === 'Issue') {
                $diamondInventorySummary['issue_pcs'] += $pcs;
                $diamondInventorySummary['issue_carat'] += $carat;
            } else {
                $diamondInventorySummary['return_pcs'] += $pcs;
                $diamondInventorySummary['return_carat'] += $carat;
            }
        }
        $diamondInventorySummary['balance_pcs'] = $diamondInventorySummary['issue_pcs'] - $diamondInventorySummary['return_pcs'];
        $diamondInventorySummary['balance_carat'] = $diamondInventorySummary['issue_carat'] - $diamondInventorySummary['return_carat'];
        $receivePrefill = $this->buildReceivePrefillData($id);
        $followups = $this->followupModel
            ->select('order_followups.*, admin_users.name as followup_taken_by_name')
            ->join('admin_users', 'admin_users.id = order_followups.followup_taken_by', 'left')
            ->where('order_followups.order_id', $id)
            ->orderBy('order_followups.id', 'DESC')
            ->findAll();

        $latestPacking = db_connect()->table('packing_lists')
            ->select('id, packing_no, packing_date, status')
            ->where('order_id', $id)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        return view('admin/orders/show', [
            'title'      => 'Order Details',
            'order'      => $order,
            'items'      => $items,
            'summary'    => $summary,
            'budgetMonitor' => $this->getBudgetMonitor($id, $summary),
            'movements'  => $movements,
            'goldLedgers'=> $goldLedgers,
            'stoneLedgers' => $stoneLedgers,
            'diamondInventoryTransactions' => $diamondInventoryTransactions,
            'diamondInventorySummary' => $diamondInventorySummary,
            'attachments'=> $this->attachmentModel->where('order_id', $id)->orderBy('id', 'DESC')->findAll(),
            'statuses'   => $this->jewelleryConfig->orderStatuses,
            'locations' => $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'receivePurityPercent' => (float) ($receivePurityInfo['avg_purity_percent'] ?? 100),
            'receiveLabourRate' => (float) ($order['karigar_rate_per_gm'] ?? 0),
            'receivePrefill' => $receivePrefill,
            'followups' => $followups,
            'latestPacking' => is_array($latestPacking) ? $latestPacking : null,
        ]);
    }

    public function edit(int $id): string
    {
        $order = $this->orderModel->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }

        return view('admin/orders/edit', [
            'title'      => 'Edit Order',
            'order'      => $order,
            'customers'  => $this->customerModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'leads'      => $this->leadModel->where('status', 'Open')->orWhere('id', (int) ($order['lead_id'] ?? 0))->orderBy('id', 'DESC')->findAll(),
            'priorities' => $this->jewelleryConfig->orderPriorities,
        ]);
    }

    public function update(int $id)
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }

        if ((string) $order['status'] === 'Cancelled') {
            return redirect()->back()->with('error', 'Cancelled order cannot be edited.');
        }
        if ((string) $order['status'] === 'Completed') {
            return redirect()->back()->with('error', 'Completed order cannot be edited.');
        }

        $orderType = trim((string) $this->request->getPost('order_type'));
        $isRepairOrder = $this->isRepairType($orderType);

        $rules = [
            'order_type'  => 'required|max_length[30]',
            'customer_id' => 'permit_empty|integer',
            'lead_id'     => 'permit_empty|integer',
            'priority'    => 'required',
            'due_date'    => 'permit_empty|valid_date',
            'order_notes' => 'permit_empty',
        ];
        if ($isRepairOrder) {
            $rules = $rules + [
                'repair_ornament_details' => 'required',
                'repair_work_details' => 'required',
                'repair_receive_weight_gm' => 'required|decimal|greater_than[0]',
                'repair_received_at' => 'required|valid_date',
            ];
        }

        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $priority = (string) $this->request->getPost('priority');
        if (! in_array($priority, $this->jewelleryConfig->orderPriorities, true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid order priority.');
        }

        $this->orderModel->update($id, [
            'order_type'  => $isRepairOrder ? 'Repair' : $orderType,
            'customer_id' => $this->nullableInt($this->request->getPost('customer_id')),
            'lead_id'     => $this->nullableInt($this->request->getPost('lead_id')),
            'priority'    => $priority,
            'due_date'    => $this->nullableDate((string) $this->request->getPost('due_date')),
            'order_notes' => trim((string) $this->request->getPost('order_notes')),
            'repair_ornament_details' => $isRepairOrder ? trim((string) $this->request->getPost('repair_ornament_details')) : null,
            'repair_work_details' => $isRepairOrder ? trim((string) $this->request->getPost('repair_work_details')) : null,
            'repair_receive_weight_gm' => $isRepairOrder ? (float) $this->request->getPost('repair_receive_weight_gm') : null,
            'repair_received_at' => $isRepairOrder ? $this->nullableDate((string) $this->request->getPost('repair_received_at')) : null,
        ]);

        $redirectList = $isRepairOrder ? 'admin/orders/repair' : 'admin/orders';
        return redirect()->to(site_url($redirectList))->with('success', 'Order updated successfully.');
    }

    public function assignKarigar(int $id)
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }

        $karigarId = (int) ($this->request->getPost('karigar_id') ?? 0);
        if ($karigarId <= 0) {
            return redirect()->back()->with('error', 'Please select a karigar.');
        }

        $karigar = $this->karigarModel->where('is_active', 1)->find($karigarId);
        if (! $karigar) {
            return redirect()->back()->with('error', 'Selected karigar not found.');
        }

        if (! empty($order['assigned_karigar_id'])) {
            return redirect()->back()->with('error', 'Order already assigned.');
        }
        if ((string) ($order['status'] ?? '') === 'Completed') {
            return redirect()->back()->with('error', 'Completed order cannot be assigned.');
        }

        $this->orderModel->update($id, [
            'assigned_karigar_id' => $karigarId,
            'assigned_at'         => date('Y-m-d H:i:s'),
        ]);

        return redirect()->back()->with('success', 'Karigar assigned successfully.');
    }

    public function karigarSummary(int $id)
    {
        $karigar = $this->karigarModel->where('is_active', 1)->find($id);
        if (! $karigar) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Karigar not found.',
            ]);
        }

        $pendingStatuses = ['Confirmed', 'In Production', 'QC', 'Ready', 'Packed'];

        $pendingOrders = $this->orderModel
            ->select('id, order_no, status, due_date')
            ->where('assigned_karigar_id', $id)
            ->whereIn('status', $pendingStatuses)
            ->findAll();

        $orderIds = array_map(static fn(array $row): int => (int) $row['id'], $pendingOrders);
        $pendingOrderCount = count($orderIds);

        $db = db_connect();
        $pendingOrderGoldWeight = 0.0;
        $orderGoldMap = [];
        $orderIssueMap = [];
        $orderReceiveMap = [];
        $pendingOrderDetails = [];

        if ($orderIds !== []) {
            $goldRows = $this->orderItemModel
                ->select('order_id, COALESCE(SUM(gold_required_gm),0) as total_gold', false)
                ->whereIn('order_id', $orderIds)
                ->groupBy('order_id')
                ->findAll();
            foreach ($goldRows as $row) {
                $orderGoldMap[(int) $row['order_id']] = (float) ($row['total_gold'] ?? 0);
            }

            if ($db->tableExists('gold_inventory_issue_headers') && $db->tableExists('gold_inventory_issue_lines')) {
                $issuedRows = $db->table('gold_inventory_issue_headers ih')
                    ->select('ih.order_id, COALESCE(SUM(il.weight_gm),0) as total_issue', false)
                    ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                    ->where('ih.karigar_id', $id)
                    ->whereIn('ih.order_id', $orderIds)
                    ->groupBy('ih.order_id')
                    ->get()
                    ->getResultArray();
            } else {
                $issuedRows = $this->movementModel
                    ->select('order_id, COALESCE(SUM(gold_gm),0) as total_issue', false)
                    ->whereIn('order_id', $orderIds)
                    ->where('movement_type', 'issue')
                    ->groupBy('order_id')
                    ->findAll();
            }
            foreach ($issuedRows as $row) {
                $orderIssueMap[(int) $row['order_id']] = (float) ($row['total_issue'] ?? 0);
            }

            if ($db->tableExists('gold_inventory_return_headers') && $db->tableExists('gold_inventory_return_lines')) {
                $receivedRows = $db->table('gold_inventory_return_headers rh')
                    ->select('rh.order_id, COALESCE(SUM(rl.weight_gm),0) as total_receive', false)
                    ->join('gold_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                    ->where('rh.karigar_id', $id)
                    ->whereIn('rh.order_id', $orderIds)
                    ->groupBy('rh.order_id')
                    ->get()
                    ->getResultArray();
            } else {
                $receivedRows = $this->movementModel
                    ->select('order_id, COALESCE(SUM(gold_gm),0) as total_receive', false)
                    ->whereIn('order_id', $orderIds)
                    ->where('movement_type', 'receive')
                    ->groupBy('order_id')
                    ->findAll();
            }
            foreach ($receivedRows as $row) {
                $orderReceiveMap[(int) $row['order_id']] = (float) ($row['total_receive'] ?? 0);
            }

            foreach ($pendingOrders as $order) {
                $orderId = (int) $order['id'];
                $requiredGold = (float) ($orderGoldMap[$orderId] ?? 0);
                $issuedGold = (float) ($orderIssueMap[$orderId] ?? 0);
                $receivedGold = (float) ($orderReceiveMap[$orderId] ?? 0);
                $pendingOrderGoldWeight += $requiredGold;

                $pendingOrderDetails[] = [
                    'order_id' => $orderId,
                    'order_no' => (string) ($order['order_no'] ?? ''),
                    'status' => (string) ($order['status'] ?? ''),
                    'due_date' => (string) ($order['due_date'] ?? ''),
                    'required_gold_gm' => round($requiredGold, 3),
                    'issued_gold_gm' => round($issuedGold, 3),
                    'received_gold_gm' => round($receivedGold, 3),
                    'balance_gold_gm' => round(max(0, $issuedGold - $receivedGold), 3),
                ];
            }
        }

        $totalGoldWithHim = 0.0;
        $hasNewGoldMovements = false;
        if ($db->tableExists('gold_inventory_issue_headers') && $db->tableExists('gold_inventory_issue_lines')) {
            $issueRow = $db->table('gold_inventory_issue_headers ih')
                ->select('COALESCE(SUM(il.weight_gm),0) as total_issue', false)
                ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->where('ih.karigar_id', $id)
                ->get()
                ->getRowArray();

            $returnTotal = 0.0;
            if ($db->tableExists('gold_inventory_return_headers') && $db->tableExists('gold_inventory_return_lines')) {
                $returnRow = $db->table('gold_inventory_return_headers rh')
                    ->select('COALESCE(SUM(rl.weight_gm),0) as total_return', false)
                    ->join('gold_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                    ->where('rh.karigar_id', $id)
                    ->get()
                    ->getRowArray();
                $returnTotal = (float) ($returnRow['total_return'] ?? 0);
            }

            $issueTotal = (float) ($issueRow['total_issue'] ?? 0);
            $hasNewGoldMovements = $issueTotal > 0 || $returnTotal > 0;
            if ($hasNewGoldMovements) {
                $totalGoldWithHim = $issueTotal - $returnTotal;
            }
        }

        if (! $hasNewGoldMovements) {
            if ($db->tableExists('accounts') && $db->tableExists('account_balances')) {
                $balanceRow = $db->table('account_balances ab')
                    ->select('COALESCE(SUM(ab.qty_weight),0) as total_weight', false)
                    ->join('accounts a', 'a.id = ab.account_id', 'inner')
                    ->where('a.account_type', 'karigar')
                    ->where('a.reference_id', $id)
                    ->where('ab.item_type', 'GOLD')
                    ->get()
                    ->getRowArray();
                $totalGoldWithHim = (float) ($balanceRow['total_weight'] ?? 0);
            } elseif ($db->tableExists('gold_ledger_entries')) {
                $issueRow = $db->table('gold_ledger_entries')
                    ->select('COALESCE(SUM(weight_gm),0) as total_issue', false)
                    ->where('karigar_id', $id)
                    ->where('entry_type', 'issue')
                    ->get()
                    ->getRowArray();
                $receiveRow = $db->table('gold_ledger_entries')
                    ->select('COALESCE(SUM(weight_gm),0) as total_receive', false)
                    ->where('karigar_id', $id)
                    ->where('entry_type', 'receive')
                    ->get()
                    ->getRowArray();
                $totalGoldWithHim = (float) ($issueRow['total_issue'] ?? 0) - (float) ($receiveRow['total_receive'] ?? 0);
            } elseif ($db->tableExists('order_material_movements')) {
                $issueRow = $db->table('order_material_movements')
                    ->select('COALESCE(SUM(gold_gm),0) as total_issue', false)
                    ->where('karigar_id', $id)
                    ->where('movement_type', 'issue')
                    ->get()
                    ->getRowArray();
                $receiveRow = $db->table('order_material_movements')
                    ->select('COALESCE(SUM(gold_gm),0) as total_receive', false)
                    ->where('karigar_id', $id)
                    ->where('movement_type', 'receive')
                    ->get()
                    ->getRowArray();
                $totalGoldWithHim = (float) ($issueRow['total_issue'] ?? 0) - (float) ($receiveRow['total_receive'] ?? 0);
            }
        }

        $totalGoldWithHim = max(0, $totalGoldWithHim);

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => [
                'karigar_name'              => $karigar['name'],
                'total_gold_with_him'       => round($totalGoldWithHim, 3),
                'pending_order_count'       => $pendingOrderCount,
                'pending_order_gold_weight' => round($pendingOrderGoldWeight, 3),
                'pending_orders'            => $pendingOrderDetails,
            ],
        ]);
    }

    public function receivePrefill(int $orderId)
    {
        $order = $this->orderModel
            ->select('orders.id, orders.assigned_karigar_id, karigars.rate_per_gm as karigar_rate_per_gm')
            ->join('karigars', 'karigars.id = orders.assigned_karigar_id', 'left')
            ->find($orderId);

        if (! $order) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Order not found.',
            ]);
        }

        $purityInfo = $this->getOrderPurityInfo($orderId);
        $prefill = $this->buildReceivePrefillData($orderId);

        return $this->response->setJSON([
            'status' => 'ok',
            'data' => [
                'purity_percent' => (float) ($purityInfo['avg_purity_percent'] ?? 100),
                'labour_rate' => (float) ($order['karigar_rate_per_gm'] ?? 0),
                'diamond_rows' => $prefill['diamond_rows'],
                'stone_rows' => $prefill['stone_rows'],
            ],
        ]);
    }

    public function diamondBagItems(int $orderId)
    {
        return $this->response->setStatusCode(410)->setJSON([
            'status' => 'error',
            'message' => 'Old bagging-based diamond issue is disabled. Use Diamond Inventory Issuement/Return module.',
        ]);

        $order = $this->orderModel->find($orderId);
        if (! $order) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Order not found.',
            ]);
        }

        if (empty($order['assigned_karigar_id'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'status' => 'error',
                'message' => 'Assign karigar first, then issue material.',
            ]);
        }

        $bags = $this->diamondBagItemModel
            ->select('diamond_bags.id, diamond_bags.bag_no, SUM(diamond_bag_items.pcs_available) as pcs_available, SUM(diamond_bag_items.weight_cts_available) as weight_cts_available')
            ->join('diamond_bags', 'diamond_bags.id = diamond_bag_items.bag_id', 'inner')
            ->where('diamond_bags.order_id', $orderId)
            ->where('diamond_bag_items.pcs_available >', 0)
            ->where('diamond_bag_items.weight_cts_available >', 0)
            ->groupBy('diamond_bags.id, diamond_bags.bag_no')
            ->orderBy('diamond_bags.id', 'DESC')
            ->findAll();

        return $this->response->setJSON([
            'status' => 'ok',
            'data'   => $bags,
        ]);
    }

    public function issueDiamondFromBag(int $orderId)
    {
        return redirect()->to(site_url('admin/diamond-inventory/issues/create?order_id=' . $orderId))
            ->with('warning', 'Old bagging diamond issue is disabled. Use Diamond Inventory Issuement/Return module.');

        $order = $this->orderModel->find($orderId);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }
        $adminId = $this->currentAuditUserId();
        if ($adminId <= 0) {
            return redirect()->back()->with('error', 'Audit user is required. Please login again.');
        }

        if ((string) $order['status'] === 'Cancelled') {
            return redirect()->back()->with('error', 'Cancelled order cannot accept issue.');
        }

        if (empty($order['assigned_karigar_id'])) {
            return redirect()->back()->with('error', 'Assign karigar first, then issue material.');
        }

        $rules = [
            'bag_id' => 'required|integer',
            'location_id' => 'required|integer',
            'notes' => 'permit_empty',
            'audit_image' => 'uploaded[audit_image]|is_image[audit_image]|max_size[audit_image,4096]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', $this->firstValidationError());
        }

        $bagId = (int) $this->request->getPost('bag_id');
        $locationId = (int) ($this->request->getPost('location_id') ?? 0);
        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return redirect()->back()->with('error', 'Select valid inventory location.');
        }
        $bag = $this->diamondBagModel->find($bagId);
        if (! $bag || (int) $bag['order_id'] !== $orderId) {
            return redirect()->back()->with('error', 'Selected bag is not linked with this order.');
        }

        $bagItems = $this->diamondBagItemModel
            ->where('bag_id', $bagId)
            ->where('pcs_available >', 0)
            ->where('weight_cts_available >', 0)
            ->findAll();

        if ($bagItems === []) {
            return redirect()->back()->with('error', 'No available diamonds in selected bag.');
        }

        $db = \Config\Database::connect();
        try {
            $db->transException(true)->transStart();
            $this->storeAuditImageAttachment($orderId, 'audit_image', 'diamond_issue_audit', $adminId);

            $issuePcs = 0;
            $issueCts = 0.0;
            $notes = trim((string) $this->request->getPost('notes'));
            $karigarId = (int) $order['assigned_karigar_id'];

            foreach ($bagItems as $bagItem) {
                $rowIssuePcs = (int) $bagItem['pcs_available'];
                $rowIssueCts = (float) $bagItem['weight_cts_available'];

                if ($rowIssuePcs <= 0 || $rowIssueCts <= 0) {
                    continue;
                }

                $this->diamondBagItemModel->update((int) $bagItem['id'], [
                    'pcs_available' => 0,
                    'weight_cts_available' => 0,
                ]);

                $diamondIssueId = $this->diamondIssueModel->insert([
                    'order_id'         => $orderId,
                    'bag_id'           => (int) $bag['id'],
                    'bag_item_id'      => (int) $bagItem['id'],
                    'issue_pcs'        => $rowIssuePcs,
                    'issue_weight_cts' => $rowIssueCts,
                    'notes'            => $notes,
                    'created_by'       => $adminId,
                ], true);

                $this->diamondLedgerModel->insert([
                    'order_id'       => $orderId,
                    'bag_id'         => (int) $bag['id'],
                    'bag_item_id'    => (int) $bagItem['id'],
                    'karigar_id'     => $karigarId,
                    'location_id'    => $locationId,
                    'entry_type'     => 'issue',
                    'pcs'            => $rowIssuePcs,
                    'weight_cts'     => $rowIssueCts,
                    'reference_type' => 'diamond_issue',
                    'reference_id'   => (int) $diamondIssueId,
                    'notes'          => $notes,
                    'created_by'     => $adminId,
                ]);

                $this->inventoryTxnModel->insert([
                    'txn_date' => date('Y-m-d'),
                    'transaction_type' => 'issue',
                    'location_id' => $locationId,
                    'counter_location_id' => null,
                    'item_type' => 'Diamond',
                    'material_name' => (string) $bagItem['diamond_type'],
                    'gold_purity_id' => null,
                    'diamond_shape' => (string) $bagItem['diamond_type'],
                    'diamond_sieve' => (string) $bagItem['size'],
                    'diamond_color' => (string) $bagItem['color'],
                    'diamond_clarity' => (string) $bagItem['quality'],
                    'pcs' => $rowIssuePcs,
                    'weight_gm' => 0,
                    'cts' => $rowIssueCts,
                    'reference_type' => 'diamond_issue',
                    'reference_id' => (int) $diamondIssueId,
                    'document_type' => 'Issue to Karigar',
                    'document_no' => (string) $bag['bag_no'],
                    'packet_no' => (string) $bag['bag_no'],
                    'notes' => 'Order ' . $order['order_no'] . ' diamond issue from bag ' . $bag['bag_no'],
                    'created_by' => $adminId,
                ]);

                $issuePcs += $rowIssuePcs;
                $issueCts += $rowIssueCts;
            }

            if ($issuePcs <= 0 || $issueCts <= 0) {
                throw new Exception('No available diamonds in selected bag.');
            }

            $this->movementModel->insert([
                'order_id'       => $orderId,
                'movement_type'  => 'issue',
                'gold_gm'        => 0,
                'diamond_cts'    => $issueCts,
                'gold_purity_id' => null,
                'karigar_id'     => $karigarId,
                'location_id'    => $locationId,
                'notes'          => 'Diamond full bag issue from ' . $bag['bag_no'],
                'created_by'     => $adminId,
            ]);

            $this->adminPostingService->postKarigarMaterialVoucher(
                'issue',
                'DIAMOND_BAG_ISSUE',
                $locationId,
                $karigarId,
                [
                    'item_type' => 'DIAMOND_BAG',
                    'item_key' => 'BAG-' . (int) $bag['id'],
                    'material_name' => 'Bag ' . (string) $bag['bag_no'],
                    'bag_id' => (int) $bag['id'],
                    'shape' => $bag['shape'] ?? null,
                    'chalni_size' => $bag['chalni_size'] ?? null,
                    'color' => $bag['color'] ?? null,
                    'clarity' => $bag['clarity'] ?? null,
                    'qty_pcs' => $issuePcs,
                    'qty_cts' => $issueCts,
                    'qty_weight' => 0,
                    'remarks' => $notes,
                ],
                [
                    'order_id' => $orderId,
                    'remarks' => 'Order ' . $order['order_no'] . ' diamond issue from bag ' . $bag['bag_no'],
                    'created_by' => $adminId,
                ]
            );

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $summary = $this->getOrderBudgetSummary($orderId);
        $monitor = $this->getBudgetMonitor($orderId, $summary);
        $warning = null;
        if ($monitor['over_issue_diamond'] > 0) {
            $warning = sprintf('Diamond issue exceeded budget by %s cts.', number_format($monitor['over_issue_diamond'], 3));
        }

        $successMessage = sprintf(
            'Diamond bag issued: %s | Total PCS: %d | Total CTS: %s',
            (string) $bag['bag_no'],
            $issuePcs,
            number_format($issueCts, 3)
        );

        if ($warning !== null) {
            return redirect()->back()->with('success', $successMessage)->with('warning', $warning);
        }

        return redirect()->back()->with('success', $successMessage);
    }

    public function addIssue(int $id)
    {
        return $this->saveMaterialMovement($id, 'issue');
    }

    public function addReceive(int $id)
    {
        return $this->saveMaterialMovement($id, 'receive');
    }

    public function updateStatus(int $id)
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }
        $adminId = $this->currentAuditUserId();
        if ($adminId <= 0) {
            return redirect()->back()->with('error', 'Audit user is required. Please login again.');
        }

        if ((string) $order['status'] === 'Cancelled') {
            return redirect()->back()->with('error', 'Cancelled order status cannot be changed.');
        }
        if ((string) $order['status'] === 'Completed') {
            return redirect()->back()->with('error', 'Completed order status cannot be changed.');
        }

        $newStatus = (string) $this->request->getPost('status');
        $remarks   = trim((string) $this->request->getPost('remarks'));

        if (! in_array($newStatus, $this->jewelleryConfig->orderStatuses, true)) {
            return redirect()->back()->with('error', 'Invalid order status.');
        }

        if (! $this->isValidStatusTransition((string) $order['status'], $newStatus)) {
            return redirect()->back()->with('error', 'Invalid status transition.');
        }

        $imageRules = [
            'audit_image' => 'uploaded[audit_image]|is_image[audit_image]|max_size[audit_image,4096]',
        ];
        if (! $this->validate($imageRules)) {
            return redirect()->back()->with('error', $this->firstValidationError());
        }

        try {
            $this->storeAuditImageAttachment($id, 'audit_image', 'status_update_audit', $adminId);
        } catch (Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $db = db_connect();
        $db->table('orders')->where('id', $id)->update([
            'status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $db->table('order_items')->where('order_id', $id)->update([
            'item_status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->historyModel->insert([
            'order_id'    => $id,
            'from_status' => (string) $order['status'],
            'to_status'   => $newStatus,
            'remarks'     => $remarks === '' ? 'Status updated.' : $remarks,
            'changed_by'  => $adminId,
        ]);

        return redirect()->back()->with('success', 'Order status updated.');
    }

    public function cancel(int $id)
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }

        if ((string) $order['status'] === 'Cancelled') {
            return redirect()->back()->with('error', 'Order already cancelled.');
        }
        if ((string) $order['status'] === 'Completed') {
            return redirect()->back()->with('error', 'Completed order cannot be cancelled.');
        }

        $reason = trim((string) $this->request->getPost('cancel_reason'));
        if ($reason === '') {
            return redirect()->back()->with('error', 'Cancel reason is required.');
        }

        $db = db_connect();
        $db->table('orders')->where('id', $id)->update([
            'status'        => 'Cancelled',
            'cancel_reason' => $reason,
            'cancelled_at'  => date('Y-m-d H:i:s'),
            'cancelled_by'  => (int) session('admin_id'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $db->table('order_items')->where('order_id', $id)->update([
            'item_status' => 'Cancelled',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->historyModel->insert([
            'order_id'    => $id,
            'from_status' => (string) $order['status'],
            'to_status'   => 'Cancelled',
            'remarks'     => 'Cancelled: ' . $reason,
            'changed_by'  => (int) session('admin_id'),
        ]);

        return redirect()->back()->with('success', 'Order cancelled.');
    }

    public function addFollowup(int $id)
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }
        if ((string) ($order['status'] ?? '') === 'Cancelled') {
            return redirect()->back()->withInput()->with('error', 'Cancelled order cannot take followup.');
        }
        if ((string) ($order['status'] ?? '') === 'Completed') {
            return redirect()->back()->withInput()->with('error', 'Completed order cannot take followup.');
        }

        $rules = [
            'stage' => 'required|max_length[30]',
            'description' => 'required',
            'next_followup_date' => 'permit_empty|valid_date',
            'followup_image' => 'permit_empty|is_image[followup_image]|max_size[followup_image,4096]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $stage = trim((string) $this->request->getPost('stage'));
        if (! in_array($stage, $this->jewelleryConfig->orderStatuses, true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid followup stage.');
        }
        $description = trim((string) $this->request->getPost('description'));

        $imageName = null;
        $imagePath = null;
        $file = $this->request->getFile('followup_image');
        if ($file && $file->isValid() && ! $file->hasMoved()) {
            $uploadDir = FCPATH . 'uploads/orders/followups';
            if (! is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
            $storedName = $file->getRandomName();
            $file->move($uploadDir, $storedName);
            $imageName = $file->getClientName();
            $imagePath = 'uploads/orders/followups/' . $storedName;
        }

        $db = db_connect();
        try {
            $db->transException(true)->transStart();

            $this->followupModel->insert([
                'order_id' => $id,
                'stage' => $stage,
                'description' => $description,
                'next_followup_date' => $this->nullableDateTime((string) $this->request->getPost('next_followup_date')),
                'followup_taken_by' => (int) session('admin_id'),
                'followup_taken_on' => date('Y-m-d H:i:s'),
                'image_name' => $imageName,
                'image_path' => $imagePath,
            ]);

            $oldStatus = (string) ($order['status'] ?? '');
            if ($oldStatus !== $stage) {
                $db->table('orders')->where('id', $id)->update([
                    'status' => $stage,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $db->table('order_items')->where('order_id', $id)->update([
                    'item_status' => $stage,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $this->historyModel->insert([
                    'order_id' => $id,
                    'from_status' => $oldStatus,
                    'to_status' => $stage,
                    'remarks' => 'Updated from followup: ' . $description,
                    'changed_by' => (int) session('admin_id'),
                ]);
            }

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $returnTo = trim((string) $this->request->getPost('return_to'));
        if ($this->isSafeAdminReturnUrl($returnTo)) {
            return redirect()->to($returnTo)->with('success', 'Followup saved and order status synced.');
        }

        return redirect()->back()->with('success', 'Followup saved and order status synced.');
    }

    public function generatePackingList(int $id)
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }
        if ((string) ($order['status'] ?? '') !== 'Completed') {
            return redirect()->back()->with('error', 'Packing list can be generated only for completed orders.');
        }

        $adminId = (int) (session('admin_id') ?? 0);
        $packing = $this->ensurePackingListForOrder($id, $adminId);
        $packingId = (int) ($packing['id'] ?? 0);
        if ($packingId <= 0) {
            return redirect()->back()->with('error', 'Could not generate packing list.');
        }

        $print = (string) $this->request->getGet('print');
        $download = (string) $this->request->getGet('download');
        if ($print === '1') {
            $url = site_url('api/documents/packing-list/' . $packingId);
            if ($download === '1') {
                $url .= '?download=1';
            }
            return redirect()->to($url);
        }

        return redirect()->to(site_url('admin/orders/' . $id))
            ->with('success', 'Packing list ready: ' . (string) ($packing['packing_no'] ?? ('PK#' . $packingId)));
    }

    public function deliveryChallan(int $id)
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel
            ->select('orders.*, customers.name as customer_name')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }
        if ((string) ($order['status'] ?? '') !== 'Completed') {
            return redirect()->back()->with('error', 'Delivery challan can be generated only for completed orders.');
        }

        $packing = $this->ensurePackingListForOrder($id, (int) (session('admin_id') ?? 0));
        $packingId = (int) ($packing['id'] ?? 0);
        if ($packingId <= 0) {
            return redirect()->back()->with('error', 'Packing list is required before delivery challan.');
        }

        $setting = $this->companySetting();
        $db = db_connect();
        if (! $db->tableExists('delivery_challans')) {
            return redirect()->back()->with('error', 'Run migration for delivery challan table first.');
        }

        $detailRows = $this->packingDetailRows($id);
        $receive = $this->packingReceiveSummary($id);
        $pricing = $this->packingPricingSummary($id, $detailRows, $receive);
        $challan = $this->saveDeliveryChallanSnapshot($id, $packingId, $setting, $receive, $pricing);

        $pdf = $this->pdfService->render('pdf/delivery_challan', [
            'company' => $setting,
            'order' => $order,
            'packing' => $packing,
            'challan' => $challan,
            'receive' => $receive,
            'pricing' => $pricing,
            'challan_no' => (string) ($challan['challan_no'] ?? '-'),
        ]);

        $download = (string) $this->request->getGet('download');
        $disposition = $download === '0' ? 'inline' : 'attachment';

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', $disposition . '; filename="delivery_challan_' . $id . '.pdf"')
            ->setBody($pdf);
    }

    public function packingListHtml(int $id): string
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel
            ->select('orders.*, customers.name as customer_name')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }

        $packing = $this->ensurePackingListForOrder($id, (int) (session('admin_id') ?? 0));
        $packingId = (int) ($packing['id'] ?? 0);
        if ($packingId <= 0) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Packing list not found.');
        }

        $db = db_connect();
        $items = $db->table('packing_list_items')
            ->where('packing_list_id', $packingId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $photo = null;
        if ($db->tableExists('order_attachments')) {
            $photoRow = $db->table('order_attachments')
                ->select('file_path, file_type')
                ->where('order_id', $id)
                ->groupStart()
                    ->where('LOWER(file_type)', 'finish_photo')
                    ->orWhere('LOWER(file_type)', 'photo')
                ->groupEnd()
                ->orderBy("CASE WHEN LOWER(file_type) = 'finish_photo' THEN 0 ELSE 1 END", '', false)
                ->orderBy('id', 'DESC')
                ->get()
                ->getRowArray();
            $photo = (string) ($photoRow['file_path'] ?? '');
        }

        $detailRows = $this->packingDetailRows($id);
        $receive = $this->packingReceiveSummary($id);
        $pricing = $this->packingPricingSummary($id, $detailRows, $receive);

        return view('pdf/packing_list', [
            'packing' => $packing,
            'items' => $items,
            'order' => $order,
            'photo' => $photo,
            'detailRows' => $detailRows,
            'receive' => $receive,
            'pricing' => $pricing,
        ]);
    }

    public function ornamentDetails(int $id): string
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel
            ->select('orders.*, customers.name as customer_name')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }

        $packing = $this->ensurePackingListForOrder($id, (int) (session('admin_id') ?? 0));
        $packingId = (int) ($packing['id'] ?? 0);

        $db = db_connect();
        $items = $packingId > 0
            ? $db->table('packing_list_items')->where('packing_list_id', $packingId)->orderBy('id', 'ASC')->get()->getResultArray()
            : [];

        $orderPhoto = '';
        $photoRow = $db->table('order_attachments')
            ->select('file_path')
            ->where('order_id', $id)
            ->where('LOWER(file_type)', 'photo')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        if (is_array($photoRow) && ! empty($photoRow['file_path'])) {
            $orderPhoto = (string) $photoRow['file_path'];
        }

        $finishPhoto = '';
        $finishRow = $db->table('order_attachments')
            ->select('file_path')
            ->where('order_id', $id)
            ->where('LOWER(file_type)', 'finish_photo')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        if (is_array($finishRow) && ! empty($finishRow['file_path'])) {
            $finishPhoto = (string) $finishRow['file_path'];
        }

        $detailRows = $this->packingDetailRows($id);
        $receive = $this->packingReceiveSummary($id);
        $pricing = $this->packingPricingSummary($id, $detailRows, $receive);

        return view('admin/orders/ornament_details', [
            'title' => 'Ornament Details',
            'order' => $order,
            'packing' => $packing,
            'items' => $items,
            'detailRows' => $detailRows,
            'receive' => $receive,
            'pricing' => $pricing,
            'orderPhoto' => $orderPhoto,
            'finishPhoto' => $finishPhoto,
        ]);
    }

    public function uploadFinishPhoto(int $id)
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }

        $rules = [
            'finish_photo' => 'uploaded[finish_photo]|is_image[finish_photo]|max_size[finish_photo,6144]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $file = $this->request->getFile('finish_photo');
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            return redirect()->back()->with('error', 'Invalid finish photo file.');
        }

        $uploadDir = FCPATH . 'uploads/orders';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $newName = $file->getRandomName();
        $file->move($uploadDir, $newName);

        $this->attachmentModel->insert([
            'order_id' => $id,
            'file_type' => 'finish_photo',
            'file_name' => $file->getClientName(),
            'file_path' => 'uploads/orders/' . $newName,
            'uploaded_by' => (int) (session('admin_id') ?? 0),
        ]);

        return redirect()->to(site_url('admin/orders/' . $id . '/ornament-details'))->with('success', 'Finish photo updated.');
    }

    public function addAttachment(int $id)
    {
        $order = $this->orderModel->find($id);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }

        $this->storeAttachments($id);

        return redirect()->to(site_url('admin/orders/' . $id))->with('success', 'Attachment uploaded.');
    }

    private function storeAttachments(int $orderId): void
    {
        $files = $this->request->getFileMultiple('order_files');
        if (! is_array($files)) {
            return;
        }

        $uploadDir = FCPATH . 'uploads/orders';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $fileType = trim((string) $this->request->getPost('file_type'));
        if ($fileType === '') {
            $fileType = 'reference';
        }

        foreach ($files as $file) {
            if (! $file || ! $file->isValid() || $file->hasMoved()) {
                continue;
            }

            $newName = $file->getRandomName();
            $file->move($uploadDir, $newName);

            $this->attachmentModel->insert([
                'order_id'    => $orderId,
                'file_type'   => $fileType,
                'file_name'   => $file->getClientName(),
                'file_path'   => 'uploads/orders/' . $newName,
                'uploaded_by' => (int) session('admin_id'),
            ]);
        }
    }

    private function saveMaterialMovement(int $orderId, string $type)
    {
        $order = $this->orderModel->find($orderId);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }

        if ((string) $order['status'] === 'Cancelled') {
            return redirect()->back()->with('error', 'Cancelled order cannot accept issue/receive.');
        }
        if ((string) $order['status'] === 'Completed') {
            return redirect()->back()->with('error', 'Completed order cannot accept any transaction.');
        }

        $assignedKarigarId = (int) ($order['assigned_karigar_id'] ?? 0);
        if ($type === 'issue' && $assignedKarigarId <= 0) {
            return redirect()->back()->with('error', 'Assign karigar first, then issue material.');
        }

        $rules = [
            'gold_gm'     => 'permit_empty|decimal|greater_than_equal_to[0]',
            'diamond_cts' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'gold_purity_id' => 'permit_empty|integer',
            'location_id' => 'required|integer',
            'gross_weight_gm' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'other_weight_gm' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'gold_rate_per_gm' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'notes'       => 'permit_empty',
        ];

        if (! $this->validate($rules)) {
            return redirect()->back()->with('error', $this->firstValidationError());
        }

        $goldGm     = (float) ($this->request->getPost('gold_gm') ?? 0);
        $diamondCts = (float) ($this->request->getPost('diamond_cts') ?? 0);
        $goldPurityId = $this->nullableInt($this->request->getPost('gold_purity_id'));
        $locationId = (int) ($this->request->getPost('location_id') ?? 0);
        $grossWeightGm = null;
        $otherWeightGm = null;
        $diamondWeightGm = null;
        $netGoldWeightGm = null;
        $pureGoldWeightGm = null;
        $purityPercent = null;
        $labourRateInput = 0.0;
        $labourTotalInput = 0.0;
        $otherAmountTotal = 0.0;
        $goldRatePerGmInput = 0.0;
        $goldAmountTotal = 0.0;
        $diamondRowsData = [];
        $stoneRowsData = [];
        $otherRowsData = [];
        $stoneWeightGm = 0.0;

        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return redirect()->back()->with('error', 'Select valid inventory location.');
        }

        if ($type === 'issue' && $diamondCts > 0) {
            return redirect()->back()->with('error', 'Diamond issue must be from bagging. Use Diamond Issue action.');
        }

        if ($type === 'issue' && $goldGm > 0 && $goldPurityId === null) {
            return redirect()->back()->with('error', 'Gold purity is required for gold issue.');
        }

        if ($type === 'issue' && $goldPurityId !== null) {
            $purity = $this->goldPurityModel->where('is_active', 1)->find($goldPurityId);
            if (! $purity) {
                return redirect()->back()->with('error', 'Selected gold purity not found.');
            }
        }

        if ($type === 'receive') {
            $grossWeightGm = (float) ($this->request->getPost('gross_weight_gm') ?? 0);
            if ($grossWeightGm <= 0) {
                return redirect()->back()->with('error', 'Gross weight is required for receiving.');
            }

            $diamondRows = $this->collectReceiveComponentRows(
                (array) $this->request->getPost('studded_diamond_type'),
                (array) $this->request->getPost('studded_diamond_pcs'),
                (array) $this->request->getPost('studded_diamond_weight'),
                (array) $this->request->getPost('studded_diamond_rate')
            );
            $stoneRows = $this->collectReceiveComponentRows(
                (array) $this->request->getPost('stone_type'),
                (array) $this->request->getPost('stone_pcs'),
                (array) $this->request->getPost('stone_weight'),
                (array) $this->request->getPost('stone_rate')
            );
            $otherRows = $this->collectReceiveOtherRows(
                (array) $this->request->getPost('other_desc'),
                (array) $this->request->getPost('other_pcs'),
                (array) $this->request->getPost('other_weight_line_gm'),
                (array) $this->request->getPost('other_price')
            );
            $diamondRowsData = $diamondRows['rows'];
            $stoneRowsData = $stoneRows['rows'];
            $otherRowsData = $otherRows['rows'];
            $diamondRowsData = $this->applyInventoryRateMapToReceiveRows(
                $diamondRowsData,
                $this->receiveRateMapFromPrefillRows($this->pendingDiamondReceiveRows($orderId)),
                'weight_cts'
            );
            $stoneRowsData = $this->applyInventoryRateMapToReceiveRows(
                $stoneRowsData,
                $this->receiveRateMapFromPrefillRows($this->pendingStoneReceiveRows($orderId)),
                'weight_cts'
            );
            $diamondRows = $this->summarizeReceiveRows($diamondRowsData, 'weight_cts');
            $stoneRows = $this->summarizeReceiveRows($stoneRowsData, 'weight_cts');

            $diamondCts = $diamondRows['total_weight_cts'];
            $diamondWeightGm = round($diamondCts * 0.2, 3);
            $stoneWeightCts = $stoneRows['total_weight_cts'];
            $stoneWeightGm = round($stoneWeightCts * 0.2, 3);
            $otherOnlyWeightGm = $otherRows['total_weight_gm'];
            $otherWeightGm = round($stoneWeightGm + $otherOnlyWeightGm, 3);

            $totalDeductionGm = round($diamondWeightGm + $stoneWeightGm + $otherOnlyWeightGm, 3);
            $netGoldWeightGm = round($grossWeightGm - $totalDeductionGm, 3);
            if ($netGoldWeightGm < 0) {
                return redirect()->back()->with('error', 'Net weight cannot be negative. Please check gross, diamond, stone and other weights.');
            }

            $purityInfo = $this->getOrderPurityInfo($orderId);
            $goldPurityId = $purityInfo['primary_purity_id'];
            $purityPercentInput = (float) ($this->request->getPost('purity_percent') ?? 0);
            $purityPercent = $purityPercentInput > 0 ? $purityPercentInput : $purityInfo['avg_purity_percent'];
            $pureGoldWeightGm = round($netGoldWeightGm * ($purityPercent / 100), 3);
            $goldGm = $netGoldWeightGm;
            $goldRatePerGmInput = max(0, (float) ($this->request->getPost('gold_rate_per_gm') ?? 0));
            if ($goldRatePerGmInput <= 0) {
                return redirect()->back()->withInput()->with('error', 'Gold rate per gm is required in receive.');
            }
            $goldAmountTotal = round($goldGm * $goldRatePerGmInput, 2);
            $labourRateInput = max(0, (float) ($this->request->getPost('labour_rate_per_gm') ?? 0));
            $labourTotalInput = round($goldGm * $labourRateInput, 2);
            $otherAmountTotal = $otherRows['total_amount'];
            $diamondAmountTotal = $diamondRows['total_amount'];
            $stoneAmountTotal = $stoneRows['total_amount'];

            $calcText = sprintf(
                'RecvCalc: Gross %.3f gm - (Diamond %.3f gm [%.3f cts] + Stone %.3f gm [%.3f cts] + Other %.3f gm) = Net %.3f gm | Pure @%.3f%% = %.3f gm',
                $grossWeightGm,
                $diamondWeightGm,
                $diamondCts,
                $stoneWeightGm,
                $stoneWeightCts,
                $otherOnlyWeightGm,
                $netGoldWeightGm,
                $purityPercent,
                $pureGoldWeightGm
            );
            $sectionText = sprintf(
                'Sections: Diamond Amt %.2f | Stone Amt %.2f | Gold Rate %.2f | Gold Amt %.2f | Labour Rate %.2f | Labour Total %.2f | Other Bill %.2f',
                $diamondAmountTotal,
                $stoneAmountTotal,
                $goldRatePerGmInput,
                $goldAmountTotal,
                $labourRateInput,
                $labourTotalInput,
                $otherAmountTotal
            );
            $postedNotes = trim((string) $this->request->getPost('notes'));
            $notesForSave = $postedNotes === '' ? ($calcText . ' | ' . $sectionText) : ($postedNotes . ' | ' . $calcText . ' | ' . $sectionText);
        } else {
            if ($goldGm <= 0 && $diamondCts <= 0) {
                return redirect()->back()->with('error', 'Enter gold grams or diamond cts.');
            }
            $notesForSave = trim((string) $this->request->getPost('notes'));
        }

        $db = \Config\Database::connect();
        try {
            $db->transException(true)->transStart();

            $movementId = $this->movementModel->insert([
                'order_id'       => $orderId,
                'movement_type'  => $type,
                'gold_gm'        => $goldGm,
                'diamond_cts'    => $diamondCts,
                'gold_purity_id' => $goldGm > 0 ? $goldPurityId : null,
                'karigar_id'     => $assignedKarigarId > 0 ? $assignedKarigarId : null,
                'location_id'    => $locationId,
                'gross_weight_gm'=> $grossWeightGm,
                'other_weight_gm'=> $otherWeightGm,
                'diamond_weight_gm' => $diamondWeightGm,
                'net_gold_weight_gm' => $netGoldWeightGm,
                'pure_gold_weight_gm'=> $pureGoldWeightGm,
                'notes'          => $notesForSave,
                'created_by'     => (int) session('admin_id'),
            ], true);

            if ($type === 'receive') {
                $diamondAmountTotal = (float) ($diamondAmountTotal ?? 0);
                $stoneAmountTotal = (float) ($stoneAmountTotal ?? 0);
                $otherAmountTotal = (float) ($otherAmountTotal ?? 0);
                $goldAmountTotal = (float) ($goldAmountTotal ?? 0);
                $labourTotalInput = (float) ($labourTotalInput ?? 0);
                $totalValuation = round(
                    $diamondAmountTotal + $stoneAmountTotal + $otherAmountTotal + $goldAmountTotal + $labourTotalInput,
                    2
                );

                $this->persistReceiveSnapshot(
                    (int) $movementId,
                    $orderId,
                    [
                        'gross_weight_gm' => (float) ($grossWeightGm ?? 0),
                        'net_gold_weight_gm' => (float) ($netGoldWeightGm ?? 0),
                        'pure_gold_weight_gm' => (float) ($pureGoldWeightGm ?? 0),
                        'diamond_weight_cts' => (float) ($diamondCts ?? 0),
                        'diamond_weight_gm' => (float) ($diamondWeightGm ?? 0),
                        'stone_weight_cts' => (float) ($stoneWeightCts ?? 0),
                        'stone_weight_gm' => (float) ($stoneWeightGm ?? 0),
                        'other_weight_gm' => (float) ($otherOnlyWeightGm ?? 0),
                        'diamond_amount' => $diamondAmountTotal,
                        'stone_amount' => $stoneAmountTotal,
                        'other_amount' => $otherAmountTotal,
                        'gold_amount' => $goldAmountTotal,
                        'labour_rate_per_gm' => (float) ($labourRateInput ?? 0),
                        'labour_amount' => $labourTotalInput,
                        'total_valuation' => $totalValuation,
                        'created_by' => (int) session('admin_id'),
                    ],
                    [
                        'diamond' => $diamondRowsData,
                        'stone' => $stoneRowsData,
                        'other' => $otherRowsData,
                    ]
                );
            }

            if ($goldGm > 0) {
                $this->goldLedgerModel->insert([
                    'order_id'       => $orderId,
                    'entry_type'     => $type,
                    'weight_gm'      => $goldGm,
                    'gold_purity_id' => $goldPurityId,
                    'karigar_id'     => $assignedKarigarId > 0 ? $assignedKarigarId : null,
                    'location_id'    => $locationId,
                    'gross_weight_gm'=> $grossWeightGm,
                    'other_weight_gm'=> $otherWeightGm,
                    'diamond_weight_gm' => $diamondWeightGm,
                    'net_gold_weight_gm' => $netGoldWeightGm,
                    'pure_gold_weight_gm'=> $pureGoldWeightGm,
                    'purity_percent' => $purityPercent,
                    'reference_type' => 'order_material_movement',
                    'reference_id'   => (int) $movementId,
                    'notes'          => $notesForSave,
                    'created_by'     => (int) session('admin_id'),
                ]);
            }

            if ($diamondCts > 0) {
                $this->diamondLedgerModel->insert([
                    'order_id'       => $orderId,
                    'bag_id'         => null,
                    'bag_item_id'    => null,
                    'karigar_id'     => $assignedKarigarId > 0 ? $assignedKarigarId : null,
                    'location_id'    => $locationId,
                    'entry_type'     => $type,
                    'pcs'            => 0,
                    'weight_cts'     => $diamondCts,
                    'reference_type' => 'order_material_movement',
                    'reference_id'   => (int) $movementId,
                    'notes'          => $notesForSave,
                    'created_by'     => (int) session('admin_id'),
                ]);
            }

            if ($goldGm > 0) {
                $this->inventoryTxnModel->insert([
                    'txn_date' => date('Y-m-d'),
                    'transaction_type' => $type === 'issue' ? 'issue' : 'receive',
                    'location_id' => $locationId,
                    'counter_location_id' => null,
                    'item_type' => 'Gold',
                    'material_name' => 'Gold Movement',
                    'gold_purity_id' => $goldPurityId,
                    'diamond_shape' => null,
                    'diamond_sieve' => null,
                    'diamond_color' => null,
                    'diamond_clarity' => null,
                    'pcs' => 0,
                    'weight_gm' => $goldGm,
                    'cts' => 0,
                    'reference_type' => 'order_material_movement',
                    'reference_id' => (int) $movementId,
                    'document_type' => $type === 'issue' ? 'Issue to Karigar' : 'Return from Karigar',
                    'document_no' => (string) $order['order_no'],
                    'notes' => 'Order ' . $order['order_no'] . ' ' . $type . ' gold',
                    'created_by' => (int) session('admin_id'),
                ]);
            }

            if ($diamondCts > 0 && $type === 'receive') {
                $this->inventoryTxnModel->insert([
                    'txn_date' => date('Y-m-d'),
                    'transaction_type' => 'receive',
                    'location_id' => $locationId,
                    'counter_location_id' => null,
                    'item_type' => 'Diamond',
                    'material_name' => 'Diamond Movement',
                    'gold_purity_id' => null,
                    'diamond_shape' => null,
                    'diamond_sieve' => null,
                    'diamond_color' => null,
                    'diamond_clarity' => null,
                    'pcs' => 0,
                    'weight_gm' => 0,
                    'cts' => $diamondCts,
                    'reference_type' => 'order_material_movement',
                    'reference_id' => (int) $movementId,
                    'document_type' => 'Return from Karigar',
                    'document_no' => (string) $order['order_no'],
                    'notes' => 'Order ' . $order['order_no'] . ' receive diamond',
                    'created_by' => (int) session('admin_id'),
                ]);
            }

            if ($goldGm > 0) {
                $goldInventoryService = new GoldInventoryStockService($db);
                $goldInventoryService->postExternalMovement([
                    'direction' => $type === 'issue' ? 'out' : 'in',
                    'weight_gm' => $goldGm,
                    'fine_weight_gm' => $pureGoldWeightGm,
                    'gold_purity_id' => $goldPurityId,
                    'form_type' => 'Ornament',
                    'reference_table' => 'order_material_movements',
                    'reference_id' => (int) $movementId,
                    'txn_type' => $type === 'issue' ? 'order_issue' : 'order_receive',
                    'txn_date' => date('Y-m-d'),
                    'order_id' => $orderId,
                    'karigar_id' => $assignedKarigarId > 0 ? $assignedKarigarId : null,
                    'location_id' => $locationId,
                    'notes' => $notesForSave,
                    'created_by' => (int) session('admin_id'),
                ]);
            }

            if ($goldGm > 0 && $assignedKarigarId > 0) {
                $goldLineMeta = $this->adminPostingService->buildGoldLineMeta($goldPurityId, $goldGm, $pureGoldWeightGm);
                $this->adminPostingService->postKarigarMaterialVoucher(
                    $type === 'issue' ? 'issue' : 'return',
                    $type === 'issue' ? 'GOLD_ISSUE' : 'GOLD_RETURN',
                    $locationId,
                    $assignedKarigarId,
                    [
                        'item_type' => 'GOLD',
                        'item_key' => $goldLineMeta['item_key'],
                        'material_name' => $goldLineMeta['material_name'],
                        'gold_purity_id' => $goldPurityId,
                        'qty_weight' => $goldGm,
                        'fine_gold' => $goldLineMeta['fine_gold'],
                        'remarks' => $notesForSave,
                    ],
                    [
                        'order_id' => $orderId,
                        'remarks' => 'Order ' . $order['order_no'] . ' ' . ($type === 'issue' ? 'gold issue' : 'gold receive'),
                        'created_by' => (int) session('admin_id'),
                    ]
                );
            }

            if ($type === 'receive' && $goldGm > 0 && $assignedKarigarId > 0) {
                $this->createLabourBillFromReceive(
                    (int) $orderId,
                    (array) $order,
                    (int) $movementId,
                    $assignedKarigarId,
                    $goldGm,
                    $notesForSave,
                    $labourRateInput,
                    $otherAmountTotal
                );
            }

            if ($type === 'receive' && (string) ($order['status'] ?? '') !== 'Completed') {
                $this->orderModel->update($orderId, ['status' => 'Completed']);
                $this->orderItemModel->where('order_id', $orderId)->set(['item_status' => 'Completed'])->update();
                $this->historyModel->insert([
                    'order_id' => $orderId,
                    'from_status' => (string) ($order['status'] ?? ''),
                    'to_status' => 'Completed',
                    'changed_by' => (int) session('admin_id'),
                    'remarks' => 'Auto-completed after receiving.',
                ]);
            }

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $summary = $this->getOrderBudgetSummary($orderId);
        $monitor = $this->getBudgetMonitor($orderId, $summary);

        $message = $type === 'issue' ? 'Material issue saved.' : 'Material receive saved. Order marked as Completed.';
        $warning = null;

        if ($type === 'receive' && ($monitor['over_receive_gold'] > 0 || $monitor['over_receive_diamond'] > 0)) {
            $warning = sprintf(
                'Over budget at receiving: Gold +%s gm, Diamond +%s cts.',
                number_format($monitor['over_receive_gold'], 3),
                number_format($monitor['over_receive_diamond'], 3)
            );
        } elseif ($type === 'issue' && ($monitor['over_issue_gold'] > 0 || $monitor['over_issue_diamond'] > 0)) {
            $warning = sprintf(
                'Issue exceeded budget: Gold +%s gm, Diamond +%s cts.',
                number_format($monitor['over_issue_gold'], 3),
                number_format($monitor['over_issue_diamond'], 3)
            );
        }

        if ($warning !== null) {
            return redirect()->back()
                ->with('success', $message)
                ->with('warning', $warning);
        }

        return redirect()->back()->with('success', $message);
    }

    private function getPendingDiamondForReceive(int $orderId): float
    {
        $rows = $this->movementModel
            ->select('movement_type, diamond_cts')
            ->where('order_id', $orderId)
            ->findAll();

        $issued = 0.0;
        $received = 0.0;
        foreach ($rows as $row) {
            if ((string) $row['movement_type'] === 'issue') {
                $issued += (float) $row['diamond_cts'];
            }
            if ((string) $row['movement_type'] === 'receive') {
                $received += (float) $row['diamond_cts'];
            }
        }

        return round(max(0, $issued - $received), 3);
    }

    /**
     * @return array{primary_purity_id: ?int, avg_purity_percent: float}
     */
    private function getOrderPurityInfo(int $orderId): array
    {
        $rows = $this->orderItemModel
            ->select('order_items.gold_purity_id, order_items.gold_required_gm, gold_purities.purity_percent')
            ->join('gold_purities', 'gold_purities.id = order_items.gold_purity_id', 'left')
            ->where('order_items.order_id', $orderId)
            ->findAll();

        $totalWeight = 0.0;
        $weightedPurity = 0.0;
        $purityWeights = [];

        foreach ($rows as $row) {
            $purityId = $row['gold_purity_id'] === null ? null : (int) $row['gold_purity_id'];
            $reqWeight = (float) ($row['gold_required_gm'] ?? 0);
            $weight = $reqWeight > 0 ? $reqWeight : 1.0;
            $purityPercent = (float) ($row['purity_percent'] ?? 0);
            if ($purityPercent <= 0) {
                continue;
            }

            $totalWeight += $weight;
            $weightedPurity += $weight * $purityPercent;
            if ($purityId !== null) {
                $purityWeights[$purityId] = ($purityWeights[$purityId] ?? 0) + $weight;
            }
        }

        $avgPercent = $totalWeight > 0 ? round($weightedPurity / $totalWeight, 3) : 100.0;
        $primaryPurityId = null;
        if ($purityWeights !== []) {
            arsort($purityWeights);
            $primaryPurityId = (int) array_key_first($purityWeights);
        }

        return [
            'primary_purity_id' => $primaryPurityId,
            'avg_purity_percent' => $avgPercent,
        ];
    }

    /**
     * @return array{gold_required_gm: float, diamond_required_cts: float}
     */
    private function getOrderBudgetSummary(int $orderId): array
    {
        $items = $this->orderItemModel->where('order_id', $orderId)->findAll();
        $summary = [
            'gold_required_gm'     => 0.0,
            'diamond_required_cts' => 0.0,
        ];

        foreach ($items as $item) {
            $summary['gold_required_gm'] += (float) $item['gold_required_gm'];
            $summary['diamond_required_cts'] += (float) $item['diamond_required_cts'];
        }

        return $summary;
    }

    /**
     * @param array{gold_required_gm: float, diamond_required_cts: float} $summary
     * @return array<string, float>
     */
    private function getBudgetMonitor(int $orderId, array $summary): array
    {
        $issueGold = 0.0;
        $issueDiamond = 0.0;
        $receiveGold = 0.0;
        $receiveDiamond = 0.0;

        $db = db_connect();

        if ($db->tableExists('gold_inventory_issue_headers') && $db->tableExists('gold_inventory_issue_lines')) {
            $issueGold = (float) ($db->table('gold_inventory_issue_headers ih')
                ->select('COALESCE(SUM(il.weight_gm),0) as total_gold', false)
                ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->where('ih.order_id', $orderId)
                ->get()
                ->getRowArray()['total_gold'] ?? 0);
        }
        if ($db->tableExists('gold_inventory_return_headers') && $db->tableExists('gold_inventory_return_lines')) {
            $receiveGold = (float) ($db->table('gold_inventory_return_headers rh')
                ->select('COALESCE(SUM(rl.weight_gm),0) as total_gold', false)
                ->join('gold_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->where('rh.order_id', $orderId)
                ->get()
                ->getRowArray()['total_gold'] ?? 0);
        }

        if ($db->tableExists('issue_headers') && $db->tableExists('issue_lines')) {
            $issueDiamond = (float) ($db->table('issue_headers ih')
                ->select('COALESCE(SUM(il.carat),0) as total_carat', false)
                ->join('issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->where('ih.order_id', $orderId)
                ->get()
                ->getRowArray()['total_carat'] ?? 0);
        }
        if ($db->tableExists('return_headers') && $db->tableExists('return_lines')) {
            $receiveDiamond = (float) ($db->table('return_headers rh')
                ->select('COALESCE(SUM(rl.carat),0) as total_carat', false)
                ->join('return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->where('rh.order_id', $orderId)
                ->get()
                ->getRowArray()['total_carat'] ?? 0);
        }

        if ($issueGold <= 0 && $receiveGold <= 0 && $issueDiamond <= 0 && $receiveDiamond <= 0) {
            $movements = $this->movementModel->where('order_id', $orderId)->findAll();
            foreach ($movements as $row) {
                if ($row['movement_type'] === 'issue') {
                    $issueGold += (float) $row['gold_gm'];
                    $issueDiamond += (float) $row['diamond_cts'];
                }
                if ($row['movement_type'] === 'receive') {
                    $receiveGold += (float) $row['gold_gm'];
                    $receiveDiamond += (float) $row['diamond_cts'];
                }
            }
        }

        $budgetGold = (float) $summary['gold_required_gm'];
        $budgetDiamond = (float) $summary['diamond_required_cts'];

        return [
            'budget_gold'            => $budgetGold,
            'budget_diamond'         => $budgetDiamond,
            'issue_gold'             => $issueGold,
            'issue_diamond'          => $issueDiamond,
            'receive_gold'           => $receiveGold,
            'receive_diamond'        => $receiveDiamond,
            'remaining_issue_gold'   => $budgetGold - $issueGold,
            'remaining_issue_diamond'=> $budgetDiamond - $issueDiamond,
            'remaining_receive_gold' => $budgetGold - $receiveGold,
            'remaining_receive_diamond' => $budgetDiamond - $receiveDiamond,
            'over_issue_gold'        => max(0, $issueGold - $budgetGold),
            'over_issue_diamond'     => max(0, $issueDiamond - $budgetDiamond),
            'over_receive_gold'      => max(0, $receiveGold - $budgetGold),
            'over_receive_diamond'   => max(0, $receiveDiamond - $budgetDiamond),
        ];
    }

    private function createLabourBillFromReceive(
        int $orderId,
        array $order,
        int $movementId,
        int $karigarId,
        float $goldWeightGm,
        string $notes,
        float $labourRateInput = 0.0,
        float $otherAmountInput = 0.0
    ): void {
        $db = db_connect();
        if (! $db->tableExists('labour_bills')) {
            return;
        }

        $existing = $db->table('labour_bills')->where('receive_movement_id', $movementId)->countAllResults();
        if ($existing > 0) {
            return;
        }

        $karigar = $this->karigarModel->find($karigarId);
        if (! $karigar) {
            return;
        }

        $karigarRate = round((float) ($karigar['rate_per_gm'] ?? 0), 2);
        $ratePerGm = $labourRateInput > 0 ? round($labourRateInput, 2) : $karigarRate;
        // Labour is always calculated on net received gold weight.
        $labourAmount = round(max(0, $goldWeightGm) * $ratePerGm, 2);
        $otherAmount = max(0, round($otherAmountInput, 2));
        $totalAmount = round($labourAmount + $otherAmount, 2);
        $billNo = $this->nextLabourBillNo();
        $dueDate = trim((string) ($order['due_date'] ?? ''));
        if ($dueDate === '') {
            $dueDate = date('Y-m-d');
        }

        $billId = (int) $this->labourBillModel->insert([
            'bill_no' => $billNo,
            'bill_date' => date('Y-m-d'),
            'order_id' => $orderId,
            'receive_movement_id' => $movementId,
            'karigar_id' => $karigarId,
            'gold_weight_gm' => round($goldWeightGm, 3),
            'rate_per_gm' => $ratePerGm,
            'labour_amount' => $labourAmount,
            'other_amount' => $otherAmount,
            'total_amount' => $totalAmount,
            'due_date' => $dueDate,
            'payment_status' => $totalAmount > 0 ? 'Pending' : 'Paid',
            'notes' => $notes,
            'created_by' => (int) session('admin_id'),
        ], true);

        if ($billId <= 0 || ! $db->tableExists('karigar_payment_ledgers') || $totalAmount <= 0) {
            return;
        }

        $db->table('karigar_payment_ledgers')->insert([
            'karigar_id' => $karigarId,
            'order_id' => $orderId,
            'entry_type' => 'charge',
            'amount' => $totalAmount,
            'reference_no' => $billNo,
            'notes' => 'Auto labour bill generated from receiving.',
            'created_by' => (int) session('admin_id'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function nextLabourBillNo(): string
    {
        $db = db_connect();
        $next = 1;
        if ($db->tableExists('labour_bills')) {
            $lastRow = $db->table('labour_bills')->select('id')->orderBy('id', 'DESC')->get(1)->getRowArray();
            $next = ((int) ($lastRow['id'] ?? 0)) + 1;
        }

        return 'LB' . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param list<array<string,mixed>> $orders
     * @return array<int,float>
     */
    private function buildOrderPurityPercentMap(array $orders): array
    {
        $orderIds = [];
        foreach ($orders as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $orderIds[] = $id;
            }
        }
        $orderIds = array_values(array_unique($orderIds));
        if ($orderIds === []) {
            return [];
        }

        $rows = db_connect()->table('order_items oi')
            ->select('oi.order_id, oi.gold_required_gm, gp.purity_percent')
            ->join('gold_purities gp', 'gp.id = oi.gold_purity_id', 'left')
            ->whereIn('oi.order_id', $orderIds)
            ->get()
            ->getResultArray();

        $acc = [];
        foreach ($rows as $row) {
            $oid = (int) ($row['order_id'] ?? 0);
            if ($oid <= 0) {
                continue;
            }
            $weight = (float) ($row['gold_required_gm'] ?? 0);
            if ($weight <= 0) {
                $weight = 1.0;
            }
            $purity = (float) ($row['purity_percent'] ?? 0);
            if ($purity <= 0) {
                continue;
            }

            if (! isset($acc[$oid])) {
                $acc[$oid] = ['sum_w' => 0.0, 'sum_wp' => 0.0];
            }
            $acc[$oid]['sum_w'] += $weight;
            $acc[$oid]['sum_wp'] += ($weight * $purity);
        }

        $map = [];
        foreach ($orderIds as $oid) {
            $sumW = (float) ($acc[$oid]['sum_w'] ?? 0);
            $sumWp = (float) ($acc[$oid]['sum_wp'] ?? 0);
            $map[$oid] = $sumW > 0 ? round($sumWp / $sumW, 3) : 100.0;
        }

        return $map;
    }

    /**
     * @param array<int,mixed> $types
     * @param array<int,mixed> $pcsList
     * @param array<int,mixed> $weightList
     * @param array<int,mixed> $rateList
     * @return array{rows:list<array<string,mixed>>,total_pcs:float,total_weight_cts:float,total_amount:float}
     */
    private function collectReceiveComponentRows(array $types, array $pcsList, array $weightList, array $rateList): array
    {
        $max = max(count($types), count($pcsList), count($weightList), count($rateList));
        $totalPcs = 0.0;
        $totalWeight = 0.0;
        $totalAmount = 0.0;
        $rows = [];

        for ($i = 0; $i < $max; $i++) {
            $type = trim((string) ($types[$i] ?? ''));
            $pcs = max(0.0, (float) ($pcsList[$i] ?? 0));
            $weight = max(0.0, (float) ($weightList[$i] ?? 0));
            $rate = max(0.0, (float) ($rateList[$i] ?? 0));

            if ($type === '' && $pcs <= 0 && $weight <= 0 && $rate <= 0) {
                continue;
            }

            $lineTotal = round($weight * $rate, 2);
            $totalPcs += $pcs;
            $totalWeight += $weight;
            $totalAmount += $lineTotal;
            $rows[] = [
                'name' => $type === '' ? '-' : $type,
                'pcs' => round($pcs, 3),
                'weight_cts' => round($weight, 3),
                'rate' => round($rate, 2),
                'line_total' => $lineTotal,
            ];
        }

        return [
            'rows' => $rows,
            'total_pcs' => round($totalPcs, 3),
            'total_weight_cts' => round($totalWeight, 3),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /**
     * @param array<int,mixed> $descs
     * @param array<int,mixed> $pcsList
     * @param array<int,mixed> $weightList
     * @param array<int,mixed> $priceList
     * @return array{rows:list<array<string,mixed>>,total_pcs:float,total_weight_gm:float,total_amount:float}
     */
    private function collectReceiveOtherRows(array $descs, array $pcsList, array $weightList, array $priceList): array
    {
        $max = max(count($descs), count($pcsList), count($weightList), count($priceList));
        $totalPcs = 0.0;
        $totalWeight = 0.0;
        $totalAmount = 0.0;
        $rows = [];

        for ($i = 0; $i < $max; $i++) {
            $desc = trim((string) ($descs[$i] ?? ''));
            $pcs = max(0.0, (float) ($pcsList[$i] ?? 0));
            $weight = max(0.0, (float) ($weightList[$i] ?? 0));
            $price = max(0.0, (float) ($priceList[$i] ?? 0));

            if ($desc === '' && $pcs <= 0 && $weight <= 0 && $price <= 0) {
                continue;
            }

            $totalPcs += $pcs;
            $totalWeight += $weight;
            $totalAmount += $price;
            $rows[] = [
                'name' => $desc === '' ? '-' : $desc,
                'pcs' => round($pcs, 3),
                'weight_gm' => round($weight, 3),
                'rate' => round($price, 2),
                'line_total' => round($price, 2),
            ];
        }

        return [
            'rows' => $rows,
            'total_pcs' => round($totalPcs, 3),
            'total_weight_gm' => round($totalWeight, 3),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,float> $rateMap
     * @return list<array<string,mixed>>
     */
    private function applyInventoryRateMapToReceiveRows(array $rows, array $rateMap, string $weightKey): array
    {
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $key = strtoupper(preg_replace('/\s+/', ' ', $name));
            $postedRate = max(0.0, (float) ($row['rate'] ?? 0));
            $rate = array_key_exists($key, $rateMap) ? max(0.0, (float) $rateMap[$key]) : $postedRate;
            $weight = max(0.0, (float) ($row[$weightKey] ?? 0));
            $row['rate'] = round($rate, 2);
            $row['line_total'] = round($weight * $rate, 2);
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @param list<array<string,mixed>> $prefillRows
     * @return array<string,float>
     */
    private function receiveRateMapFromPrefillRows(array $prefillRows): array
    {
        $map = [];
        foreach ($prefillRows as $row) {
            $name = trim((string) ($row['type'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = strtoupper(preg_replace('/\s+/', ' ', $name));
            $map[$key] = max(0.0, (float) ($row['rate'] ?? 0));
        }
        return $map;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{rows:list<array<string,mixed>>,total_pcs:float,total_weight_cts:float,total_amount:float}
     */
    private function summarizeReceiveRows(array $rows, string $weightKey): array
    {
        $totalPcs = 0.0;
        $totalWeight = 0.0;
        $totalAmount = 0.0;
        foreach ($rows as $row) {
            $totalPcs += max(0.0, (float) ($row['pcs'] ?? 0));
            $totalWeight += max(0.0, (float) ($row[$weightKey] ?? 0));
            $totalAmount += max(0.0, (float) ($row['line_total'] ?? 0));
        }
        return [
            'rows' => $rows,
            'total_pcs' => round($totalPcs, 3),
            'total_weight_cts' => round($totalWeight, 3),
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /**
     * @return array{diamond_rows:list<array<string,mixed>>,stone_rows:list<array<string,mixed>>}
     */
    private function buildReceivePrefillData(int $orderId): array
    {
        return [
            'diamond_rows' => $this->pendingDiamondReceiveRows($orderId),
            'stone_rows' => $this->pendingStoneReceiveRows($orderId),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function pendingDiamondReceiveRows(int $orderId): array
    {
        $db = db_connect();
        if (! $db->tableExists('issue_headers') || ! $db->tableExists('issue_lines')) {
            return [];
        }

        $issueRows = $db->table('issue_headers ih')
            ->select('il.item_id, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity, COALESCE(SUM(il.pcs),0) as total_pcs, COALESCE(SUM(il.carat),0) as total_cts, COALESCE(SUM(il.line_value),0) as total_value', false)
            ->join('issue_lines il', 'il.issue_id = ih.id', 'inner')
            ->join('items i', 'i.id = il.item_id', 'left')
            ->where('ih.order_id', $orderId)
            ->groupBy('il.item_id, i.diamond_type, i.shape, i.chalni_from, i.chalni_to, i.color, i.clarity')
            ->get()
            ->getResultArray();

        if ($issueRows === []) {
            return [];
        }

        $returnMap = [];
        if ($db->tableExists('return_headers') && $db->tableExists('return_lines')) {
            $returnRows = $db->table('return_headers rh')
                ->select('rl.item_id, COALESCE(SUM(rl.pcs),0) as total_pcs, COALESCE(SUM(rl.carat),0) as total_cts', false)
                ->join('return_lines rl', 'rl.return_id = rh.id', 'inner')
                ->where('rh.order_id', $orderId)
                ->groupBy('rl.item_id')
                ->get()
                ->getResultArray();

            foreach ($returnRows as $row) {
                $itemId = (int) ($row['item_id'] ?? 0);
                if ($itemId <= 0) {
                    continue;
                }
                $returnMap[$itemId] = [
                    'pcs' => (float) ($row['total_pcs'] ?? 0),
                    'cts' => (float) ($row['total_cts'] ?? 0),
                ];
            }
        }

        $rows = [];
        foreach ($issueRows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            $issuePcs = (float) ($row['total_pcs'] ?? 0);
            $issueCts = (float) ($row['total_cts'] ?? 0);
            $issueValue = (float) ($row['total_value'] ?? 0);
            $retPcs = (float) ($returnMap[$itemId]['pcs'] ?? 0);
            $retCts = (float) ($returnMap[$itemId]['cts'] ?? 0);

            $pendingPcs = round(max(0, $issuePcs - $retPcs), 3);
            $pendingCts = round(max(0, $issueCts - $retCts), 3);
            if ($pendingPcs <= 0 && $pendingCts <= 0) {
                continue;
            }

            $rate = 0.0;
            if ($issueCts > 0) {
                $rate = $issueValue / $issueCts;
            }

            $diamondType = trim((string) ($row['diamond_type'] ?? 'Diamond'));
            $shape = trim((string) ($row['shape'] ?? ''));
            $chalniFrom = trim((string) ($row['chalni_from'] ?? ''));
            $chalniTo = trim((string) ($row['chalni_to'] ?? ''));
            $color = trim((string) ($row['color'] ?? ''));
            $clarity = trim((string) ($row['clarity'] ?? ''));
            $parts = array_filter([
                $diamondType,
                $shape,
                ($chalniFrom !== '' || $chalniTo !== '') ? ('CH ' . $chalniFrom . '-' . $chalniTo) : '',
                $color,
                $clarity,
            ], static fn(string $v): bool => $v !== '');

            $rows[] = [
                'type' => implode(' | ', $parts),
                'pcs' => $pendingPcs,
                'weight_cts' => $pendingCts,
                'rate' => round($rate, 2),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function pendingStoneReceiveRows(int $orderId): array
    {
        $db = db_connect();
        $rows = [];

        if ($db->tableExists('stone_inventory_issue_headers') && $db->tableExists('stone_inventory_issue_lines')) {
            $issueRows = $db->table('stone_inventory_issue_headers ih')
                ->select('il.item_id, i.product_name, i.stone_type, COALESCE(SUM(il.qty),0) as total_qty, COALESCE(SUM(il.line_value),0) as total_value', false)
                ->join('stone_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->join('stone_inventory_items i', 'i.id = il.item_id', 'left')
                ->where('ih.order_id', $orderId)
                ->groupBy('il.item_id, i.product_name, i.stone_type')
                ->get()
                ->getResultArray();

            if ($issueRows !== []) {
                $returnMap = [];
                if ($db->tableExists('stone_inventory_return_headers') && $db->tableExists('stone_inventory_return_lines')) {
                    $returnRows = $db->table('stone_inventory_return_headers rh')
                        ->select('rl.item_id, COALESCE(SUM(rl.qty),0) as total_qty', false)
                        ->join('stone_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                        ->where('rh.order_id', $orderId)
                        ->groupBy('rl.item_id')
                        ->get()
                        ->getResultArray();

                    foreach ($returnRows as $row) {
                        $itemId = (int) ($row['item_id'] ?? 0);
                        if ($itemId > 0) {
                            $returnMap[$itemId] = (float) ($row['total_qty'] ?? 0);
                        }
                    }
                }

                foreach ($issueRows as $row) {
                    $itemId = (int) ($row['item_id'] ?? 0);
                    $issueQty = (float) ($row['total_qty'] ?? 0);
                    $returnQty = (float) ($returnMap[$itemId] ?? 0);
                    $pendingQty = round(max(0, $issueQty - $returnQty), 3);
                    if ($pendingQty <= 0) {
                        continue;
                    }

                    $issueValue = (float) ($row['total_value'] ?? 0);
                    $rate = $issueQty > 0 ? ($issueValue / $issueQty) : 0.0;

                    $product = trim((string) ($row['product_name'] ?? 'Stone'));
                    $type = trim((string) ($row['stone_type'] ?? ''));
                    $parts = array_filter([$product, $type], static fn(string $v): bool => $v !== '');

                    $rows[] = [
                        'type' => implode(' | ', $parts),
                        'pcs' => $pendingQty,
                        'weight_cts' => $pendingQty,
                        'rate' => round($rate, 2),
                    ];
                }
            }

            return $rows;
        }

        if ($db->tableExists('stone_ledger_entries')) {
            $issueRows = $db->table('stone_ledger_entries')
                ->select('stone_type, size, color, quality, COALESCE(SUM(pcs),0) as total_pcs, COALESCE(SUM(weight_cts),0) as total_cts', false)
                ->where('order_id', $orderId)
                ->where('entry_type', 'issue')
                ->groupBy('stone_type, size, color, quality')
                ->get()
                ->getResultArray();

            $receiveRows = $db->table('stone_ledger_entries')
                ->select('stone_type, size, color, quality, COALESCE(SUM(pcs),0) as total_pcs, COALESCE(SUM(weight_cts),0) as total_cts', false)
                ->where('order_id', $orderId)
                ->where('entry_type', 'receive')
                ->groupBy('stone_type, size, color, quality')
                ->get()
                ->getResultArray();

            $recvMap = [];
            foreach ($receiveRows as $row) {
                $key = strtoupper(trim((string) ($row['stone_type'] ?? '') . '|' . (string) ($row['size'] ?? '') . '|' . (string) ($row['color'] ?? '') . '|' . (string) ($row['quality'] ?? '')));
                $recvMap[$key] = [
                    'pcs' => (float) ($row['total_pcs'] ?? 0),
                    'cts' => (float) ($row['total_cts'] ?? 0),
                ];
            }

            foreach ($issueRows as $row) {
                $key = strtoupper(trim((string) ($row['stone_type'] ?? '') . '|' . (string) ($row['size'] ?? '') . '|' . (string) ($row['color'] ?? '') . '|' . (string) ($row['quality'] ?? '')));
                $issuePcs = (float) ($row['total_pcs'] ?? 0);
                $issueCts = (float) ($row['total_cts'] ?? 0);
                $retPcs = (float) ($recvMap[$key]['pcs'] ?? 0);
                $retCts = (float) ($recvMap[$key]['cts'] ?? 0);
                $pendingPcs = round(max(0, $issuePcs - $retPcs), 3);
                $pendingCts = round(max(0, $issueCts - $retCts), 3);
                if ($pendingPcs <= 0 && $pendingCts <= 0) {
                    continue;
                }

                $stoneType = trim((string) ($row['stone_type'] ?? 'Stone'));
                $size = trim((string) ($row['size'] ?? ''));
                $color = trim((string) ($row['color'] ?? ''));
                $quality = trim((string) ($row['quality'] ?? ''));
                $parts = array_filter([$stoneType, $size, $color, $quality], static fn(string $v): bool => $v !== '');
                $rows[] = [
                    'type' => implode(' | ', $parts),
                    'pcs' => $pendingPcs,
                    'weight_cts' => $pendingCts,
                    'rate' => 0.0,
                ];
            }

            return $rows;
        }

        if ($db->tableExists('stone_issues')) {
            $issueRows = $db->table('stone_issues')
                ->select('stone_type, size, color, quality, COALESCE(SUM(issue_pcs),0) as total_pcs, COALESCE(SUM(issue_weight_cts),0) as total_cts', false)
                ->where('order_id', $orderId)
                ->groupBy('stone_type, size, color, quality')
                ->get()
                ->getResultArray();

            foreach ($issueRows as $row) {
                $rows[] = [
                    'type' => trim(implode(' | ', array_filter([
                        (string) ($row['stone_type'] ?? 'Stone'),
                        (string) ($row['size'] ?? ''),
                        (string) ($row['color'] ?? ''),
                        (string) ($row['quality'] ?? ''),
                    ], static fn(string $v): bool => trim($v) !== ''))),
                    'pcs' => round((float) ($row['total_pcs'] ?? 0), 3),
                    'weight_cts' => round((float) ($row['total_cts'] ?? 0), 3),
                    'rate' => 0.0,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectItemsFromRequest(): array
    {
        $designIds   = (array) $this->request->getPost('design_id');
        $goldPurityIds = (array) $this->request->getPost('gold_purity_id');
        $descriptions = (array) $this->request->getPost('item_description');
        $sizes       = (array) $this->request->getPost('size_label');
        $qtys        = (array) $this->request->getPost('qty');
        $goldReqs    = (array) $this->request->getPost('gold_required_gm');
        $diamondReqs = (array) $this->request->getPost('diamond_required_cts');

        $max = max(count($designIds), count($goldPurityIds), count($descriptions), count($sizes), count($qtys), count($goldReqs), count($diamondReqs));
        $items = [];

        for ($i = 0; $i < $max; $i++) {
            $designId = $designIds[$i] ?? '';
            $goldPurityId = $goldPurityIds[$i] ?? '';
            $desc     = trim((string) ($descriptions[$i] ?? ''));
            $size     = trim((string) ($sizes[$i] ?? ''));
            $qty      = (int) ($qtys[$i] ?? 0);
            $goldReq  = (float) ($goldReqs[$i] ?? 0);
            $diaReq   = (float) ($diamondReqs[$i] ?? 0);

            if ($qty <= 0 || ($designId === '' && $desc === '')) {
                continue;
            }

            $items[] = [
                'design_id'            => $designId === '' ? null : (int) $designId,
                'gold_purity_id'       => $goldPurityId === '' ? null : (int) $goldPurityId,
                'item_description'     => $desc,
                'size_label'           => $size,
                'qty'                  => $qty,
                'gold_required_gm'     => $goldReq,
                'diamond_required_cts' => $diaReq,
            ];
        }

        return $items;
    }

    private function syncCompletedOrdersFromReceive(array $orderIds = []): void
    {
        $db = db_connect();
        if (! $db->tableExists('orders') || ! $db->tableExists('order_items') || ! $db->tableExists('order_material_movements')) {
            return;
        }

        $receiveBuilder = $db->table('order_material_movements')
            ->select('DISTINCT order_id', false)
            ->where('movement_type', 'receive');

        if ($orderIds !== []) {
            $validIds = array_values(array_unique(array_map(static fn($v): int => (int) $v, $orderIds)));
            $validIds = array_values(array_filter($validIds, static fn(int $v): bool => $v > 0));
            if ($validIds === []) {
                return;
            }
            $receiveBuilder->whereIn('order_id', $validIds);
        }

        $receiveRows = $receiveBuilder->get()->getResultArray();
        if ($receiveRows === []) {
            return;
        }

        $receiveOrderIds = [];
        foreach ($receiveRows as $row) {
            $rid = (int) ($row['order_id'] ?? 0);
            if ($rid > 0) {
                $receiveOrderIds[] = $rid;
            }
        }
        $receiveOrderIds = array_values(array_unique($receiveOrderIds));
        if ($receiveOrderIds === []) {
            return;
        }

        $pendingRows = $db->table('orders')
            ->select('id')
            ->whereIn('id', $receiveOrderIds)
            ->whereNotIn('status', ['Completed', 'Cancelled'])
            ->get()
            ->getResultArray();

        if ($pendingRows === []) {
            return;
        }

        $toUpdate = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $pendingRows);
        $toUpdate = array_values(array_filter($toUpdate, static fn(int $v): bool => $v > 0));
        if ($toUpdate === []) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $db->transStart();
        $db->table('orders')->whereIn('id', $toUpdate)->set([
            'status' => 'Completed',
            'updated_at' => $now,
        ])->update();
        $db->table('order_items')->whereIn('order_id', $toUpdate)->set([
            'item_status' => 'Completed',
            'updated_at' => $now,
        ])->update();
        $db->transComplete();
    }

    /**
     * @return array<string,mixed>
     */
    private function ensurePackingListForOrder(int $orderId, int $adminId): array
    {
        $db = db_connect();
        $existing = $db->table('packing_lists')
            ->where('order_id', $orderId)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        if (is_array($existing) && $existing !== []) {
            return $existing;
        }

        $order = $db->table('orders')->where('id', $orderId)->get()->getRowArray();
        if (! $order) {
            throw new Exception('Order not found.');
        }

        $rows = $db->table('order_items')
            ->select('id, qty, gold_required_gm, diamond_required_cts')
            ->where('order_id', $orderId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        if ($rows === []) {
            throw new Exception('No order items found to create packing list.');
        }

        $packingNo = $this->nextPackingNo();
        $db->transException(true)->transStart();

        $packingId = (int) $db->table('packing_lists')->insert([
            'packing_no' => $packingNo,
            'packing_date' => date('Y-m-d'),
            'order_id' => $orderId,
            'customer_id' => (int) ($order['customer_id'] ?? 0) > 0 ? (int) $order['customer_id'] : null,
            'warehouse_id' => null,
            'status' => 'Packed',
            'notes' => 'Auto-generated from completed order.',
            'created_by' => $adminId > 0 ? $adminId : null,
        ], true);

        foreach ($rows as $index => $line) {
            $qty = (int) max(1, (int) ($line['qty'] ?? 1));
            $netGold = round((float) ($line['gold_required_gm'] ?? 0), 3);
            $diamondCts = round((float) ($line['diamond_required_cts'] ?? 0), 3);
            $gross = round($netGold + ($diamondCts * 0.2), 3);

            $db->table('packing_list_items')->insert([
                'packing_list_id' => $packingId,
                'fg_item_id' => 0,
                'tag_no' => (string) ($order['order_no'] ?? 'ORD') . '-' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'qty' => $qty,
                'gross_wt' => $gross,
                'net_gold_wt' => $netGold,
                'diamond_cts' => $diamondCts,
                'stone_wt' => 0,
            ]);
        }

        $db->transComplete();

        $created = $db->table('packing_lists')->where('id', $packingId)->get()->getRowArray();
        return is_array($created) ? $created : [];
    }

    private function nextPackingNo(): string
    {
        $db = db_connect();
        $prefix = 'PK' . date('ymd');
        $rows = $db->table('packing_lists')
            ->select('packing_no')
            ->like('packing_no', $prefix, 'after')
            ->get()
            ->getResultArray();

        $max = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d{3,})$/';
        foreach ($rows as $row) {
            $no = (string) ($row['packing_no'] ?? '');
            if ($no !== '' && preg_match($pattern, $no, $m) === 1) {
                $n = (int) ($m[1] ?? 0);
                if ($n > $max) {
                    $max = $n;
                }
            }
        }

        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string,mixed>
     */
    private function companySetting(): array
    {
        $row = $this->companySettingModel->orderBy('id', 'ASC')->first();
        return is_array($row) ? $row : [];
    }

    /**
     * @param array<string,mixed> $summary
     * @param array<string,list<array<string,mixed>>> $components
     */
    private function persistReceiveSnapshot(int $movementId, int $orderId, array $summary, array $components): void
    {
        $db = db_connect();
        if ($movementId <= 0 || $orderId <= 0) {
            return;
        }

        if ($db->tableExists('order_receive_summaries')) {
            $exists = $this->receiveSummaryModel->where('movement_id', $movementId)->first();
            $summaryData = [
                'movement_id' => $movementId,
                'order_id' => $orderId,
                'gross_weight_gm' => round((float) ($summary['gross_weight_gm'] ?? 0), 3),
                'net_gold_weight_gm' => round((float) ($summary['net_gold_weight_gm'] ?? 0), 3),
                'pure_gold_weight_gm' => round((float) ($summary['pure_gold_weight_gm'] ?? 0), 3),
                'diamond_weight_cts' => round((float) ($summary['diamond_weight_cts'] ?? 0), 3),
                'diamond_weight_gm' => round((float) ($summary['diamond_weight_gm'] ?? 0), 3),
                'stone_weight_cts' => round((float) ($summary['stone_weight_cts'] ?? 0), 3),
                'stone_weight_gm' => round((float) ($summary['stone_weight_gm'] ?? 0), 3),
                'other_weight_gm' => round((float) ($summary['other_weight_gm'] ?? 0), 3),
                'diamond_amount' => round((float) ($summary['diamond_amount'] ?? 0), 2),
                'stone_amount' => round((float) ($summary['stone_amount'] ?? 0), 2),
                'other_amount' => round((float) ($summary['other_amount'] ?? 0), 2),
                'gold_amount' => round((float) ($summary['gold_amount'] ?? 0), 2),
                'labour_rate_per_gm' => round((float) ($summary['labour_rate_per_gm'] ?? 0), 2),
                'labour_amount' => round((float) ($summary['labour_amount'] ?? 0), 2),
                'total_valuation' => round((float) ($summary['total_valuation'] ?? 0), 2),
                'created_by' => (int) ($summary['created_by'] ?? 0),
            ];
            if ($exists) {
                $this->receiveSummaryModel->update((int) $exists['id'], $summaryData);
            } else {
                $this->receiveSummaryModel->insert($summaryData);
            }
        }

        if (! $db->tableExists('order_receive_details')) {
            return;
        }

        $this->receiveDetailModel->where('movement_id', $movementId)->delete();
        $adminId = (int) session('admin_id');
        foreach (['diamond', 'stone', 'other'] as $componentType) {
            foreach ((array) ($components[$componentType] ?? []) as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                $pcs = max(0.0, (float) ($row['pcs'] ?? 0));
                $weightCts = max(0.0, (float) ($row['weight_cts'] ?? 0));
                $weightGm = max(0.0, (float) ($row['weight_gm'] ?? 0));
                $rate = max(0.0, (float) ($row['rate'] ?? 0));
                $lineTotal = max(0.0, (float) ($row['line_total'] ?? 0));
                if ($name === '' && $pcs <= 0 && $weightCts <= 0 && $weightGm <= 0 && $lineTotal <= 0) {
                    continue;
                }

                $this->receiveDetailModel->insert([
                    'movement_id' => $movementId,
                    'order_id' => $orderId,
                    'component_type' => $componentType,
                    'component_name' => $name === '' ? ucfirst($componentType) : $name,
                    'pcs' => round($pcs, 3),
                    'weight_cts' => round($weightCts, 3),
                    'weight_gm' => round($weightGm, 3),
                    'rate' => round($rate, 2),
                    'line_total' => round($lineTotal, 2),
                    'created_by' => $adminId > 0 ? $adminId : null,
                ]);
            }
        }
    }

    /**
     * @param array<string,mixed> $setting
     * @param array<string,float|int> $receive
     * @param array<string,float> $pricing
     * @return array<string,mixed>
     */
    private function saveDeliveryChallanSnapshot(int $orderId, int $packingId, array $setting, array $receive, array $pricing): array
    {
        $prefix = strtoupper(trim((string) ($setting['delivery_challan_suffix'] ?? 'DC')));
        $prefix = preg_replace('/[^A-Z0-9]/', '', $prefix) ?: 'DC';
        $taxPercent = 3.0;
        $taxable = round((float) ($pricing['total'] ?? 0), 2);
        $taxAmount = round($taxable * ($taxPercent / 100), 2);
        $totalAmount = round($taxable + $taxAmount, 2);

        $existing = $this->deliveryChallanModel
            ->where('order_id', $orderId)
            ->where('packing_list_id', $packingId)
            ->orderBy('id', 'DESC')
            ->first();

        $challanNo = (string) ($existing['challan_no'] ?? '');
        if ($challanNo === '') {
            $challanNo = $this->nextDeliveryChallanNo($prefix);
        }

        $payload = [
            'challan_no' => $challanNo,
            'challan_date' => date('Y-m-d'),
            'order_id' => $orderId,
            'packing_list_id' => $packingId > 0 ? $packingId : null,
            'receive_movement_id' => (int) ($receive['movement_id'] ?? 0) > 0 ? (int) $receive['movement_id'] : null,
            'gross_weight_gm' => round((float) ($receive['gross'] ?? 0), 3),
            'net_gold_weight_gm' => round((float) ($receive['net'] ?? 0), 3),
            'diamond_weight_cts' => round((float) ($receive['diamond_cts'] ?? 0), 3),
            'color_stone_weight_cts' => round((float) ($receive['stone_cts'] ?? 0), 3),
            'other_weight_gm' => round((float) ($receive['other_gm'] ?? 0), 3),
            'taxable_value' => $taxable,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'summary_json' => json_encode(
                ['receive' => $receive, 'pricing' => $pricing],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'created_by' => (int) (session('admin_id') ?? 0),
        ];

        if ($existing) {
            $id = (int) ($existing['id'] ?? 0);
            if ($id > 0) {
                $this->deliveryChallanModel->update($id, $payload);
                $updated = $this->deliveryChallanModel->find($id);
                return is_array($updated) ? $updated : $payload;
            }
        }

        $newId = (int) $this->deliveryChallanModel->insert($payload, true);
        if ($newId > 0) {
            $saved = $this->deliveryChallanModel->find($newId);
            if (is_array($saved)) {
                return $saved;
            }
        }

        return $payload;
    }

    private function nextDeliveryChallanNo(string $prefix): string
    {
        $rows = $this->deliveryChallanModel
            ->select('challan_no')
            ->like('challan_no', $prefix, 'after')
            ->findAll();

        $max = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d{3,})$/';
        foreach ($rows as $row) {
            $no = (string) ($row['challan_no'] ?? '');
            if ($no !== '' && preg_match($pattern, $no, $m) === 1) {
                $serial = (int) ($m[1] ?? 0);
                if ($serial > $max) {
                    $max = $serial;
                }
            }
        }

        return $prefix . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function packingDetailRows(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }
        $db = db_connect();

        if ($db->tableExists('order_receive_details')) {
            $receiveRows = $db->table('order_receive_details')
                ->select('component_type, component_name, pcs, weight_cts, weight_gm, rate, line_total')
                ->where('order_id', $orderId)
                ->orderBy('movement_id', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            if ($receiveRows !== []) {
                $rows = [];
                foreach ($receiveRows as $row) {
                    $componentType = strtolower((string) ($row['component_type'] ?? ''));
                    $weight = $componentType === 'other'
                        ? (float) ($row['weight_gm'] ?? 0)
                        : (float) ($row['weight_cts'] ?? 0);
                    $pcs = (float) ($row['pcs'] ?? 0);
                    $amt = (float) ($row['line_total'] ?? 0);
                    if ($pcs <= 0 && $weight <= 0 && $amt <= 0) {
                        continue;
                    }
                    $name = trim((string) ($row['component_name'] ?? ''));
                    if ($name === '') {
                        $name = ucfirst($componentType !== '' ? $componentType : 'detail');
                    }
                    $rows[] = [
                        'name' => $name,
                        'grade' => ucfirst($componentType !== '' ? $componentType : '-'),
                        'pcs' => round($pcs, 3),
                        'wt' => round($weight, 3),
                        'rate' => round((float) ($row['rate'] ?? 0), 2),
                        'amt' => round($amt, 2),
                    ];
                }
                if ($rows !== []) {
                    return $rows;
                }
            }
        }

        $rows = [];

        if ($db->tableExists('issue_headers') && $db->tableExists('issue_lines') && $db->tableExists('items')) {
            $issueRows = $db->table('issue_headers ih')
                ->select('il.item_id, i.diamond_type, i.shape, i.color, i.clarity, COALESCE(SUM(il.pcs),0) as issue_pcs, COALESCE(SUM(il.carat),0) as issue_carat, COALESCE(SUM(il.line_value),0) as issue_amount', false)
                ->join('issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->join('items i', 'i.id = il.item_id', 'left')
                ->where('ih.order_id', $orderId)
                ->groupBy('il.item_id, i.diamond_type, i.shape, i.color, i.clarity')
                ->get()
                ->getResultArray();

            $returnMap = [];
            if ($db->tableExists('return_headers') && $db->tableExists('return_lines')) {
                $returnRows = $db->table('return_headers rh')
                    ->select('rl.item_id, COALESCE(SUM(rl.pcs),0) as return_pcs, COALESCE(SUM(rl.carat),0) as return_carat, COALESCE(SUM(rl.line_value),0) as return_amount', false)
                    ->join('return_lines rl', 'rl.return_id = rh.id', 'inner')
                    ->where('rh.order_id', $orderId)
                    ->groupBy('rl.item_id')
                    ->get()
                    ->getResultArray();
                foreach ($returnRows as $row) {
                    $returnMap[(int) ($row['item_id'] ?? 0)] = $row;
                }
            }

            foreach ($issueRows as $row) {
                $itemId = (int) ($row['item_id'] ?? 0);
                $r = $returnMap[$itemId] ?? ['return_pcs' => 0, 'return_carat' => 0, 'return_amount' => 0];
                $pcs = max(0.0, (float) ($row['issue_pcs'] ?? 0) - (float) ($r['return_pcs'] ?? 0));
                $wt = max(0.0, (float) ($row['issue_carat'] ?? 0) - (float) ($r['return_carat'] ?? 0));
                $amt = max(0.0, (float) ($row['issue_amount'] ?? 0) - (float) ($r['return_amount'] ?? 0));
                if ($pcs <= 0 && $wt <= 0 && $amt <= 0) {
                    continue;
                }
                $grade = trim(implode('/', array_filter([
                    (string) ($row['shape'] ?? ''),
                    (string) ($row['color'] ?? ''),
                    (string) ($row['clarity'] ?? ''),
                ], static fn(string $v): bool => trim($v) !== '')));
                $rows[] = [
                    'name' => trim((string) ($row['diamond_type'] ?? 'Diamond')),
                    'grade' => $grade === '' ? '-' : $grade,
                    'pcs' => round($pcs, 3),
                    'wt' => round($wt, 3),
                    'rate' => $wt > 0 ? round($amt / $wt, 2) : 0.0,
                    'amt' => round($amt, 2),
                ];
            }
        }

        if ($db->tableExists('stone_inventory_issue_headers') && $db->tableExists('stone_inventory_issue_lines') && $db->tableExists('stone_inventory_items')) {
            $issueRows = $db->table('stone_inventory_issue_headers ih')
                ->select('il.item_id, i.product_name, i.stone_type, COALESCE(SUM(il.pcs),0) as issue_pcs, COALESCE(SUM(il.qty),0) as issue_wt, COALESCE(SUM(il.line_value),0) as issue_amount', false)
                ->join('stone_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->join('stone_inventory_items i', 'i.id = il.item_id', 'left')
                ->where('ih.order_id', $orderId)
                ->groupBy('il.item_id, i.product_name, i.stone_type')
                ->get()
                ->getResultArray();

            $returnMap = [];
            if ($db->tableExists('stone_inventory_return_headers') && $db->tableExists('stone_inventory_return_lines')) {
                $returnRows = $db->table('stone_inventory_return_headers rh')
                    ->select('rl.item_id, COALESCE(SUM(rl.qty),0) as return_pcs, COALESCE(SUM(rl.qty),0) as return_wt, COALESCE(SUM(rl.line_value),0) as return_amount', false)
                    ->join('stone_inventory_return_lines rl', 'rl.return_id = rh.id', 'inner')
                    ->where('rh.order_id', $orderId)
                    ->groupBy('rl.item_id')
                    ->get()
                    ->getResultArray();
                foreach ($returnRows as $row) {
                    $returnMap[(int) ($row['item_id'] ?? 0)] = $row;
                }
            }

            foreach ($issueRows as $row) {
                $itemId = (int) ($row['item_id'] ?? 0);
                $r = $returnMap[$itemId] ?? ['return_pcs' => 0, 'return_wt' => 0, 'return_amount' => 0];
                $pcs = max(0.0, (float) ($row['issue_pcs'] ?? 0) - (float) ($r['return_pcs'] ?? 0));
                $wt = max(0.0, (float) ($row['issue_wt'] ?? 0) - (float) ($r['return_wt'] ?? 0));
                $amt = max(0.0, (float) ($row['issue_amount'] ?? 0) - (float) ($r['return_amount'] ?? 0));
                if ($pcs <= 0 && $wt <= 0 && $amt <= 0) {
                    continue;
                }
                $rows[] = [
                    'name' => trim((string) ($row['product_name'] ?? 'Stone')),
                    'grade' => trim((string) ($row['stone_type'] ?? '-')) ?: '-',
                    'pcs' => round($pcs, 3),
                    'wt' => round($wt, 3),
                    'rate' => $wt > 0 ? round($amt / $wt, 2) : 0.0,
                    'amt' => round($amt, 2),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<string,float>
     */
    private function packingReceiveSummary(int $orderId): array
    {
        if ($orderId <= 0) {
            return [
                'gross' => 0.0,
                'net' => 0.0,
                'pure' => 0.0,
                'diamond_cts' => 0.0,
                'diamond_gm' => 0.0,
                'stone_cts' => 0.0,
                'stone_gm' => 0.0,
                'other_gm' => 0.0,
                'movement_id' => 0,
            ];
        }

        $db = db_connect();
        if ($db->tableExists('order_receive_summaries')) {
            $row = $db->table('order_receive_summaries')
                ->select('COALESCE(SUM(gross_weight_gm),0) as gross, COALESCE(SUM(net_gold_weight_gm),0) as net, COALESCE(SUM(pure_gold_weight_gm),0) as pure, COALESCE(SUM(diamond_weight_cts),0) as diamond_cts, COALESCE(SUM(diamond_weight_gm),0) as diamond_gm, COALESCE(SUM(stone_weight_cts),0) as stone_cts, COALESCE(SUM(stone_weight_gm),0) as stone_gm, COALESCE(SUM(other_weight_gm),0) as other_gm, COALESCE(MAX(movement_id),0) as movement_id', false)
                ->where('order_id', $orderId)
                ->get()
                ->getRowArray();

            if ($row && ((float) ($row['gross'] ?? 0) > 0 || (float) ($row['net'] ?? 0) > 0)) {
                return [
                    'gross' => round((float) ($row['gross'] ?? 0), 3),
                    'net' => round((float) ($row['net'] ?? 0), 3),
                    'pure' => round((float) ($row['pure'] ?? 0), 3),
                    'diamond_cts' => round((float) ($row['diamond_cts'] ?? 0), 3),
                    'diamond_gm' => round((float) ($row['diamond_gm'] ?? 0), 3),
                    'stone_cts' => round((float) ($row['stone_cts'] ?? 0), 3),
                    'stone_gm' => round((float) ($row['stone_gm'] ?? 0), 3),
                    'other_gm' => round((float) ($row['other_gm'] ?? 0), 3),
                    'movement_id' => (int) ($row['movement_id'] ?? 0),
                ];
            }
        }

        $row = $db->table('order_material_movements')
            ->select('COALESCE(SUM(gross_weight_gm),0) as gross, COALESCE(SUM(net_gold_weight_gm),0) as net, COALESCE(SUM(pure_gold_weight_gm),0) as pure, COALESCE(SUM(diamond_cts),0) as diamond_cts, COALESCE(SUM(diamond_weight_gm),0) as diamond_gm, COALESCE(SUM(other_weight_gm),0) as other_gm, COALESCE(MAX(id),0) as movement_id', false)
            ->where('order_id', $orderId)
            ->where('movement_type', 'receive')
            ->get()
            ->getRowArray();

        return [
            'gross' => round((float) ($row['gross'] ?? 0), 3),
            'net' => round((float) ($row['net'] ?? 0), 3),
            'pure' => round((float) ($row['pure'] ?? 0), 3),
            'diamond_cts' => round((float) ($row['diamond_cts'] ?? 0), 3),
            'diamond_gm' => round((float) ($row['diamond_gm'] ?? 0), 3),
            'stone_cts' => 0.0,
            'stone_gm' => 0.0,
            'other_gm' => round((float) ($row['other_gm'] ?? 0), 3),
            'movement_id' => (int) ($row['movement_id'] ?? 0),
        ];
    }

    /**
     * @param list<array<string,mixed>> $detailRows
     * @return array<string,float>
     */
    private function packingPricingSummary(int $orderId, array $detailRows, array $receive): array
    {
        $db = db_connect();

        if ($orderId > 0 && $db->tableExists('order_receive_summaries')) {
            $sum = $db->table('order_receive_summaries')
                ->select('COALESCE(SUM(diamond_amount),0) as diamond_amount, COALESCE(SUM(stone_amount),0) as stone_amount, COALESCE(SUM(other_amount),0) as other_amount, COALESCE(SUM(gold_amount),0) as gold_amount, COALESCE(SUM(labour_amount),0) as labour_amount, COALESCE(SUM(total_valuation),0) as total_valuation', false)
                ->where('order_id', $orderId)
                ->get()
                ->getRowArray();

            $diamondAmount = round((float) ($sum['diamond_amount'] ?? 0), 2);
            $stoneAmount = round((float) ($sum['stone_amount'] ?? 0), 2);
            $otherAmount = round((float) ($sum['other_amount'] ?? 0), 2);
            $goldAmount = round((float) ($sum['gold_amount'] ?? 0), 2);
            $labourAmount = round((float) ($sum['labour_amount'] ?? 0), 2);
            $studdedAmount = round($diamondAmount + $stoneAmount + $otherAmount, 2);
            $totalValuation = round((float) ($sum['total_valuation'] ?? 0), 2);
            if ($totalValuation <= 0) {
                $totalValuation = round($studdedAmount + $goldAmount + $labourAmount, 2);
            }

            if ($studdedAmount > 0 || $goldAmount > 0 || $labourAmount > 0 || $totalValuation > 0) {
                return [
                    'diamond' => $diamondAmount,
                    'stone' => $stoneAmount,
                    'other' => $otherAmount,
                    'studded' => $studdedAmount,
                    'gold' => $goldAmount,
                    'labour' => $labourAmount,
                    'total' => $totalValuation,
                ];
            }
        }

        $studdedAmount = 0.0;
        foreach ($detailRows as $row) {
            $studdedAmount += (float) ($row['amt'] ?? 0);
        }

        $goldAmount = 0.0;
        if ($orderId > 0 && $db->tableExists('gold_inventory_issue_headers') && $db->tableExists('gold_inventory_issue_lines')) {
            $avg = $db->table('gold_inventory_issue_headers ih')
                ->select('COALESCE(SUM(il.line_value),0) as amount, COALESCE(SUM(il.weight_gm),0) as wt', false)
                ->join('gold_inventory_issue_lines il', 'il.issue_id = ih.id', 'inner')
                ->where('ih.order_id', $orderId)
                ->get()
                ->getRowArray();
            $wt = (float) ($avg['wt'] ?? 0);
            if ($wt > 0) {
                $rate = (float) ($avg['amount'] ?? 0) / $wt;
                $goldAmount = max(0.0, (float) ($receive['pure'] ?? 0) * $rate);
            }
        }

        $labour = 0.0;
        if ($orderId > 0 && $db->tableExists('labour_bills')) {
            $row = $db->table('labour_bills')
                ->select('COALESCE(SUM(total_amount),0) as total', false)
                ->where('order_id', $orderId)
                ->get()
                ->getRowArray();
            $labour = (float) ($row['total'] ?? 0);
        }

        return [
            'diamond' => round($studdedAmount, 2),
            'stone' => 0.0,
            'other' => 0.0,
            'studded' => round($studdedAmount, 2),
            'gold' => round($goldAmount, 2),
            'labour' => round($labour, 2),
            'total' => round($studdedAmount + $goldAmount + $labour, 2),
        ];
    }

    private function isValidStatusTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        $allowed = [
            'Confirmed'     => 'In Production',
            'In Production' => 'QC',
            'QC'            => 'Ready',
            'Ready'         => 'Packed',
            'Packed'        => 'Dispatched',
            'Dispatched'    => 'Completed',
            'Completed'     => null,
            'Cancelled'     => null,
        ];

        return isset($allowed[$from]) && $allowed[$from] === $to;
    }

    private function isRepairType(string $orderType): bool
    {
        return strcasecmp(trim($orderType), 'Repair') === 0;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableDate(string $date): ?string
    {
        if ($date === '') {
            return null;
        }

        return date('Y-m-d', strtotime($date));
    }

    private function nullableDateTime(string $dateTime): ?string
    {
        if ($dateTime === '') {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime($dateTime));
    }

    private function isSafeAdminReturnUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        return str_starts_with($url, site_url('admin/'));
    }

    private function firstValidationError(): string
    {
        $errors = $this->validator ? $this->validator->getErrors() : [];
        return $errors === [] ? 'Validation failed.' : (string) array_values($errors)[0];
    }

    private function currentAuditUserId(): int
    {
        return (int) (session('admin_id') ?? 0);
    }

    private function storeAuditImageAttachment(int $orderId, string $fileField, string $fileType, int $adminId): void
    {
        $file = $this->request->getFile($fileField);
        if (! $file || ! $file->isValid() || $file->hasMoved()) {
            throw new Exception('Valid audit image is required.');
        }

        $uploadDir = FCPATH . 'uploads/orders';
        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $newName = $file->getRandomName();
        $file->move($uploadDir, $newName);

        $this->attachmentModel->insert([
            'order_id' => $orderId,
            'file_type' => $fileType,
            'file_name' => $file->getClientName(),
            'file_path' => 'uploads/orders/' . $newName,
            'uploaded_by' => $adminId,
        ]);
    }
}
