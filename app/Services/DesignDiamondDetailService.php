<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/** Read-only diamond specifications, disclosed from the Designs screen on request. */
class DesignDiamondDetailService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /** @return list<array<string,mixed>> */
    public function ordersForDesign(array $design): array
    {
        $sourceOrderId = (int) ($design['source_order_id'] ?? 0);
        $orderIds = $sourceOrderId > 0 ? [$sourceOrderId] : [];
        if ($this->db->tableExists('order_items')) {
            $links = $this->db->table('order_items')->select('order_id')
                ->where('design_id', (int) $design['id'])->get()->getResultArray();
            foreach ($links as $link) {
                $orderIds[] = (int) $link['order_id'];
            }
        }
        $orderIds = array_values(array_unique(array_filter($orderIds)));
        if ($orderIds === [] || ! $this->db->tableExists('orders')) {
            return [];
        }

        $orders = $this->db->table('orders')->select('id, order_no')
            ->whereIn('id', $orderIds)->orderBy('id', 'DESC')->get()->getResultArray();
        usort($orders, static fn(array $a, array $b): int =>
            ((int) $b['id'] === $sourceOrderId ? 1 : 0) <=> ((int) $a['id'] === $sourceOrderId ? 1 : 0)
        );
        foreach ($orders as &$order) {
            $orderId = (int) $order['id'];
            $order['is_source'] = $orderId === $sourceOrderId;
            $order['multiple_items'] = $this->db->tableExists('order_items')
                && $this->db->table('order_items')->where('order_id', $orderId)->countAllResults() > 1;
            $received = $this->receivedRows($orderId);
            $historical = $this->historicalRows($orderId);
            // Historical issue rows explain old production sizes, but are never
            // added to receiving totals or represented as confirmed studding.
            $hasLiveTrace = array_filter($received, static fn(array $row): bool => ! empty($row['bag_no']));
            $order['historical'] = $hasLiveTrace === [] && $historical !== [];
            $order['rows'] = $order['historical'] ? $historical : $received;
            $order['total_pcs'] = array_sum(array_column($order['rows'], 'pcs'));
            $order['total_cts'] = array_sum(array_column($order['rows'], 'weight_cts'));
            $order['has_receiving'] = $received !== [];
            $order['received_total_pcs'] = array_sum(array_column($received, 'pcs'));
            $order['received_total_cts'] = array_sum(array_column($received, 'weight_cts'));
            $order['unmatched_received_pcs'] = round(max(0, $order['received_total_pcs'] - $order['total_pcs']), 3);
            $order['unmatched_received_cts'] = round(max(0, $order['received_total_cts'] - $order['total_cts']), 3);
            $order['reference_exceeds_receiving'] = $order['historical'] && $order['has_receiving']
                && ($order['total_pcs'] > $order['received_total_pcs'] + 0.0005
                    || $order['total_cts'] > $order['received_total_cts'] + 0.0005);
        }
        unset($order);

        return $orders;
    }

    private function receivedRows(int $orderId): array
    {
        if (! $this->db->tableExists('order_receive_details')) {
            return [];
        }
        $builder = $this->db->table('order_receive_details rd')
            ->select('rd.component_name AS quality, rd.pcs, rd.weight_cts')
            ->where('rd.order_id', $orderId)->where('rd.component_type', 'diamond');
        $traceReady = $this->db->fieldExists('diamond_bag_item_id', 'order_receive_details');
        foreach (['diamond_bag_items', 'diamond_bags', 'diamond_shape_masters', 'diamond_size_masters'] as $table) {
            $traceReady = $traceReady && $this->db->tableExists($table);
        }
        if ($traceReady) {
            $builder->select('b.bag_no, sm.name AS shape_name, sz.size_label, sz.size_code')
                ->join('diamond_bag_items bi', 'bi.id = rd.diamond_bag_item_id', 'left')
                ->join('diamond_bags b', 'b.id = bi.bag_id', 'left')
                ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
                ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left');
            if ($this->db->fieldExists('chalni_label', 'diamond_size_masters')) {
                $builder->select('sz.chalni_label');
            }
        }
        return $builder->orderBy('rd.id', 'ASC')->get()->getResultArray();
    }

    private function historicalRows(int $orderId): array
    {
        if (! $this->db->tableExists('order_diamond_size_details')) {
            return [];
        }
        return $this->db->table('order_diamond_size_details')
            ->select('quality, shade, shape_name, size_label, chalni_label, pcs, weight_cts, source_file, source_sheet, source_row, source_block, notes')
            ->where('order_id', $orderId)->where('match_status', 'matched')
            ->orderBy('source_sheet', 'ASC')->orderBy('source_row', 'ASC')
            ->get()->getResultArray();
    }
}
