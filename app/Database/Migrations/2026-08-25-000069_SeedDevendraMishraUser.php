<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class SeedDevendraMishraUser extends Migration
{
    private const EMAIL = 'devendra.mishra@aabhushan.in';
    private const ROLE_CODE = 'ORDER_OPERATIONS';

    /** Password is stored only as a one-way bcrypt hash. */
    private const PASSWORD_HASH = '$2y$12$HjO58j1vHLVST73J3X/fQe0pC3x4kPC.AR1PvbXpPBADH4625egbu';

    /** @var list<string> */
    private const PERMISSIONS = [
        'dashboard.read',
        'orders.read',
        'orders.create',
        'orders.followup',
        'issuements.read',
        'issuements.create',
        'issuements.edit',
        'issuements.print',
    ];

    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->transStart();

        $user = $this->db->table('admin_users')->where('email', self::EMAIL)->get()->getRowArray();
        $userData = [
            'name' => 'Devendra Mishra',
            'email' => self::EMAIL,
            'password_hash' => self::PASSWORD_HASH,
            'is_active' => 1,
            'updated_at' => $now,
        ];

        if ($user) {
            $userId = (int) $user['id'];
            $this->db->table('admin_users')->where('id', $userId)->update($userData);
        } else {
            $userData['created_at'] = $now;
            $this->db->table('admin_users')->insert($userData);
            $userId = (int) $this->db->insertID();
        }

        $roleId = $this->ensureOrderOperationsRole($now);
        $hasRole = $this->db->table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->countAllResults() > 0;
        if (! $hasRole) {
            $this->db->table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => $now,
            ]);
        }

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            throw new RuntimeException('Devendra Mishra login could not be created.');
        }
    }

    public function down(): void
    {
        $user = $this->db->table('admin_users')->where('email', self::EMAIL)->get()->getRowArray();
        if (! $user) {
            return;
        }

        $userId = (int) $user['id'];
        $this->db->table('user_roles')->where('user_id', $userId)->delete();
        if ($this->db->tableExists('user_permissions')) {
            $this->db->table('user_permissions')->where('user_id', $userId)->delete();
        }
        if ($this->db->tableExists('admin_remember_tokens')) {
            $this->db->table('admin_remember_tokens')->where('admin_user_id', $userId)->delete();
        }
        $this->db->table('admin_users')->where('id', $userId)->delete();
    }

    private function ensureOrderOperationsRole(string $now): int
    {
        $role = $this->db->table('roles')->where('role_code', self::ROLE_CODE)->get()->getRowArray();
        if ($role) {
            $roleId = (int) $role['id'];
        } else {
            $this->db->table('roles')->insert([
                'role_code' => self::ROLE_CODE,
                'name' => 'Order Operations',
                'description' => 'Order creator, order follower and material issuement access',
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $roleId = (int) $this->db->insertID();
        }

        $permissions = $this->db->table('permissions')
            ->select('id')
            ->whereIn('code', self::PERMISSIONS)
            ->get()
            ->getResultArray();
        foreach ($permissions as $permission) {
            $permissionId = (int) ($permission['id'] ?? 0);
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

        return $roleId;
    }
}
