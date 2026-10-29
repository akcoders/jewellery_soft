<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Jewellery;
use RuntimeException;

class OrderWorkflowService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function followupStageError(string $stage): ?string
    {
        if (in_array(strtolower(trim($stage)), ['completed', 'complete'], true)) {
            return 'Completed is recorded only after finished jewellery receiving. Select Ready for the final follow-up.';
        }
        if (! in_array($stage, config(Jewellery::class)->followupStages, true)) {
            return 'Select a valid production stage. Ready is the final follow-up stage.';
        }
        return null;
    }

    public function assertFollowupStage(string $stage): void
    {
        $error = $this->followupStageError($stage);
        if ($error !== null) {
            throw new RuntimeException($error);
        }
    }

    public function statusError(string $status, int $orderId = 0): ?string
    {
        if (in_array(strtolower(trim($status)), ['packed', 'dispatched', 'delivered', 'completed', 'complete'], true)
            && ($orderId <= 0 || ! (new OrderReceivingService($this->db))->hasRecordedReceipt($orderId))) {
            return 'Receive finished jewellery before changing the order to Packed, Dispatched, Delivered, or Completed.';
        }
        if (! in_array($status, array_merge(config(Jewellery::class)->orderStatuses, ['Draft', 'Cancelled', 'Delivered', 'Complete']), true)) {
            return 'Select a valid order status.';
        }
        return null;
    }

    /** A queued family follow-up must preserve orders already finished or received. */
    public function canApplyFollowup(array $order): bool
    {
        return empty($order['deleted_at'])
            && ! in_array(strtolower(trim((string) ($order['status'] ?? ''))), [
                'ready', 'packed', 'dispatched', 'delivered', 'completed', 'complete', 'cancelled',
            ], true)
            && ! (new OrderReceivingService($this->db))->hasRecordedReceipt((int) ($order['id'] ?? 0));
    }
}
