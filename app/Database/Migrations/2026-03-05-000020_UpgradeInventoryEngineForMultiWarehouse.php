<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpgradeInventoryEngineForMultiWarehouse extends Migration
{
    public function up()
    {
        $this->extendInventoryLocations();
        $this->createInventoryBins();
        $this->createVoucherCounters();
        $this->createInventoryBalances();
        $this->extendInventoryItems();
        $this->extendInventoryTransactions();
        $this->seedDefaultBins();
        $this->backfillInventoryTransactions();
        $this->backfillInventoryItems();
    }

    public function down()
    {
        if ($this->db->tableExists('inventory_items')) {
            $this->dropColumns('inventory_items', [
                'warehouse_id',
                'bin_id',
                'packet_no',
                'lot_no',
                'certificate_no',
            ]);
        }

        if ($this->db->tableExists('inventory_transactions')) {
            $this->dropColumns('inventory_transactions', [
                'voucher_no',
                'voucher_group',
                'txn_datetime',
                'from_warehouse_id',
                'from_bin_id',
                'to_warehouse_id',
                'to_bin_id',
                'qty_sign',
                'fine_gold_gm',
                'diamond_sieve_min',
                'diamond_sieve_max',
                'diamond_cut',
                'diamond_quality',
                'diamond_fluorescence',
                'diamond_lab',
                'certificate_no',
                'packet_no',
                'lot_no',
                'stone_type',
                'stone_size',
                'stone_color_shade',
                'stone_quality_grade',
                'document_type',
                'document_no',
                'reversal_of_id',
                'reversed_by_id',
                'reversed_at',
                'reversal_reason',
                'immutable_hash',
                'is_reversal',
                'is_void',
                'status',
            ]);
        }

        if ($this->db->tableExists('inventory_locations')) {
            $this->dropColumns('inventory_locations', ['code', 'address']);
        }

        $this->forge->dropTable('inventory_balances', true);
        $this->forge->dropTable('inventory_voucher_counters', true);
        $this->forge->dropTable('inventory_bins', true);
    }

    private function extendInventoryLocations(): void
    {
        if (! $this->db->tableExists('inventory_locations')) {
            return;
        }

        $this->addColumnIfMissing('inventory_locations', 'code', [
            'type'       => 'VARCHAR',
            'constraint' => 30,
            'null'       => true,
        ]);

        $this->addColumnIfMissing('inventory_locations', 'address', [
            'type' => 'TEXT',
            'null' => true,
        ]);

        $this->addUniqueIndexIfMissing('inventory_locations', 'uniq_inventory_locations_code', 'code');
    }

    private function createInventoryBins(): void
    {
        if ($this->db->tableExists('inventory_bins')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'location_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'bin_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('location_id');
        $this->forge->addUniqueKey(['location_id', 'bin_code'], 'uniq_inventory_bins_location_code');
        $this->forge->createTable('inventory_bins', true);
    }

    private function createVoucherCounters(): void
    {
        if ($this->db->tableExists('inventory_voucher_counters')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'counter_date' => [
                'type' => 'DATE',
            ],
            'last_number' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('counter_date', 'uniq_inventory_voucher_date');
        $this->forge->createTable('inventory_voucher_counters', true);
    }

    private function createInventoryBalances(): void
    {
        if ($this->db->tableExists('inventory_balances')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'balance_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 190,
            ],
            'item_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            'material_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'gold_purity_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'diamond_shape' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'diamond_sieve' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'diamond_sieve_min' => ['type' => 'DECIMAL', 'constraint' => '8,3', 'null' => true],
            'diamond_sieve_max' => ['type' => 'DECIMAL', 'constraint' => '8,3', 'null' => true],
            'diamond_color' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'diamond_clarity' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'diamond_cut' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'diamond_quality' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'diamond_fluorescence' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'diamond_lab' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'certificate_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'packet_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'lot_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'stone_type' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'stone_size' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'stone_color_shade' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'stone_quality_grade' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'warehouse_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'bin_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'pcs_balance' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'weight_gm_balance' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'cts_balance' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'fine_gold_balance' => ['type' => 'DECIMAL', 'constraint' => '16,3', 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('balance_key', 'uniq_inventory_balances_key');
        $this->forge->addKey('warehouse_id');
        $this->forge->addKey('bin_id');
        $this->forge->addKey('item_type');
        $this->forge->addKey('material_name');
        $this->forge->addKey('packet_no');
        $this->forge->createTable('inventory_balances', true);
    }

    private function extendInventoryItems(): void
    {
        if (! $this->db->tableExists('inventory_items')) {
            return;
        }

        $this->addColumnIfMissing('inventory_items', 'warehouse_id', [
            'type'       => 'INT',
            'constraint' => 11,
            'unsigned'   => true,
            'null'       => true,
        ]);
        $this->addColumnIfMissing('inventory_items', 'bin_id', [
            'type'       => 'INT',
            'constraint' => 11,
            'unsigned'   => true,
            'null'       => true,
        ]);
        $this->addColumnIfMissing('inventory_items', 'packet_no', [
            'type'       => 'VARCHAR',
            'constraint' => 80,
            'null'       => true,
        ]);
        $this->addColumnIfMissing('inventory_items', 'lot_no', [
            'type'       => 'VARCHAR',
            'constraint' => 80,
            'null'       => true,
        ]);
        $this->addColumnIfMissing('inventory_items', 'certificate_no', [
            'type'       => 'VARCHAR',
            'constraint' => 120,
            'null'       => true,
        ]);

        $this->addIndexIfMissing('inventory_items', 'idx_inventory_items_warehouse', 'warehouse_id');
        $this->addIndexIfMissing('inventory_items', 'idx_inventory_items_bin', 'bin_id');
    }

    private function extendInventoryTransactions(): void
    {
        if (! $this->db->tableExists('inventory_transactions')) {
            return;
        }

        $columns = [
            'voucher_no' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'voucher_group' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'txn_datetime' => ['type' => 'DATETIME', 'null' => true],
            'from_warehouse_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'from_bin_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'to_warehouse_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'to_bin_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'qty_sign' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'fine_gold_gm' => ['type' => 'DECIMAL', 'constraint' => '14,3', 'default' => 0],
            'diamond_sieve_min' => ['type' => 'DECIMAL', 'constraint' => '8,3', 'null' => true],
            'diamond_sieve_max' => ['type' => 'DECIMAL', 'constraint' => '8,3', 'null' => true],
            'diamond_cut' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'diamond_quality' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'diamond_fluorescence' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'diamond_lab' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'certificate_no' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'packet_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'lot_no' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'stone_type' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true],
            'stone_size' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'stone_color_shade' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'stone_quality_grade' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'document_type' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'document_no' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'reversal_of_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'reversed_by_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'reversed_at' => ['type' => 'DATETIME', 'null' => true],
            'reversal_reason' => ['type' => 'TEXT', 'null' => true],
            'immutable_hash' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'is_reversal' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_void' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'posted'],
        ];

        foreach ($columns as $name => $definition) {
            $this->addColumnIfMissing('inventory_transactions', $name, $definition);
        }

        $this->addUniqueIndexIfMissing('inventory_transactions', 'uniq_inventory_transactions_voucher', 'voucher_no');
        $this->addIndexIfMissing('inventory_transactions', 'idx_inventory_txn_datetime', 'txn_datetime');
        $this->addIndexIfMissing('inventory_transactions', 'idx_inventory_txn_from_wh', 'from_warehouse_id');
        $this->addIndexIfMissing('inventory_transactions', 'idx_inventory_txn_to_wh', 'to_warehouse_id');
        $this->addIndexIfMissing('inventory_transactions', 'idx_inventory_txn_packet', 'packet_no');
        $this->addIndexIfMissing('inventory_transactions', 'idx_inventory_txn_status', 'status');
    }

    private function seedDefaultBins(): void
    {
        if (! $this->db->tableExists('inventory_bins') || ! $this->db->tableExists('inventory_locations')) {
            return;
        }

        $locations = $this->db->table('inventory_locations')->select('id')->get()->getResultArray();
        foreach ($locations as $location) {
            $locationId = (int) ($location['id'] ?? 0);
            if ($locationId <= 0) {
                continue;
            }

            $exists = $this->db->table('inventory_bins')
                ->where('location_id', $locationId)
                ->where('bin_code', 'MAIN')
                ->get()
                ->getRowArray();

            if (! $exists) {
                $this->db->table('inventory_bins')->insert([
                    'location_id' => $locationId,
                    'bin_code'    => 'MAIN',
                    'name'        => 'Main Bin',
                    'is_active'   => 1,
                    'created_at'  => date('Y-m-d H:i:s'),
                    'updated_at'  => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    private function backfillInventoryTransactions(): void
    {
        if (! $this->db->tableExists('inventory_transactions')) {
            return;
        }

        $this->db->query("UPDATE inventory_transactions SET txn_datetime = CONCAT(txn_date, ' 00:00:00') WHERE txn_date IS NOT NULL AND txn_datetime IS NULL");
        $this->db->query("UPDATE inventory_transactions SET voucher_no = CONCAT('LEG-', LPAD(id, 8, '0')) WHERE voucher_no IS NULL OR voucher_no = ''");
        $this->db->query("UPDATE inventory_transactions SET voucher_group = voucher_no WHERE voucher_group IS NULL OR voucher_group = ''");
        $this->db->query("UPDATE inventory_transactions SET qty_sign = CASE WHEN transaction_type IN ('issue','transfer_out','adjustment_minus','dispatch','consume','loss','breakage') THEN -1 ELSE 1 END WHERE qty_sign IS NULL OR qty_sign = 0");

        $this->db->query("UPDATE inventory_transactions SET from_warehouse_id = location_id WHERE from_warehouse_id IS NULL AND location_id IS NOT NULL AND transaction_type IN ('issue','adjustment_minus','transfer_out','dispatch','consume','loss','breakage')");
        $this->db->query("UPDATE inventory_transactions SET to_warehouse_id = location_id WHERE to_warehouse_id IS NULL AND location_id IS NOT NULL AND transaction_type IN ('purchase','receive','adjustment_plus','transfer_in','production_receive')");
        $this->db->query("UPDATE inventory_transactions SET to_warehouse_id = counter_location_id WHERE to_warehouse_id IS NULL AND counter_location_id IS NOT NULL AND transaction_type = 'transfer_out'");
        $this->db->query("UPDATE inventory_transactions SET from_warehouse_id = counter_location_id WHERE from_warehouse_id IS NULL AND counter_location_id IS NOT NULL AND transaction_type = 'transfer_in'");

        if ($this->db->tableExists('inventory_bins')) {
            $this->db->query("UPDATE inventory_transactions t JOIN inventory_bins b ON b.location_id = t.from_warehouse_id AND b.bin_code = 'MAIN' SET t.from_bin_id = b.id WHERE t.from_warehouse_id IS NOT NULL AND t.from_bin_id IS NULL");
            $this->db->query("UPDATE inventory_transactions t JOIN inventory_bins b ON b.location_id = t.to_warehouse_id AND b.bin_code = 'MAIN' SET t.to_bin_id = b.id WHERE t.to_warehouse_id IS NOT NULL AND t.to_bin_id IS NULL");
        }

        $this->db->query("UPDATE inventory_transactions SET immutable_hash = SHA2(CONCAT(id, '|', COALESCE(voucher_no, ''), '|', COALESCE(txn_datetime, ''), '|', COALESCE(item_type, ''), '|', COALESCE(material_name, '')), 256) WHERE immutable_hash IS NULL OR immutable_hash = ''");
    }

    private function backfillInventoryItems(): void
    {
        if (! $this->db->tableExists('inventory_items')) {
            return;
        }

        $this->db->query('UPDATE inventory_items SET warehouse_id = location_id WHERE warehouse_id IS NULL AND location_id IS NOT NULL');

        if ($this->db->tableExists('inventory_bins')) {
            $this->db->query("UPDATE inventory_items i JOIN inventory_bins b ON b.location_id = i.warehouse_id AND b.bin_code = 'MAIN' SET i.bin_id = b.id WHERE i.warehouse_id IS NOT NULL AND i.bin_id IS NULL");
        }
    }

    private function addColumnIfMissing(string $table, string $name, array $definition): void
    {
        if (! $this->db->tableExists($table) || $this->db->fieldExists($name, $table)) {
            return;
        }

        $this->forge->addColumn($table, [$name => $definition]);
    }

    private function dropColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            if ($this->db->fieldExists($column, $table)) {
                $this->forge->dropColumn($table, $column);
            }
        }
    }

    private function addIndexIfMissing(string $table, string $indexName, string $column): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $exists = $this->db->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'")->getResultArray();
        if ($exists !== []) {
            return;
        }

        $this->db->query("CREATE INDEX {$indexName} ON {$table} ({$column})");
    }

    private function addUniqueIndexIfMissing(string $table, string $indexName, string $column): void
    {
        if (! $this->db->tableExists($table)) {
            return;
        }

        $exists = $this->db->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'")->getResultArray();
        if ($exists !== []) {
            return;
        }

        $this->db->query("CREATE UNIQUE INDEX {$indexName} ON {$table} ({$column})");
    }
}
