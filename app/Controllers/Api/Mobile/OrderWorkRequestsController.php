<?php

namespace App\Controllers\Api\Mobile;

use App\Services\DiamondBagService;
use App\Services\MobileNotificationEventService;
use App\Services\OrderWorkRequestService;
use Throwable;

class OrderWorkRequestsController extends MobileBaseController
{
    private const TYPES = ['order_delay', 'gold_requirement', 'diamond_requirement', 'follower_change'];

    public function index()
    {
        if ($failure = $this->requireMobileAuth()) return $failure;
        $userId = (int) $this->mobileAdmin['id'];
        $orderId = (int) ($this->request->getGet('order_id') ?? 0);
        return $this->ok((new OrderWorkRequestService())->listForUser(
            $userId,
            $orderId > 0 ? $orderId : null
        ));
    }

    public function create(int $orderId)
    {
        if ($failure = $this->requireMobileAuth()) return $failure;
        $db = db_connect();
        $order = $db->table('orders')->where('id', $orderId)->get()->getRowArray();
        if (! $order) return $this->fail('Order not found.', 404);
        if ((int) ($order['followup_assigned_to'] ?? 0) !== (int) $this->mobileAdmin['id']) {
            return $this->fail('Only this order\'s follower can raise a request.', 403);
        }
        if (in_array((string) $order['status'], ['Ready', 'Packed', 'Dispatched', 'Delivered', 'Complete', 'Completed', 'Cancelled'], true)) {
            return $this->fail('Requests are available only for open orders.', 422);
        }
        $payload = $this->payload();
        $type = trim((string) ($payload['request_type'] ?? ''));
        $details = trim((string) ($payload['details'] ?? ''));
        if (! in_array($type, self::TYPES, true) || $details === '') {
            return $this->fail('Select a request type and enter details.', 422);
        }
        if ($type === 'diamond_requirement' && ! (new DiamondBagService($db))->supportsOrder($orderId)) {
            return $this->fail('Diamond bags can be requested only for Diamond or Jadau orders.', 422);
        }
        if ($db->table('order_work_requests')->where('order_id', $orderId)
            ->where('request_type', $type)->where('status', 'pending')->countAllResults() > 0) {
            return $this->fail('This order already has a pending request of that type.', 422);
        }
        $dueAt = null;
        if ($type === 'order_delay') {
            $date = trim((string) ($payload['requested_due_at'] ?? ''));
            $timestamp = strtotime($date);
            if ($date === '' || $timestamp === false || $timestamp <= time()) {
                return $this->fail('Select a future requested delivery date.', 422);
            }
            $dueAt = date('Y-m-d H:i:s', $timestamp);
        }
        $gold = $type === 'gold_requirement' ? (float) ($payload['gold_quantity_gm'] ?? 0) : 0;
        if ($type === 'gold_requirement' && ($gold <= 0 || $gold > 100000)) {
            return $this->fail('Enter a valid gold requirement in grams.', 422);
        }
        $id = $db->table('order_work_requests')->insert([
            'order_id' => $orderId,
            'request_type' => $type,
            'details' => $details,
            'requested_due_at' => $dueAt,
            'gold_quantity_gm' => $type === 'gold_requirement' ? round($gold, 3) : null,
            'status' => 'pending',
            'requested_by' => (int) $this->mobileAdmin['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]) ? $db->insertID() : 0;
        if (! $id) return $this->fail('Could not create request.', 500);
        try {
            (new MobileNotificationEventService())->notifyOrderWorkRequestRaised((int) $id);
        } catch (Throwable $e) {
            log_message('error', 'Order work request notification failed: {message}', ['message' => $e->getMessage()]);
        }
        return $this->ok(['id' => (int) $id], 'Request sent for admin approval.', 201);
    }

    public function review(int $id)
    {
        if ($failure = $this->requireMobileAuth()) return $failure;
        $payload = $this->payload();
        try {
            $result = (new OrderWorkRequestService())->review(
                $id,
                (int) $this->mobileAdmin['id'],
                $payload
            );
        } catch (Throwable $e) {
            $statusCode = in_array($e->getCode(), [403, 404, 422], true) ? $e->getCode() : 422;
            return $this->fail($e->getMessage(), $statusCode);
        }
        return $this->ok(
            ['id' => $id],
            'Request ' . ((string) $result['decision'] === 'approve' ? 'approved' : 'rejected') . '.'
        );
    }
}
