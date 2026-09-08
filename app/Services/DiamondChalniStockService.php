<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Maintains the physical chalni/size split below the existing product stock.
 * The stock table remains the financial product ledger; this service exposes
 * the difference as untraced/over-traced instead of silently changing it.
 */
class DiamondChalniStockService
{
    private const EPSILON = 0.0005;

    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function ready(): bool
    {
        return $this->db->tableExists('diamond_chalni_stocks')
            && $this->db->tableExists('diamond_chalni_stock_movements');
    }

    /** @return list<array<string,mixed>> */
    public function products(): array
    {
        $select = 'i.*, COALESCE(s.pcs_balance,0) AS pcs_balance, COALESCE(s.carat_balance,0) AS carat_balance, '
            . 'COALESCE(s.avg_cost_per_carat,0) AS avg_cost_per_carat, COALESCE(s.stock_value,0) AS stock_value';
        if ($this->ready()) {
            $select .= ', COALESCE((SELECT SUM(cs.pcs_balance) FROM diamond_chalni_stocks cs WHERE cs.item_id=i.id AND cs.is_active=1),0) AS traced_pcs'
                . ', COALESCE((SELECT SUM(cs.carat_balance) FROM diamond_chalni_stocks cs WHERE cs.item_id=i.id AND cs.is_active=1),0) AS traced_cts';
        } else {
            $select .= ', 0 AS traced_pcs, 0 AS traced_cts';
        }

        $rows = $this->db->table('items i')
            ->select($select, false)
            ->join('stock s', 's.item_id=i.id', 'left')
            ->orderBy('i.clarity', 'ASC')->orderBy('i.diamond_type', 'ASC')->orderBy('i.id', 'ASC')
            ->get()->getResultArray();
        foreach ($rows as &$row) {
            $row['product_label'] = $this->productLabel($row);
            $ledger = round((float) ($row['carat_balance'] ?? 0), 3);
            $traced = round((float) ($row['traced_cts'] ?? 0), 3);
            $row['untraced_cts'] = round(max(0, $ledger - $traced), 3);
            $row['over_traced_cts'] = round(max(0, $traced - $ledger), 3);
        }
        unset($row);

        return $rows;
    }

    /** @return array<string,mixed> */
    public function snapshot(int $itemId): array
    {
        $product = null;
        foreach ($this->products() as $row) {
            if ((int) $row['id'] === $itemId) {
                $product = $row;
                break;
            }
        }
        if (! is_array($product)) {
            throw new RuntimeException('Selected diamond product was not found.');
        }

        $buckets = [];
        if ($this->ready()) {
            $buckets = $this->db->table('diamond_chalni_stocks cs')
                ->select('cs.*, sh.name AS shape_name, sh.code AS shape_code, sz.size_code, sz.size_label, sz.chalni_label, sz.length_mm, sz.width_mm')
                ->join('diamond_shape_masters sh', 'sh.id=cs.shape_id', 'left')
                ->join('diamond_size_masters sz', 'sz.id=cs.size_id', 'left')
                ->where('cs.item_id', $itemId)->where('cs.is_active', 1)
                ->orderBy('sh.sort_order', 'ASC')->orderBy('cs.id', 'ASC')
                ->get()->getResultArray();
        }

        $tracedPcs = 0.0;
        $tracedCts = 0.0;
        foreach ($buckets as &$bucket) {
            $tracedPcs += (float) ($bucket['pcs_balance'] ?? 0);
            $tracedCts += (float) ($bucket['carat_balance'] ?? 0);
            $bucket['dimension_label'] = $this->dimensionLabel($bucket);
        }
        unset($bucket);

        $ledgerPcs = round((float) ($product['pcs_balance'] ?? 0), 3);
        $ledgerCts = round((float) ($product['carat_balance'] ?? 0), 3);
        $tracedPcs = round($tracedPcs, 3);
        $tracedCts = round($tracedCts, 3);
        $bagged = $this->baggedTotals($itemId);

        return [
            'product' => $product,
            'buckets' => $buckets,
            'stats' => [
                'ledger_pcs' => $ledgerPcs,
                'ledger_cts' => $ledgerCts,
                'traced_pcs' => $tracedPcs,
                'traced_cts' => $tracedCts,
                'untraced_pcs' => round(max(0, $ledgerPcs - $tracedPcs), 3),
                'untraced_cts' => round(max(0, $ledgerCts - $tracedCts), 3),
                'over_traced_pcs' => round(max(0, $tracedPcs - $ledgerPcs), 3),
                'over_traced_cts' => round(max(0, $tracedCts - $ledgerCts), 3),
                'bagged_pcs' => $bagged['pcs'],
                'bagged_cts' => $bagged['cts'],
                'category_count' => count($buckets),
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    public function shapes(): array
    {
        return $this->db->table('diamond_shape_masters')
            ->where('is_active', 1)->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')
            ->get()->getResultArray();
    }

    /** @return list<array<string,mixed>> */
    public function sizes(): array
    {
        return $this->db->table('diamond_size_masters')
            ->where('is_active', 1)->orderBy('shape_id', 'ASC')->orderBy('sort_order', 'ASC')->orderBy('size_label', 'ASC')
            ->get()->getResultArray();
    }

    /** @param array<string,mixed> $input */
    public function save(array $input, int $userId): int
    {
        if (! $this->ready()) {
            throw new RuntimeException('Run the latest database migration before adding chalni stock.');
        }
        $id = (int) ($input['id'] ?? 0);
        $itemId = (int) ($input['item_id'] ?? 0);
        $shapeId = (int) ($input['shape_id'] ?? 0);
        $sizeId = (int) ($input['size_id'] ?? 0);
        $category = $this->normalizeCategory((string) ($input['category_label'] ?? ''));
        $pcs = round((float) ($input['pcs_balance'] ?? 0), 3);
        $cts = round((float) ($input['carat_balance'] ?? 0), 3);
        $notes = trim((string) ($input['notes'] ?? ''));

        if (! $this->db->table('items')->where('id', $itemId)->countAllResults()) {
            throw new RuntimeException('Select a valid diamond product.');
        }
        if (! $this->db->table('diamond_shape_masters')->where('id', $shapeId)->where('is_active', 1)->countAllResults()) {
            throw new RuntimeException('Select a valid diamond shape.');
        }
        if ($pcs < 0 || $cts < 0 || ($pcs <= self::EPSILON && $cts <= self::EPSILON)) {
            throw new RuntimeException('Enter a positive PCS or carat balance.');
        }

        $size = null;
        if ($sizeId > 0) {
            $size = $this->db->table('diamond_size_masters')->where('id', $sizeId)->where('shape_id', $shapeId)->where('is_active', 1)->get()->getRowArray();
            if (! $size) {
                throw new RuntimeException('Selected size does not belong to the selected shape.');
            }
            if ($category === '') {
                $category = $this->normalizeCategory((string) (($size['chalni_label'] ?? '') ?: ($size['size_label'] ?? '')));
            }
        }
        if ($category === '') {
            throw new RuntimeException('Chalni/category is required. Select a size or enter a custom category.');
        }

        $existing = null;
        if ($id > 0) {
            $existing = $this->db->table('diamond_chalni_stocks')->where('id', $id)->where('item_id', $itemId)->get()->getRowArray();
            if (! $existing) {
                throw new RuntimeException('Chalni stock row was not found for this product.');
            }
        } else {
            $existing = $this->db->table('diamond_chalni_stocks')
                ->where('item_id', $itemId)->where('shape_id', $shapeId)->where('category_label', $category)
                ->get()->getRowArray();
            $id = (int) ($existing['id'] ?? 0);
        }

        $beforePcs = (float) ($existing['pcs_balance'] ?? 0);
        $beforeCts = (float) ($existing['carat_balance'] ?? 0);
        $now = date('Y-m-d H:i:s');
        $data = [
            'item_id' => $itemId,
            'shape_id' => $shapeId,
            'size_id' => $sizeId > 0 ? $sizeId : null,
            'category_label' => $category,
            'pcs_balance' => $pcs,
            'carat_balance' => $cts,
            'source_type' => 'MANUAL',
            'source_reference' => 'Stock classification',
            'notes' => $notes !== '' ? $notes : null,
            'is_active' => 1,
            'updated_by' => $userId > 0 ? $userId : null,
            'updated_at' => $now,
        ];

        if ($id > 0) {
            $this->db->table('diamond_chalni_stocks')->where('id', $id)->update($data);
        } else {
            $data['created_by'] = $userId > 0 ? $userId : null;
            $data['created_at'] = $now;
            $this->db->table('diamond_chalni_stocks')->insert($data);
            $id = (int) $this->db->insertID();
        }

        $this->recordMovement(
            $id,
            'MANUAL_RECOUNT',
            null,
            null,
            $pcs - $beforePcs,
            $cts - $beforeCts,
            $pcs,
            $cts,
            $notes !== '' ? $notes : 'Manual chalni stock classification',
            $userId
        );
        return $id;
    }

    public function applyIssue(int $issueId): void
    {
        if (! $this->ready()) {
            return;
        }
        $lines = $this->transactionLines('issue_lines', 'issue_id', $issueId);
        foreach ($lines as $line) {
            if ($this->hasSourceMovement('issue_lines', (int) $line['id'])) {
                continue;
            }
            $bucket = $this->findBucket((int) $line['item_id'], (int) $line['shape_id'], (int) $line['size_id']);
            if (! $bucket) {
                continue; // This issue is consumed from the product's untraced balance.
            }
            $pcs = min((float) ($bucket['pcs_balance'] ?? 0), (float) ($line['pcs'] ?? 0));
            $cts = min((float) ($bucket['carat_balance'] ?? 0), (float) ($line['carat'] ?? 0));
            if ($pcs <= self::EPSILON && $cts <= self::EPSILON) {
                continue;
            }
            $this->changeBucket(
                $bucket,
                -$pcs,
                -$cts,
                'ISSUE',
                'issue_lines',
                (int) $line['id'],
                'Issued from traced bag ' . (string) ($line['bag_no'] ?? ''),
                (int) ($line['created_by'] ?? 0),
                (string) ($line['transaction_date'] ?? date('Y-m-d'))
            );
        }
    }

    public function reverseIssue(int $issueId): void
    {
        $this->reverseHeaderMovements('issue_lines', 'issue_id', $issueId);
    }

    public function applyReturn(int $returnId): void
    {
        if (! $this->ready()) {
            return;
        }
        $lines = $this->transactionLines('return_lines', 'return_id', $returnId);
        foreach ($lines as $line) {
            if ($this->hasSourceMovement('return_lines', (int) $line['id'])) {
                continue;
            }
            $bucket = $this->findBucket((int) $line['item_id'], (int) $line['shape_id'], (int) $line['size_id']);
            if (! $bucket) {
                $bucket = $this->createBucketFromSize($line, (int) ($line['created_by'] ?? 0));
            }
            if (! $bucket) {
                continue;
            }
            $this->changeBucket(
                $bucket,
                (float) ($line['pcs'] ?? 0),
                (float) ($line['carat'] ?? 0),
                'RETURN',
                'return_lines',
                (int) $line['id'],
                'Returned into original traced bag ' . (string) ($line['bag_no'] ?? ''),
                (int) ($line['created_by'] ?? 0),
                (string) ($line['transaction_date'] ?? date('Y-m-d'))
            );
        }
    }

    public function reverseReturn(int $returnId): void
    {
        $this->reverseHeaderMovements('return_lines', 'return_id', $returnId);
    }

    /** @param list<array<string,mixed>> $rows */
    public function assertPackable(array $rows, int $excludeBagId = 0): void
    {
        if (! $this->ready()) {
            return;
        }
        $requested = [];
        foreach ($rows as $row) {
            $bucket = $this->findBucket((int) ($row['inventory_item_id'] ?? 0), (int) ($row['shape_master_id'] ?? 0), (int) ($row['size_master_id'] ?? 0));
            if (! $bucket) {
                continue;
            }
            $bucketId = (int) $bucket['id'];
            $requested[$bucketId]['bucket'] = $bucket;
            $requested[$bucketId]['cts'] = round((float) ($requested[$bucketId]['cts'] ?? 0) + (float) ($row['weight_cts'] ?? 0), 3);
        }
        foreach ($requested as $request) {
            $bucket = $request['bucket'];
            $reserved = $this->reservedForBucket($bucket, $excludeBagId);
            $available = round(max(0, (float) $bucket['carat_balance'] - $reserved), 3);
            if ((float) $request['cts'] > ($available + self::EPSILON)) {
                throw new RuntimeException(
                    'Chalni ' . (string) $bucket['category_label'] . ' has only '
                    . number_format($available, 3) . ' unbagged cts available.'
                );
            }
        }
    }

    /** @return array<string,float> */
    private function baggedTotals(int $itemId): array
    {
        if (! $this->db->tableExists('diamond_bag_items')) {
            return ['pcs' => 0.0, 'cts' => 0.0];
        }
        $row = $this->db->table('diamond_bag_items')
            ->select('COALESCE(SUM(pcs_available),0) AS pcs, COALESCE(SUM(weight_cts_available),0) AS cts', false)
            ->where('inventory_item_id', $itemId)->get()->getRowArray();
        return [
            'pcs' => round((float) ($row['pcs'] ?? 0), 3),
            'cts' => round((float) ($row['cts'] ?? 0), 3),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function transactionLines(string $table, string $headerField, int $headerId): array
    {
        $headerTable = $table === 'issue_lines' ? 'issue_headers' : 'return_headers';
        $headerAlias = $table === 'issue_lines' ? 'ih' : 'rh';
        $dateField = $table === 'issue_lines' ? 'issue_date' : 'return_date';
        return $this->db->table($table . ' ln')
            ->select('ln.id, ln.item_id, ln.pcs, ln.carat, bi.shape_master_id AS shape_id, bi.size_master_id AS size_id, b.bag_no, '
                . $headerAlias . '.' . $dateField . ' AS transaction_date, ' . $headerAlias . '.created_by')
            ->join($headerTable . ' ' . $headerAlias, $headerAlias . '.id=ln.' . $headerField, 'inner')
            ->join('diamond_bag_items bi', 'bi.id=ln.bag_item_id', 'inner')
            ->join('diamond_bags b', 'b.id=bi.bag_id', 'left')
            ->where('ln.' . $headerField, $headerId)
            ->where('ln.bag_item_id IS NOT NULL', null, false)
            ->get()->getResultArray();
    }

    /** @return array<string,mixed>|null */
    private function findBucket(int $itemId, int $shapeId, int $sizeId): ?array
    {
        if ($itemId <= 0 || $shapeId <= 0 || $sizeId <= 0 || ! $this->ready()) {
            return null;
        }
        $exact = $this->db->table('diamond_chalni_stocks')->where('item_id', $itemId)->where('shape_id', $shapeId)
            ->where('size_id', $sizeId)->where('is_active', 1)->get()->getRowArray();
        if ($exact) {
            return $exact;
        }
        $size = $this->db->table('diamond_size_masters')->select('chalni_label, size_label')->where('id', $sizeId)->get()->getRowArray();
        if (! $size) {
            return null;
        }
        $label = trim((string) (($size['chalni_label'] ?? '') ?: ($size['size_label'] ?? '')));
        if ($label === '') {
            return null;
        }
        $buckets = $this->db->table('diamond_chalni_stocks')->where('item_id', $itemId)->where('shape_id', $shapeId)
            ->where('is_active', 1)->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($buckets as $bucket) {
            if ($this->sameCategory((string) $bucket['category_label'], $label)) {
                return $bucket;
            }
        }
        $sizeRange = $this->numericRange($label);
        if (! $sizeRange) {
            return null;
        }
        $matches = [];
        foreach ($buckets as $bucket) {
            $bucketRange = $this->numericRange((string) $bucket['category_label']);
            if ($bucketRange && $bucketRange[0] <= ($sizeRange[0] + self::EPSILON) && $bucketRange[1] >= ($sizeRange[1] - self::EPSILON)) {
                $matches[] = ['span' => $bucketRange[1] - $bucketRange[0], 'row' => $bucket];
            }
        }
        usort($matches, static fn(array $a, array $b): int => $a['span'] <=> $b['span']);
        return $matches[0]['row'] ?? null;
    }

    /** @param array<string,mixed> $line @return array<string,mixed>|null */
    private function createBucketFromSize(array $line, int $userId): ?array
    {
        $size = $this->db->table('diamond_size_masters')->where('id', (int) ($line['size_id'] ?? 0))->get()->getRowArray();
        if (! $size) {
            return null;
        }
        $category = $this->normalizeCategory((string) (($size['chalni_label'] ?? '') ?: ($size['size_label'] ?? '')));
        if ($category === '') {
            return null;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->table('diamond_chalni_stocks')->insert([
            'item_id' => (int) $line['item_id'],
            'shape_id' => (int) $line['shape_id'],
            'size_id' => (int) $line['size_id'],
            'category_label' => $category,
            'pcs_balance' => 0,
            'carat_balance' => 0,
            'source_type' => 'RETURN_CLASSIFICATION',
            'source_reference' => 'Created from exact bag return',
            'is_active' => 1,
            'created_by' => $userId > 0 ? $userId : null,
            'updated_by' => $userId > 0 ? $userId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->db->table('diamond_chalni_stocks')->where('id', (int) $this->db->insertID())->get()->getRowArray() ?: null;
    }

    /** @param array<string,mixed> $bucket */
    private function changeBucket(
        array $bucket,
        float $pcsDelta,
        float $ctsDelta,
        string $type,
        ?string $sourceTable,
        ?int $sourceId,
        string $notes,
        int $userId,
        string $date
    ): void {
        $newPcs = round(max(0, (float) $bucket['pcs_balance'] + $pcsDelta), 3);
        $newCts = round(max(0, (float) $bucket['carat_balance'] + $ctsDelta), 3);
        $this->db->table('diamond_chalni_stocks')->where('id', (int) $bucket['id'])->update([
            'pcs_balance' => $newPcs,
            'carat_balance' => $newCts,
            'updated_by' => $userId > 0 ? $userId : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->recordMovement((int) $bucket['id'], $type, $sourceTable, $sourceId, $pcsDelta, $ctsDelta, $newPcs, $newCts, $notes, $userId, $date);
    }

    private function reverseHeaderMovements(string $lineTable, string $headerField, int $headerId): void
    {
        if (! $this->ready()) {
            return;
        }
        $lineIds = array_map(
            static fn(array $row): int => (int) $row['id'],
            $this->db->table($lineTable)->select('id')->where($headerField, $headerId)->get()->getResultArray()
        );
        if ($lineIds === []) {
            return;
        }
        $movements = $this->db->table('diamond_chalni_stock_movements')
            ->where('source_table', $lineTable)->whereIn('source_id', $lineIds)->orderBy('id', 'DESC')->get()->getResultArray();
        foreach ($movements as $movement) {
            $bucket = $this->db->table('diamond_chalni_stocks')->where('id', (int) $movement['chalni_stock_id'])->get()->getRowArray();
            if (! $bucket) {
                continue;
            }
            $this->db->table('diamond_chalni_stocks')->where('id', (int) $bucket['id'])->update([
                'pcs_balance' => round(max(0, (float) $bucket['pcs_balance'] - (float) $movement['pcs_delta']), 3),
                'carat_balance' => round(max(0, (float) $bucket['carat_balance'] - (float) $movement['carat_delta']), 3),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->db->table('diamond_chalni_stock_movements')->where('source_table', $lineTable)->whereIn('source_id', $lineIds)->delete();
    }

    /** @param array<string,mixed> $bucket */
    private function reservedForBucket(array $bucket, int $excludeBagId = 0): float
    {
        if (! $this->db->tableExists('diamond_bag_items')) {
            return 0.0;
        }
        $builder = $this->db->table('diamond_bag_items')
            ->select('size_master_id, weight_cts_available')->where('inventory_item_id', (int) $bucket['item_id'])
            ->where('shape_master_id', (int) $bucket['shape_id']);
        if ($excludeBagId > 0) {
            $builder->where('bag_id !=', $excludeBagId);
        }
        $rows = $builder->get()->getResultArray();
        $reserved = 0.0;
        foreach ($rows as $row) {
            $matched = $this->findBucket((int) $bucket['item_id'], (int) $bucket['shape_id'], (int) ($row['size_master_id'] ?? 0));
            if ($matched && (int) $matched['id'] === (int) $bucket['id']) {
                $reserved += (float) ($row['weight_cts_available'] ?? 0);
            }
        }
        return round($reserved, 3);
    }

    private function hasSourceMovement(string $table, int $sourceId): bool
    {
        return $this->db->table('diamond_chalni_stock_movements')
            ->where('source_table', $table)->where('source_id', $sourceId)->countAllResults() > 0;
    }

    private function recordMovement(
        int $stockId,
        string $type,
        ?string $sourceTable,
        ?int $sourceId,
        float $pcsDelta,
        float $ctsDelta,
        float $pcsAfter,
        float $ctsAfter,
        string $notes,
        int $userId,
        ?string $date = null
    ): void {
        $this->db->table('diamond_chalni_stock_movements')->insert([
            'chalni_stock_id' => $stockId,
            'movement_date' => $date && strtotime($date) !== false ? date('Y-m-d', strtotime($date)) : date('Y-m-d'),
            'movement_type' => $type,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'pcs_delta' => round($pcsDelta, 3),
            'carat_delta' => round($ctsDelta, 3),
            'pcs_balance_after' => round($pcsAfter, 3),
            'carat_balance_after' => round($ctsAfter, 3),
            'notes' => $notes,
            'created_by' => $userId > 0 ? $userId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array{0:float,1:float}|null */
    private function numericRange(string $value): ?array
    {
        $value = trim(str_replace(['–', '—'], '-', $value));
        $value = ltrim($value, '.');
        if (preg_match('/^(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)$/', $value, $match) !== 1) {
            return null;
        }
        // Leading-zero sieve codes are labels, not decimal numeric intervals.
        if ((strlen($match[1]) > 1 && $match[1][0] === '0') || (strlen($match[2]) > 1 && $match[2][0] === '0')) {
            return null;
        }
        $from = (float) $match[1];
        $to = (float) $match[2];
        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    private function sameCategory(string $left, string $right): bool
    {
        $normalize = static fn(string $value): string => strtoupper((string) preg_replace('/\s+/', '', str_replace(['–', '—'], '-', trim($value))));
        return $normalize($left) === $normalize($right);
    }

    private function normalizeCategory(string $value): string
    {
        $value = trim(str_replace(['–', '—'], '-', $value));
        $value = (string) preg_replace('/\s+/', '', $value);
        if (mb_strlen($value) > 80) {
            $value = mb_substr($value, 0, 80);
        }
        return $value;
    }

    /** @param array<string,mixed> $row */
    private function productLabel(array $row): string
    {
        $primary = trim((string) ($row['clarity'] ?? ''));
        if ($primary === '' || strtoupper($primary) === 'NA') {
            $primary = trim((string) ($row['diamond_type'] ?? 'Diamond'));
        }
        $meta = array_values(array_unique(array_filter([
            trim((string) ($row['diamond_type'] ?? '')),
            trim((string) ($row['shape'] ?? '')),
            trim((string) ($row['color'] ?? '')),
        ], static fn(string $value): bool => $value !== '' && strtoupper($value) !== 'NA' && strcasecmp($value, $primary) !== 0)));
        return $primary . ($meta !== [] ? ' · ' . implode(' / ', $meta) : '');
    }

    /** @param array<string,mixed> $row */
    private function dimensionLabel(array $row): string
    {
        $length = (float) ($row['length_mm'] ?? 0);
        $width = (float) ($row['width_mm'] ?? 0);
        if ($length <= 0) {
            return '-';
        }
        $format = static fn(float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
        return abs($length - $width) < self::EPSILON
            ? $format($length) . ' mm'
            : $format($length) . ' × ' . $format($width) . ' mm';
    }
}
