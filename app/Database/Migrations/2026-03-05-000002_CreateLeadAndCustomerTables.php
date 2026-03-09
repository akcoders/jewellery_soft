<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLeadAndCustomerTables extends Migration
{
    public function up()
    {
        $this->createLeadSources();
        $this->createLeads();
        $this->createLeadImages();
        $this->createLeadNotes();
        $this->createLeadFollowups();
        $this->createPricingRules();
        $this->createCustomers();
        $this->createCustomerAddresses();
    }

    public function down()
    {
        $this->forge->dropTable('customer_addresses', true);
        $this->forge->dropTable('customers', true);
        $this->forge->dropTable('pricing_rules', true);
        $this->forge->dropTable('lead_followups', true);
        $this->forge->dropTable('lead_notes', true);
        $this->forge->dropTable('lead_images', true);
        $this->forge->dropTable('leads', true);
        $this->forge->dropTable('lead_sources', true);
    }

    private function createLeadSources(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('lead_sources', true);
    }

    private function createLeads(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'lead_no' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 20],
            'email' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'source_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'city' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'requirement_text' => ['type' => 'TEXT', 'null' => true],
            'stage' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'New'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Open'],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('lead_no');
        $this->forge->addKey('phone');
        $this->forge->addKey('stage');
        $this->forge->createTable('leads', true);
    }

    private function createLeadImages(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'lead_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'file_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_path' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('lead_id');
        $this->forge->createTable('lead_images', true);
    }

    private function createLeadNotes(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'lead_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'note' => ['type' => 'TEXT'],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('lead_id');
        $this->forge->createTable('lead_notes', true);
    }

    private function createLeadFollowups(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'lead_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'followup_at' => ['type' => 'DATETIME'],
            'reminder_at' => ['type' => 'DATETIME', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'Pending'],
            'notes' => ['type' => 'TEXT', 'null' => true],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('lead_id');
        $this->forge->addKey('followup_at');
        $this->forge->createTable('lead_followups', true);
    }

    private function createPricingRules(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'rule_type' => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'making_charge'],
            'value_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'percent'],
            'value' => ['type' => 'DECIMAL', 'constraint' => '18,4', 'default' => 0],
            'unit' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->createTable('pricing_rules', true);
    }

    private function createCustomers(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'customer_code' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'gstin' => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true],
            'terms_text' => ['type' => 'TEXT', 'null' => true],
            'pricing_rule_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('customer_code');
        $this->forge->addKey('name');
        $this->forge->createTable('customers', true);
    }

    private function createCustomerAddresses(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'address_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'Billing'],
            'line1' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'line2' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'city' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'state' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'country' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'pincode' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('customer_id');
        $this->forge->createTable('customer_addresses', true);
    }
}

