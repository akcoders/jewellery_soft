<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/** Adds reference metadata only: no issue, receiving, stock, payment or ledger posting. */
class HistoricalDiamondSizeImportService
{
    public function __construct(private ?BaseConnection $db = null)
    {
        $this->db ??= db_connect();
    }

    public function import(array $manifest, bool $apply = true): array
    {
        if (($manifest['version'] ?? null) !== 1 || ! preg_match('/^[a-f0-9]{64}$/', $manifest['source_sha256'] ?? '')) {
            throw new RuntimeException('Invalid historical diamond source manifest.');
        }
        $reader = new HistoricalDiamondSizeWorkbook();
        $targets = [];
        foreach ($manifest['blocks'] as $block) {
            if ($block['status'] !== 'matched') {
                continue;
            }
            $signature = $reader->signature($block['lines'], true);
            if ($signature != $block['signature'] || ! $block['target'] || isset($signature['UNKNOWN'])) {
                throw new RuntimeException('Invalid verified size reference: ' . $block['key']);
            }
            foreach ($signature as $family => $totals) {
                if (($block['target']['signature'][$family] ?? null) != $totals) {
                    throw new RuntimeException('Source/target product quantities no longer reconcile: ' . $block['key']);
                }
            }
            $targetKey = $block['target']['sheet'] . ':' . $block['target']['row'];
            if (isset($targets[$targetKey])) {
                throw new RuntimeException('More than one size block targets the same completed order.');
            }
            $targets[$targetKey] = true;
        }
        $summary = ['mapped_orders' => 0, 'inserted_lines' => 0, 'existing_lines' => 0, 'review' => 0, 'unmatched' => 0, 'skipped' => 0, 'blocks' => []];
        if ($apply) {
            $this->db->transException(true)->transBegin();
        }
        try {
            foreach ($manifest['blocks'] as $block) {
                $status = $block['status'];
                $reason = $block['reason'];
                $orderId = null;
                if ($status === 'matched') {
                    [$orderId, $reason] = $this->resolveOrder($block);
                    if ($orderId === null) {
                        $status = 'skipped';
                    } else {
                        $summary['mapped_orders']++;
                        if ($apply) {
                            foreach ($block['lines'] as $line) {
                                $key = [
                                    'source_sha256' => $manifest['source_sha256'],
                                    'source_sheet' => $block['sheet'], 'source_row' => $line['source_row'],
                                ];
                                $existing = $this->db->table('order_diamond_size_details')->where($key)->get()->getRowArray();
                                if ($existing) {
                                    if ((int) $existing['order_id'] !== $orderId || abs((float) $existing['pcs'] - $line['pcs']) > 0.0005 || abs((float) $existing['weight_cts'] - $line['weight_cts']) > 0.0005) {
                                        throw new RuntimeException('Conflicting size reference already exists for ' . $block['key']);
                                    }
                                    $summary['existing_lines']++;
                                    continue;
                                }
                                $this->db->table('order_diamond_size_details')->insert($key + [
                                    'order_id' => $orderId, 'order_receive_detail_id' => null,
                                    'source_file' => $manifest['source_file'], 'source_block' => $block['key'],
                                    'quality' => $line['quality'], 'shade' => $line['shade'],
                                    'shape_name' => $line['shape_name'], 'size_label' => $line['size_label'], 'chalni_label' => null,
                                    'pcs' => $line['pcs'], 'weight_cts' => $line['weight_cts'],
                                    'source_kind' => 'HISTORICAL_ISSUEMENT', 'match_status' => 'matched',
                                    'notes' => $line['notes'],
                                    'source_data_json' => json_encode($line['raw_cells'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                                    'created_at' => date('Y-m-d H:i:s'),
                                ]);
                                $summary['inserted_lines']++;
                            }
                        }
                    }
                }
                if (isset($summary[$status])) {
                    $summary[$status]++;
                }
                $summary['blocks'][] = ['source' => $block['key'], 'order_id' => $orderId, 'status' => $status, 'reason' => $reason];
                if ($apply) {
                    $key = ['source_sha256' => $manifest['source_sha256'], 'source_block' => $block['key']];
                    $audit = [
                        'source_file' => $manifest['source_file'], 'order_id' => $orderId,
                        'status' => $status, 'reason' => $reason,
                        'source_data_json' => json_encode($block, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ];
                    if ($this->db->table('historical_diamond_size_blocks')->where($key)->countAllResults()) {
                        $this->db->table('historical_diamond_size_blocks')->where($key)->update($audit);
                    } else {
                        $this->db->table('historical_diamond_size_blocks')->insert($key + $audit);
                    }
                }
            }
            if ($apply) {
                if (! $this->db->transStatus()) {
                    throw new RuntimeException('Diamond size reference import failed.');
                }
                $this->db->transCommit();
            }
        } catch (Throwable $error) {
            if ($apply) {
                $this->db->transRollback();
            }
            throw $error;
        }
        return $summary;
    }

    private function resolveOrder(array $block): array
    {
        $target = $block['target'];
        $rows = [];
        if ($this->db->tableExists('production_ready_items')) {
            $rows = $this->db->table('production_ready_items')->select('order_id')
                ->where('source_sheet', $target['sheet'])->where('source_row', $target['row'])
                ->where('order_id IS NOT NULL', null, false)->get()->getResultArray();
        }
        if (count($rows) > 1) {
            return [null, 'Multiple historical ready records exist for the target sheet/row.'];
        }
        $builder = $this->db->table('orders')->select('id, order_no');
        if ($rows) {
            $builder->where('id', (int) $rows[0]['order_id']);
        } else {
            $builder->where('order_no', $target['order_no']);
        }
        $orders = $builder->get()->getResultArray();
        if (count($orders) !== 1) {
            return [null, 'The mapped completed order is missing or ambiguous in this database.'];
        }
        $orderId = (int) $orders[0]['id'];
        $received = $this->db->table('order_receive_details')
            ->select('component_type AS type, component_name AS name, pcs, weight_cts')
            ->where('order_id', $orderId)->where('component_type', 'diamond')->get()->getResultArray();
        $signature = (new HistoricalDiamondSizeWorkbook())->signature($received);
        if ($signature != $target['signature']) {
            return [null, 'Current receiving PCS/carats/product totals differ from the verified ready workbook. No size rows were imported.'];
        }
        return [$orderId, $block['reason']];
    }
}
