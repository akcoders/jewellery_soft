<?php

namespace App\Controllers\Api\Mobile;

use Config\Jewellery;
use Throwable;

class OrdersController extends MobileBaseController
{
    private Jewellery $jewelleryConfig;

    public function __construct()
    {
        $this->jewelleryConfig = config(Jewellery::class);
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
            ->select('o.id, o.order_no, o.status, o.priority, o.due_date, o.order_type, o.created_at, c.name as customer_name, k.name as karigar_name')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left');

        if ($status !== '') {
            $builder->where('o.status', $status);
        }
        if ($search !== '') {
            $builder->groupStart()
                ->like('o.order_no', $search)
                ->orLike('c.name', $search)
                ->orLike('k.name', $search)
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

    public function show(int $id)
    {
        $authFail = $this->requireMobileAuth();
        if ($authFail) {
            return $authFail;
        }

        $db = db_connect();
        $order = $db->table('orders o')
            ->select('o.*, c.name as customer_name, c.phone as customer_phone, c.email as customer_email, k.name as karigar_name, k.phone as karigar_phone')
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
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

        return $this->ok([
            'order' => $order,
            'items' => $items,
            'followups' => $followups,
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
        if ($currentStatus === 'Cancelled' || $currentStatus === 'Completed') {
            return $this->fail('Followup not allowed for this order status.', 422);
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
            if ($ts === false) {
                return $this->fail('Invalid next_followup_date format.', 422);
            }
            $nextFollowupDateTime = date('Y-m-d H:i:s', $ts);
        }

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

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            return $this->fail('Could not save followup: ' . $e->getMessage(), 500);
        }

        return $this->ok([
            'order_id' => $id,
            'status' => $stage,
            'followups' => $this->followupRows($id),
        ], 'Followup saved and order status synced.');
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
            $order['next_followup_date'] = (string) ($latest['next_followup_date'] ?? '');
        }
        unset($order);

        return $orders;
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
