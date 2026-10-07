<?php

namespace App\Controllers\Api\Mobile;

use App\Services\DiamondBagService;
use App\Services\RbacService;
use Throwable;

class DiamondBagsController extends MobileBaseController
{
    private DiamondBagService $bags;
    private RbacService $rbac;

    public function __construct()
    {
        $this->bags = new DiamondBagService();
        $this->rbac = new RbacService();
    }

    public function index()
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }

        $db = db_connect();
        if (! $db->tableExists('diamond_bags')) {
            return $this->ok(['items' => []]);
        }

        $items = $db->table('diamond_bags b')
            ->select('b.*, dr.requirement_no, o.order_no, COUNT(DISTINCT bi.id) AS item_count, COUNT(DISTINCT il.allocation_order_id) AS order_count, COUNT(DISTINCT il.id) AS issue_line_count', false)
            ->join('diamond_bag_items bi', 'bi.bag_id = b.id', 'left')
            ->join('issue_lines il', 'il.bag_id = b.id', 'left')
            ->join('diamond_requirements dr', 'dr.id = b.requirement_id', 'left')
            ->join('orders o', 'o.id = b.order_id', 'left')
            ->groupBy('b.id')
            ->orderBy('b.id', 'DESC')
            ->get()->getResultArray();

        foreach ($items as &$item) {
            $item['status'] = $this->bagStatus($item);
            $item['audit_image_url'] = ! empty($item['audit_image_path'])
                ? base_url(ltrim((string) $item['audit_image_path'], '/'))
                : null;
        }
        unset($item);

        return $this->ok(['items' => $items]);
    }

    public function createForm()
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }

        $userId = (int) $this->mobileAdmin['id'];
        $canManage = $this->rbac->userCan($userId, 'diamond.inventory.manage');
        $orderId = (int) ($this->request->getGet('order_id') ?? 0);
        $workRequestId = (int) ($this->request->getGet('work_request_id') ?? 0);
        if ($orderId > 0 && ! $this->bags->canCreateForOrder($orderId, $userId, $canManage, $workRequestId)) {
            return $this->fail('You cannot create a diamond bag for this order.', 403);
        }

        $orders = $this->bags->availableOrders($userId, $canManage);
        $selectedOrder = null;
        foreach ($orders as $order) {
            if ((int) ($order['id'] ?? 0) === $orderId) {
                $selectedOrder = $order;
                break;
            }
        }

        return $this->ok([
            'lookups' => $this->bags->lookups(),
            'orders' => $orders,
            'selected_order' => $selectedOrder,
        ]);
    }

    public function store()
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }

        $payload = $this->payload();
        $rows = $payload['items'] ?? $payload['rows'] ?? [];
        if (! is_array($rows)) {
            return $this->fail('items must be an array of diamond size rows.', 422);
        }
        $userId = (int) $this->mobileAdmin['id'];
        $orderId = (int) ($payload['order_id'] ?? 0);
        $workRequestId = (int) ($payload['work_request_id'] ?? 0);
        $canManage = $this->rbac->userCan($userId, 'diamond.inventory.manage');
        if (! $this->bags->canCreateForOrder($orderId, $userId, $canManage, $workRequestId)) {
            return $this->fail('You cannot create a diamond bag for this order.', 403);
        }

        $imageName = null;
        $imagePath = null;
        $imageBase64 = trim((string) ($payload['image_base64'] ?? ''));
        try {
            if ($imageBase64 !== '') {
                $image = $this->saveBase64Image($imageBase64);
                $imageName = $image['name'];
                $imagePath = $image['path'];
            }
            $bag = $this->bags->create(
                $orderId,
                $userId,
                (int) ($payload['location_id'] ?? 0),
                (string) ($payload['prepared_date'] ?? date('Y-m-d')),
                array_values($rows),
                (string) ($payload['notes'] ?? ''),
                $imageName,
                $imagePath,
                $workRequestId
            );
            return $this->ok(['bag' => $bag], 'Diamond bag created and linked to the order.', 201);
        } catch (Throwable $e) {
            if ($imagePath !== null && is_file(FCPATH . ltrim($imagePath, '/'))) {
                @unlink(FCPATH . ltrim($imagePath, '/'));
            }
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function storeSize()
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }
        $payload = $this->payload();
        $shapeId = (int) ($payload['shape_id'] ?? 0);
        $groupId = (int) ($payload['chalni_group_id'] ?? 0);
        $label = trim((string) ($payload['size_label'] ?? ''));
        if ($shapeId <= 0 || $groupId <= 0 || $label === '' || mb_strlen($label) > 80) {
            return $this->fail('Shape, chalni group and exact size are required.', 422);
        }
        $db = db_connect();
        if ($db->table('diamond_shape_masters')->where('id', $shapeId)->where('is_active', 1)->countAllResults() === 0
            || $db->table('diamond_chalni_groups')->where('id', $groupId)->where('is_active', 1)->countAllResults() === 0) {
            return $this->fail('Selected shape or chalni group is invalid.', 422);
        }
        $codeBase = trim((string) preg_replace('/[^A-Z0-9]+/', '-', strtoupper($label)), '-') ?: 'SIZE';
        $code = $codeBase;
        $suffix = 1;
        while ($db->table('diamond_size_masters')->where('shape_id', $shapeId)->where('size_code', $code)->countAllResults() > 0) {
            $code = $codeBase . '-' . $suffix++;
        }
        try {
            $db->transException(true)->transStart();
            $db->table('diamond_size_masters')->insert([
                'shape_id' => $shapeId, 'size_code' => $code, 'size_label' => $label,
                'sort_order' => 999, 'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $sizeId = (int) $db->insertID();
            $db->table('diamond_chalni_group_sizes')->insert([
                'group_id' => $groupId, 'size_id' => $sizeId, 'created_at' => date('Y-m-d H:i:s'),
            ]);
            $db->transComplete();
            return $this->ok(['size' => [
                'id' => $sizeId, 'shape_id' => $shapeId, 'group_id' => $groupId,
                'size_code' => $code, 'size_label' => $label,
            ]], 'Diamond size created.', 201);
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail($e->getMessage(), 422);
        }
    }

    public function show(int $id)
    {
        if ($response = $this->requireMobileAuth()) {
            return $response;
        }

        $db = db_connect();
        $bag = $db->table('diamond_bags b')
            ->select('b.*, dr.requirement_no, dr.status AS requirement_status, o.order_no, creator.name AS prepared_by_name')
            ->join('diamond_requirements dr', 'dr.id = b.requirement_id', 'left')
            ->join('orders o', 'o.id = b.order_id', 'left')
            ->join('admin_users creator', 'creator.id = b.created_by', 'left')
            ->where('b.id', $id)->get()->getRowArray();
        if (! is_array($bag)) {
            return $this->fail('Diamond bag not found.', 404);
        }

        $items = $db->table('diamond_bag_items bi')
            ->select('bi.*, i.diamond_type, i.color, i.clarity, i.cut, sm.name AS shape_name, sz.size_code, sz.size_label, cg.name AS chalni_group_name, cg.range_label AS chalni_group_range')
            ->join('items i', 'i.id = bi.inventory_item_id', 'left')
            ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
            ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left')
            ->join('diamond_chalni_groups cg', 'cg.id = bi.chalni_group_id', 'left')
            ->where('bi.bag_id', $id)
            ->orderBy('bi.id', 'ASC')->get()->getResultArray();

        $movements = $db->table('diamond_bag_movements bm')
            ->select('bm.*, ih.voucher_no, o.order_no, k.name AS karigar_name, i.diamond_type, sm.name AS shape_name, sz.size_label, cg.name AS chalni_group_name')
            ->join('issue_lines il', 'il.id = bm.issue_line_id', 'left')
            ->join('issue_headers ih', 'ih.id = il.issue_id', 'left')
            ->join('diamond_bag_items bi', 'bi.id = bm.bag_item_id', 'left')
            ->join('items i', 'i.id = bi.inventory_item_id', 'left')
            ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
            ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left')
            ->join('diamond_chalni_groups cg', 'cg.id = bi.chalni_group_id', 'left')
            ->join('orders o', 'o.id = bm.order_id', 'left')
            ->join('karigars k', 'k.id = bm.karigar_id', 'left')
            ->where('bm.bag_id', $id)
            ->orderBy('bm.id', 'DESC')->get()->getResultArray();

        $bag['issue_line_count'] = $db->table('issue_lines')->where('bag_id', $id)->countAllResults();
        $bag['status'] = $this->bagStatus($bag);
        $bag['audit_image_url'] = ! empty($bag['audit_image_path'])
            ? base_url(ltrim((string) $bag['audit_image_path'], '/'))
            : null;

        return $this->ok([
            'bag' => $bag,
            'items' => $items,
            'movements' => $movements,
        ]);
    }

    /** @param array<string,mixed> $bag */
    private function bagStatus(array $bag): string
    {
        if ((float) ($bag['cts_balance'] ?? 0) <= .0005) {
            return 'consumed';
        }
        if ((int) ($bag['issue_line_count'] ?? 0) > 0) {
            return 'partly_issued';
        }
        return 'ready';
    }

    /** @return array{name:string,path:string} */
    private function saveBase64Image(string $input): array
    {
        $raw = $input;
        $extension = 'jpg';
        if (preg_match('/^data:image\/(\w+);base64,/', $input, $matches) === 1) {
            $extension = strtolower((string) ($matches[1] ?? 'jpg'));
            $raw = substr($input, strpos($input, ',') + 1);
        }
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \RuntimeException('Bag photo must be JPG, PNG or WebP.');
        }
        $binary = base64_decode(str_replace(' ', '+', $raw), true);
        if ($binary === false || strlen($binary) > 4 * 1024 * 1024) {
            throw new \RuntimeException('Invalid bag photo or file is larger than 4 MB.');
        }
        $directory = FCPATH . 'uploads/diamond-bags';
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Could not create the bag photo directory.');
        }
        $name = 'mobile_bag_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        if (file_put_contents($directory . DIRECTORY_SEPARATOR . $name, $binary) === false) {
            throw new \RuntimeException('Could not save the bag photo.');
        }
        return ['name' => $name, 'path' => 'uploads/diamond-bags/' . $name];
    }
}
