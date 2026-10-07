<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Keeps the physical diamond bag balance and the order/studding audit trail in
 * sync with the existing inventory and karigar accounting transactions.
 */
class DiamondBagTraceService
{
    private const EPSILON = 0.0005;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    /** @return list<array<string,mixed>> */
    public function availableBagItems(bool $includeEmpty = false): array
    {
        if (! $this->ready()) {
            return [];
        }

        $builder = $this->db->table('diamond_bag_items bi')
            ->select(
                'bi.id, bi.bag_id, bi.inventory_item_id AS item_id, bi.pcs_available, '
                . 'bi.weight_cts_available, bi.pcs_total, bi.weight_cts_total, '
                . 'b.bag_no, b.prepared_date, b.order_id AS bag_order_id, linked_order.order_no AS bag_order_no, '
                . 'i.diamond_type, i.shape AS item_shape, '
                . 'i.chalni_from, i.chalni_to, i.color, i.clarity, i.cut, '
                . 'sm.name AS shape_name, sz.size_code, sz.size_label, sz.min_mm, sz.max_mm, cg.name AS chalni_group_name, cg.range_label AS chalni_group_range, '
                . 'COALESCE(s.avg_cost_per_carat, 0) AS avg_cost_per_carat',
                false
            )
            ->join('diamond_bags b', 'b.id = bi.bag_id', 'inner')
            ->join('orders linked_order', 'linked_order.id = b.order_id', 'left')
            ->join('items i', 'i.id = bi.inventory_item_id', 'inner')
            ->join('stock s', 's.item_id = i.id', 'left')
            ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
            ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left')
            ->join('diamond_chalni_groups cg', 'cg.id = bi.chalni_group_id', 'left');
        if ($this->db->tableExists('diamond_requirements') && $this->db->fieldExists('requirement_id', 'diamond_bags')) {
            $builder->select('b.requirement_id, dr.order_id AS requirement_order_id, requirement_order.order_no AS requirement_order_no')
                ->join('diamond_requirements dr', 'dr.id = b.requirement_id', 'left')
                ->join('orders requirement_order', 'requirement_order.id = dr.order_id', 'left');
        }
        if (! $includeEmpty) {
            $builder->groupStart()->where('bi.pcs_available >', 0)->orWhere('bi.weight_cts_available >', 0)->groupEnd();
        }
        return $builder->orderBy('b.bag_no', 'ASC')
            ->orderBy('bi.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function receiptOptions(int $karigarId, int $orderId = 0): array
    {
        if ($karigarId <= 0 || ! $this->ready() || ! $this->db->fieldExists('bag_item_id', 'issue_lines')) {
            return [];
        }

        $rows = $this->db->table('issue_lines il')
            ->select(
                'il.id AS issue_line_id, il.issue_id, il.item_id, il.bag_id, il.bag_item_id, '
                . 'il.allocation_order_id, il.pcs AS issued_pcs, il.carat AS issued_cts, '
                . 'ih.voucher_no, ih.issue_date, b.bag_no, i.diamond_type, o.order_no, '
                . 'sm.name AS shape_name, sz.size_code, sz.size_label, cg.name AS chalni_group_name, cg.range_label AS chalni_group_range, i.color, i.clarity, '
                . 'COALESCE((SELECT SUM(rl.pcs) FROM return_lines rl WHERE rl.issue_line_id = il.id), 0) AS returned_pcs, '
                . 'COALESCE((SELECT SUM(rl.carat) FROM return_lines rl WHERE rl.issue_line_id = il.id), 0) AS returned_cts, '
                . 'COALESCE((SELECT SUM(rd.pcs) FROM order_receive_details rd WHERE rd.diamond_issue_line_id = il.id AND rd.component_type = \'diamond\'), 0) AS studded_pcs, '
                . 'COALESCE((SELECT SUM(rd.weight_cts) FROM order_receive_details rd WHERE rd.diamond_issue_line_id = il.id AND rd.component_type = \'diamond\'), 0) AS studded_cts',
                false
            )
            ->join('issue_headers ih', 'ih.id = il.issue_id', 'inner')
            ->join('diamond_bags b', 'b.id = il.bag_id', 'inner')
            ->join('items i', 'i.id = il.item_id', 'inner')
            ->join('diamond_bag_items bi', 'bi.id = il.bag_item_id', 'inner')
            ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
            ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left')
            ->join('diamond_chalni_groups cg', 'cg.id = bi.chalni_group_id', 'left')
            ->join('orders o', 'o.id = il.allocation_order_id', 'left')
            ->where('ih.karigar_id', $karigarId)
            ->where('il.bag_item_id IS NOT NULL', null, false);
        if ($orderId > 0) {
            $rows->where('il.allocation_order_id', $orderId);
        }
        $rows = $rows->orderBy('ih.issue_date', 'ASC')
            ->orderBy('il.id', 'ASC')
            ->get()
            ->getResultArray();

        $options = [];
        foreach ($rows as $row) {
            $availablePcs = round(max(0, (float) $row['issued_pcs'] - (float) $row['returned_pcs'] - (float) $row['studded_pcs']), 3);
            $availableCts = round(max(0, (float) $row['issued_cts'] - (float) $row['returned_cts'] - (float) $row['studded_cts']), 3);
            if ($availablePcs <= self::EPSILON && $availableCts <= self::EPSILON) {
                continue;
            }

            $shape = trim((string) ($row['shape_name'] ?? ''));
            $size = trim((string) (($row['size_label'] ?? '') ?: ($row['chalni_group_name'] ?? $row['size_code'] ?? '')));
            $parts = array_values(array_filter([
                (string) ($row['bag_no'] ?? ''),
                (string) ($row['diamond_type'] ?? ''),
                $shape,
                $size,
                trim((string) ($row['color'] ?? '')),
                trim((string) ($row['clarity'] ?? '')),
                trim((string) ($row['order_no'] ?? '')) !== '' ? 'Order ' . trim((string) $row['order_no']) : 'Unallocated',
            ], static fn(string $value): bool => $value !== ''));

            $row['value'] = 'issue:' . (int) $row['issue_line_id'];
            $row['label'] = implode(' / ', $parts);
            $row['available_pcs'] = $availablePcs;
            $row['available_cts'] = $availableCts;
            $options[] = $row;
        }

        return $options;
    }

    /**
     * Exact issue rows that can still be returned. Legacy unbagged issue rows are
     * included so old vouchers remain editable after the traceability migration.
     *
     * @return list<array<string,mixed>>
     */
    public function returnableIssueLines(int $issueId = 0, int $excludeReturnId = 0, bool $includeConsumed = false): array
    {
        if (! $this->db->tableExists('issue_lines') || ! $this->db->tableExists('issue_headers')) {
            return [];
        }

        $builder = $this->db->table('issue_lines il')
            ->select(
                'il.id AS issue_line_id, il.issue_id, il.item_id, il.bag_id, il.bag_item_id, '
                . 'il.allocation_order_id, il.pcs AS issued_pcs, il.carat AS issued_cts, '
                . 'il.rate_per_carat, ih.voucher_no, ih.issue_date, ih.karigar_id, '
                . 'b.bag_no, i.diamond_type, i.shape AS item_shape, i.chalni_from, i.chalni_to, '
                . 'i.color, i.clarity, sm.name AS shape_name, sz.size_code, sz.size_label, o.order_no',
                false
            )
            ->join('issue_headers ih', 'ih.id = il.issue_id', 'inner')
            ->join('items i', 'i.id = il.item_id', 'left')
            ->join('diamond_bags b', 'b.id = il.bag_id', 'left')
            ->join('diamond_bag_items bi', 'bi.id = il.bag_item_id', 'left')
            ->join('diamond_shape_masters sm', 'sm.id = bi.shape_master_id', 'left')
            ->join('diamond_size_masters sz', 'sz.id = bi.size_master_id', 'left')
            ->join('orders o', 'o.id = il.allocation_order_id', 'left');
        if ($issueId > 0) {
            $builder->where('il.issue_id', $issueId);
        }

        $rows = $builder->orderBy('ih.issue_date', 'DESC')->orderBy('il.id', 'ASC')->get()->getResultArray();
        $options = [];
        foreach ($rows as $row) {
            $lineId = (int) $row['issue_line_id'];
            $consumed = $this->consumedAgainstIssueLine($lineId, 0, $excludeReturnId);
            $availablePcs = round(max(0, (float) $row['issued_pcs'] - $consumed['pcs']), 3);
            $availableCts = round(max(0, (float) $row['issued_cts'] - $consumed['carat']), 3);
            if (! $includeConsumed && $availablePcs <= self::EPSILON && $availableCts <= self::EPSILON) {
                continue;
            }

            $shape = trim((string) (($row['shape_name'] ?? '') ?: ($row['item_shape'] ?? '')));
            $size = trim((string) (($row['size_label'] ?? '') ?: ($row['size_code'] ?? '')));
            if ($size === '' && ((string) ($row['chalni_from'] ?? '') !== '' || (string) ($row['chalni_to'] ?? '') !== '')) {
                $size = trim((string) ($row['chalni_from'] ?? '') . '-' . (string) ($row['chalni_to'] ?? ''), '-');
            }
            $parts = array_values(array_filter([
                trim((string) ($row['bag_no'] ?? '')) !== '' ? (string) $row['bag_no'] : 'Legacy unbagged',
                (string) ($row['diamond_type'] ?? ''),
                $shape,
                $size,
                trim((string) ($row['color'] ?? '')),
                trim((string) ($row['clarity'] ?? '')),
                trim((string) ($row['order_no'] ?? '')) !== '' ? 'Order ' . trim((string) $row['order_no']) : '',
            ], static fn(string $value): bool => $value !== ''));
            $row['label'] = implode(' / ', $parts);
            $row['available_pcs'] = $availablePcs;
            $row['available_cts'] = $availableCts;
            $options[] = $row;
        }

        return $options;
    }

    public function applyIssue(int $issueId): void
    {
        if (! $this->ready()) {
            return;
        }
        $lines = $this->mappedIssueLines($issueId);
        if ($lines === []) {
            return;
        }

        $totals = [];
        foreach ($lines as $line) {
            $this->assertRequirementAllocation($line);
            $bagItemId = (int) $line['bag_item_id'];
            $totals[$bagItemId]['pcs'] = (float) ($totals[$bagItemId]['pcs'] ?? 0) + (float) $line['pcs'];
            $totals[$bagItemId]['carat'] = (float) ($totals[$bagItemId]['carat'] ?? 0) + (float) $line['carat'];
        }

        foreach ($totals as $bagItemId => $qty) {
            $bagItem = $this->lockBagItem((int) $bagItemId);
            if ((float) $qty['pcs'] > ((float) $bagItem['pcs_available'] + self::EPSILON)
                || (float) $qty['carat'] > ((float) $bagItem['weight_cts_available'] + self::EPSILON)) {
                throw new RuntimeException('Bag ' . (string) ($bagItem['bag_no'] ?? ('#' . $bagItemId)) . ' does not have enough PCS/CTS available.');
            }
            $this->db->table('diamond_bag_items')->where('id', $bagItemId)->update([
                'pcs_available' => round((float) $bagItem['pcs_available'] - (float) $qty['pcs'], 3),
                'weight_cts_available' => round((float) $bagItem['weight_cts_available'] - (float) $qty['carat'], 3),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->refreshBagBalance((int) $bagItem['bag_id']);
        }

        $header = $this->db->table('issue_headers')->where('id', $issueId)->get()->getRowArray() ?? [];
        foreach ($lines as $line) {
            $this->db->table('diamond_bag_movements')->insert([
                'movement_date' => (string) ($header['issue_date'] ?? date('Y-m-d')),
                'movement_type' => 'ISSUE',
                'bag_id' => (int) $line['bag_id'],
                'bag_item_id' => (int) $line['bag_item_id'],
                'issue_line_id' => (int) $line['id'],
                'order_id' => (int) ($line['allocation_order_id'] ?? 0) ?: null,
                'karigar_id' => (int) ($header['karigar_id'] ?? 0) ?: null,
                'pcs' => round((float) $line['pcs'], 3),
                'carat' => round((float) $line['carat'], 3),
                'notes' => 'Issued from bag under voucher ' . (string) ($header['voucher_no'] ?? ('#' . $issueId)),
                'created_by' => (int) ($header['created_by'] ?? 0) ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        (new DiamondChalniStockService($this->db))->applyIssue($issueId);
        $this->markRequirementsIssued($lines);
    }

    public function reverseIssue(int $issueId): void
    {
        if (! $this->ready()) {
            return;
        }
        $lines = $this->mappedIssueLines($issueId);
        if ($lines === []) {
            return;
        }
        $lineIds = array_map(static fn(array $line): int => (int) $line['id'], $lines);
        $used = $this->db->table('diamond_bag_movements')
            ->whereIn('issue_line_id', $lineIds)
            ->whereIn('movement_type', ['RETURN', 'STUDDED'])
            ->countAllResults();
        if ($used > 0) {
            throw new RuntimeException('This diamond issue is already returned or studded. It cannot be edited or deleted.');
        }

        (new DiamondChalniStockService($this->db))->reverseIssue($issueId);

        foreach ($lines as $line) {
            $bagItem = $this->lockBagItem((int) $line['bag_item_id']);
            $this->db->table('diamond_bag_items')->where('id', (int) $line['bag_item_id'])->update([
                'pcs_available' => round((float) $bagItem['pcs_available'] + (float) $line['pcs'], 3),
                'weight_cts_available' => round((float) $bagItem['weight_cts_available'] + (float) $line['carat'], 3),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->refreshBagBalance((int) $bagItem['bag_id']);
        }
        $this->db->table('diamond_bag_movements')
            ->whereIn('issue_line_id', $lineIds)
            ->where('movement_type', 'ISSUE')
            ->delete();
        $this->refreshRequirementIssueStatus($lines);
    }

    public function applyReturn(int $returnId): void
    {
        if (! $this->ready() || ! $this->db->fieldExists('issue_line_id', 'return_lines')) {
            return;
        }

        $header = $this->db->table('return_headers')->where('id', $returnId)->get()->getRowArray() ?? [];
        $lines = $this->db->table('return_lines rl')
            ->select('rl.*, il.pcs AS issued_pcs, il.carat AS issued_cts, il.issue_id')
            ->join('issue_lines il', 'il.id = rl.issue_line_id', 'inner')
            ->where('rl.return_id', $returnId)
            ->where('rl.bag_item_id IS NOT NULL', null, false)
            ->get()->getResultArray();
        foreach ($lines as $line) {
            if ((int) ($line['issue_id'] ?? 0) !== (int) ($header['issue_id'] ?? 0)) {
                throw new RuntimeException('Return line does not belong to the selected issue voucher.');
            }
            $used = $this->consumedAgainstIssueLine((int) $line['issue_line_id'], 0, $returnId);
            $pcs = round((float) ($line['pcs'] ?? 0), 3);
            $cts = round((float) ($line['carat'] ?? 0), 3);
            if ($pcs <= 0 || $cts <= 0) {
                throw new RuntimeException('Returned diamond PCS and CTS are mandatory.');
            }
            if (($used['pcs'] + $pcs) > ((float) $line['issued_pcs'] + self::EPSILON)
                || ($used['carat'] + $cts) > ((float) $line['issued_cts'] + self::EPSILON)) {
                throw new RuntimeException('Diamond return exceeds the balance of its selected issue bag line.');
            }

            $bagItem = $this->lockBagItem((int) $line['bag_item_id']);
            $this->db->table('diamond_bag_items')->where('id', (int) $line['bag_item_id'])->update([
                'pcs_available' => round((float) $bagItem['pcs_available'] + $pcs, 3),
                'weight_cts_available' => round((float) $bagItem['weight_cts_available'] + $cts, 3),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->refreshBagBalance((int) $bagItem['bag_id']);
            $this->db->table('diamond_bag_movements')->insert([
                'movement_date' => (string) ($header['return_date'] ?? date('Y-m-d')),
                'movement_type' => 'RETURN',
                'bag_id' => (int) $line['bag_id'],
                'bag_item_id' => (int) $line['bag_item_id'],
                'issue_line_id' => (int) $line['issue_line_id'],
                'return_line_id' => (int) $line['id'],
                'order_id' => (int) ($line['allocation_order_id'] ?? 0) ?: null,
                'karigar_id' => (int) ($header['karigar_id'] ?? 0) ?: null,
                'pcs' => $pcs,
                'carat' => $cts,
                'notes' => 'Returned to original diamond bag',
                'created_by' => (int) ($header['created_by'] ?? 0) ?: null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        (new DiamondChalniStockService($this->db))->applyReturn($returnId);
    }

    public function reverseReturn(int $returnId): void
    {
        if (! $this->ready()) {
            return;
        }
        (new DiamondChalniStockService($this->db))->reverseReturn($returnId);
        $movements = $this->db->table('diamond_bag_movements bm')
            ->select('bm.*')
            ->join('return_lines rl', 'rl.id = bm.return_line_id', 'inner')
            ->where('bm.movement_type', 'RETURN')
            ->where('rl.return_id', $returnId)
            ->get()->getResultArray();
        foreach ($movements as $movement) {
            $bagItem = $this->lockBagItem((int) $movement['bag_item_id']);
            $pcs = (float) ($movement['pcs'] ?? 0);
            $cts = (float) ($movement['carat'] ?? 0);
            if ($pcs > ((float) $bagItem['pcs_available'] + self::EPSILON)
                || $cts > ((float) $bagItem['weight_cts_available'] + self::EPSILON)) {
                throw new RuntimeException('Returned diamonds from this voucher were already reissued. This return cannot be edited or deleted.');
            }
            $this->db->table('diamond_bag_items')->where('id', (int) $movement['bag_item_id'])->update([
                'pcs_available' => round((float) $bagItem['pcs_available'] - $pcs, 3),
                'weight_cts_available' => round((float) $bagItem['weight_cts_available'] - $cts, 3),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->refreshBagBalance((int) $bagItem['bag_id']);
        }
        if ($movements !== []) {
            $movementIds = array_map(static fn(array $row): int => (int) $row['id'], $movements);
            $this->db->table('diamond_bag_movements')->whereIn('id', $movementIds)->delete();
        }
    }

    public function recordStudding(int $receiveDetailId): void
    {
        if (! $this->ready() || $receiveDetailId <= 0) {
            return;
        }
        $detail = $this->db->table('order_receive_details rd')
            ->select('rd.*, il.pcs AS issued_pcs, il.carat AS issued_cts, il.allocation_order_id, ih.karigar_id')
            ->join('issue_lines il', 'il.id = rd.diamond_issue_line_id', 'inner')
            ->join('issue_headers ih', 'ih.id = il.issue_id', 'inner')
            ->where('rd.id', $receiveDetailId)
            ->get()->getRowArray();
        if (! $detail || (int) ($detail['diamond_bag_item_id'] ?? 0) <= 0) {
            return;
        }

        $issueLineId = (int) $detail['diamond_issue_line_id'];
        $used = $this->consumedAgainstIssueLine($issueLineId, $receiveDetailId);
        $pcs = round((float) ($detail['pcs'] ?? 0), 3);
        $cts = round((float) ($detail['weight_cts'] ?? 0), 3);
        if ($pcs <= 0 || $cts <= 0) {
            throw new RuntimeException('Studded diamond PCS and CTS are mandatory for bag traceability.');
        }
        if (($used['pcs'] + $pcs) > ((float) $detail['issued_pcs'] + self::EPSILON)
            || ($used['carat'] + $cts) > ((float) $detail['issued_cts'] + self::EPSILON)) {
            throw new RuntimeException('Studded diamond exceeds the available PCS/CTS in the selected issue bag line.');
        }
        if ((int) ($detail['allocation_order_id'] ?? 0) > 0
            && (int) ($detail['allocation_order_id'] ?? 0) !== (int) ($detail['order_id'] ?? 0)) {
            throw new RuntimeException('Selected diamond bag was issued for another order.');
        }

        $this->db->table('diamond_bag_movements')->insert([
            'movement_date' => date('Y-m-d'),
            'movement_type' => 'STUDDED',
            'bag_id' => (int) $detail['diamond_bag_id'],
            'bag_item_id' => (int) $detail['diamond_bag_item_id'],
            'issue_line_id' => $issueLineId,
            'receive_detail_id' => $receiveDetailId,
            'order_id' => (int) ($detail['order_id'] ?? 0) ?: null,
            'karigar_id' => (int) ($detail['karigar_id'] ?? 0) ?: null,
            'pcs' => $pcs,
            'carat' => $cts,
            'notes' => 'Studded in received jewellery',
            'created_by' => (int) ($detail['created_by'] ?? 0) ?: null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string,mixed> $line */
    private function assertRequirementAllocation(array $line): void
    {
        $bagId = (int) ($line['bag_id'] ?? 0);
        if ($bagId <= 0) {
            return;
        }
        $builder = $this->db->table('diamond_bags b')->select('b.order_id');
        if ($this->db->tableExists('diamond_requirements')
            && $this->db->fieldExists('requirement_id', 'diamond_bags')) {
            $builder->select('dr.order_id AS requirement_order_id')
                ->join('diamond_requirements dr', 'dr.id = b.requirement_id', 'left');
        }
        $bag = $builder->where('b.id', $bagId)->get()->getRowArray();
        if (! is_array($bag)) {
            return;
        }
        $requiredOrderId = (int) (($bag['order_id'] ?? 0) ?: ($bag['requirement_order_id'] ?? 0));
        $allocatedOrderId = (int) ($line['allocation_order_id'] ?? 0);
        if ($requiredOrderId > 0 && $allocatedOrderId !== $requiredOrderId) {
            throw new RuntimeException('This diamond bag can only be issued against its linked order.');
        }
    }

    /** @param list<array<string,mixed>> $lines */
    private function markRequirementsIssued(array $lines): void
    {
        if (! $this->db->tableExists('diamond_requirements') || ! $this->db->fieldExists('requirement_id', 'diamond_bags')) {
            return;
        }
        $bagIds = array_values(array_unique(array_filter(array_map(
            static fn(array $line): int => (int) ($line['bag_id'] ?? 0),
            $lines
        ))));
        if ($bagIds === []) {
            return;
        }
        $requirements = $this->db->table('diamond_bags')->select('requirement_id')
            ->whereIn('id', $bagIds)->where('requirement_id IS NOT NULL', null, false)->get()->getResultArray();
        $ids = array_values(array_unique(array_filter(array_map(static fn(array $row): int => (int) ($row['requirement_id'] ?? 0), $requirements))));
        if ($ids !== []) {
            $this->db->table('diamond_requirements')->whereIn('id', $ids)->where('status', 'bag_ready')->update([
                'status' => 'issued',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /** @param list<array<string,mixed>> $lines */
    private function refreshRequirementIssueStatus(array $lines): void
    {
        if (! $this->db->tableExists('diamond_requirements') || ! $this->db->fieldExists('requirement_id', 'diamond_bags')) {
            return;
        }
        $bagIds = array_values(array_unique(array_filter(array_map(
            static fn(array $line): int => (int) ($line['bag_id'] ?? 0),
            $lines
        ))));
        if ($bagIds === []) {
            return;
        }

        foreach ($bagIds as $bagId) {
            $bag = $this->db->table('diamond_bags')->select('requirement_id')->where('id', $bagId)->get()->getRowArray();
            $requirementId = (int) ($bag['requirement_id'] ?? 0);
            if ($requirementId <= 0) {
                continue;
            }
            $remainingIssues = $this->db->table('diamond_bag_movements')
                ->where('bag_id', $bagId)
                ->where('movement_type', 'ISSUE')
                ->countAllResults();
            if ($remainingIssues === 0) {
                $this->db->table('diamond_requirements')->where('id', $requirementId)->where('status', 'issued')->update([
                    'status' => 'bag_ready',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /** @return array{pcs:float,carat:float} */
    private function consumedAgainstIssueLine(int $issueLineId, int $excludeReceiveDetailId = 0, int $excludeReturnId = 0): array
    {
        $returnBuilder = $this->db->table('return_lines')
            ->select('COALESCE(SUM(pcs),0) AS pcs, COALESCE(SUM(carat),0) AS carat', false)
            ->where('issue_line_id', $issueLineId);
        if ($excludeReturnId > 0) {
            $returnBuilder->where('return_id !=', $excludeReturnId);
        }
        $returned = $returnBuilder->get()->getRowArray() ?? [];
        $receipts = $this->db->table('order_receive_details')
            ->select('COALESCE(SUM(pcs),0) AS pcs, COALESCE(SUM(weight_cts),0) AS carat', false)
            ->where('diamond_issue_line_id', $issueLineId)
            ->where('component_type', 'diamond');
        if ($excludeReceiveDetailId > 0) {
            $receipts->where('id !=', $excludeReceiveDetailId);
        }
        $received = $receipts->get()->getRowArray() ?? [];

        return [
            'pcs' => round((float) ($returned['pcs'] ?? 0) + (float) ($received['pcs'] ?? 0), 3),
            'carat' => round((float) ($returned['carat'] ?? 0) + (float) ($received['carat'] ?? 0), 3),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function mappedIssueLines(int $issueId): array
    {
        return $this->db->table('issue_lines')
            ->where('issue_id', $issueId)
            ->where('bag_id IS NOT NULL', null, false)
            ->where('bag_item_id IS NOT NULL', null, false)
            ->get()->getResultArray();
    }

    /** @return array<string,mixed> */
    private function lockBagItem(int $bagItemId): array
    {
        $row = $this->db->query(
            'SELECT bi.*, b.bag_no FROM diamond_bag_items bi INNER JOIN diamond_bags b ON b.id = bi.bag_id WHERE bi.id = ? FOR UPDATE',
            [$bagItemId]
        )->getRowArray();
        if (! $row) {
            throw new RuntimeException('Selected diamond bag row was not found.');
        }
        return $row;
    }

    private function refreshBagBalance(int $bagId): void
    {
        $sum = $this->db->table('diamond_bag_items')
            ->select('COALESCE(SUM(pcs_available),0) AS pcs, COALESCE(SUM(weight_cts_available),0) AS cts', false)
            ->where('bag_id', $bagId)->get()->getRowArray() ?? [];
        $this->db->table('diamond_bags')->where('id', $bagId)->update([
            'pcs_balance' => round((float) ($sum['pcs'] ?? 0), 3),
            'cts_balance' => round((float) ($sum['cts'] ?? 0), 3),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function ready(): bool
    {
        return $this->db->tableExists('diamond_bags')
            && $this->db->tableExists('diamond_bag_items')
            && $this->db->tableExists('diamond_bag_movements');
    }
}
