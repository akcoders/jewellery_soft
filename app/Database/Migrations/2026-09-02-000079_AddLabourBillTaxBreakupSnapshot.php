<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLabourBillTaxBreakupSnapshot extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('labour_bills')) {
            return;
        }
        if (! $this->db->fieldExists('tax_breakup_json', 'labour_bills')) {
            $this->forge->addColumn('labour_bills', [
                'tax_breakup_json' => ['type' => 'LONGTEXT', 'null' => true, 'after' => 'gst_master_id'],
            ]);
        }

        foreach ($this->db->table('labour_bills')->select('id,cgst_rate,cgst_amount,sgst_rate,sgst_amount,igst_rate,igst_amount')->get()->getResultArray() as $bill) {
            $components = [];
            foreach (['CGST', 'SGST', 'IGST'] as $name) {
                $key = strtolower($name);
                $rate = (float) ($bill[$key . '_rate'] ?? 0);
                $amount = (float) ($bill[$key . '_amount'] ?? 0);
                if ($rate > 0 || $amount != 0.0) {
                    $components[] = ['name' => $name, 'percentage' => $rate, 'amount' => $amount];
                }
            }
            $this->db->table('labour_bills')->where('id', (int) $bill['id'])->update([
                'tax_breakup_json' => json_encode($components, JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('labour_bills') && $this->db->fieldExists('tax_breakup_json', 'labour_bills')) {
            $this->forge->dropColumn('labour_bills', 'tax_breakup_json');
        }
    }
}
