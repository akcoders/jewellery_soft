<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DecoupleMaterialTransactionsFromOrders extends Migration
{
    private const HEADER_TABLES = [
        'gold_inventory_issue_headers',
        'gold_inventory_return_headers',
        'issue_headers',
        'return_headers',
        'stone_inventory_issue_headers',
        'stone_inventory_return_headers',
    ];

    public function up()
    {
        foreach (self::HEADER_TABLES as $table) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $this->dropOrderForeignKeys($table);
            if ($this->db->fieldExists('order_id', $table)) {
                $this->db->table($table)->set('order_id', null)->update();
            }
            if (! $this->db->fieldExists('account_voucher_id', $table)) {
                $this->forge->addColumn($table, [
                    'account_voucher_id' => [
                        'type' => 'BIGINT',
                        'constraint' => 20,
                        'unsigned' => true,
                        'null' => true,
                        'after' => 'created_by',
                    ],
                ]);
            }
            $this->addKarigarDateIndex($table);
        }

        if ($this->db->tableExists('order_receive_summaries')
            && ! $this->db->fieldExists('account_voucher_id', 'order_receive_summaries')) {
            $this->forge->addColumn('order_receive_summaries', [
                'account_voucher_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'movement_id',
                ],
            ]);
        }
    }

    public function down()
    {
        // Order links are deliberately not reconstructed: issue/return records
        // are now karigar transactions and no longer belong to an order.
    }

    private function dropOrderForeignKeys(string $table): void
    {
        foreach ($this->db->getForeignKeyData($table) as $foreignKey) {
            if (in_array('order_id', (array) ($foreignKey->column_name ?? []), true)) {
                $this->forge->dropForeignKey($table, (string) $foreignKey->constraint_name);
            }
        }
    }

    private function addKarigarDateIndex(string $table): void
    {
        if (! $this->db->fieldExists('karigar_id', $table)) {
            return;
        }
        $dateField = str_contains($table, 'return') ? 'return_date' : 'issue_date';
        if (! $this->db->fieldExists($dateField, $table)) {
            return;
        }
        $index = 'idx_' . substr(preg_replace('/[^a-z0-9]+/i', '', $table), 0, 34) . '_karigar_date';
        if (! isset($this->db->getIndexData($table)[$index])) {
            $this->db->query(sprintf(
                'ALTER TABLE `%s` ADD INDEX `%s` (`karigar_id`, `%s`)',
                $table,
                $index,
                $dateField
            ));
        }
    }
}
