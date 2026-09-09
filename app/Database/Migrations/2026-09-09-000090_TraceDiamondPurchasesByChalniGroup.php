<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TraceDiamondPurchasesByChalniGroup extends Migration
{
    /** @var array<string,array{name:string,range:string,chalnis:list<string>}> */
    private const GROUPS = [
        'MINUS_TWO' => [
            'name' => 'Minus Two (-2)',
            'range' => '000 se +1.5',
            'chalnis' => ['-000000', '000000-00000', '00000-0000', '0000-000', '000-00', '00-0', '0-1', '1-1.5'],
        ],
        'STAR' => [
            'name' => 'Star (+2 to +5.5)',
            'range' => '+2 se +5.5',
            'chalnis' => ['1.5-2', '2-2.5', '2.5-3', '3-3.5', '3.5-4', '4-4.5', '4.5-5', '5-5.5'],
        ],
        'MELLE' => [
            'name' => 'Melle (+6 to +11)',
            'range' => '+6 se +11',
            'chalnis' => ['5.5-6', '6-6.5', '6.5-7', '7-7.5', '7.5-8', '8-8.5', '8.5-9', '9-9.5', '9.5-10', '10-10.5', '10.5-11'],
        ],
        'POINTER' => [
            'name' => 'Pointer (+11 Up)',
            'range' => '11.5 se upar',
            'chalnis' => ['11-11.5', '11.5-12', '12-12.5', '12.5-13', '13-13.5', '13.5-14', '14-14.5', '14.5-15', '15-15.5', '15.5-16', '16-16.5', '16.5-17', '17-17.5', '17.5-18', '18', '18.5', '19', '19.5', '20'],
        ],
    ];

    public function up(): void
    {
        $this->createGroupTables();
        $this->seedGroupsAndRoundChalnis();
        $this->extendPurchaseLines();
        $this->extendChalniStock();
        $this->backfillHistoricalPurchaseClassification();
    }

    public function down(): void
    {
        $this->dropIndex('diamond_chalni_stocks', 'idx_diamond_chalni_group');
        $this->dropIndex('purchase_lines', 'idx_purchase_line_shape_group');
        $this->dropColumn('diamond_chalni_stocks', 'chalni_group_id');
        $this->dropColumn('purchase_lines', 'chalni_group_id');
        $this->dropColumn('purchase_lines', 'shape_master_id');
        $this->forge->dropTable('diamond_chalni_group_sizes', true);
        $this->forge->dropTable('diamond_chalni_groups', true);
    }

    private function createGroupTables(): void
    {
        if (! $this->db->tableExists('diamond_chalni_groups')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'code' => ['type' => 'VARCHAR', 'constraint' => 30],
                'name' => ['type' => 'VARCHAR', 'constraint' => 80],
                'range_label' => ['type' => 'VARCHAR', 'constraint' => 80],
                'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('code', 'uq_diamond_chalni_group_code');
            $this->forge->createTable('diamond_chalni_groups', true);
        }

        if (! $this->db->tableExists('diamond_chalni_group_sizes')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'group_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'size_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addPrimaryKey('id');
            $this->forge->addUniqueKey('size_id', 'uq_diamond_size_purchase_group');
            $this->forge->addKey('group_id', false, false, 'idx_diamond_chalni_group_size_group');
            $this->forge->createTable('diamond_chalni_group_sizes', true);
        }
    }

    private function seedGroupsAndRoundChalnis(): void
    {
        if (! $this->db->tableExists('diamond_shape_masters') || ! $this->db->tableExists('diamond_size_masters')) {
            return;
        }
        $round = $this->db->table('diamond_shape_masters')->select('id')->where('code', 'ROUND')->get()->getRowArray();
        $now = date('Y-m-d H:i:s');
        $position = 0;
        foreach (self::GROUPS as $code => $group) {
            $position++;
            $existing = $this->db->table('diamond_chalni_groups')->select('id')->where('code', $code)->get()->getRowArray();
            $data = [
                'name' => $group['name'],
                'range_label' => $group['range'],
                'sort_order' => $position * 10,
                'is_active' => 1,
                'updated_at' => $now,
            ];
            if ($existing) {
                $groupId = (int) $existing['id'];
                $this->db->table('diamond_chalni_groups')->where('id', $groupId)->update($data);
            } else {
                $this->db->table('diamond_chalni_groups')->insert(['code' => $code, 'created_at' => $now] + $data);
                $groupId = (int) $this->db->insertID();
            }
            if (! $round || $groupId <= 0) {
                continue;
            }
            $sizes = $this->db->table('diamond_size_masters')
                ->select('id')
                ->where('shape_id', (int) $round['id'])
                ->whereIn('chalni_label', $group['chalnis'])
                ->get()
                ->getResultArray();
            foreach ($sizes as $size) {
                $sizeId = (int) $size['id'];
                $mapped = $this->db->table('diamond_chalni_group_sizes')->where('size_id', $sizeId)->get()->getRowArray();
                if ($mapped) {
                    $this->db->table('diamond_chalni_group_sizes')->where('id', (int) $mapped['id'])->update(['group_id' => $groupId]);
                } else {
                    $this->db->table('diamond_chalni_group_sizes')->insert([
                        'group_id' => $groupId,
                        'size_id' => $sizeId,
                        'created_at' => $now,
                    ]);
                }
            }
        }
    }

    private function extendPurchaseLines(): void
    {
        if (! $this->db->tableExists('purchase_lines')) {
            return;
        }
        $this->addColumn('purchase_lines', 'shape_master_id', [
            'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'item_id',
        ]);
        $this->addColumn('purchase_lines', 'chalni_group_id', [
            'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'shape_master_id',
        ]);
        $this->ensureIndex('purchase_lines', 'idx_purchase_line_shape_group', ['shape_master_id', 'chalni_group_id']);
    }

    private function extendChalniStock(): void
    {
        if (! $this->db->tableExists('diamond_chalni_stocks')) {
            return;
        }
        $this->addColumn('diamond_chalni_stocks', 'chalni_group_id', [
            'type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'size_id',
        ]);
        $this->ensureIndex('diamond_chalni_stocks', 'idx_diamond_chalni_group', ['chalni_group_id']);
    }

    private function backfillHistoricalPurchaseClassification(): void
    {
        if (! $this->db->tableExists('purchase_lines') || ! $this->db->tableExists('items')) {
            return;
        }
        $shapeMap = [];
        foreach ($this->db->table('diamond_shape_masters')->select('id, code, name')->get()->getResultArray() as $shape) {
            $shapeMap[$this->key((string) $shape['code'])] = (int) $shape['id'];
            $shapeMap[$this->key((string) $shape['name'])] = (int) $shape['id'];
        }
        $sizeGroupMap = [];
        foreach ($this->db->table('diamond_chalni_group_sizes gs')
            ->select('gs.group_id, sz.shape_id, sz.chalni_label')
            ->join('diamond_size_masters sz', 'sz.id = gs.size_id', 'inner')
            ->get()->getResultArray() as $mapping) {
            $sizeGroupMap[(int) $mapping['shape_id']][$this->key((string) $mapping['chalni_label'])] = (int) $mapping['group_id'];
        }
        $rows = $this->db->table('purchase_lines pl')
            ->select('pl.id, i.shape, i.chalni_from, i.chalni_to')
            ->join('items i', 'i.id = pl.item_id', 'inner')
            ->get()
            ->getResultArray();
        foreach ($rows as $row) {
            $shapeId = $shapeMap[$this->key((string) ($row['shape'] ?? ''))] ?? 0;
            if ($shapeId <= 0) {
                continue;
            }
            $from = trim((string) ($row['chalni_from'] ?? ''));
            $to = trim((string) ($row['chalni_to'] ?? ''));
            $chalni = $from !== '' && $to !== '' ? $from . '-' . $to : ($from ?: $to);
            $groupId = $sizeGroupMap[$shapeId][$this->key($chalni)] ?? null;
            $this->db->table('purchase_lines')->where('id', (int) $row['id'])->update([
                'shape_master_id' => $shapeId,
                'chalni_group_id' => $groupId,
            ]);
        }
    }

    /** @param array<string,mixed> $definition */
    private function addColumn(string $table, string $column, array $definition): void
    {
        if (! $this->db->fieldExists($column, $table)) {
            $this->forge->addColumn($table, [$column => $definition]);
        }
    }

    /** @param list<string> $columns */
    private function ensureIndex(string $table, string $name, array $columns): void
    {
        if ($this->db->query('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$name])->getResultArray() !== []) {
            return;
        }
        $quoted = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
        $this->db->query('CREATE INDEX `' . $name . '` ON `' . $table . '` (' . $quoted . ')');
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        if ($this->db->query('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$name])->getResultArray() !== []) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP INDEX `' . $name . '`');
        }
    }

    private function dropColumn(string $table, string $column): void
    {
        if ($this->db->tableExists($table) && $this->db->fieldExists($column, $table)) {
            $this->forge->dropColumn($table, $column);
        }
    }

    private function key(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Z0-9]+/i', '', trim($value)));
    }
}
