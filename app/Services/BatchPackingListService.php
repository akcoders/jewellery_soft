<?php

namespace App\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class BatchPackingListService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /** @return list<array<string,mixed>> */
    public function eligibleOrders(?int $userId = null, bool $canManageAll = true): array
    {
        $rows = $this->eligibleQuery($userId, $canManageAll)
            ->orderBy('COALESCE(o.received_at, rs.created_at)', 'DESC', false)
            ->orderBy('o.id', 'DESC')
            ->get()->getResultArray();

        $photos = $this->photoPaths(array_map(static fn(array $row): int => (int) $row['id'], $rows));
        foreach ($rows as &$row) {
            $row['photo_url'] = isset($photos[(int) $row['id']]) ? base_url($photos[(int) $row['id']]) : '';
        }
        unset($row);

        return $rows;
    }

    /** @param list<int|string> $orderIds @return array<string,mixed> */
    public function report(array $orderIds, ?int $userId = null, bool $canManageAll = true): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds), static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            throw new RuntimeException('Select at least one completed and received order.');
        }
        if (count($ids) > 100) {
            throw new RuntimeException('A maximum of 100 orders can be included in one packing list.');
        }

        $records = $this->eligibleQuery($userId, $canManageAll)->whereIn('o.id', $ids)->get()->getResultArray();
        $byId = [];
        foreach ($records as $record) {
            $byId[(int) $record['id']] = $record;
        }
        if (count($byId) !== count($ids)) {
            throw new RuntimeException('One or more selected orders are not completed, not received, or outside your access.');
        }

        $movementIds = [];
        foreach ($records as $record) {
            $movementIds[] = (int) ($record['movement_id'] ?? 0);
        }
        $detailMap = $this->componentDetails($movementIds);
        $photoMap = $this->photoPaths($ids);
        $itemMap = $this->orderItemSummary($ids);

        $rows = [];
        foreach ($ids as $index => $id) {
            $record = $byId[$id];
            $movementId = (int) ($record['movement_id'] ?? 0);
            $details = $detailMap[$movementId] ?? [];
            $diamondPcs = 0.0;
            $diamondWeight = 0.0;
            $diamondAmount = 0.0;
            $stonePcs = 0.0;
            $stoneWeight = 0.0;
            $stoneAmount = 0.0;
            $diamondLines = [];
            $stoneLines = [];
            foreach ($details as $detail) {
                $type = strtolower(trim((string) ($detail['component_type'] ?? '')));
                $pcs = (float) ($detail['pcs'] ?? 0);
                $cts = (float) ($detail['weight_cts'] ?? 0);
                $rate = (float) ($detail['rate'] ?? 0);
                $amount = (float) ($detail['line_total'] ?? 0);
                $line = strtoupper((string) ($detail['component_name'] ?: $detail['component_type']))
                    . ' | ' . $this->compactNumber($pcs) . ' pcs | ' . number_format($cts, 3, '.', '') . ' ct'
                    . ' | rate ' . number_format($rate, 2, '.', '')
                    . ' | amt ' . number_format($amount, 2, '.', '');
                if ($type === 'diamond') {
                    $diamondPcs += $pcs;
                    $diamondWeight += $cts;
                    $diamondAmount += $amount;
                    $diamondLines[] = $line;
                } elseif ($type === 'stone') {
                    $stonePcs += $pcs;
                    $stoneWeight += $cts;
                    $stoneAmount += $amount;
                    $stoneLines[] = $line;
                }
            }

            $photo = $photoMap[$id] ?? '';
            $record['batch_no'] = $index + 1;
            $record['item_no'] = 1;
            $record['item_description'] = $itemMap[$id] ?? (string) ($record['order_name'] ?? 'Jewellery');
            $record['diamond_details'] = $diamondLines;
            $record['diamond_pcs'] = round($diamondPcs, 3);
            $record['diamond_weight_cts'] = round($diamondWeight, 3);
            $record['diamond_amount_total'] = round($diamondAmount, 2);
            $record['stone_details'] = $stoneLines;
            $record['stone_pcs'] = $stonePcs;
            $record['stone_weight_cts'] = $stoneWeight;
            $record['stone_amount_total'] = $stoneAmount;
            $record['photo_data_uri'] = $this->imageDataUri($photo);
            $rows[] = $record;
        }

        return [
            'packing_no' => 'PK-MULTI-' . date('Ymd-His'),
            'generated_at' => date('Y-m-d H:i:s'),
            'rows' => $rows,
            'totals' => $this->totals($rows),
        ];
    }

    private function eligibleQuery(?int $userId, bool $canManageAll): BaseBuilder
    {
        if (! $this->db->tableExists('order_receive_summaries')) {
            throw new RuntimeException('Receiving setup is not available. Run database migrations first.');
        }

        $latestReceipt = '(SELECT MAX(r2.id) FROM order_receive_summaries r2 WHERE r2.order_id = o.id)';
        $builder = $this->db->table('orders o')
            ->select('o.id, o.order_no, o.order_name, o.order_received_date, o.created_at AS order_created_at, o.due_date, o.received_at, o.status, o.created_by, o.followup_assigned_to')
            ->select('c.name AS customer_name, cu.name AS sales_person_name, k.name AS karigar_name')
            ->select('rs.id AS receive_summary_id, rs.movement_id, rs.created_at AS receipt_created_at, rs.gross_weight_gm, rs.net_gold_weight_gm, rs.pure_gold_weight_gm, rs.diamond_weight_cts, rs.stone_weight_cts, rs.diamond_amount, rs.stone_amount, rs.other_amount, rs.gold_amount, rs.labour_amount, rs.total_valuation')
            ->select('rs.wastage_percent, rs.pure_wastage_weight_gm, COALESCE(gp.purity_code, item_gp.purity_code) AS purity_code, COALESCE(NULLIF(rs.purity_percent, 0), item_gp.purity_percent, 0) AS purity_percent', false)
            ->join('order_receive_summaries rs', 'rs.id = ' . $latestReceipt, 'inner', false)
            ->join('customers c', 'c.id = o.customer_id', 'left')
            ->join('customer_users cu', 'cu.id = o.sales_person_user_id', 'left')
            ->join('karigars k', 'k.id = o.assigned_karigar_id', 'left')
            ->join('gold_purities gp', 'gp.id = rs.gold_purity_id', 'left')
            ->join('order_items first_item', 'first_item.id = (SELECT MIN(oi2.id) FROM order_items oi2 WHERE oi2.order_id = o.id)', 'left', false)
            ->join('gold_purities item_gp', 'item_gp.id = first_item.gold_purity_id', 'left')
            ->whereIn('o.status', ['Completed', 'Complete'])
            ->where('o.deleted_at', null);

        if (! $canManageAll) {
            if (($userId ?? 0) <= 0) {
                $builder->where('1 = 0', null, false);
            } else {
                $builder->groupStart()
                    ->where('o.followup_assigned_to', $userId)
                    ->orWhere('o.created_by', $userId)
                    ->groupEnd();
            }
        }

        return $builder;
    }

    /** @param list<int> $movementIds @return array<int,list<array<string,mixed>>> */
    private function componentDetails(array $movementIds): array
    {
        $movementIds = array_values(array_filter($movementIds));
        if ($movementIds === [] || ! $this->db->tableExists('order_receive_details')) {
            return [];
        }
        $map = [];
        $rows = $this->db->table('order_receive_details')
            ->whereIn('movement_id', $movementIds)->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($rows as $row) {
            $map[(int) $row['movement_id']][] = $row;
        }
        return $map;
    }

    /** @param list<int> $orderIds @return array<int,string> */
    private function orderItemSummary(array $orderIds): array
    {
        if ($orderIds === [] || ! $this->db->tableExists('order_items')) {
            return [];
        }
        $map = [];
        $rows = $this->db->table('order_items')->select('order_id, item_description, qty')
            ->whereIn('order_id', $orderIds)->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($rows as $row) {
            $description = trim((string) ($row['item_description'] ?? ''));
            if ($description === '') {
                continue;
            }
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $map[(int) $row['order_id']][] = $description . ($qty > 1 ? ' × ' . $qty : '');
        }
        return array_map(static fn(array $items): string => implode(', ', $items), $map);
    }

    /** @param list<int> $orderIds @return array<int,string> */
    private function photoPaths(array $orderIds): array
    {
        if ($orderIds === [] || ! $this->db->tableExists('order_attachments')) {
            return [];
        }
        $map = [];
        $rows = $this->db->table('order_attachments')
            ->select('order_id, file_path, file_type, id')
            ->whereIn('order_id', $orderIds)
            ->groupStart()->where('LOWER(file_type)', 'finish_photo')->orWhere('LOWER(file_type)', 'photo')->groupEnd()
            ->orderBy("CASE WHEN LOWER(file_type) = 'finish_photo' THEN 0 ELSE 1 END", '', false)
            ->orderBy('id', 'DESC')->get()->getResultArray();
        foreach ($rows as $row) {
            $id = (int) $row['order_id'];
            if (! isset($map[$id]) && trim((string) ($row['file_path'] ?? '')) !== '') {
                $map[$id] = ltrim((string) $row['file_path'], '/');
            }
        }
        return $map;
    }

    private function imageDataUri(string $path): string
    {
        if ($path === '') {
            return '';
        }
        $candidates = [
            FCPATH . ltrim($path, '/'),
            ROOTPATH . ltrim($path, '/'),
            WRITEPATH . ltrim(preg_replace('#^writable/#', '', $path) ?? $path, '/'),
        ];
        foreach ($candidates as $absolute) {
            if (! is_file($absolute) || filesize($absolute) === false || filesize($absolute) > 8 * 1024 * 1024) {
                continue;
            }
            $mime = mime_content_type($absolute) ?: '';
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
                continue;
            }
            $contents = file_get_contents($absolute);
            return $contents === false ? '' : 'data:' . $mime . ';base64,' . base64_encode($contents);
        }
        return '';
    }

    /** @param list<array<string,mixed>> $rows @return array<string,float> */
    private function totals(array $rows): array
    {
        $fields = [
            'gross_weight_gm', 'diamond_pcs', 'diamond_weight_cts', 'diamond_amount_total',
            'stone_pcs', 'stone_weight_cts', 'stone_amount_total',
            'net_gold_weight_gm', 'pure_gold_weight_gm', 'gold_amount',
            'pure_wastage_weight_gm', 'labour_amount', 'total_valuation',
        ];
        $totals = [];
        foreach ($fields as $field) {
            $totals[$field] = round(array_sum(array_map(static fn(array $row): float => (float) ($row[$field] ?? 0), $rows)), 3);
        }
        return $totals;
    }

    private function compactNumber(float $number): string
    {
        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }
}
