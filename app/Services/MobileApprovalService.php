<?php

namespace App\Services;

use App\Models\CustomerAddressModel;
use App\Models\CustomerModel;
use App\Models\GoldInventoryIssueHeaderModel;
use App\Models\GoldInventoryIssueLineModel;
use App\Models\IssueHeaderModel;
use App\Models\IssueLineModel;
use App\Models\KarigarModel;
use App\Models\MobileApprovalRequestModel;
use App\Models\StoneInventoryIssueHeaderModel;
use App\Models\StoneInventoryIssueLineModel;
use App\Services\DiamondInventory\StockService as DiamondStockService;
use App\Services\GoldInventory\StockService as GoldStockService;
use App\Services\StoneInventory\StockService as StoneStockService;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

class MobileApprovalService
{
    public const TYPES = ['customer_create', 'karigar_create', 'issuement', 'followup', 'delivery_challan'];

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /** @param array<string,mixed> $payload */
    public function submit(
        string $type,
        array $payload,
        int $requestedBy,
        string $summary,
        ?string $attachmentName = null,
        ?string $attachmentPath = null,
        ?string $subjectTable = null,
        ?int $subjectId = null
    ): array {
        if (! in_array($type, self::TYPES, true) || $requestedBy <= 0) {
            throw new RuntimeException('Invalid approval request.');
        }
        $subjectTable = trim((string) $subjectTable) ?: null;
        $subjectId = ($subjectId ?? 0) > 0 ? (int) $subjectId : null;
        if ($subjectTable !== null && $subjectId !== null) {
            $pending = $this->db->table('mobile_approval_requests')
                ->where('request_type', $type)
                ->where('subject_table', $subjectTable)
                ->where('subject_id', $subjectId)
                ->where('status', 'pending')
                ->get()->getRowArray();
            if (is_array($pending)) {
                throw new RuntimeException('This request is already pending for approval.');
            }
        }
        $id = (int) (new MobileApprovalRequestModel())->insert([
            'request_type' => $type,
            'status' => 'pending',
            'summary' => mb_substr(trim($summary), 0, 255),
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'attachment_name' => $attachmentName,
            'attachment_path' => $attachmentPath,
            'requested_by' => $requestedBy,
            'subject_table' => $subjectTable,
            'subject_id' => $subjectId,
        ], true);
        if ($id <= 0) {
            throw new RuntimeException('Approval request could not be saved.');
        }
        $request = $this->find($id) ?? ['id' => $id, 'status' => 'pending'];
        try {
            (new MobileNotificationEventService())->notifyApprovalRequested($request);
        } catch (Throwable $e) {
            log_message('error', 'Approval request notification failed: {message}', ['message' => $e->getMessage()]);
        }
        return $request;
    }

    /** @return list<array<string,mixed>> */
    public function list(?int $requestedBy = null): array
    {
        $builder = $this->db->table('mobile_approval_requests ar')
            ->select('ar.*, requester.name AS requested_by_name, reviewer.name AS reviewed_by_name')
            ->join('admin_users requester', 'requester.id = ar.requested_by', 'left')
            ->join('admin_users reviewer', 'reviewer.id = ar.reviewed_by', 'left');
        if (($requestedBy ?? 0) > 0) {
            $builder->where('ar.requested_by', $requestedBy);
        }
        $rows = $builder->orderBy("CASE WHEN ar.status = 'pending' THEN 0 ELSE 1 END", 'ASC', false)
            ->orderBy('ar.id', 'DESC')->limit(500)->get()->getResultArray();
        return array_map(fn(array $row): array => $this->hydrate($row), $rows);
    }

    public function find(int $id): ?array
    {
        $row = $this->db->table('mobile_approval_requests ar')
            ->select('ar.*, requester.name AS requested_by_name, reviewer.name AS reviewed_by_name')
            ->join('admin_users requester', 'requester.id = ar.requested_by', 'left')
            ->join('admin_users reviewer', 'reviewer.id = ar.reviewed_by', 'left')
            ->where('ar.id', $id)->get()->getRowArray();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    public function reject(int $id, int $reviewerId, string $note = ''): array
    {
        $before = $this->find($id);
        $affected = $this->db->table('mobile_approval_requests')
            ->where('id', $id)->where('status', 'pending')->update([
                'status' => 'rejected', 'reviewed_by' => $reviewerId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_note' => trim($note) ?: null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        if (! $affected || $this->db->affectedRows() !== 1) {
            throw new RuntimeException('Request is no longer pending.');
        }
        $request = $this->find($id) ?? [];
        try {
            (new MobileNotificationEventService())->notifyApprovalOutcome($request ?: ($before ?? []), false);
        } catch (Throwable $e) {
            log_message('error', 'Approval rejection notification failed: {message}', ['message' => $e->getMessage()]);
        }
        return $request;
    }

    public function approve(int $id, int $reviewerId, string $note = ''): array
    {
        $this->db->transException(true)->transStart();
        try {
            $request = $this->db->query(
                'SELECT * FROM mobile_approval_requests WHERE id = ? FOR UPDATE',
                [$id]
            )->getRowArray();
            if (! is_array($request) || (string) ($request['status'] ?? '') !== 'pending') {
                throw new RuntimeException('Request is no longer pending.');
            }
            $payload = json_decode((string) ($request['payload_json'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new RuntimeException('Request data is invalid.');
            }
            $result = match ((string) $request['request_type']) {
                'customer_create' => $this->approveCustomer($payload),
                'karigar_create' => $this->approveKarigar($payload),
                'issuement' => $this->approveIssuement($payload, $request),
                'followup' => $this->approveFollowup($payload, $request),
                'delivery_challan' => $this->approveDeliveryChallan($payload, $request),
                default => throw new RuntimeException('Unsupported request type.'),
            };
            $this->db->table('mobile_approval_requests')->where('id', $id)->update([
                'status' => 'approved', 'reviewed_by' => $reviewerId,
                'reviewed_at' => date('Y-m-d H:i:s'), 'review_note' => trim($note) ?: null,
                'result_table' => $result['table'] ?? null,
                'result_id' => $result['id'] ?? null,
                'result_reference' => $result['reference'] ?? null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        if ((string) ($request['request_type'] ?? '') === 'issuement') {
            try {
                (new MobileNotificationEventService())->notifyInventoryTransactionCreated(
                    'issue',
                    (string) ($result['material_label'] ?? 'Material'),
                    (string) ($result['table'] ?? ''),
                    (int) ($result['id'] ?? 0),
                    'mobile',
                    ['voucher_no' => (string) ($result['reference'] ?? ''), 'approval_request_id' => $id]
                );
            } catch (Throwable $e) {
                log_message('error', 'Approved mobile issuement notification failed: {message}', ['message' => $e->getMessage()]);
            }
        }
        $approved = $this->find($id) ?? [];
        try {
            (new MobileNotificationEventService())->notifyApprovalOutcome($approved, true);
        } catch (Throwable $e) {
            log_message('error', 'Approval outcome notification failed: {message}', ['message' => $e->getMessage()]);
        }
        return $approved;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function createIssuementWithoutApproval(
        array $payload,
        int $requestedBy,
        ?string $attachmentName = null,
        ?string $attachmentPath = null
    ): array {
        $request = [
            'requested_by' => $requestedBy,
            'attachment_name' => $attachmentName,
            'attachment_path' => $attachmentPath,
        ];
        try {
            $this->db->transException(true)->transStart();
            $result = $this->approveIssuement($payload, $request);
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        try {
            (new MobileNotificationEventService())->notifyInventoryTransactionCreated(
                'issue',
                (string) ($result['material_label'] ?? 'Material'),
                (string) ($result['table'] ?? ''),
                (int) ($result['id'] ?? 0),
                'mobile',
                ['voucher_no' => (string) ($result['reference'] ?? '')]
            );
        } catch (Throwable $e) {
            log_message('error', 'Direct mobile issuement notification failed: {message}', ['message' => $e->getMessage()]);
        }
        return $result;
    }

    public function pendingForSubject(string $type, string $subjectTable, int $subjectId): ?array
    {
        if ($subjectId <= 0 || ! in_array($type, self::TYPES, true)) {
            return null;
        }
        $row = $this->db->table('mobile_approval_requests ar')
            ->select('ar.*, requester.name AS requested_by_name, reviewer.name AS reviewed_by_name')
            ->join('admin_users requester', 'requester.id = ar.requested_by', 'left')
            ->join('admin_users reviewer', 'reviewer.id = ar.reviewed_by', 'left')
            ->where('ar.request_type', $type)
            ->where('ar.subject_table', $subjectTable)
            ->where('ar.subject_id', $subjectId)
            ->where('ar.status', 'pending')
            ->orderBy('ar.id', 'DESC')->get()->getRowArray();
        return is_array($row) ? $this->hydrate($row) : null;
    }

    /** @param array<string,mixed> $payload */
    private function approveCustomer(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if (mb_strlen($name) < 2) {
            throw new RuntimeException('Customer name is required.');
        }
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email !== '' && (new CustomerModel())->where('email', $email)->first()) {
            throw new RuntimeException('A customer with this email already exists.');
        }
        do {
            $code = 'CU' . date('ymdHis') . random_int(10, 99);
        } while ((new CustomerModel())->where('customer_code', $code)->first());
        $customerId = (int) (new CustomerModel())->insert([
            'customer_code' => $code, 'name' => $name,
            'phone' => $this->nullable($payload['phone'] ?? null),
            'email' => $this->nullable($email),
            'gstin' => $this->nullable($payload['gstin'] ?? null),
            'terms_text' => $this->nullable($payload['notes'] ?? null),
            'is_active' => 1,
        ], true);
        if ($customerId <= 0) {
            throw new RuntimeException('Customer could not be created.');
        }
        $address = $payload['address'] ?? [];
        if (is_array($address) && trim(implode('', array_map('strval', $address))) !== '') {
            (new CustomerAddressModel())->insert([
                'customer_id' => $customerId, 'address_type' => 'Billing',
                'line1' => $this->nullable($address['line1'] ?? null),
                'line2' => $this->nullable($address['line2'] ?? null),
                'city' => $this->nullable($address['city'] ?? null),
                'state' => $this->nullable($address['state'] ?? null),
                'country' => $this->nullable($address['country'] ?? 'India'),
                'pincode' => $this->nullable($address['pincode'] ?? null),
                'is_default' => 1,
            ]);
        }
        return ['table' => 'customers', 'id' => $customerId, 'reference' => $code];
    }

    /** @param array<string,mixed> $payload */
    private function approveKarigar(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        if (mb_strlen($name) < 2) {
            throw new RuntimeException('Karigar name is required.');
        }
        $id = (int) (new KarigarModel())->insert([
            'name' => $name,
            'phone' => $this->nullable($payload['phone'] ?? null),
            'email' => $this->nullable($payload['email'] ?? null),
            'address' => $this->nullable($payload['address'] ?? null),
            'city' => $this->nullable($payload['city'] ?? null),
            'state' => $this->nullable($payload['state'] ?? null),
            'pincode' => $this->nullable($payload['pincode'] ?? null),
            'department' => $this->nullable($payload['department'] ?? null),
            'skills_text' => $this->nullable($payload['skills_text'] ?? null),
            'rate_per_gm' => (float) ($payload['rate_per_gm'] ?? 0),
            'wastage_percentage' => (float) ($payload['wastage_percentage'] ?? 0),
            'notes' => $this->nullable($payload['notes'] ?? null),
            'is_active' => 1,
        ], true);
        if ($id <= 0) {
            throw new RuntimeException('Karigar could not be created.');
        }
        return ['table' => 'karigars', 'id' => $id, 'reference' => $name];
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $request */
    private function approveIssuement(array $payload, array $request): array
    {
        $issueDate = (string) ($payload['issue_date'] ?? '');
        $karigarId = (int) ($payload['karigar_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);
        $karigar = (new KarigarModel())->where('id', $karigarId)->where('is_active', 1)->first();
        if (! $karigar) {
            throw new RuntimeException('Selected karigar is no longer active.');
        }
        $voucherNo = (new IssuementVoucherNumberService($this->db))
            ->resolveForCreate((string) ($payload['voucher_no'] ?? ''));
        $common = [
            'voucher_no' => $voucherNo, 'issue_date' => $issueDate,
            'karigar_id' => $karigarId, 'location_id' => $locationId,
            'issue_to' => (string) ($karigar['name'] ?? ''),
            'purpose' => (string) ($payload['purpose'] ?? ''),
            'notes' => $this->nullable($payload['notes'] ?? null),
            'attachment_name' => $request['attachment_name'] ?? null,
            'attachment_path' => $request['attachment_path'] ?? null,
            'created_by' => (int) ($request['requested_by'] ?? 0),
        ];
        $created = [];
        $gold = is_array($payload['gold_lines'] ?? null) ? $payload['gold_lines'] : [];
        if ($gold !== []) {
            $issueId = (int) (new GoldInventoryIssueHeaderModel())->insert($common, true);
            $lineModel = new GoldInventoryIssueLineModel();
            foreach ($gold as $line) {
                $lineModel->insert([
                    'issue_id' => $issueId, 'item_id' => $line['item_id'],
                    'weight_gm' => $line['weight_gm'], 'fine_weight_gm' => $line['fine_weight_gm'],
                    'rate_per_gm' => $line['rate_per_gm'], 'line_value' => $line['line_value'],
                ]);
            }
            (new GoldStockService($this->db))->applyIssue($issueId, [
                'txn_date' => $issueDate, 'karigar_id' => $karigarId,
                'location_id' => $locationId, 'created_by' => (int) $request['requested_by'],
                'notes' => 'PWA issuement - Gold',
            ]);
            (new KarigarMaterialAccountingService($this->db))->postInventoryHeader('gold', 'issue', $issueId);
            $created['gold_issue_id'] = $issueId;
        }
        $diamond = is_array($payload['diamond_lines'] ?? null) ? $payload['diamond_lines'] : [];
        if ($diamond !== []) {
            $issueId = (int) (new IssueHeaderModel())->insert($common, true);
            $lineModel = new IssueLineModel();
            foreach ($diamond as $line) {
                $lineModel->insert([
                    'issue_id' => $issueId, 'item_id' => $line['item_id'],
                    'bag_id' => $line['bag_id'], 'bag_item_id' => $line['bag_item_id'],
                    'allocation_order_id' => $line['allocation_order_id'],
                    'pcs' => $line['pcs'], 'carat' => $line['carat'],
                    'rate_per_carat' => $line['rate_per_carat'], 'line_value' => $line['line_value'],
                ]);
            }
            (new DiamondStockService($this->db))->applyIssue($issueId);
            (new DiamondBagTraceService($this->db))->applyIssue($issueId);
            (new KarigarMaterialAccountingService($this->db))->postInventoryHeader('diamond', 'issue', $issueId);
            $created['diamond_issue_id'] = $issueId;
        }
        $stone = is_array($payload['stone_lines'] ?? null) ? $payload['stone_lines'] : [];
        if ($stone !== []) {
            $issueId = (int) (new StoneInventoryIssueHeaderModel())->insert($common, true);
            $lineModel = new StoneInventoryIssueLineModel();
            foreach ($stone as $line) {
                $lineModel->insert([
                    'issue_id' => $issueId, 'item_id' => $line['item_id'],
                    'pcs' => $line['pcs'], 'qty' => $line['qty'],
                    'rate' => $line['rate'], 'line_value' => $line['line_value'],
                ]);
            }
            (new StoneStockService($this->db))->applyIssue($issueId);
            (new KarigarMaterialAccountingService($this->db))->postInventoryHeader('stone', 'issue', $issueId);
            $created['stone_issue_id'] = $issueId;
        }
        if ($created === []) {
            throw new RuntimeException('Issuement contains no material lines.');
        }
        $workRequestId = (int) ($payload['work_request_id'] ?? 0);
        if ($workRequestId > 0) {
            $updated = $this->db->table('order_work_requests')->where('id', $workRequestId)
                ->where('status', 'approved')->update([
                    'status' => 'completed', 'completed_at' => date('Y-m-d H:i:s'),
                    'voucher_no' => $voucherNo, 'updated_at' => date('Y-m-d H:i:s'),
                ]);
            if (! $updated || $this->db->affectedRows() !== 1) {
                throw new RuntimeException('Linked gold request is no longer available.');
            }
            (new WorkflowTaskService())->complete('order_gold_request', $workRequestId, (int) $request['requested_by']);
        }
        $firstKey = (string) array_key_first($created);
        $table = match ($firstKey) {
            'gold_issue_id' => 'gold_inventory_issue_headers',
            'diamond_issue_id' => 'issue_headers',
            default => 'stone_inventory_issue_headers',
        };
        $materialLabel = implode(' + ', array_map(
            static fn(string $key): string => ucfirst(str_replace('_issue_id', '', $key)),
            array_keys($created)
        ));
        return [
            'table' => $table, 'id' => (int) $created[$firstKey],
            'reference' => $voucherNo, 'material_label' => $materialLabel,
        ];
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $request */
    private function approveFollowup(array $payload, array $request): array
    {
        $orderId = (int) ($payload['order_id'] ?? $request['subject_id'] ?? 0);
        return (new OrderFollowupService($this->db))->create(
            $orderId,
            $payload,
            (int) ($request['requested_by'] ?? 0),
            $request['attachment_name'] ?? null,
            $request['attachment_path'] ?? null
        );
    }

    /** @param array<string,mixed> $payload @param array<string,mixed> $request */
    private function approveDeliveryChallan(array $payload, array $request): array
    {
        $challan = (new DeliveryChallanService($this->db))->create($payload, (int) ($request['requested_by'] ?? 0));
        return [
            'table' => 'delivery_challans',
            'id' => (int) ($challan['id'] ?? 0),
            'reference' => (string) ($challan['challan_no'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $row */
    private function hydrate(array $row): array
    {
        try {
            $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $payload = [];
        }
        $row['payload'] = is_array($payload) ? $payload : [];
        unset($row['payload_json']);
        $row['attachment_url'] = ! empty($row['attachment_path'])
            ? base_url(ltrim((string) $row['attachment_path'], '/')) : null;
        return $row;
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
