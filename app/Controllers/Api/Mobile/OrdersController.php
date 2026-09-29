<?php

namespace App\Controllers\Api\Mobile;

use App\Models\JobCardModel;
use App\Models\OrderAttachmentModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\OrderStatusHistoryModel;
use App\Services\MobileNotificationEventService;
use App\Services\DiamondRequirementService;
use App\Services\OrderCategoryService;
use App\Services\OrderNumberService;
use App\Services\OrderWhatsAppService;
use App\Services\RbacService;
use App\Services\StaffPerformanceService;
use Config\Jewellery;
use Throwable;

class OrdersController extends MobileBaseController
{
    private Jewellery $jewelleryConfig;
    private MobileNotificationEventService $mobileNotificationEvents;
    private StaffPerformanceService $staffPerformanceService;
    private DiamondRequirementService $diamondRequirementService;
    private RbacService $rbacService;

    public function __construct()
    {
        $this->jewelleryConfig = config(Jewellery::class);
        $this->mobileNotificationEvents = new MobileNotificationEventService();
        $this->staffPerformanceService = new StaffPerformanceService();
        $this->diamondRequirementService = new DiamondRequirementService();
        $this->rbacService = new RbacService();
    }

    public function index()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $db = db_connect();
        $status = trim((string) $this->request->getGet('status'));
        $search = trim((string) $this->request->getGet('q'));
        $page = max(1, (int) $this->request->getGet('page'));
        $limit = max(1, min(100, (int) ($this->request->getGet('limit') ?? 20)));
        $offset = ($page - 1) * $limit;

        $builder = $db->table('orders o')
            ->select('o.id, o.order_no, o.order_name, o.status, o.priority, o.due_date, o.order_type, o.created_at, o.followup_assigned_to, o.followup_due_at, c.name as customer_name, k.name as karigar_name, follower.name as follower_name, oc.name as order_category_name, oc.code as order_category_code')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->join('admin_users follower', 'follower.id = o.followup_assigned_to', 'left');
        $builder->join('order_categories oc', 'oc.id = o.order_category_id', 'left');

        if ($status !== '') {
            $builder->where('o.status', $status);
        }
        if ($search !== '') {
            $builder->groupStart()
                ->like('o.order_no', $search)
                ->orLike('o.order_name', $search)
                ->orLike('c.name', $search)
                ->orLike('k.name', $search)
                ->orLike('follower.name', $search)
                ->groupEnd();
        }

        $countBuilder = clone $builder;
        $total = $countBuilder->countAllResults();
        $rows = $builder->orderBy('o.id', 'DESC')->limit($limit, $offset)->get()->getResultArray();

        $rows = $this->appendLatestFollowup($rows);

        return $this->ok([
            'items' => $rows,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int) $total,
                'total_pages' => $limit > 0 ? (int) ceil($total / $limit) : 1,
            ],
        ]);
    }

    public function formOptions()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $db = db_connect();

        return $this->ok([
            'customers' => $db->table('customers')
                ->select('id, customer_code, name, phone, email')
                ->where('is_active', 1)
                ->orderBy('name', 'ASC')->get()->getResultArray(),
            'sales_people' => $db->table('customer_users')
                ->select('id, customer_id, name, mobile')
                ->where('role', 'sales_person')->where('is_active', 1)
                ->orderBy('name', 'ASC')->get()->getResultArray(),
            'designs' => $db->table('design_masters')
                ->select('id, design_code, name')
                ->where('is_active', 1)
                ->orderBy('name', 'ASC')->get()->getResultArray(),
            'gold_purities' => $db->table('gold_purities')
                ->select('id, purity_code, purity_percent, color_name')
                ->where('is_active', 1)
                ->orderBy('purity_percent', 'DESC')->get()->getResultArray(),
            'order_categories' => (new OrderCategoryService($db))->options(),
            'priorities' => $this->jewelleryConfig->orderPriorities,
            'statuses' => $this->jewelleryConfig->orderStatuses,
            'material_categories' => ['Gold', 'Diamond', 'Jadau', 'Silver'],
            'certificate_requirements' => ['', 'IGI', 'Kalasha', 'IGI / Kalasha', 'Other'],
        ]);
    }

    public function create()
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $orderName = trim((string) ($payload['order_name'] ?? ''));
        $orderType = trim((string) ($payload['order_type'] ?? ''));
        $designType = trim((string) ($payload['order_design_type'] ?? ''));
        $receivedDate = trim((string) ($payload['order_received_date'] ?? ''));
        $materialCategory = trim((string) ($payload['material_category'] ?? ''));
        $priority = trim((string) ($payload['priority'] ?? ''));
        $status = trim((string) ($payload['status'] ?? ''));
        $goldRateStatus = trim((string) ($payload['gold_rate_block_status'] ?? '')) === 'Fixed' ? 'Fixed' : 'Not Fixed';
        $isRepair = strcasecmp($orderType, 'Repair') === 0;

        if ($orderName === '' || mb_strlen($orderName) > 180) {
            return $this->fail('Order name is required and must not exceed 180 characters.', 422);
        }
        if (! in_array($orderType, ['Sales', 'Manufacturing', 'Repair'], true)) {
            return $this->fail('Select a valid order type.', 422);
        }
        if (! in_array($designType, ['Fresh', 'Repeat'], true)) {
            return $this->fail('Select Fresh or Repeat order design type.', 422);
        }
        if ($receivedDate === '' || strtotime($receivedDate) === false) {
            return $this->fail('Order received date is required.', 422);
        }
        if (! in_array($materialCategory, ['Gold', 'Diamond', 'Jadau', 'Silver'], true)) {
            return $this->fail('Select a valid material category.', 422);
        }
        if (! in_array($priority, $this->jewelleryConfig->orderPriorities, true)) {
            return $this->fail('Select a valid priority.', 422);
        }
        if (! in_array($status, $this->jewelleryConfig->orderStatuses, true)) {
            return $this->fail('Select a valid order status.', 422);
        }

        $dueDate = $this->validOptionalDate($payload['due_date'] ?? null);
        if ($dueDate === false) {
            return $this->fail('Enter a valid client delivery date.', 422);
        }
        $repairReceivedAt = $this->validOptionalDate($payload['repair_received_at'] ?? null);
        if ($repairReceivedAt === false) {
            return $this->fail('Enter a valid repair received date.', 422);
        }

        $goldRate = $this->optionalDecimal($payload['gold_rate_per_gm'] ?? null);
        $approximatePrice = $this->optionalDecimal($payload['approximate_price'] ?? null);
        $advanceAmount = max(0, (float) ($payload['advance_amount'] ?? 0));
        if ($goldRateStatus === 'Fixed' && ($goldRate === null || $goldRate <= 0)) {
            return $this->fail('Enter the fixed gold rate per gram.', 422);
        }
        if ($approximatePrice !== null && $approximatePrice < 0) {
            return $this->fail('Approximate price cannot be negative.', 422);
        }
        if ($approximatePrice !== null && $advanceAmount > $approximatePrice) {
            return $this->fail('Advance amount cannot exceed the approximate price.', 422);
        }

        $repairOrnament = trim((string) ($payload['repair_ornament_details'] ?? ''));
        $repairWork = trim((string) ($payload['repair_work_details'] ?? ''));
        $repairWeight = (float) ($payload['repair_receive_weight_gm'] ?? 0);
        if ($isRepair && ($repairOrnament === '' || $repairWork === '' || $repairWeight <= 0 || $repairReceivedAt === null)) {
            return $this->fail('Repair ornament, work, receive weight and received date are required.', 422);
        }

        $db = db_connect();
        $customerId = max(0, (int) ($payload['customer_id'] ?? 0));
        $salesPersonId = max(0, (int) ($payload['sales_person_user_id'] ?? 0));
        $customer = $customerId > 0
            ? $db->table('customers')->select('id, phone')->where('id', $customerId)->where('is_active', 1)->get()->getRowArray()
            : null;
        if ($customerId > 0 && ! $customer) {
            return $this->fail('Selected customer was not found.', 422);
        }
        if ($salesPersonId > 0) {
            $salesPerson = $db->table('customer_users')->select('id')->where([
                'id' => $salesPersonId,
                'customer_id' => $customerId,
                'role' => 'sales_person',
                'is_active' => 1,
            ])->get()->getRowArray();
            if (! $salesPerson) {
                return $this->fail('Selected sales person does not belong to the selected customer.', 422);
            }
        }

        $itemsResult = $this->parseOrderItems($payload['items'] ?? [], $designType);
        if ($itemsResult['error'] !== null) {
            return $this->fail($itemsResult['error'], 422);
        }
        $items = $itemsResult['items'];
        if ($items === [] && ! $isRepair) {
            return $this->fail('At least one order item is required.', 422);
        }
        if ($items === []) {
            $items[] = [
                'design_id' => null,
                'gold_purity_id' => null,
                'item_description' => $repairWork,
                'size_label' => null,
                'qty' => 1,
                'gold_required_gm' => 0.0,
                'diamond_required_cts' => 0.0,
            ];
        }

        $contactNumber = trim((string) ($payload['contact_number'] ?? ''));
        if ($contactNumber === '' && $customer) {
            $contactNumber = trim((string) ($customer['phone'] ?? ''));
        }
        $whatsappNumber = preg_replace('/\D+/', '', (string) ($payload['whatsapp_notification_number'] ?? '')) ?: '';
        $notifyWhatsapp = ! empty($payload['whatsapp_notify_order_created']);
        $savedFiles = [];
        $orderId = 0;

        try {
            $db->transException(true)->transStart();
            $category = (new OrderCategoryService($db))->resolve(
                (int) ($payload['order_category_id'] ?? 0),
                (string) ($payload['new_order_category'] ?? '')
            );
            $orderNo = (new OrderNumberService($db))->generate(
                $customerId,
                (string) $category['code'],
                $salesPersonId,
                trim((string) ($payload['order_from'] ?? ''))
            );

            $orderId = (int) (new OrderModel())->insert([
                'order_no' => $orderNo,
                'order_name' => $orderName,
                'order_category_id' => (int) $category['id'],
                'order_type' => $isRepair ? 'Repair' : $orderType,
                'order_design_type' => $designType,
                'order_from' => trim((string) ($payload['order_from'] ?? '')) ?: null,
                'order_received_date' => date('Y-m-d', strtotime($receivedDate)),
                'contact_number' => $contactNumber !== '' ? $contactNumber : null,
                'material_category' => $materialCategory,
                'certificate_requirement' => trim((string) ($payload['certificate_requirement'] ?? '')) ?: null,
                'additional_details' => trim((string) ($payload['additional_details'] ?? '')) ?: null,
                'gold_rate_block_status' => $goldRateStatus,
                'gold_rate_per_gm' => $goldRateStatus === 'Fixed' ? $goldRate : null,
                'approximate_price' => $approximatePrice,
                'advance_amount' => round($advanceAmount, 2),
                'customer_id' => $customerId > 0 ? $customerId : null,
                'sales_person_user_id' => $salesPersonId > 0 ? $salesPersonId : null,
                'status' => $status,
                'priority' => $priority,
                'due_date' => $dueDate,
                'order_notes' => trim((string) ($payload['order_notes'] ?? '')),
                'whatsapp_notification_number' => $whatsappNumber !== '' ? $whatsappNumber : null,
                'whatsapp_notify_order_created' => $notifyWhatsapp ? 1 : 0,
                'expected_diamond_spec' => trim((string) ($payload['expected_diamond_spec'] ?? '')) ?: null,
                'expected_stone_spec' => trim((string) ($payload['expected_stone_spec'] ?? '')) ?: null,
                'priority_level' => max(0, min(10, (int) ($payload['priority_level'] ?? 0))),
                'repair_ornament_details' => $isRepair ? $repairOrnament : null,
                'repair_work_details' => $isRepair ? $repairWork : null,
                'repair_receive_weight_gm' => $isRepair ? round($repairWeight, 3) : null,
                'repair_received_at' => $isRepair ? $repairReceivedAt : null,
                'created_by' => (int) ($this->mobileAdmin['id'] ?? 0),
            ], true);
            if ($orderId <= 0) {
                throw new \RuntimeException('The order header could not be saved.');
            }

            $itemModel = new OrderItemModel();
            $jobCardModel = new JobCardModel();
            foreach ($items as $index => $item) {
                $itemId = (int) $itemModel->insert([
                    'order_id' => $orderId,
                    'design_id' => $item['design_id'],
                    'gold_purity_id' => $item['gold_purity_id'],
                    'item_description' => $item['item_description'],
                    'size_label' => $item['size_label'],
                    'qty' => $item['qty'],
                    'gold_required_gm' => $item['gold_required_gm'],
                    'diamond_required_cts' => $item['diamond_required_cts'],
                    'item_status' => $status,
                ], true);
                if ($itemId <= 0) {
                    throw new \RuntimeException('An order item could not be saved.');
                }
                $jobCardId = (int) $jobCardModel->insert([
                    'job_card_no' => 'JC' . date('ymdHis') . random_int(10, 99) . $index,
                    'order_id' => $orderId,
                    'order_item_id' => $itemId,
                    'status' => 'Pending',
                    'priority' => $priority,
                    'due_date' => $dueDate,
                    'qc_status' => 'Pending',
                    'created_by' => (int) ($this->mobileAdmin['id'] ?? 0),
                ], true);
                if ($jobCardId <= 0) {
                    throw new \RuntimeException('The order job card could not be saved.');
                }
            }

            (new OrderStatusHistoryModel())->insert([
                'order_id' => $orderId,
                'from_status' => null,
                'to_status' => $status,
                'remarks' => 'Order created from PWA.',
                'changed_by' => (int) ($this->mobileAdmin['id'] ?? 0),
            ]);

            $savedFiles = $this->saveOrderAttachments($orderId, $payload['attachments'] ?? []);
            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            foreach ($savedFiles as $savedFile) {
                if (is_file($savedFile)) {
                    @unlink($savedFile);
                }
            }
            log_message('error', 'Mobile order creation failed: {message}', ['message' => $e->getMessage()]);
            return $this->fail('Could not create the order: ' . $e->getMessage(), 500);
        }

        if ($notifyWhatsapp) {
            try {
                (new OrderWhatsAppService())->notifyOrderCreated($orderId);
            } catch (Throwable $e) {
                log_message('error', 'Mobile order WhatsApp notification failed: {message}', ['message' => $e->getMessage()]);
            }
        }
        try {
            $this->mobileNotificationEvents->notifyOrderCreated($orderId, 'mobile');
        } catch (Throwable $e) {
            log_message('error', 'Mobile order push notification failed: {message}', ['message' => $e->getMessage()]);
        }

        return $this->ok(['order_id' => $orderId, 'order_no' => $orderNo], 'Order created.', 201);
    }

    public function show(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $db = db_connect();
        $order = $db->table('orders o')
            ->select('o.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email, k.name as karigar_name, k.phone as karigar_phone, follower.name as follower_name, oc.name as order_category_name, oc.code as order_category_code')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->join('admin_users follower', 'follower.id = o.followup_assigned_to', 'left')
            ->join('order_categories oc', 'oc.id = o.order_category_id', 'left')
            ->where('o.id', $id)
            ->get()
            ->getRowArray();

        if (! $order) {
            return $this->fail('Order not found.', 404);
        }

        $items = $db->table('order_items oi')
            ->select('oi.*, dm.design_code, dm.name as design_name, gp.purity_code, gp.color_name')
            ->join('design_masters dm', 'dm.id = oi.design_id', 'left')
            ->join('gold_purities gp', 'gp.id = oi.gold_purity_id', 'left')
            ->where('oi.order_id', $id)
            ->orderBy('oi.id', 'ASC')
            ->get()
            ->getResultArray();

        $followups = $this->followupRows($id);
        $documents = $this->documentLinks($order);
        $media = $this->orderMedia($id);
        $mobileUserId = (int) ($this->mobileAdmin['id'] ?? 0);

        return $this->ok([
            'order' => array_merge($order, $documents, $media),
            'items' => $items,
            'followups' => $followups,
            'diamond_requirements' => $this->diamondRequirementService->forOrder($id),
            'can_raise_diamond_requirement' => $this->diamondRequirementService->canRaise(
                $id,
                $mobileUserId,
                $this->rbacService->userCan($mobileUserId, 'diamond.inventory.manage')
            ),
            'allowed_stages' => $this->jewelleryConfig->orderStatuses,
        ]);
    }

    public function followups(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $exists = db_connect()->table('orders')->where('id', $id)->countAllResults();
        if ((int) $exists === 0) {
            return $this->fail('Order not found.', 404);
        }

        return $this->ok($this->followupRows($id));
    }

    public function addFollowup(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $payload = $this->payload();
        $stage = trim((string) ($payload['stage'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $nextFollowupDate = trim((string) ($payload['next_followup_date'] ?? ''));

        if ($stage === '' || ! in_array($stage, $this->jewelleryConfig->orderStatuses, true)) {
            return $this->fail('Invalid stage.', 422);
        }
        if ($description === '') {
            return $this->fail('description is required.', 422);
        }

        $db = db_connect();
        $order = $db->table('orders')->where('id', $id)->get()->getRowArray();
        if (! $order) {
            return $this->fail('Order not found.', 404);
        }

        $currentStatus = (string) ($order['status'] ?? '');
        if (in_array($currentStatus, ['Cancelled', 'Completed', 'Complete', 'Ready', 'Packed', 'Delivered', 'Dispatched'], true)) {
            return $this->fail('Followup not allowed for this order status.', 422);
        }
        $assignedFollowerId = (int) ($order['followup_assigned_to'] ?? 0);
        $currentUserId = (int) ($this->mobileAdmin['id'] ?? 0);
        if ($assignedFollowerId <= 0) {
            return $this->fail('This order does not have an assigned follower yet.', 422);
        }
        if ($assignedFollowerId !== $currentUserId) {
            return $this->fail('Only the assigned order follower can submit this follow-up.', 403);
        }

        $terminalStage = in_array($stage, ['Ready', 'Packed', 'Dispatched', 'Completed', 'Cancelled'], true);
        if (! $terminalStage && $nextFollowupDate === '') {
            return $this->fail('next_followup_date is required while the order remains open.', 422);
        }

        $imageName = null;
        $imagePath = null;
        $imageBase64 = trim((string) ($payload['image_base64'] ?? ''));
        if ($imageBase64 !== '') {
            $saved = $this->saveBase64Image($imageBase64, FCPATH . 'uploads/orders/followups');
            if (! $saved['ok']) {
                return $this->fail((string) $saved['message'], 422);
            }
            $imageName = $saved['name'];
            $imagePath = $saved['path'];
        }

        $nextFollowupDateTime = null;
        if ($nextFollowupDate !== '') {
            $ts = strtotime($nextFollowupDate);
            if ($ts === false || $ts <= time()) {
                return $this->fail('next_followup_date must be a future date and time.', 422);
            }
            $nextFollowupDateTime = date('Y-m-d H:i:s', $ts);
        }
        if ($terminalStage) {
            $nextFollowupDateTime = null;
        }

        $push = ['queued' => false, 'message' => 'No followup notification queued.'];

        try {
            $db->transException(true)->transStart();

            $db->table('order_followups')->insert([
                'order_id' => $id,
                'stage' => $stage,
                'description' => $description,
                'next_followup_date' => $nextFollowupDateTime,
                'followup_taken_by' => (int) ($this->mobileAdmin['id'] ?? 0),
                'followup_taken_on' => date('Y-m-d H:i:s'),
                'image_name' => $imageName,
                'image_path' => $imagePath,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $followupId = (int) $db->insertID();

            if ($currentStatus !== $stage) {
                $db->table('orders')->where('id', $id)->update([
                    'status' => $stage,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $db->table('order_items')->where('order_id', $id)->update([
                    'item_status' => $stage,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $db->table('order_status_history')->insert([
                    'order_id' => $id,
                    'from_status' => $currentStatus !== '' ? $currentStatus : null,
                    'to_status' => $stage,
                    'remarks' => 'Updated from mobile followup: ' . $description,
                    'changed_by' => (int) ($this->mobileAdmin['id'] ?? 0),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->staffPerformanceService->completeOrderFollowup(
                $id,
                $followupId,
                (int) ($this->mobileAdmin['id'] ?? 0),
                $nextFollowupDateTime
            );

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail('Could not save followup: ' . $e->getMessage(), 500);
        }

        try {
            $push = $this->mobileNotificationEvents->notifyFollowupAdded($id, $followupId);
        } catch (Throwable $e) {
            log_message('error', 'Mobile followup push notification failed: {message}', ['message' => $e->getMessage()]);
            $push = ['queued' => false, 'message' => 'Followup saved, but push notification failed.'];
        }

        return $this->ok([
            'order_id' => $id,
            'status' => $stage,
            'followups' => $this->followupRows($id),
            'notification' => $push,
        ], 'Followup saved and order status synced.');
    }

    /** @return array{items:list<array<string,mixed>>,error:?string} */
    private function parseOrderItems($rawItems, string $designType): array
    {
        if (! is_array($rawItems)) {
            return ['items' => [], 'error' => 'Invalid order items payload.'];
        }

        $db = db_connect();
        $items = [];
        foreach ($rawItems as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $designId = (int) ($raw['design_id'] ?? 0);
            $purityId = (int) ($raw['gold_purity_id'] ?? 0);
            $description = trim((string) ($raw['item_description'] ?? ''));
            $qty = (int) ($raw['qty'] ?? 0);
            if ($qty <= 0 || ($designId <= 0 && $description === '')) {
                continue;
            }
            if ($designType === 'Repeat') {
                if ($designId <= 0) {
                    return ['items' => [], 'error' => 'Every repeat-order item must have a unique design code selected.'];
                }
                if ($db->table('design_masters')->where('id', $designId)->where('is_active', 1)->countAllResults() === 0) {
                    return ['items' => [], 'error' => 'One or more repeat designs are not available.'];
                }
            } else {
                $designId = 0;
            }
            if ($purityId > 0 && $db->table('gold_purities')->where('id', $purityId)->where('is_active', 1)->countAllResults() === 0) {
                return ['items' => [], 'error' => 'One or more selected gold purities are not available.'];
            }

            $items[] = [
                'design_id' => $designId > 0 ? $designId : null,
                'gold_purity_id' => $purityId > 0 ? $purityId : null,
                'item_description' => $description,
                'size_label' => trim((string) ($raw['size_label'] ?? '')) ?: null,
                'qty' => $qty,
                'gold_required_gm' => round(max(0, (float) ($raw['gold_required_gm'] ?? 0)), 3),
                'diamond_required_cts' => round(max(0, (float) ($raw['diamond_required_cts'] ?? 0)), 3),
            ];
        }

        return ['items' => $items, 'error' => null];
    }

    /** @return string|null|false */
    private function validOptionalDate($value)
    {
        $date = trim((string) $value);
        if ($date === '') {
            return null;
        }
        $timestamp = strtotime($date);
        return $timestamp === false ? false : date('Y-m-d', $timestamp);
    }

    private function optionalDecimal($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        return round((float) $value, 2);
    }

    /** @return list<string> absolute paths written during the transaction */
    private function saveOrderAttachments(int $orderId, $attachments): array
    {
        if (! is_array($attachments) || $attachments === []) {
            return [];
        }

        $uploadDir = FCPATH . 'uploads/orders';
        if (! is_dir($uploadDir) && ! mkdir($uploadDir, 0775, true) && ! is_dir($uploadDir)) {
            throw new \RuntimeException('Unable to create the order upload directory.');
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'dwg', 'dxf'];
        $saved = [];
        $model = new OrderAttachmentModel();
        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }
            $base64 = trim((string) ($attachment['base64'] ?? ''));
            if ($base64 === '') {
                continue;
            }
            $extension = strtolower(trim((string) ($attachment['extension'] ?? '')));
            if (preg_match('/^data:([^;]+);base64,/', $base64, $matches)) {
                $mime = strtolower((string) ($matches[1] ?? ''));
                $extension = match ($mime) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'application/pdf' => 'pdf',
                    default => $extension,
                };
                $base64 = substr($base64, strpos($base64, ',') + 1);
            }
            if (! in_array($extension, $allowed, true)) {
                throw new \RuntimeException('Order attachment type is not allowed.');
            }
            $binary = base64_decode(str_replace(' ', '+', $base64), true);
            if ($binary === false || strlen($binary) > 10 * 1024 * 1024) {
                throw new \RuntimeException('Each order attachment must be valid and 10MB or less.');
            }

            $storedName = date('YmdHis') . '_' . bin2hex(random_bytes(5)) . '.' . $extension;
            $absolute = $uploadDir . DIRECTORY_SEPARATOR . $storedName;
            if (file_put_contents($absolute, $binary) === false) {
                throw new \RuntimeException('Could not save an order attachment.');
            }
            $saved[] = $absolute;
            $model->insert([
                'order_id' => $orderId,
                'order_item_id' => null,
                'file_type' => trim((string) ($attachment['file_type'] ?? 'reference')) ?: 'reference',
                'file_name' => trim((string) ($attachment['name'] ?? '')) ?: $storedName,
                'file_path' => 'uploads/orders/' . $storedName,
                'uploaded_by' => (int) ($this->mobileAdmin['id'] ?? 0),
            ]);
        }

        return $saved;
    }

    private function followupRows(int $orderId): array
    {
        $rows = db_connect()->table('order_followups ofu')
            ->select('ofu.*, au.name as followup_taken_by_name')
            ->join('admin_users au', 'au.id = ofu.followup_taken_by', 'left')
            ->where('ofu.order_id', $orderId)
            ->orderBy('ofu.id', 'DESC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $imagePath = (string) ($row['image_path'] ?? '');
            $row['image_url'] = $imagePath !== '' ? base_url($imagePath) : null;
        }
        unset($row);

        return $rows;
    }

    private function appendLatestFollowup(array $orders): array
    {
        if ($orders === []) {
            return $orders;
        }

        $orderIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $orders);
        $orderIds = array_values(array_filter($orderIds, static fn(int $id): bool => $id > 0));
        if ($orderIds === []) {
            return $orders;
        }

        $db = db_connect();
        $sub = $db->table('order_followups')
            ->select('MAX(id) as id')
            ->whereIn('order_id', $orderIds)
            ->groupBy('order_id')
            ->getCompiledSelect();

        $latestRows = $db->table('order_followups ofu')
            ->select('ofu.order_id, ofu.stage, ofu.next_followup_date, ofu.followup_taken_on, au.name as followup_taken_by_name')
            ->join('(' . $sub . ') latest', 'latest.id = ofu.id', 'inner', false)
            ->join('admin_users au', 'au.id = ofu.followup_taken_by', 'left')
            ->get()
            ->getResultArray();

        $map = [];
        foreach ($latestRows as $row) {
            $map[(int) ($row['order_id'] ?? 0)] = $row;
        }

        foreach ($orders as &$order) {
            $latest = $map[(int) ($order['id'] ?? 0)] ?? null;
            $order['last_followup_stage'] = (string) ($latest['stage'] ?? '-');
            $order['last_followup_on'] = (string) ($latest['followup_taken_on'] ?? '');
            $order['last_followup_by'] = (string) ($latest['followup_taken_by_name'] ?? '');
            $order['next_followup_date'] = (string) (($order['followup_due_at'] ?? '') ?: ($latest['next_followup_date'] ?? ''));
        }
        unset($order);

        return $orders;
    }

    private function documentLinks(array $order): array
    {
        $status = (string) ($order['status'] ?? '');
        $eligible = in_array($status, ['Ready', 'Packed', 'Dispatched', 'Completed'], true);
        $orderId = (int) ($order['id'] ?? 0);
        $hasDeliveryChallanTable = db_connect()->tableExists('delivery_challans');

        return [
            'packing_list_url' => $eligible && $orderId > 0
                ? base_url('api/documents/orders/' . $orderId . '/packing-list?download=1')
                : null,
            'delivery_challan_url' => $eligible && $orderId > 0 && $hasDeliveryChallanTable
                ? base_url('api/documents/orders/' . $orderId . '/delivery-challan?download=1')
                : null,
        ];
    }

    private function orderMedia(int $orderId): array
    {
        $empty = [
            'order_photo_url' => null,
            'finish_photo_url' => null,
            'primary_image_url' => null,
        ];

        $db = db_connect();
        if ($orderId <= 0 || ! $db->tableExists('order_attachments')) {
            return $empty;
        }

        $orderPhoto = $db->table('order_attachments')
            ->select('file_path')
            ->where('order_id', $orderId)
            ->where('LOWER(file_type)', 'photo')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $finishPhoto = $db->table('order_attachments')
            ->select('file_path')
            ->where('order_id', $orderId)
            ->where('LOWER(file_type)', 'finish_photo')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $orderPhotoPath = (string) ($orderPhoto['file_path'] ?? '');
        $finishPhotoPath = (string) ($finishPhoto['file_path'] ?? '');
        $orderPhotoUrl = $orderPhotoPath !== '' ? base_url($orderPhotoPath) : null;
        $finishPhotoUrl = $finishPhotoPath !== '' ? base_url($finishPhotoPath) : null;

        return [
            'order_photo_url' => $orderPhotoUrl,
            'finish_photo_url' => $finishPhotoUrl,
            'primary_image_url' => $finishPhotoUrl ?? $orderPhotoUrl,
        ];
    }

    private function saveBase64Image(string $input, string $uploadDir): array
    {
        $raw = $input;
        $extension = 'jpg';

        if (preg_match('/^data:image\/(\w+);base64,/', $input, $matches)) {
            $extension = strtolower((string) ($matches[1] ?? 'jpg'));
            $raw = substr($input, strpos($input, ',') + 1);
        }

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        $binary = base64_decode(str_replace(' ', '+', $raw), true);
        if ($binary === false) {
            return ['ok' => false, 'message' => 'Invalid image_base64 payload.'];
        }

        if (! is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $name = 'mob_fu_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $path = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
        if (file_put_contents($path, $binary) === false) {
            return ['ok' => false, 'message' => 'Could not save image file.'];
        }

        return [
            'ok' => true,
            'name' => $name,
            'path' => 'uploads/orders/followups/' . $name,
        ];
    }
}
