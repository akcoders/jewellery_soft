<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use DateTimeImmutable;

class AddPaymentTermsDaysToDiamondPurchases extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('purchase_headers')) {
            return;
        }

        if (! $this->db->fieldExists('payment_terms_days', 'purchase_headers')) {
            $this->forge->addColumn('purchase_headers', [
                'payment_terms_days' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'due_date',
                ],
            ]);
        }

        $rows = $this->db->table('purchase_headers')
            ->select('id, purchase_date, due_date')
            ->where('payment_terms_days', null)
            ->where('purchase_date IS NOT NULL', null, false)
            ->where('due_date IS NOT NULL', null, false)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $purchaseDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $row['purchase_date']);
            $dueDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $row['due_date']);
            if ($purchaseDate === false || $dueDate === false || $dueDate < $purchaseDate) {
                continue;
            }

            $this->db->table('purchase_headers')
                ->where('id', (int) $row['id'])
                ->update(['payment_terms_days' => (int) $purchaseDate->diff($dueDate)->days]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('purchase_headers')
            && $this->db->fieldExists('payment_terms_days', 'purchase_headers')) {
            $this->forge->dropColumn('purchase_headers', 'payment_terms_days');
        }
    }
}
