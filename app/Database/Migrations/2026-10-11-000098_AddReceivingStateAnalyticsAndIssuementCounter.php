<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReceivingStateAnalyticsAndIssuementCounter extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('company_settings')
            && ! $this->db->fieldExists('issuement_last_number', 'company_settings')) {
            $this->forge->addColumn('company_settings', [
                'issuement_last_number' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'default' => 0,
                    'after' => 'issuement_start_count',
                ],
            ]);
        }

        if ($this->db->tableExists('orders')) {
            $fields = [];
            if (! $this->db->fieldExists('receiving_status', 'orders')) {
                $fields['receiving_status'] = [
                    'type' => 'VARCHAR',
                    'constraint' => 24,
                    'default' => 'pending',
                    'after' => 'status',
                ];
            }
            if (! $this->db->fieldExists('received_at', 'orders')) {
                $fields['received_at'] = ['type' => 'DATETIME', 'null' => true, 'after' => 'receiving_status'];
            }
            if (! $this->db->fieldExists('received_by', 'orders')) {
                $fields['received_by'] = [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'received_at',
                ];
            }
            if ($fields !== []) {
                $this->forge->addColumn('orders', $fields);
            }

            if ($this->db->tableExists('order_material_movements')) {
                $this->db->query(
                    "UPDATE orders o
                     SET o.receiving_status = 'received',
                         o.received_at = COALESCE(o.received_at, (
                             SELECT MAX(m.created_at)
                             FROM order_material_movements m
                             WHERE m.order_id = o.id AND m.movement_type = 'receive'
                         )),
                         o.received_by = COALESCE(o.received_by, (
                             SELECT m2.created_by
                             FROM order_material_movements m2
                             WHERE m2.order_id = o.id AND m2.movement_type = 'receive'
                             ORDER BY m2.id DESC LIMIT 1
                         ))
                     WHERE EXISTS (
                         SELECT 1 FROM order_material_movements mx
                         WHERE mx.order_id = o.id AND mx.movement_type = 'receive'
                     )"
                );
            }
        }

        foreach (['purchase_headers', 'stone_inventory_purchase_headers'] as $table) {
            if ($this->db->tableExists($table) && ! $this->db->fieldExists('created_by', $table)) {
                $this->forge->addColumn($table, [
                    'created_by' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'notes'],
                ]);
            }
        }

        $this->seedAnalyticsPermission();
    }

    public function down(): void
    {
        if ($this->db->tableExists('permissions')) {
            $permission = $this->db->table('permissions')->select('id')
                ->where('code', 'analytics.business.read')->get()->getRowArray();
            if ($permission && $this->db->tableExists('role_permissions')) {
                $this->db->table('role_permissions')->where('permission_id', (int) $permission['id'])->delete();
            }
            $this->db->table('permissions')->where('code', 'analytics.business.read')->delete();
        }

        if ($this->db->tableExists('orders')) {
            foreach (['received_by', 'received_at', 'receiving_status'] as $field) {
                if ($this->db->fieldExists($field, 'orders')) {
                    $this->forge->dropColumn('orders', $field);
                }
            }
        }
        if ($this->db->tableExists('company_settings')
            && $this->db->fieldExists('issuement_last_number', 'company_settings')) {
            $this->forge->dropColumn('company_settings', 'issuement_last_number');
        }
        foreach (['purchase_headers', 'stone_inventory_purchase_headers'] as $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('created_by', $table)) {
                $this->forge->dropColumn($table, 'created_by');
            }
        }
    }

    private function seedAnalyticsPermission(): void
    {
        if (! $this->db->tableExists('permissions')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $permission = $this->db->table('permissions')->where('code', 'analytics.business.read')->get()->getRowArray();
        $data = [
            'name' => 'View Business Analytics',
            'module_group' => 'Analytics',
            'action_key' => 'read',
            'description' => 'View advanced order, inventory, customer and employee analytics',
            'sort_order' => 540,
            'is_active' => 1,
            'updated_at' => $now,
        ];
        if ($permission) {
            $this->db->table('permissions')->where('id', (int) $permission['id'])->update($data);
            $permissionId = (int) $permission['id'];
        } else {
            $this->db->table('permissions')->insert(['code' => 'analytics.business.read', 'created_at' => $now] + $data);
            $permissionId = (int) $this->db->insertID();
        }
        if ($permissionId <= 0 || ! $this->db->tableExists('roles') || ! $this->db->tableExists('role_permissions')) {
            return;
        }
        $roles = $this->db->table('roles')->select('id')
            ->groupStart()
                ->whereIn('role_code', ['SUPER_ADMIN', 'ADMIN', 'OWNER'])
                ->orWhereIn('name', ['SUPER_ADMIN', 'ADMIN', 'OWNER', 'Super Admin', 'Admin', 'Owner'])
            ->groupEnd()->get()->getResultArray();
        foreach ($roles as $role) {
            $roleId = (int) $role['id'];
            if ($this->db->table('role_permissions')->where(['role_id' => $roleId, 'permission_id' => $permissionId])->countAllResults() === 0) {
                $this->db->table('role_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                ]);
            }
        }
    }
}
