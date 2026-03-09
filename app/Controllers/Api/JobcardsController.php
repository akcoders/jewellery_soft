<?php

namespace App\Controllers\Api;

class JobcardsController extends ApiBaseController
{
    public function create()
    {
        $payload = $this->payload();
        $orderId = (int) ($payload['order_id'] ?? 0);
        $karigarId = (int) ($payload['karigar_id'] ?? 0);

        if ($orderId <= 0) {
            return $this->fail('order_id is required.', 422);
        }

        $db = db_connect();
        $order = $db->table('orders')->where('id', $orderId)->get()->getRowArray();
        if (! $order) {
            return $this->fail('Order not found.', 404);
        }

        $jobCardNo = trim((string) ($payload['job_card_no'] ?? ''));
        if ($jobCardNo === '') {
            $jobCardNo = 'JC-' . date('YmdHis');
        }

        $db->transStart();
        $jobCardId = (int) $db->table('job_cards')->insert([
            'job_card_no' => $jobCardNo,
            'order_id' => $orderId,
            'due_date' => (string) ($payload['due_date'] ?? $order['due_date'] ?? date('Y-m-d')),
            'status' => 'Pending',
            'priority' => (string) ($payload['priority'] ?? ($order['priority'] ?? 'Normal')),
            'order_item_id' => isset($payload['order_item_id']) ? (int) $payload['order_item_id'] : null,
            'created_by' => (int) (session('admin_id') ?: 0),
        ], true);

        $items = (array) ($payload['items'] ?? []);
        foreach ($items as $item) {
            $db->table('job_card_items')->insert([
                'job_card_id' => $jobCardId,
                'order_item_id' => isset($item['order_item_id']) ? (int) $item['order_item_id'] : null,
                'design_code' => (string) ($item['design_code'] ?? ''),
                'variant_size' => (string) ($item['variant_size'] ?? ''),
                'qty' => (int) ($item['qty'] ?? 1),
                'due_date' => (string) ($item['due_date'] ?? ($payload['due_date'] ?? date('Y-m-d'))),
                'priority' => (int) ($item['priority'] ?? 0),
            ]);
        }

        $stages = (array) ($payload['stages'] ?? ['Casting', 'Filing', 'Setting', 'Jadau', 'Polish', 'QC']);
        foreach ($stages as $stage) {
            $db->table('job_card_stages')->insert([
                'job_card_id' => $jobCardId,
                'stage_name' => (string) $stage,
                'status' => 'Pending',
                'created_by' => (int) (session('admin_id') ?: 0),
            ]);
        }

        if ($karigarId > 0) {
            $db->table('orders')->where('id', $orderId)->update([
                'assigned_karigar_id' => $karigarId,
                'assigned_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $db->transComplete();

        return $this->ok(['job_card_id' => $jobCardId, 'job_card_no' => $jobCardNo], 'Job card created.', 201);
    }

    public function assign(int $jobCardId)
    {
        $payload = $this->payload();
        $karigarId = (int) ($payload['karigar_id'] ?? 0);
        if ($karigarId <= 0) {
            return $this->fail('karigar_id is required.', 422);
        }

        $db = db_connect();
        $card = $db->table('job_cards')->where('id', $jobCardId)->get()->getRowArray();
        if (! $card) {
            return $this->fail('Job card not found.', 404);
        }

        $db->table('job_cards')->where('id', $jobCardId)->update([
            'status' => 'Assigned',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        if (! empty($card['order_id'])) {
            $db->table('orders')->where('id', (int) $card['order_id'])->update([
                'assigned_karigar_id' => $karigarId,
                'assigned_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->ok(['job_card_id' => $jobCardId, 'assigned_to' => $karigarId], 'Karigar assigned.');
    }

    public function stageUpdate(int $jobCardId)
    {
        $payload = $this->payload();
        $stage = trim((string) ($payload['stage_name'] ?? ''));
        $status = trim((string) ($payload['status'] ?? 'Pending'));
        if ($stage === '') {
            return $this->fail('stage_name is required.', 422);
        }

        $db = db_connect();
        $stageRow = $db->table('job_card_stages')
            ->where('job_card_id', $jobCardId)
            ->where('stage_name', $stage)
            ->get()
            ->getRowArray();

        if (! $stageRow) {
            $db->table('job_card_stages')->insert([
                'job_card_id' => $jobCardId,
                'stage_name' => $stage,
                'status' => $status,
                'created_by' => (int) (session('admin_id') ?: 0),
            ]);
        } else {
            $db->table('job_card_stages')->where('id', $stageRow['id'])->update([
                'status' => $status,
                'started_at' => $status === 'Running' ? date('Y-m-d H:i:s') : ($stageRow['started_at'] ?? null),
                'completed_at' => $status === 'Done' ? date('Y-m-d H:i:s') : null,
                'remarks' => (string) ($payload['remarks'] ?? ''),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $this->ok(['job_card_id' => $jobCardId, 'stage_name' => $stage, 'status' => $status], 'Stage updated.');
    }
}
