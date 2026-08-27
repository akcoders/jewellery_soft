<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\CustomerModel;
use App\Models\CustomerUserModel;
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
use App\Services\FinishedJewelleryService;
use App\Services\GoldInventory\StockService as GoldInventoryStockService;
use App\Services\KarigarMaterialAccountingService;
use App\Services\MobileNotificationEventService;
use App\Services\OrderWhatsAppService;
use App\Services\PdfService;
use App\Services\StoneInventory\StockService as StoneInventoryStockService;
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
    private DesignMasterModel $designModel;
    private GoldPurityModel $goldPurityModel;
    private InventoryLocationModel $locationModel;
    private InventoryTransactionModel $inventoryTxnModel;
    private DeliveryChallanModel $deliveryChallanModel;
    private AdminPostingService $adminPostingService;
    private FinishedJewelleryService $finishedJewelleryService;
    private OrderWhatsAppService $orderWhatsAppService;
    private MobileNotificationEventService $mobileNotificationEvents;
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
        $this->designModel     = new DesignMasterModel();
        $this->goldPurityModel = new GoldPurityModel();
        $this->locationModel   = new InventoryLocationModel();
        $this->inventoryTxnModel = new InventoryTransactionModel();
        $this->deliveryChallanModel = new DeliveryChallanModel();
        $this->adminPostingService = new AdminPostingService();
        $this->finishedJewelleryService = new FinishedJewelleryService();
        $this->orderWhatsAppService = new OrderWhatsAppService();
        $this->mobileNotificationEvents = new MobileNotificationEventService();
        $this->pdfService = new PdfService();
        $this->jewelleryConfig = config(Jewellery::class);
    }

    public function index(): string
    {
        return $this->renderOrderList('all');
    }

    public function dashboard(): string
    {
        $this->syncCompletedOrdersFromReceive();

        $orders = $this->orderModel
            ->select('orders.*, customers.name as customer_name, karigars.name as karigar_name')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->join('karigars', 'karigars.id = orders.assigned_karigar_id', 'left')
            ->orderBy('orders.id', 'DESC')
            ->findAll();

        $orderIds = array_values(array_filter(array_map(
            static fn(array $order): int => (int) ($order['id'] ?? 0),
            $orders
        ), static fn(int $id): bool => $id > 0));

        $latestFollowupByOrder = [];
        $itemsByOrder = [];
        $designUsage = [];
        $thumbnailByOrder = $this->dashboardOrderThumbnails($orderIds);

        if ($orderIds !== []) {
            $latestSubquery = db_connect()->table('order_followups')
                ->select('MAX(id) AS id')
                ->whereIn('order_id', $orderIds)
                ->groupBy('order_id')
                ->getCompiledSelect();

            $latestFollowups = db_connect()->table('order_followups ofu')
                ->select('ofu.*, admin_users.name AS followup_taken_by_name')
                ->join('(' . $latestSubquery . ') latest', 'latest.id = ofu.id', 'inner', false)
                ->join('admin_users', 'admin_users.id = ofu.followup_taken_by', 'left')
                ->get()
                ->getResultArray();

            foreach ($latestFollowups as $followup) {
                $latestFollowupByOrder[(int) ($followup['order_id'] ?? 0)] = $followup;
            }

            $items = db_connect()->table('order_items oi')
                ->select('oi.order_id, oi.design_id, oi.item_description, dm.design_code, dm.name AS design_name')
                ->join('design_masters dm', 'dm.id = oi.design_id', 'left')
                ->whereIn('oi.order_id', $orderIds)
                ->orderBy('oi.id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($items as $item) {
                $orderId = (int) ($item['order_id'] ?? 0);
                $designId = (int) ($item['design_id'] ?? 0);
                $description = preg_replace('/\s+/', ' ', trim((string) ($item['item_description'] ?? ''))) ?: '';

                if ($designId > 0) {
                    $designKey = 'design:' . $designId;
                    $designLabel = trim((string) ($item['design_name'] ?? ''));
                    if ($designLabel === '') {
                        $designLabel = trim((string) ($item['design_code'] ?? '')) ?: ('Design #' . $designId);
                    }
                } elseif ($description !== '') {
                    $normalized = function_exists('mb_strtolower') ? mb_strtolower($description) : strtolower($description);
                    $designKey = 'description:' . $normalized;
                    $designLabel = $description;
                } else {
                    continue;
                }

                $itemsByOrder[$orderId][$designKey] = $designLabel;
                $designUsage[$designKey]['label'] = $designLabel;
                $designUsage[$designKey]['order_ids'][$orderId] = true;
            }
        }

        $repeatDesignCount = 0;
        foreach ($designUsage as &$usage) {
            $usage['count'] = count($usage['order_ids'] ?? []);
            if ($usage['count'] > 1) {
                $repeatDesignCount++;
            }
        }
        unset($usage);

        $statusCounts = [];
        foreach ($this->jewelleryConfig->orderStatuses as $status) {
            $statusCounts[$status] = 0;
        }
        $statusCounts['Cancelled'] = 0;

        $today = new \DateTimeImmutable('today');
        $terminalStatuses = ['Completed', 'Cancelled', 'Dispatched'];
        $delayedCount = 0;
        $repeatOrderCount = 0;

        foreach ($orders as &$order) {
            $orderId = (int) ($order['id'] ?? 0);
            $status = trim((string) ($order['status'] ?? '')) ?: 'Unknown';
            if (! array_key_exists($status, $statusCounts)) {
                $statusCounts[$status] = 0;
            }
            $statusCounts[$status]++;

            $repeatDesigns = [];
            foreach (($itemsByOrder[$orderId] ?? []) as $designKey => $designLabel) {
                $repeatCount = (int) ($designUsage[$designKey]['count'] ?? 0);
                if ($repeatCount > 1) {
                    $repeatDesigns[] = [
                        'name' => $designLabel,
                        'count' => $repeatCount,
                    ];
                }
            }
            usort($repeatDesigns, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);
            $order['repeat_designs'] = $repeatDesigns;
            $order['is_repeat_design'] = $repeatDesigns !== [];
            if ($repeatDesigns !== []) {
                $repeatOrderCount++;
            }

            $dueDate = trim((string) ($order['due_date'] ?? ''));
            $due = $dueDate !== '' ? \DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate) : false;
            $isDelayed = $due instanceof \DateTimeImmutable
                && $due < $today
                && ! in_array($status, $terminalStatuses, true);

            $order['is_delayed'] = $isDelayed;
            $order['delay_days'] = $isDelayed ? (int) $due->diff($today)->days : 0;
            $latestFollowup = $latestFollowupByOrder[$orderId] ?? null;
            $reason = trim((string) ($latestFollowup['description'] ?? ''));
            $order['delay_reason'] = $reason !== '' ? $reason : 'No delay reason recorded.';
            $order['delay_reason_recorded'] = $reason !== '';
            $order['latest_followup_stage'] = (string) ($latestFollowup['stage'] ?? '');
            $order['latest_followup_at'] = (string) ($latestFollowup['followup_taken_on'] ?? '');
            $order['thumbnail_url'] = (string) ($thumbnailByOrder[$orderId] ?? '');

            if ($isDelayed) {
                $delayedCount++;
            }
        }
        unset($order);

        $selectedStatus = trim((string) $this->request->getGet('status'));
        $selectedView = trim((string) $this->request->getGet('view'));
        $filteredOrders = array_values(array_filter($orders, static function (array $order) use ($selectedStatus, $selectedView): bool {
            if ($selectedStatus !== '' && $selectedStatus !== 'all' && (string) ($order['status'] ?? '') !== $selectedStatus) {
                return false;
            }
            if ($selectedView === 'delayed' && ! (bool) ($order['is_delayed'] ?? false)) {
                return false;
            }
            if ($selectedView === 'repeat' && ! (bool) ($order['is_repeat_design'] ?? false)) {
                return false;
            }

            return true;
        }));

        return view('admin/orders/dashboard', [
            'title' => 'Order Dashboard',
            'orders' => $filteredOrders,
            'statusCounts' => $statusCounts,
            'summary' => [
                'total_orders' => count($orders),
                'delayed_orders' => $delayedCount,
                'repeat_orders' => $repeatOrderCount,
                'repeat_designs' => $repeatDesignCount,
            ],
            'selectedStatus' => $selectedStatus,
            'selectedView' => $selectedView,
        ]);
    }

    public function timeline(int $id)
    {
        $order = $this->orderModel
            ->select('orders.id, orders.order_no, orders.order_from, orders.status, orders.due_date, orders.created_at, customers.name AS customer_name, karigars.name AS karigar_name')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->join('karigars', 'karigars.id = orders.assigned_karigar_id', 'left')
            ->find($id);

        if (! $order) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Order not found.',
            ]);
        }

        $followups = $this->followupModel
            ->select('order_followups.*, admin_users.name AS followup_taken_by_name')
            ->join('admin_users', 'admin_users.id = order_followups.followup_taken_by', 'left')
            ->where('order_followups.order_id', $id)
            ->orderBy('order_followups.followup_taken_on', 'DESC')
            ->orderBy('order_followups.id', 'DESC')
            ->findAll();

        $history = $this->historyModel
            ->select('order_status_history.*, admin_users.name AS changed_by_name')
            ->join('admin_users', 'admin_users.id = order_status_history.changed_by', 'left')
            ->where('order_status_history.order_id', $id)
            ->orderBy('order_status_history.id', 'DESC')
            ->findAll();

        $attachments = $this->attachmentModel
            ->where('order_id', $id)
            ->orderBy('id', 'DESC')
            ->findAll();

        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $images = [];
        foreach ($attachments as $attachment) {
            $path = trim((string) ($attachment['file_path'] ?? ''));
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($path === '' || ! in_array($extension, $imageExtensions, true)) {
                continue;
            }
            $images[] = [
                'name' => (string) ($attachment['file_name'] ?? basename($path)),
                'type' => (string) ($attachment['file_type'] ?? 'image'),
                'url' => base_url(ltrim($path, '/')),
                'created_at' => (string) ($attachment['created_at'] ?? ''),
            ];
        }
        foreach ($this->productionReadyImages($id) as $readyImage) {
            $images[] = [
                'name' => (string) (($readyImage['design_name'] ?? '') ?: ('Ready item ' . ($readyImage['serial_no'] ?? ''))),
                'type' => 'Production ready',
                'url' => site_url('admin/orders/ready-image/' . (int) $readyImage['id']),
                'created_at' => (string) (($readyImage['ready_date'] ?? '') ?: ($readyImage['created_at'] ?? '')),
            ];
        }

        $followupRows = array_map(static function (array $followup): array {
            $imagePath = trim((string) ($followup['image_path'] ?? ''));

            return [
                'id' => (int) ($followup['id'] ?? 0),
                'stage' => (string) ($followup['stage'] ?? '-'),
                'description' => (string) ($followup['description'] ?? ''),
                'next_followup_date' => (string) ($followup['next_followup_date'] ?? ''),
                'taken_by' => (string) (($followup['followup_taken_by_name'] ?? '') ?: 'Admin'),
                'taken_on' => (string) ($followup['followup_taken_on'] ?? ''),
                'image_name' => (string) ($followup['image_name'] ?? ''),
                'image_url' => $imagePath !== '' ? base_url(ltrim($imagePath, '/')) : '',
            ];
        }, $followups);

        $historyRows = array_map(static fn(array $row): array => [
            'from_status' => (string) (($row['from_status'] ?? '') ?: 'Created'),
            'to_status' => (string) ($row['to_status'] ?? '-'),
            'remarks' => (string) ($row['remarks'] ?? ''),
            'changed_by' => (string) (($row['changed_by_name'] ?? '') ?: 'System'),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ], $history);

        return $this->response->setJSON([
            'status' => 'ok',
            'data' => [
                'order' => $order,
                'followups' => $followupRows,
                'status_history' => $historyRows,
                'images' => $images,
            ],
        ]);
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
            ->select('orders.*, customers.name as customer_name, karigars.name as karigar_name, karigars.rate_per_gm as karigar_rate_per_gm, sales_person.name as sales_person_name, sales_person.mobile as sales_person_mobile')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->join('karigars', 'karigars.id = orders.assigned_karigar_id', 'left')
            ->join('customer_users sales_person', 'sales_person.id = orders.sales_person_user_id', 'left');

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
            $title = 'All Orders';
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
            'customers'=> $this->customerModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'locations'=> $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'stoneInventoryItems' => $this->stoneInventoryOptions(),
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
            'designs'     => $this->designModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'goldPurities'=> $this->goldPurityModel->where('is_active', 1)->orderBy('purity_percent', 'DESC')->findAll(),
            'salesPeople' => (new CustomerUserModel())->select('id, customer_id, name, mobile')->where('role', 'sales_person')->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
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
            'order_from'  => 'permit_empty|max_length[150]',
            'customer_id' => 'permit_empty|integer',
            'sales_person_user_id' => 'permit_empty|integer',
            'order_design_type' => 'required|in_list[Fresh,Repeat]',
            'priority'    => 'required',
            'due_date'    => 'permit_empty|valid_date',
            'status'      => 'required',
            'order_notes' => 'permit_empty',
            'whatsapp_notification_number' => 'permit_empty|max_length[40]',
            'whatsapp_notify_order_created' => 'permit_empty|in_list[1]',
            'expected_diamond_spec' => 'permit_empty',
            'expected_stone_spec' => 'permit_empty',
            'priority_level' => 'permit_empty|integer',
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

        $customerId = $this->nullableInt($this->request->getPost('customer_id'));
        $salesPersonUserId = $this->nullableInt($this->request->getPost('sales_person_user_id'));
        if ($salesPersonUserId !== null) {
            if ($customerId === null || (new CustomerUserModel())->where('id', $salesPersonUserId)
                ->where('customer_id', $customerId)->where('role', 'sales_person')->where('is_active', 1)->countAllResults() === 0) {
                return redirect()->back()->withInput()->with('error', 'Selected sales person does not belong to the selected customer.');
            }
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
        $designType = (string) $this->request->getPost('order_design_type');
        if ($designType === 'Fresh') {
            foreach ($items as &$freshItem) {
                $freshItem['design_id'] = null;
            }
            unset($freshItem);
        }
        if ($designType === 'Repeat' && array_filter($items, static fn(array $item): bool => empty($item['design_id'])) !== []) {
            return redirect()->back()->withInput()->with('error', 'Every repeat-order item must have a unique design code selected.');
        }
        if ($designType === 'Repeat') {
            $designIds = array_values(array_unique(array_map(static fn(array $item): int => (int) $item['design_id'], $items)));
            $validDesigns = $designIds === [] ? 0 : $this->designModel->whereIn('id', $designIds)->where('is_active', 1)->countAllResults();
            if ($validDesigns !== count($designIds)) {
                return redirect()->back()->withInput()->with('error', 'One or more repeat designs are not available.');
            }
        }
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
                'order_design_type' => $designType,
                'order_from'  => trim((string) $this->request->getPost('order_from')) ?: null,
                'customer_id' => $customerId,
                'sales_person_user_id' => $salesPersonUserId,
                'lead_id'     => null,
                'assigned_karigar_id' => null,
                'assigned_at' => null,
                'status'      => $status,
                'priority'    => $priority,
                'due_date'    => $this->nullableDate((string) $this->request->getPost('due_date')),
                'order_notes' => trim((string) $this->request->getPost('order_notes')),
                'whatsapp_notification_number' => $this->normalizeWhatsappNumber((string) $this->request->getPost('whatsapp_notification_number')),
                'whatsapp_notify_order_created' => $this->request->getPost('whatsapp_notify_order_created') ? 1 : 0,
                'expected_diamond_spec' => trim((string) $this->request->getPost('expected_diamond_spec')) ?: null,
                'expected_stone_spec' => trim((string) $this->request->getPost('expected_stone_spec')) ?: null,
                'priority_level' => max(0, (int) $this->request->getPost('priority_level')),
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

        if ($this->request->getPost('whatsapp_notify_order_created')) {
            $this->dispatchWhatsappOrderCreated((int) $orderId);
        }

        try {
            $this->mobileNotificationEvents->notifyOrderCreated((int) $orderId, 'admin');
        } catch (Throwable $e) {
            log_message('error', 'Order push notification failed: {message}', ['message' => $e->getMessage()]);
        }

        return redirect()->to(site_url('admin/orders/' . $orderId))->with('success', 'Order created successfully.');
    }

    public function show(int $id): string
    {
        $this->syncCompletedOrdersFromReceive([$id]);

        $order = $this->orderModel
            ->select('orders.*, customers.name as customer_name, karigars.name as karigar_name, karigars.rate_per_gm as karigar_rate_per_gm, sales_person.name as sales_person_name, sales_person.mobile as sales_person_mobile')
            ->join('customers', 'customers.id = orders.customer_id', 'left')
            ->join('karigars', 'karigars.id = orders.assigned_karigar_id', 'left')
            ->join('customer_users sales_person', 'sales_person.id = orders.sales_person_user_id', 'left')
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

        $followups = $this->followupModel
            ->select('order_followups.*, admin_users.name as followup_taken_by_name')
            ->join('admin_users', 'admin_users.id = order_followups.followup_taken_by', 'left')
            ->where('order_followups.order_id', $id)
            ->orderBy('order_followups.id', 'DESC')
            ->findAll();


        $receiveSummary = $this->receiveSummaryModel
            ->where('order_id', $id)
            ->orderBy('id', 'DESC')
            ->first();
        $studdedDetails = [];
        if ($receiveSummary) {
            $studdedDetails = $this->receiveDetailModel
                ->where('movement_id', (int) $receiveSummary['movement_id'])
                ->orderBy('component_type', 'ASC')
                ->orderBy('id', 'ASC')
                ->findAll();
        }

        return view('admin/orders/show', [
            'title'      => 'Order Details',
            'order'      => $order,
            'items'      => $items,
            'attachments'=> $this->attachmentModel->where('order_id', $id)->orderBy('id', 'DESC')->findAll(),
            'locations' => $this->locationModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
            'stoneInventoryItems' => $this->stoneInventoryOptions(),
            'followups' => $followups,
            'readyImages' => $this->productionReadyImages($id),
            'receiveSummary' => is_array($receiveSummary) ? $receiveSummary : [],
            'studdedDetails' => $studdedDetails,
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
            'salesPeople'=> (new CustomerUserModel())->select('id, customer_id, name, mobile')->where('role', 'sales_person')->where('is_active', 1)->orderBy('name', 'ASC')->findAll(),
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
            'order_from'  => 'permit_empty|max_length[150]',
            'customer_id' => 'permit_empty|integer',
            'sales_person_user_id' => 'permit_empty|integer',
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

        $customerId = $this->nullableInt($this->request->getPost('customer_id'));
        $salesPersonUserId = $this->nullableInt($this->request->getPost('sales_person_user_id'));
        if ($salesPersonUserId !== null && ($customerId === null || (new CustomerUserModel())
            ->where('id', $salesPersonUserId)
            ->where('customer_id', $customerId)
            ->where('role', 'sales_person')
            ->where('is_active', 1)
            ->countAllResults() === 0)) {
            return redirect()->back()->withInput()->with('error', 'Selected sales person does not belong to the selected customer.');
        }

        $priority = (string) $this->request->getPost('priority');
        if (! in_array($priority, $this->jewelleryConfig->orderPriorities, true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid order priority.');
        }

        $this->orderModel->update($id, [
            'order_type'  => $isRepairOrder ? 'Repair' : $orderType,
            'order_from'  => trim((string) $this->request->getPost('order_from')) ?: null,
            'customer_id' => $customerId,
            'sales_person_user_id' => $salesPersonUserId,
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
        $customerId = (int) ($this->request->getPost('customer_id') ?? 0);
        if ($customerId <= 0) {
            return redirect()->back()->with('error', 'Please select a customer before assigning the order.');
        }
        if ($karigarId <= 0) {
            return redirect()->back()->with('error', 'Please select a karigar.');
        }

        $customer = $this->customerModel->where('is_active', 1)->find($customerId);
        if (! $customer) {
            return redirect()->back()->with('error', 'Selected customer not found.');
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
            'customer_id' => $customerId,
            'assigned_karigar_id' => $karigarId,
            'assigned_at'         => date('Y-m-d H:i:s'),
        ]);

        return redirect()->back()->with('success', 'Customer selected and karigar assigned successfully.');
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

            foreach ($pendingOrders as $order) {
                $orderId = (int) $order['id'];
                $requiredGold = (float) ($orderGoldMap[$orderId] ?? 0);
                $pendingOrderGoldWeight += $requiredGold;

                $pendingOrderDetails[] = [
                    'order_id' => $orderId,
                    'order_no' => (string) ($order['order_no'] ?? ''),
                    'status' => (string) ($order['status'] ?? ''),
                    'due_date' => (string) ($order['due_date'] ?? ''),
                    'required_gold_gm' => round($requiredGold, 3),
                ];
            }
        }

        $totalGoldWithHim = 0.0;
        if ($db->tableExists('accounts') && $db->tableExists('account_balances')) {
            $balanceRow = $db->table('account_balances ab')
                ->select('COALESCE(SUM(ab.fine_gold_qty),0) as total_fine_gold', false)
                ->join('accounts a', 'a.id = ab.account_id', 'inner')
                ->where('a.account_type', 'KARIGAR')
                ->where('a.reference_table', 'karigars')
                ->where('a.reference_id', $id)
                ->where('ab.item_type', 'GOLD')
                ->get()
                ->getRowArray();
            $totalGoldWithHim = (float) ($balanceRow['total_fine_gold'] ?? 0);
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

    public function addReceive(int $id)
    {
        return $this->saveFinishedJewelleryReceipt($id);
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

        $this->dispatchWhatsappStatusChanged($id, (string) $order['status'], $newStatus, $remarks);
        if (in_array($newStatus, ['Ready', 'Completed'], true)) {
            $this->dispatchWhatsappOrderReady($id, $newStatus);
        }
        if ($newStatus === 'Completed') {
            $this->finishedJewelleryService->createForCompletedOrder($id, $adminId);
        }

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

            $followupId = (int) $this->followupModel->insert([
                'order_id' => $id,
                'stage' => $stage,
                'description' => $description,
                'next_followup_date' => $this->nullableDateTime((string) $this->request->getPost('next_followup_date')),
                'followup_taken_by' => (int) session('admin_id'),
                'followup_taken_on' => date('Y-m-d H:i:s'),
                'image_name' => $imageName,
                'image_path' => $imagePath,
            ], true);

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

        try {
            $this->mobileNotificationEvents->notifyFollowupAdded($id, $followupId);
        } catch (Throwable $e) {
            log_message('error', 'Followup push notification failed: {message}', ['message' => $e->getMessage()]);
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

        $this->finishedJewelleryService->createForCompletedOrder($id, (int) (session('admin_id') ?? 0));

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

    private function saveFinishedJewelleryReceipt(int $orderId)
    {
        $order = $this->orderModel->find($orderId);
        if (! $order) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound('Order not found.');
        }
        if (in_array((string) ($order['status'] ?? ''), ['Cancelled', 'Completed'], true)) {
            return redirect()->back()->with('error', 'Cancelled or completed order cannot be received again.');
        }

        $karigarId = (int) ($order['assigned_karigar_id'] ?? 0);
        if ($karigarId <= 0) {
            return redirect()->back()->with('error', 'Assign a karigar before receiving finished jewellery.');
        }
        if (! $this->validate([
            'location_id' => 'required|integer|greater_than[0]',
            'gross_weight_gm' => 'required|decimal|greater_than[0]',
            'purity_percent' => 'required|decimal|greater_than[0]|less_than_equal_to[100]',
            'gold_rate_per_gm' => 'required|decimal|greater_than[0]',
            'labour_rate_per_gm' => 'permit_empty|decimal|greater_than_equal_to[0]',
            'notes' => 'permit_empty',
        ])) {
            return redirect()->back()->withInput()->with('error', $this->firstValidationError());
        }

        $locationId = (int) $this->request->getPost('location_id');
        if (! $this->locationModel->where('is_active', 1)->find($locationId)) {
            return redirect()->back()->withInput()->with('error', 'Select a valid inventory location.');
        }

        $grossWeightGm = round((float) $this->request->getPost('gross_weight_gm'), 3);
        $purityPercent = round((float) $this->request->getPost('purity_percent'), 3);
        $goldRate = round((float) $this->request->getPost('gold_rate_per_gm'), 2);
        $labourRate = round(max(0, (float) $this->request->getPost('labour_rate_per_gm')), 2);

        $diamond = $this->collectReceiveComponentRows(
            (array) $this->request->getPost('studded_diamond_type'),
            (array) $this->request->getPost('studded_diamond_pcs'),
            (array) $this->request->getPost('studded_diamond_weight'),
            (array) $this->request->getPost('studded_diamond_rate')
        );
        $stone = $this->collectReceiveComponentRows(
            (array) $this->request->getPost('stone_type'),
            (array) $this->request->getPost('stone_pcs'),
            (array) $this->request->getPost('stone_weight'),
            (array) $this->request->getPost('stone_rate'),
            (array) $this->request->getPost('stone_item_id')
        );
        $other = $this->collectReceiveOtherRows(
            (array) $this->request->getPost('other_desc'),
            (array) $this->request->getPost('other_pcs'),
            (array) $this->request->getPost('other_weight_line_gm'),
            (array) $this->request->getPost('other_price')
        );

        $diamondCts = (float) $diamond['total_weight_cts'];
        $diamondPcs = (float) $diamond['total_pcs'];
        $diamondWeightGm = round($diamondCts * 0.2, 3);
        $stoneCts = (float) $stone['total_weight_cts'];
        $stoneWeightGm = round($stoneCts * 0.2, 3);
        $otherOnlyWeightGm = (float) $other['total_weight_gm'];
        $otherWeightGm = round($stoneWeightGm + $otherOnlyWeightGm, 3);
        $netGoldWeightGm = round($grossWeightGm - $diamondWeightGm - $stoneWeightGm - $otherOnlyWeightGm, 3);
        if ($netGoldWeightGm <= 0) {
            return redirect()->back()->withInput()->with('error', 'Net gold weight must be greater than zero. Check all entered weights.');
        }
        $pureGoldWeightGm = round($netGoldWeightGm * ($purityPercent / 100), 3);
        $goldAmount = round($netGoldWeightGm * $goldRate, 2);
        $labourAmount = round($netGoldWeightGm * $labourRate, 2);
        $totalValuation = round(
            $goldAmount + $labourAmount + (float) $diamond['total_amount'] + (float) $stone['total_amount'] + (float) $other['total_amount'],
            2
        );
        $postedNotes = trim((string) $this->request->getPost('notes'));
        $calculationNote = sprintf(
            'Finished receive: Gross %.3f gm, Net gold %.3f gm, Pure gold %.3f gm @ %.3f%%, Diamond %.3f cts, Stone %.3f cts',
            $grossWeightGm,
            $netGoldWeightGm,
            $pureGoldWeightGm,
            $purityPercent,
            $diamondCts,
            $stoneCts
        );
        $notes = $postedNotes === '' ? $calculationNote : $postedNotes . ' | ' . $calculationNote;
        $adminId = (int) session('admin_id');
        $db = \Config\Database::connect();

        try {
            $db->transException(true)->transStart();

            $movementId = (int) $this->movementModel->insert([
                'order_id' => $orderId,
                'movement_type' => 'receive',
                'gold_gm' => $netGoldWeightGm,
                'diamond_cts' => $diamondCts,
                'gold_purity_id' => null,
                'karigar_id' => $karigarId,
                'location_id' => $locationId,
                'gross_weight_gm' => $grossWeightGm,
                'other_weight_gm' => $otherWeightGm,
                'diamond_weight_gm' => $diamondWeightGm,
                'net_gold_weight_gm' => $netGoldWeightGm,
                'pure_gold_weight_gm' => $pureGoldWeightGm,
                'notes' => $notes,
                'created_by' => $adminId,
            ], true);

            $materialAccounting = new KarigarMaterialAccountingService($db);
            $receivedStoneRows = $stone['rows'];
            $this->backflushReceivedStone(
                $db,
                $materialAccounting,
                $movementId,
                $orderId,
                $karigarId,
                $locationId,
                $receivedStoneRows,
                $stoneCts,
                $adminId
            );
            $stone['rows'] = $receivedStoneRows;

            $accountVoucherId = $materialAccounting->postFinishedJewelleryReceipt(
                $orderId,
                $karigarId,
                $locationId,
                $pureGoldWeightGm,
                $diamondPcs,
                $diamondCts,
                $notes,
                $adminId,
                null,
                $stoneCts
            );

            $this->persistReceiveSnapshot(
                $movementId,
                $orderId,
                [
                    'account_voucher_id' => $accountVoucherId,
                    'stone_account_voucher_id' => $stoneCts > 0 ? $accountVoucherId : null,
                    'gross_weight_gm' => $grossWeightGm,
                    'net_gold_weight_gm' => $netGoldWeightGm,
                    'pure_gold_weight_gm' => $pureGoldWeightGm,
                    'diamond_weight_cts' => $diamondCts,
                    'diamond_weight_gm' => $diamondWeightGm,
                    'stone_weight_cts' => $stoneCts,
                    'stone_weight_gm' => $stoneWeightGm,
                    'other_weight_gm' => $otherOnlyWeightGm,
                    'diamond_amount' => (float) $diamond['total_amount'],
                    'stone_amount' => (float) $stone['total_amount'],
                    'other_amount' => (float) $other['total_amount'],
                    'gold_amount' => $goldAmount,
                    'labour_rate_per_gm' => $labourRate,
                    'labour_amount' => $labourAmount,
                    'total_valuation' => $totalValuation,
                    'created_by' => $adminId,
                ],
                [
                    'diamond' => $diamond['rows'],
                    'stone' => $stone['rows'],
                    'other' => $other['rows'],
                ]
            );

            $this->createLabourBillFromReceive(
                $orderId,
                $order,
                $movementId,
                $karigarId,
                $netGoldWeightGm,
                $notes,
                $labourRate,
                (float) $other['total_amount']
            );

            $this->orderModel->update($orderId, ['status' => 'Completed']);
            $this->orderItemModel->where('order_id', $orderId)->set(['item_status' => 'Completed'])->update();
            $this->historyModel->insert([
                'order_id' => $orderId,
                'from_status' => (string) ($order['status'] ?? ''),
                'to_status' => 'Completed',
                'changed_by' => $adminId,
                'remarks' => 'Completed after manual finished-jewellery receiving.',
            ]);
            $this->finishedJewelleryService->createForCompletedOrder($orderId, $adminId);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $this->dispatchWhatsappOrderReady($orderId, 'Completed');
        return redirect()->back()->with('success', 'Finished jewellery received, karigar material balance reduced, and inventory created.');
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

    private function dispatchWhatsappOrderCreated(int $orderId): void
    {
        try {
            $this->orderWhatsAppService->notifyOrderCreated($orderId);
        } catch (Throwable $e) {
            log_message('error', 'WhatsApp order created failed for order {id}: {message}', [
                'id' => $orderId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function normalizeWhatsappNumber(string $number): ?string
    {
        $normalized = preg_replace('/[^0-9+]/', '', trim($number)) ?? '';
        return $normalized === '' ? null : $normalized;
    }

    private function dispatchWhatsappStatusChanged(int $orderId, string $fromStatus, string $toStatus, string $remarks = ''): void
    {
        try {
            $this->orderWhatsAppService->notifyOrderStatusChanged($orderId, $fromStatus, $toStatus, $remarks);
        } catch (Throwable $e) {
            log_message('error', 'WhatsApp status update failed for order {id}: {message}', [
                'id' => $orderId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function dispatchWhatsappOrderReady(int $orderId, string $status): void
    {
        try {
            $this->orderWhatsAppService->notifyOrderReady($orderId, $status);
        } catch (Throwable $e) {
            log_message('error', 'WhatsApp ready alert failed for order {id}: {message}', [
                'id' => $orderId,
                'message' => $e->getMessage(),
            ]);
        }
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
     * @param array<int,mixed> $itemIds
     * @return array{rows:list<array<string,mixed>>,total_pcs:float,total_weight_cts:float,total_amount:float}
     */
    private function collectReceiveComponentRows(
        array $types,
        array $pcsList,
        array $weightList,
        array $rateList,
        array $itemIds = []
    ): array
    {
        $max = max(count($types), count($pcsList), count($weightList), count($rateList), count($itemIds));
        $totalPcs = 0.0;
        $totalWeight = 0.0;
        $totalAmount = 0.0;
        $rows = [];

        for ($i = 0; $i < $max; $i++) {
            $type = trim((string) ($types[$i] ?? ''));
            $pcs = max(0.0, (float) ($pcsList[$i] ?? 0));
            $weight = max(0.0, (float) ($weightList[$i] ?? 0));
            $rate = max(0.0, (float) ($rateList[$i] ?? 0));
            $itemId = max(0, (int) ($itemIds[$i] ?? 0));

            if ($type === '' && $pcs <= 0 && $weight <= 0 && $rate <= 0) {
                continue;
            }

            $lineTotal = round($weight * $rate, 2);
            $totalPcs += $pcs;
            $totalWeight += $weight;
            $totalAmount += $lineTotal;
            $rows[] = [
                'name' => $type === '' ? '-' : $type,
                'item_id' => $itemId > 0 ? $itemId : null,
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
     * Issues only the stone quantity missing from the karigar's pooled stone balance.
     * This keeps a normal prior issue from being deducted twice while still consuming
     * central stock when finished jewellery is received without any earlier issue.
     *
     * @param list<array<string,mixed>> $stoneRows
     */
    private function backflushReceivedStone(
        \CodeIgniter\Database\BaseConnection $db,
        KarigarMaterialAccountingService $materialAccounting,
        int $movementId,
        int $orderId,
        int $karigarId,
        int $locationId,
        array &$stoneRows,
        float $stoneCts,
        int $createdBy
    ): ?int {
        $stoneCts = round(max(0, $stoneCts), 3);
        if ($stoneCts <= 0) {
            return null;
        }

        $stoneStockService = new StoneInventoryStockService($db);
        foreach ($stoneRows as &$stoneRow) {
            if ((float) ($stoneRow['weight_cts'] ?? 0) <= 0 || (int) ($stoneRow['item_id'] ?? 0) > 0) {
                continue;
            }
            $stoneName = trim((string) ($stoneRow['name'] ?? ''));
            if ($stoneName === '' || $stoneName === '-') {
                throw new \RuntimeException('Select a Stone Inventory item or enter a stone description for every received stone line.');
            }
            $stoneRow['item_id'] = $stoneStockService->upsertItemFromSignature([
                'product_name' => $stoneName,
                'stone_type' => $stoneName,
                'default_rate' => max(0, (float) ($stoneRow['rate'] ?? 0)),
                'remarks' => 'Created automatically during finished-jewellery receiving.',
            ]);
        }
        unset($stoneRow);

        $positiveRows = array_values(array_filter(
            $stoneRows,
            static fn(array $row): bool => (float) ($row['weight_cts'] ?? 0) > 0
        ));
        if ($positiveRows === []) {
            throw new \RuntimeException('Enter at least one stone line with weight.');
        }

        $itemIds = [];
        foreach ($positiveRows as $row) {
            $itemId = (int) ($row['item_id'] ?? 0);
            if ($itemId <= 0) {
                throw new \RuntimeException('Select a Stone Inventory item or enter a stone description for every received stone line.');
            }
            $itemIds[] = $itemId;
        }
        $itemIds = array_values(array_unique($itemIds));

        $itemRows = $db->table('stone_inventory_items i')
            ->select('i.id, i.product_name, COALESCE(s.avg_rate, i.default_rate, 0) AS stock_rate', false)
            ->join('stone_inventory_stock s', 's.item_id = i.id', 'left')
            ->whereIn('i.id', $itemIds)
            ->get()
            ->getResultArray();
        $itemsById = [];
        foreach ($itemRows as $item) {
            $itemsById[(int) $item['id']] = $item;
        }
        if (count($itemsById) !== count($itemIds)) {
            throw new \RuntimeException('One or more selected Stone Inventory items are invalid.');
        }

        $shortfall = $materialAccounting->stoneReceiptShortfall($karigarId, $stoneCts);
        if ($shortfall <= 0.0005) {
            return null;
        }

        $existing = $db->table('stone_inventory_issue_headers')
            ->select('id')
            ->where('receive_movement_id', $movementId)
            ->get()
            ->getRowArray();
        if ($existing) {
            throw new \RuntimeException('Stone stock has already been consumed for this receiving entry.');
        }

        $karigar = $db->table('karigars')->select('name')->where('id', $karigarId)->get()->getRowArray();
        $now = date('Y-m-d H:i:s');
        $db->table('stone_inventory_issue_headers')->insert([
            'voucher_no' => 'SRV-' . $movementId,
            'issue_date' => date('Y-m-d'),
            // Material issues remain independent from orders; the receive movement is the audit link.
            'order_id' => null,
            'receive_movement_id' => $movementId,
            'karigar_id' => $karigarId,
            'location_id' => $locationId,
            'issue_to' => (string) ($karigar['name'] ?? ('Karigar #' . $karigarId)),
            'purpose' => 'Finished receipt backflush',
            'notes' => sprintf(
                'Auto-issued %.3f cts stone shortage while receiving order #%d.',
                $shortfall,
                $orderId
            ),
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $issueId = (int) $db->insertID();
        if ($issueId <= 0) {
            throw new \RuntimeException('Unable to create automatic stone inventory consumption.');
        }

        $remaining = $shortfall;
        $lastIndex = count($positiveRows) - 1;
        foreach ($positiveRows as $index => $row) {
            $rowWeight = round((float) ($row['weight_cts'] ?? 0), 3);
            $qty = $index === $lastIndex
                ? $remaining
                : round($shortfall * ($rowWeight / $stoneCts), 3);
            $qty = round(min($rowWeight, max(0, $qty)), 3);
            $remaining = round(max(0, $remaining - $qty), 3);
            if ($qty <= 0) {
                continue;
            }

            $itemId = (int) $row['item_id'];
            $submittedRate = max(0, (float) ($row['rate'] ?? 0));
            $rate = round($submittedRate > 0 ? $submittedRate : (float) $itemsById[$itemId]['stock_rate'], 2);
            $pcs = $rowWeight > 0
                ? round(max(0, (float) ($row['pcs'] ?? 0)) * ($qty / $rowWeight), 3)
                : 0;
            $db->table('stone_inventory_issue_lines')->insert([
                'issue_id' => $issueId,
                'item_id' => $itemId,
                'pcs' => $pcs,
                'qty' => $qty,
                'rate' => $rate,
                'line_value' => round($qty * $rate, 2),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $stoneStockService->applyReceiptBackflushIssue($issueId);
        $materialAccounting->postInventoryHeader('stone', 'issue', $issueId);

        return $issueId;
    }

    /** @return list<array<string,mixed>> */
    private function stoneInventoryOptions(): array
    {
        $db = db_connect();
        if (! $db->tableExists('stone_inventory_items')) {
            return [];
        }

        return $db->table('stone_inventory_items i')
            ->select(
                'i.id, i.product_name, i.stone_type, i.default_rate, '
                . 'COALESCE(s.qty_balance, 0) AS qty_balance, COALESCE(s.avg_rate, 0) AS avg_rate',
                false
            )
            ->join('stone_inventory_stock s', 's.item_id = i.id', 'left')
            ->orderBy('i.product_name', 'ASC')
            ->get()
            ->getResultArray();
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
        foreach ($toUpdate as $completedOrderId) {
            $this->finishedJewelleryService->createForCompletedOrder($completedOrderId, (int) (session('admin_id') ?? 0));
        }
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
                'account_voucher_id' => (int) ($summary['account_voucher_id'] ?? 0) > 0
                    ? (int) $summary['account_voucher_id']
                    : null,
                'stone_account_voucher_id' => (int) ($summary['stone_account_voucher_id'] ?? 0) > 0
                    ? (int) $summary['stone_account_voucher_id']
                    : null,
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
                    'stone_inventory_item_id' => $componentType === 'stone' && (int) ($row['item_id'] ?? 0) > 0
                        ? (int) $row['item_id']
                        : null,
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

        return [];
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

    /**
     * @param list<int> $orderIds
     * @return array<int,string>
     */
    private function dashboardOrderThumbnails(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        $db = db_connect();
        $thumbnails = [];
        $priorities = [];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if ($db->tableExists('order_attachments')) {
            $attachments = $db->table('order_attachments')
                ->select('id, order_id, file_type, file_path')
                ->whereIn('order_id', $orderIds)
                ->where('file_path !=', '')
                ->orderBy('id', 'DESC')
                ->get()
                ->getResultArray();

            foreach ($attachments as $attachment) {
                $orderId = (int) ($attachment['order_id'] ?? 0);
                $filePath = trim((string) ($attachment['file_path'] ?? ''));
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if ($orderId <= 0 || $filePath === '' || ! in_array($extension, $imageExtensions, true)) {
                    continue;
                }

                $fileType = strtolower(trim((string) ($attachment['file_type'] ?? '')));
                $priority = $fileType === 'finish_photo' ? 0 : 2;
                if (! isset($priorities[$orderId]) || $priority < $priorities[$orderId]) {
                    $priorities[$orderId] = $priority;
                    $thumbnails[$orderId] = base_url(ltrim($filePath, '/'));
                }
            }
        }

        if ($db->tableExists('production_ready_items')
            && $db->fieldExists('order_id', 'production_ready_items')
            && $db->fieldExists('image_path', 'production_ready_items')) {
            $readyImages = $db->table('production_ready_items')
                ->select('id, order_id')
                ->whereIn('order_id', $orderIds)
                ->where('image_path IS NOT NULL', null, false)
                ->where('image_path !=', '')
                ->orderBy('source_row', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            foreach ($readyImages as $readyImage) {
                $orderId = (int) ($readyImage['order_id'] ?? 0);
                $readyItemId = (int) ($readyImage['id'] ?? 0);
                if ($orderId <= 0 || $readyItemId <= 0 || (($priorities[$orderId] ?? PHP_INT_MAX) <= 1)) {
                    continue;
                }

                $priorities[$orderId] = 1;
                $thumbnails[$orderId] = site_url('admin/orders/ready-image/' . $readyItemId);
            }
        }

        return $thumbnails;
    }

    /** @return list<array<string,mixed>> */
    private function productionReadyImages(int $orderId): array
    {
        $db = db_connect();
        if (! $db->tableExists('production_ready_items') || ! $db->fieldExists('image_path', 'production_ready_items')) {
            return [];
        }

        return $db->table('production_ready_items')
            ->select('id, serial_no, design_name, reference_no, ready_date, image_path, created_at')
            ->where('order_id', $orderId)
            ->where('image_path IS NOT NULL', null, false)
            ->where('image_path !=', '')
            ->orderBy('source_row', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
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
