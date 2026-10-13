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
        ?int $subjectId = null,
        bool $sendNotification = true
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
        if ($sendNotification) {
            try {
                (new MobileNotificationEventService())->notifyApprovalRequested($request);
            } catch (Throwable $e) {
                log_message('error', 'Approval request notification failed: {message}', ['message' => $e->getMessage()]);
            }
        }
        return $request;
    }

    /** @return list<array<string,mixed>> */
    public function list(?int $requestedBy = null, ?string $status = null): array
    {
        $builder = $this->db->table('mobile_approval_requests ar')
            ->select('ar.*, requester.name AS requested_by_name, reviewer.name AS reviewed_by_name')
            ->join('admin_users requester', 'requester.id = ar.requested_by', 'left')
            ->join('admin_users reviewer', 'reviewer.id = ar.reviewed_by', 'left');
        if (($requestedBy ?? 0) > 0) {
            $builder->where('ar.requested_by', $requestedBy);
        }
        $status = strtolower(trim((string) $status));
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $builder->where('ar.status', $status);
        } elseif ($status === 'history') {
            $builder->whereIn('ar.status', ['approved', 'rejected']);
        }
        $rows = $builder->orderBy("CASE WHEN ar.status = 'pending' THEN 0 ELSE 1 END", 'ASC', false)
            ->orderBy('ar.id', 'DESC')->limit(500)->get()->getResultArray();
        return $this->withFollowupContexts(
            array_map(fn(array $row): array => $this->hydrate($row), $rows)
        );
    }

    public function find(int $id): ?array
    {
        $row = $this->db->table('mobile_approval_requests ar')
            ->select('ar.*, requester.name AS requested_by_name, reviewer.name AS reviewed_by_name')
            ->join('admin_users requester', 'requester.id = ar.requested_by', 'left')
            ->join('admin_users reviewer', 'reviewer.id = ar.reviewed_by', 'left')
            ->where('ar.id', $id)->get()->getRowArray();
        if (! is_array($row)) {
            return null;
        }
        return $this->withFollowupContexts([$this->hydrate($row)])[0] ?? null;
    }

    public function reject(int $id, int $reviewerId, string $note = ''): array
    {
        $this->db->transException(true)->transStart();
        try {
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
            if (is_array($before) && (string) ($before['request_type'] ?? '') === 'followup') {
                $followupId = $this->followupIdFromRequest($before);
                if ($followupId > 0) {
                    (new OrderFollowupService($this->db))->reviewPending($followupId, false, $reviewerId, $note);
                }
            }
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();
            throw $e;
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
            $status = (string) ($request['status'] ?? '');
            $isReapprovable = $status === 'rejected'
                && (string) ($request['request_type'] ?? '') === 'issuement';
            if (! is_array($request) || ($status !== 'pending' && ! $isReapprovable)) {
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
                'followup' => $this->approveFollowup($payload, $request, $reviewerId, $note),
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
        $approved['was_reapproved'] = $isReapprovable;
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
    private function approveFollowup(array $payload, array $request, int $reviewerId, string $note): array
    {
        $followupId = $this->followupIdFromRequest($request, $payload);
        if ($followupId > 0) {
            return (new OrderFollowupService($this->db))->reviewPending($followupId, true, $reviewerId, $note);
        }
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
        $row['can_reapprove'] = (string) ($row['status'] ?? '') === 'rejected'
            && (string) ($row['request_type'] ?? '') === 'issuement';
        return $row;
    }

    /**
     * Adds the last completed follow-up that existed when each approval request
     * was submitted. This also enriches requests created before this context was
     * added to the approval desk.
     *
     * @param list<array<string,mixed>> $requests
     * @return list<array<string,mixed>>
     */
    private function withFollowupContexts(array $requests): array
    {
        $orderIds = [];
        foreach ($requests as $request) {
            if ((string) ($request['request_type'] ?? '') !== 'followup') {
                continue;
            }
            $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
            $orderId = (int) ($payload['order_id'] ?? $request['subject_id'] ?? 0);
            if ($orderId > 0) {
                $orderIds[$orderId] = $orderId;
            }
        }
        if ($orderIds === []) {
            return $requests;
        }

        $followups = $this->db->table('order_followups ofu')
            ->select('ofu.id, ofu.order_id, ofu.stage, ofu.description, ofu.next_followup_date, ofu.followup_taken_on, ofu.created_at, au.name AS followup_taken_by_name')
            ->join('admin_users au', 'au.id = ofu.followup_taken_by', 'left')
            ->whereIn('ofu.order_id', array_values($orderIds))
            ->orderBy('ofu.id', 'DESC')
            ->get()->getResultArray();
        $byOrder = [];
        foreach ($followups as $followup) {
            $byOrder[(int) ($followup['order_id'] ?? 0)][] = $followup;
        }

        foreach ($requests as &$request) {
            if ((string) ($request['request_type'] ?? '') !== 'followup') {
                continue;
            }
            $payload = is_array($request['payload'] ?? null) ? $request['payload'] : [];
            $orderId = (int) ($payload['order_id'] ?? $request['subject_id'] ?? 0);
            $requestCreatedAt = strtotime((string) ($request['created_at'] ?? '')) ?: null;
            $resultId = (int) ($request['result_id'] ?? 0);
            $subjectFollowupId = (string) ($request['subject_table'] ?? '') === 'order_followups'
                ? (int) ($request['subject_id'] ?? 0)
                : (int) ($payload['followup_id'] ?? 0);
            $previous = null;
            foreach ($byOrder[$orderId] ?? [] as $followup) {
                if ($subjectFollowupId > 0 && (int) ($followup['id'] ?? 0) === $subjectFollowupId) {
                    continue;
                }
                if ($resultId > 0 && (int) ($followup['id'] ?? 0) === $resultId) {
                    continue;
                }
                $followupCreatedAt = strtotime((string) ($followup['created_at'] ?? '')) ?: null;
                if ($requestCreatedAt !== null && $followupCreatedAt !== null
                    && $followupCreatedAt > $requestCreatedAt) {
                    continue;
                }
                $previous = [
                    'id' => (int) ($followup['id'] ?? 0),
                    'stage' => (string) ($followup['stage'] ?? ''),
                    'description' => (string) ($followup['description'] ?? ''),
                    'next_followup_date' => $followup['next_followup_date'] ?? null,
                    'followup_taken_on' => $followup['followup_taken_on'] ?? null,
                    'followup_taken_by_name' => (string) ($followup['followup_taken_by_name'] ?? ''),
                ];
                break;
            }
            $payload['previous_followup'] = $previous;
            $request['payload'] = $payload;
        }
        unset($request);

        return $requests;
    }

    /** @param array<string,mixed> $request @param array<string,mixed>|null $payload */
    private function followupIdFromRequest(array $request, ?array $payload = null): int
    {
        $payload ??= is_array($request['payload'] ?? null) ? $request['payload'] : [];
        if ((string) ($request['subject_table'] ?? '') === 'order_followups') {
            return (int) ($request['subject_id'] ?? 0);
        }
        return (int) ($payload['followup_id'] ?? 0);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
