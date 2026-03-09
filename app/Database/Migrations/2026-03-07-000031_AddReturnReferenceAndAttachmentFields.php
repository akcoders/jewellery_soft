<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReturnReferenceAndAttachmentFields extends Migration
{
    public function up()
    {
        $this->updateDiamondReturnHeaders();
        $this->updateGoldReturnHeaders();
    }

    public function down()
    {
        $this->dropDiamondReturnHeaders();
        $this->dropGoldReturnHeaders();
    }

    private function updateDiamondReturnHeaders(): void
    {
        if (! $this->db->tableExists('return_headers')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('voucher_no', 'return_headers')) {
            $fields['voucher_no'] = ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true];
        }
        if (! $this->db->fieldExists('issue_id', 'return_headers')) {
            $fields['issue_id'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true];
        }
        if (! $this->db->fieldExists('karigar_id', 'return_headers')) {
            $fields['karigar_id'] = ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true];
        }
        if (! $this->db->fieldExists('attachment_name', 'return_headers')) {
            $fields['attachment_name'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }
        if (! $this->db->fieldExists('attachment_path', 'return_headers')) {
            $fields['attachment_path'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }
        if (! $this->db->fieldExists('created_by', 'return_headers')) {
            $fields['created_by'] = ['type' => 'INT', 'constraint' => 11, 'null' => true];
        }
        if ($fields !== []) {
            $this->forge->addColumn('return_headers', $fields);
        }

        $idxVoucher = $this->db->query("SHOW INDEX FROM return_headers WHERE Key_name='idx_return_headers_voucher_no'")->getResultArray();
        if ($idxVoucher === [] && $this->db->fieldExists('voucher_no', 'return_headers')) {
            $this->db->query('CREATE INDEX idx_return_headers_voucher_no ON return_headers (voucher_no)');
        }
        $idxIssue = $this->db->query("SHOW INDEX FROM return_headers WHERE Key_name='idx_return_headers_issue_id'")->getResultArray();
        if ($idxIssue === [] && $this->db->fieldExists('issue_id', 'return_headers')) {
            $this->db->query('CREATE INDEX idx_return_headers_issue_id ON return_headers (issue_id)');
        }
        $idxKarigar = $this->db->query("SHOW INDEX FROM return_headers WHERE Key_name='idx_return_headers_karigar_id'")->getResultArray();
        if ($idxKarigar === [] && $this->db->fieldExists('karigar_id', 'return_headers')) {
            $this->db->query('CREATE INDEX idx_return_headers_karigar_id ON return_headers (karigar_id)');
        }
    }

    private function updateGoldReturnHeaders(): void
    {
        if (! $this->db->tableExists('gold_inventory_return_headers')) {
            return;
        }

        $fields = [];
        if (! $this->db->fieldExists('voucher_no', 'gold_inventory_return_headers')) {
            $fields['voucher_no'] = ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true];
        }
        if (! $this->db->fieldExists('issue_id', 'gold_inventory_return_headers')) {
            $fields['issue_id'] = ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true];
        }
        if (! $this->db->fieldExists('attachment_name', 'gold_inventory_return_headers')) {
            $fields['attachment_name'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }
        if (! $this->db->fieldExists('attachment_path', 'gold_inventory_return_headers')) {
            $fields['attachment_path'] = ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true];
        }
        if ($fields !== []) {
            $this->forge->addColumn('gold_inventory_return_headers', $fields);
        }

        $idxVoucher = $this->db->query("SHOW INDEX FROM gold_inventory_return_headers WHERE Key_name='idx_gold_return_headers_voucher_no'")->getResultArray();
        if ($idxVoucher === [] && $this->db->fieldExists('voucher_no', 'gold_inventory_return_headers')) {
            $this->db->query('CREATE INDEX idx_gold_return_headers_voucher_no ON gold_inventory_return_headers (voucher_no)');
        }
        $idxIssue = $this->db->query("SHOW INDEX FROM gold_inventory_return_headers WHERE Key_name='idx_gold_return_headers_issue_id'")->getResultArray();
        if ($idxIssue === [] && $this->db->fieldExists('issue_id', 'gold_inventory_return_headers')) {
            $this->db->query('CREATE INDEX idx_gold_return_headers_issue_id ON gold_inventory_return_headers (issue_id)');
        }
    }

    private function dropDiamondReturnHeaders(): void
    {
        if (! $this->db->tableExists('return_headers')) {
            return;
        }

        try {
            $this->db->query('DROP INDEX idx_return_headers_voucher_no ON return_headers');
        } catch (\Throwable $e) {
        }
        try {
            $this->db->query('DROP INDEX idx_return_headers_issue_id ON return_headers');
        } catch (\Throwable $e) {
        }
        try {
            $this->db->query('DROP INDEX idx_return_headers_karigar_id ON return_headers');
        } catch (\Throwable $e) {
        }

        $drop = [];
        foreach (['voucher_no', 'issue_id', 'karigar_id', 'attachment_name', 'attachment_path', 'created_by'] as $column) {
            if ($this->db->fieldExists($column, 'return_headers')) {
                $drop[] = $column;
            }
        }
        if ($drop !== []) {
            $this->forge->dropColumn('return_headers', $drop);
        }
    }

    private function dropGoldReturnHeaders(): void
    {
        if (! $this->db->tableExists('gold_inventory_return_headers')) {
            return;
        }

        try {
            $this->db->query('DROP INDEX idx_gold_return_headers_voucher_no ON gold_inventory_return_headers');
        } catch (\Throwable $e) {
        }
        try {
            $this->db->query('DROP INDEX idx_gold_return_headers_issue_id ON gold_inventory_return_headers');
        } catch (\Throwable $e) {
        }

        $drop = [];
        foreach (['voucher_no', 'issue_id', 'attachment_name', 'attachment_path'] as $column) {
            if ($this->db->fieldExists($column, 'gold_inventory_return_headers')) {
                $drop[] = $column;
            }
        }
        if ($drop !== []) {
            $this->forge->dropColumn('gold_inventory_return_headers', $drop);
        }
    }
}

