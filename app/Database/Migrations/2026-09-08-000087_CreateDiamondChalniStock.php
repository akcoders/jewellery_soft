<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class CreateDiamondChalniStock extends Migration
{
    private const SUPPLIED_TOTAL_CTS = 146.190;
    private const ORIGINAL_OPENING_REFERENCE = 'DIA-OPEN-20260401';
    private const ORIGINAL_VVS_ROUND_OPENING_CTS = 171.890;

    /** @var array<string,float> */
    private const VVS_ROUND_STOCK = [
        '000000-00000' => 3.630,
        '00000-0000' => 4.330,
        '0000-000' => 3.000,
        '000-00' => 5.990,
        '00-0' => 9.800,
        '0-1' => 10.300,
        '1-2' => 19.810,
        '2-3' => 16.670,
        '3-4' => 23.380,
        '4-5' => 11.320,
        '5-6.5' => 15.470,
        '6.5-7' => 19.450,
        '7-8' => 0.750,
        '8-9' => 0.250,
        '9-10' => 1.340,
        '10-11' => 0.280,
        '11-12' => 0.420,
    ];

    public function up()
    {
        $this->createStockTable();
        $this->createMovementTable();
        $this->seedVvsRoundStock();
    }

    public function down()
    {
        $this->forge->dropTable('diamond_chalni_stock_movements', true);
        $this->forge->dropTable('diamond_chalni_stocks', true);
    }

    private function createStockTable(): void
    {
        if ($this->db->tableExists('diamond_chalni_stocks')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'item_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'shape_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'size_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'category_label' => ['type' => 'VARCHAR', 'constraint' => 80],
            'pcs_balance' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'carat_balance' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'MANUAL'],
            'source_reference' => ['type' => 'VARCHAR', 'constraint' => 180, 'null' => true],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['item_id', 'shape_id', 'category_label'], 'uq_diamond_chalni_bucket');
        $this->forge->addKey('item_id', false, false, 'idx_diamond_chalni_item');
        $this->forge->addKey(['shape_id', 'size_id'], false, false, 'idx_diamond_chalni_shape_size');
        $this->forge->createTable('diamond_chalni_stocks', true);
    }

    private function createMovementTable(): void
    {
        if ($this->db->tableExists('diamond_chalni_stock_movements')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'chalni_stock_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'movement_date' => ['type' => 'DATE'],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'source_table' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'source_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'pcs_delta' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'carat_delta' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'pcs_balance_after' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'carat_balance_after' => ['type' => 'DECIMAL', 'constraint' => '18,3', 'default' => 0],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['chalni_stock_id', 'movement_date'], false, false, 'idx_chalni_movement_stock_date');
        $this->forge->addKey(['source_table', 'source_id'], false, false, 'idx_chalni_movement_source');
        $this->forge->createTable('diamond_chalni_stock_movements', true);
    }

    private function seedVvsRoundStock(): void
    {
        if (! $this->db->tableExists('items')
            || ! $this->db->tableExists('diamond_shape_masters')
            || ! $this->db->tableExists('diamond_size_masters')) {
            throw new RuntimeException('Diamond product and shape/size masters are required before chalni stock can be imported.');
        }
        if (abs(array_sum(self::VVS_ROUND_STOCK) - self::SUPPLIED_TOTAL_CTS) > 0.0005) {
            throw new RuntimeException('Supplied VVS Round chalni stock does not reconcile to 146.190 cts.');
        }

        $itemId = $this->resolveVvsRoundItemId();
        $shape = $this->db->table('diamond_shape_masters')->select('id')->where('code', 'ROUND')->get()->getRowArray();
        if (! $shape) {
            throw new RuntimeException('Round diamond shape master is missing.');
        }

        $shapeId = (int) $shape['id'];
        $sizes = [];
        foreach ($this->db->table('diamond_size_masters')->select('id, chalni_label')->where('shape_id', $shapeId)->get()->getResultArray() as $size) {
            $label = trim((string) ($size['chalni_label'] ?? ''));
            if ($label !== '') {
                $sizes[$label] = (int) $size['id'];
            }
        }

        $now = date('Y-m-d H:i:s');
        foreach (self::VVS_ROUND_STOCK as $category => $carat) {
            $sizeId = $sizes[$category] ?? null;
            $this->db->query(
                'INSERT INTO diamond_chalni_stocks
                    (item_id, shape_id, size_id, category_label, pcs_balance, carat_balance,
                     source_type, source_reference, notes, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    size_id = VALUES(size_id), carat_balance = VALUES(carat_balance),
                    source_type = VALUES(source_type), source_reference = VALUES(source_reference),
                    notes = VALUES(notes), is_active = 1, updated_at = VALUES(updated_at)',
                [
                    $itemId,
                    $shapeId,
                    $sizeId,
                    $category,
                    $carat,
                    'SUPPLIED_OPENING',
                    'VVS Round chalni stock supplied on 2026-09-08',
                    'Physical chalni-wise stock split. Product ledger total remains independently auditable.',
                    $now,
                    $now,
                ]
            );
        }
    }

    private function resolveVvsRoundItemId(): int
    {
        $historicalItemId = $this->resolveFromOriginalOpening();
        if ($historicalItemId > 0) {
            return $historicalItemId;
        }

        $fallback = 0;
        foreach ($this->db->table('items')->select('id, diamond_type, shape, clarity')->orderBy('id', 'ASC')->get()->getResultArray() as $item) {
            $clarity = $this->key((string) ($item['clarity'] ?? ''));
            $type = $this->key((string) ($item['diamond_type'] ?? ''));
            $shape = $this->key((string) ($item['shape'] ?? ''));
            if ($clarity === 'VVSVSROUND') {
                return (int) $item['id'];
            }
            $isVvsAlias = in_array($clarity, ['VVSROUND', 'VVSMIX', 'VVSVSMIX'], true)
                || in_array($type, ['VVS', 'VVSROUND', 'VVSMIX', 'VVSVSMIX'], true);
            if ($fallback === 0 && $isVvsAlias && $shape === 'ROUND') {
                $fallback = (int) $item['id'];
            }
        }
        if ($fallback > 0) {
            return $fallback;
        }
        throw new RuntimeException('The original VVS Round diamond stock item could not be identified; chalni stock import was stopped.');
    }

    private function resolveFromOriginalOpening(): int
    {
        if (! $this->db->tableExists('diamond_inventory_opening_balances')) {
            return 0;
        }

        $rows = $this->db->table('diamond_inventory_opening_balances ob')
            ->select('ob.item_id')
            ->join('items i', 'i.id = ob.item_id', 'inner')
            ->where('ob.reference_no', self::ORIGINAL_OPENING_REFERENCE)
            ->where('ob.carat', self::ORIGINAL_VVS_ROUND_OPENING_CTS)
            ->get()
            ->getResultArray();

        $itemIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) ($row['item_id'] ?? 0),
            $rows
        )));
        $itemIds = array_values(array_filter($itemIds, static fn (int $id): bool => $id > 0));

        return count($itemIds) === 1 ? $itemIds[0] : 0;
    }

    private function key(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '', trim($value)));
    }
}
