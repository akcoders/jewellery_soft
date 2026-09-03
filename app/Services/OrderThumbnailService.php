<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class OrderThumbnailService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    /** @param list<int> $orderIds @return array<int,string> */
    public function map(array $orderIds, bool $includeProductionReady = true): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), static fn(int $id): bool => $id > 0)));
        if ($orderIds === []) {
            return [];
        }

        $thumbnails = [];
        $priorities = [];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if ($this->db->tableExists('order_attachments')) {
            $attachments = $this->db->table('order_attachments')
                ->select('id, order_id, file_type, file_path')
                ->whereIn('order_id', $orderIds)
                ->where('file_path !=', '')
                ->orderBy('id', 'DESC')->get()->getResultArray();
            foreach ($attachments as $attachment) {
                $orderId = (int) ($attachment['order_id'] ?? 0);
                $path = trim((string) ($attachment['file_path'] ?? ''));
                if ($orderId <= 0 || $path === '' || ! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $imageExtensions, true)) {
                    continue;
                }
                $priority = strtolower(trim((string) ($attachment['file_type'] ?? ''))) === 'finish_photo' ? 0 : 2;
                if (! isset($priorities[$orderId]) || $priority < $priorities[$orderId]) {
                    $priorities[$orderId] = $priority;
                    $thumbnails[$orderId] = base_url(ltrim($path, '/'));
                }
            }
        }

        if ($includeProductionReady && $this->db->tableExists('production_ready_items')
            && $this->db->fieldExists('order_id', 'production_ready_items')
            && $this->db->fieldExists('image_path', 'production_ready_items')) {
            $readyRows = $this->db->table('production_ready_items')
                ->select('id, order_id')->whereIn('order_id', $orderIds)
                ->where('image_path IS NOT NULL', null, false)->where('image_path !=', '')
                ->orderBy('source_row', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
            foreach ($readyRows as $row) {
                $orderId = (int) ($row['order_id'] ?? 0);
                $readyId = (int) ($row['id'] ?? 0);
                if ($orderId <= 0 || $readyId <= 0 || (($priorities[$orderId] ?? PHP_INT_MAX) <= 1)) {
                    continue;
                }
                $priorities[$orderId] = 1;
                $thumbnails[$orderId] = site_url('admin/orders/ready-image/' . $readyId);
            }
        }

        return $thumbnails;
    }
}
