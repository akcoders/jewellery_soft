<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveUnusedDuplicateVendor extends Migration
{
    private const DUPLICATE_ID = 90414;
    private const DUPLICATE_NAME = 'Veer Dia';
    private const CANONICAL_NAME = 'VEER DIAM';

    public function up()
    {
        if (! $this->db->tableExists('vendors')) {
            return;
        }

        $duplicate = $this->db->table('vendors')
            ->where('id', self::DUPLICATE_ID)
            ->where('name', self::DUPLICATE_NAME)
            ->get()
            ->getRowArray();
        if (! $duplicate || ! $this->canonicalVendorExists()) {
            return;
        }

        // Vendor references are not consistently protected by foreign keys in
        // this legacy schema. Delete only while the duplicate is still unused.
        if ($this->hasVendorReferences(self::DUPLICATE_ID)) {
            return;
        }

        $this->db->table('vendors')->where('id', self::DUPLICATE_ID)->delete();
    }

    public function down()
    {
        if (! $this->db->tableExists('vendors')
            || $this->db->table('vendors')->where('id', self::DUPLICATE_ID)->countAllResults() > 0
            || $this->db->table('vendors')->where('name', self::DUPLICATE_NAME)->countAllResults() > 0) {
            return;
        }

        $this->db->table('vendors')->insert([
            'id' => self::DUPLICATE_ID,
            'name' => self::DUPLICATE_NAME,
            'contact_person' => null,
            'phone' => null,
            'email' => null,
            'address' => null,
            'gstin' => null,
            'is_active' => 1,
            'created_at' => '2026-08-24 18:30:03',
            'updated_at' => '2026-08-24 18:30:03',
        ]);
    }

    private function canonicalVendorExists(): bool
    {
        return $this->db->table('vendors')
            ->where('name', self::CANONICAL_NAME)
            ->where('gstin', '27AALFV4553C1Z2')
            ->countAllResults() > 0;
    }

    private function hasVendorReferences(int $vendorId): bool
    {
        foreach ($this->db->listTables() as $table) {
            if ($table === 'vendors' || ! $this->db->fieldExists('vendor_id', $table)) {
                continue;
            }

            if ($this->db->table($table)->where('vendor_id', $vendorId)->countAllResults() > 0) {
                return true;
            }
        }

        return $this->db->tableExists('accounts')
            && $this->db->fieldExists('reference_table', 'accounts')
            && $this->db->fieldExists('reference_id', 'accounts')
            && $this->db->table('accounts')
                ->where('reference_table', 'vendors')
                ->where('reference_id', $vendorId)
                ->countAllResults() > 0;
    }
}
