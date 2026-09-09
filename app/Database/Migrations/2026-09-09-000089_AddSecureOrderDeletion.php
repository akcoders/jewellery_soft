<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSecureOrderDeletion extends Migration
{
    private const PERMISSION_CODE = 'orders.delete';

    public function up(): void
    {
        $this->createDeletionAuditTable();
        $this->createPermission();
    }

    public function down(): void
    {
        if ($this->db->tableExists('permissions')) {
            $permission = $this->db->table('permissions')
                ->select('id')
                ->where('code', self::PERMISSION_CODE)
                ->get()
                ->getRowArray();
            if ($permission && $this->db->tableExists('role_permissions')) {
                $this->db->table('role_permissions')
                    ->where('permission_id', (int) $permission['id'])
                    ->delete();
            }
            if ($permission && $this->db->tableExists('user_permissions')) {
                $this->db->table('user_permissions')
                    ->where('permission_id', (int) $permission['id'])
                    ->delete();
            }
            $this->db->table('permissions')->where('code', self::PERMISSION_CODE)->delete();
        }

        $this->forge->dropTable('order_deletion_audits', true);
    }

    private function createDeletionAuditTable(): void
    {
        if ($this->db->tableExists('order_deletion_audits')) {
            return;
        }

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'order_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'order_no' => ['type' => 'VARCHAR', 'constraint' => 80],
            'order_status' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'reason' => ['type' => 'TEXT'],
            'summary_json' => ['type' => 'LONGTEXT', 'null' => true],
            'deleted_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('order_id');
        $this->forge->addKey('order_no');
        $this->forge->addKey('deleted_by');
        $this->forge->addKey('created_at');
        $this->forge->createTable('order_deletion_audits', true);
    }

    private function createPermission(): void
    {
        if (! $this->db->tableExists('permissions')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $permission = $this->db->table('permissions')
            ->where('code', self::PERMISSION_CODE)
            ->get()
            ->getRowArray();
        $data = [
            'name' => 'Permanently Delete Orders',
            'module_group' => 'Orders',
            'action_key' => 'delete',
            'description' => 'Permanently delete an order, its eligible transactions and private image files',
            'sort_order' => 219,
            'is_active' => 1,
            'updated_at' => $now,
        ];

        if ($permission) {
            $permissionId = (int) $permission['id'];
            $this->db->table('permissions')->where('id', $permissionId)->update($data);
        } else {
            $this->db->table('permissions')->insert(['code' => self::PERMISSION_CODE, 'created_at' => $now] + $data);
            $permissionId = (int) $this->db->insertID();
        }

        if ($permissionId <= 0 || ! $this->db->tableExists('roles') || ! $this->db->tableExists('role_permissions')) {
            return;
        }

        $roles = $this->db->table('roles')
            ->select('id')
            ->groupStart()
                ->whereIn('role_code', ['SUPER_ADMIN', 'OWNER'])
                ->orWhereIn('name', ['SUPER_ADMIN', 'OWNER', 'Super Admin', 'Owner'])
            ->groupEnd()
            ->get()
            ->getResultArray();
        foreach ($roles as $role) {
            $roleId = (int) ($role['id'] ?? 0);
            if ($roleId <= 0) {
                continue;
            }
            $exists = $this->db->table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->countAllResults() > 0;
            if (! $exists) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                ]);
            }
        }
    }
}
