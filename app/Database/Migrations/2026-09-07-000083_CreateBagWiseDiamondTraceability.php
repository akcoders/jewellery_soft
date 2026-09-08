<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBagWiseDiamondTraceability extends Migration
{
    public function up()
    {
        $this->createShapeMaster();
        $this->seedShapes();
        $this->createSizeMaster();
        $this->seedSizesFromExistingDiamondItems();
        $this->extendDiamondBags();
        $this->extendDiamondBagItems();
        $this->extendIssueLines();
        $this->extendReturnLines();
        $this->extendReceiveDetails();
        $this->createBagMovements();
    }

    public function down()
    {
        if ($this->db->tableExists('diamond_bag_movements')) {
            $this->forge->dropTable('diamond_bag_movements', true);
        }

        $this->dropIndex('order_receive_details', 'idx_receive_diamond_issue_line');
        $this->dropIndex('order_receive_details', 'idx_receive_diamond_bag_item');
        $this->dropColumns('order_receive_details', [
            'diamond_issue_line_id', 'diamond_bag_id', 'diamond_bag_item_id',
        ]);
        $this->dropIndex('return_lines', 'idx_diamond_return_issue_line');
        $this->dropIndex('return_lines', 'idx_diamond_return_bag_item');
        $this->dropColumns('return_lines', [
            'issue_line_id', 'bag_id', 'bag_item_id', 'allocation_order_id',
        ]);
        $this->dropIndex('issue_lines', 'idx_diamond_issue_bag_item');
        $this->dropIndex('issue_lines', 'idx_diamond_issue_allocation_order');
        $this->dropColumns('issue_lines', [
            'bag_id', 'bag_item_id', 'allocation_order_id',
        ]);
        $this->dropIndex('diamond_bag_items', 'idx_diamond_bag_inventory_item');
        $this->dropIndex('diamond_bag_items', 'idx_diamond_bag_shape_size');
        $this->dropColumns('diamond_bag_items', [
            'inventory_item_id', 'shape_master_id', 'size_master_id',
        ]);
        $this->dropColumns('diamond_bags', [
            'prepared_date', 'audit_image_name', 'audit_image_path',
        ]);

        if ($this->db->tableExists('diamond_size_masters')) {
            $this->forge->dropTable('diamond_size_masters', true);
        }
        if ($this->db->tableExists('diamond_shape_masters')) {
            $this->forge->dropTable('diamond_shape_masters', true);
        }
    }

    private function createShapeMaster(): void
    {
        if ($this->db->tableExists('diamond_shape_masters')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 30],
            'name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code', 'uq_diamond_shape_code');
        $this->forge->addUniqueKey('name', 'uq_diamond_shape_name');
        $this->forge->createTable('diamond_shape_masters', true);
    }

    private function seedShapes(): void
    {
        if (! $this->db->tableExists('diamond_shape_masters')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $shapes = [
            ['ROUND', 'Round'], ['OVAL', 'Oval'], ['PEAR', 'Pear'],
            ['MARQUISE', 'Marquise'], ['CUSHION', 'Cushion'], ['PRINCESS', 'Princess'],
            ['EMERALD', 'Emerald'], ['RADIANT', 'Radiant'], ['ASSCHER', 'Asscher'],
            ['HEART', 'Heart'], ['BAGUETTE', 'Baguette'], ['TRILLION', 'Trillion'],
            ['ROSE_CUT', 'Rose Cut'], ['POLKI', 'Polki'], ['OTHER', 'Other'],
        ];

        foreach ($shapes as $index => [$code, $name]) {
            $this->db->query(
                'INSERT INTO diamond_shape_masters (code, name, sort_order, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order), updated_at = VALUES(updated_at)',
                [$code, $name, ($index + 1) * 10, $now, $now]
            );
        }
    }

    private function createSizeMaster(): void
    {
        if ($this->db->tableExists('diamond_size_masters')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'shape_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'size_code' => ['type' => 'VARCHAR', 'constraint' => 40],
            'size_label' => ['type' => 'VARCHAR', 'constraint' => 80],
            'min_mm' => ['type' => 'DECIMAL', 'constraint' => '8,3', 'null' => true],
            'max_mm' => ['type' => 'DECIMAL', 'constraint' => '8,3', 'null' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['shape_id', 'size_code'], 'uq_diamond_shape_size_code');
        $this->forge->addKey('shape_id', false, false, 'idx_diamond_size_shape');
        $this->forge->createTable('diamond_size_masters', true);
    }

    private function seedSizesFromExistingDiamondItems(): void
    {
        if (! $this->db->tableExists('items') || ! $this->db->tableExists('diamond_size_masters')) {
            return;
        }
        $shapes = [];
        foreach ($this->db->table('diamond_shape_masters')->select('id, code, name')->get()->getResultArray() as $shape) {
            $shapes[strtoupper(trim((string) $shape['code']))] = (int) $shape['id'];
            $shapes[strtoupper(trim((string) $shape['name']))] = (int) $shape['id'];
        }
        $otherId = (int) ($shapes['OTHER'] ?? 0);
        $rows = $this->db->table('items')
            ->select('shape, chalni_from, chalni_to')
            ->groupStart()->where('chalni_from IS NOT NULL', null, false)->orWhere('chalni_to IS NOT NULL', null, false)->groupEnd()
            ->get()->getResultArray();
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $from = trim((string) ($row['chalni_from'] ?? ''));
            $to = trim((string) ($row['chalni_to'] ?? ''));
            if ($from === '' && $to === '') {
                continue;
            }
            $shapeKey = strtoupper(trim((string) ($row['shape'] ?? '')));
            $shapeId = (int) ($shapes[$shapeKey] ?? $otherId);
            if ($shapeId <= 0) {
                continue;
            }
            $label = $from !== '' && $to !== '' ? 'Chalni ' . $from . '-' . $to : 'Chalni ' . ($from ?: $to);
            $code = strtoupper((string) preg_replace('/[^A-Z0-9]+/', '-', 'CH-' . $from . '-' . $to));
            $code = trim($code, '-');
            $this->db->query(
                'INSERT INTO diamond_size_masters (shape_id, size_code, size_label, sort_order, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, 0, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE size_label = VALUES(size_label), updated_at = VALUES(updated_at)',
                [$shapeId, $code, $label, $now, $now]
            );
        }
    }

    private function extendDiamondBags(): void
    {
        if (! $this->db->tableExists('diamond_bags')) {
            return;
        }
        $this->addColumn('diamond_bags', 'prepared_date', ['type' => 'DATE', 'null' => true, 'after' => 'bag_no']);
        $this->addColumn('diamond_bags', 'audit_image_name', ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true]);
        $this->addColumn('diamond_bags', 'audit_image_path', ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true]);
    }

    private function extendDiamondBagItems(): void
    {
        if (! $this->db->tableExists('diamond_bag_items')) {
            return;
        }
        $this->addColumn('diamond_bag_items', 'inventory_item_id', ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'bag_id']);
        $this->addColumn('diamond_bag_items', 'shape_master_id', ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'inventory_item_id']);
        $this->addColumn('diamond_bag_items', 'size_master_id', ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'shape_master_id']);
        $this->ensureIndex('diamond_bag_items', 'idx_diamond_bag_inventory_item', ['inventory_item_id']);
        $this->ensureIndex('diamond_bag_items', 'idx_diamond_bag_shape_size', ['shape_master_id', 'size_master_id']);
    }

    private function extendIssueLines(): void
    {
        if (! $this->db->tableExists('issue_lines')) {
            return;
        }
        $this->addColumn('issue_lines', 'bag_id', ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'item_id']);
        $this->addColumn('issue_lines', 'bag_item_id', ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'bag_id']);
        $this->addColumn('issue_lines', 'allocation_order_id', ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'bag_item_id']);
        $this->ensureIndex('issue_lines', 'idx_diamond_issue_bag_item', ['bag_item_id']);
        $this->ensureIndex('issue_lines', 'idx_diamond_issue_allocation_order', ['allocation_order_id']);
    }

    private function extendReturnLines(): void
    {
        if (! $this->db->tableExists('return_lines')) {
            return;
        }
        $this->addColumn('return_lines', 'issue_line_id', ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'item_id']);
        $this->addColumn('return_lines', 'bag_id', ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'issue_line_id']);
        $this->addColumn('return_lines', 'bag_item_id', ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'bag_id']);
        $this->addColumn('return_lines', 'allocation_order_id', ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'bag_item_id']);
        $this->ensureIndex('return_lines', 'idx_diamond_return_issue_line', ['issue_line_id']);
        $this->ensureIndex('return_lines', 'idx_diamond_return_bag_item', ['bag_item_id']);
    }

    private function extendReceiveDetails(): void
    {
        if (! $this->db->tableExists('order_receive_details')) {
            return;
        }
        $this->addColumn('order_receive_details', 'diamond_issue_line_id', ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'stone_inventory_item_id']);
        $this->addColumn('order_receive_details', 'diamond_bag_id', ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'diamond_issue_line_id']);
        $this->addColumn('order_receive_details', 'diamond_bag_item_id', ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'diamond_bag_id']);
        $this->ensureIndex('order_receive_details', 'idx_receive_diamond_issue_line', ['diamond_issue_line_id']);
        $this->ensureIndex('order_receive_details', 'idx_receive_diamond_bag_item', ['diamond_bag_item_id']);
    }

    private function createBagMovements(): void
    {
        if ($this->db->tableExists('diamond_bag_movements')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'movement_date' => ['type' => 'DATE'],
            'movement_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'bag_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'bag_item_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'issue_line_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'return_line_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'receive_detail_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'karigar_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'pcs' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'carat' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['bag_id', 'bag_item_id'], false, false, 'idx_diamond_bag_movement_item');
        $this->forge->addKey('issue_line_id', false, false, 'idx_diamond_bag_movement_issue');
        $this->forge->addKey('order_id', false, false, 'idx_diamond_bag_movement_order');
        $this->forge->addKey('movement_date', false, false, 'idx_diamond_bag_movement_date');
        $this->forge->createTable('diamond_bag_movements', true);
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
        $existing = $this->db->query('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$name])->getResultArray();
        if ($existing === []) {
            $quoted = implode(', ', array_map(static fn(string $column): string => '`' . $column . '`', $columns));
            $this->db->query('CREATE INDEX `' . $name . '` ON `' . $table . '` (' . $quoted . ')');
        }
    }

    /** @param list<string> $columns */
    private function dropColumns(string $table, array $columns): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        foreach ($columns as $column) {
            if ($this->db->fieldExists($column, $table)) {
                $this->forge->dropColumn($table, $column);
            }
        }
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }
        $existing = $this->db->query('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$name])->getResultArray();
        if ($existing !== []) {
            $this->db->query('ALTER TABLE `' . $table . '` DROP INDEX `' . $name . '`');
        }
    }
}
