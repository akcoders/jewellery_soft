<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        $email = 'admin@demo.com';
        $now   = date('Y-m-d H:i:s');

        $table = $this->db->table('admin_users');
        $user  = $table->where('email', $email)->get()->getRowArray();

        $data = [
            'name'          => 'Demo Admin',
            'email'         => $email,
            'password_hash' => password_hash('Admin@123', PASSWORD_DEFAULT),
            'is_active'     => 1,
            'updated_at'    => $now,
        ];

        if ($user) {
            $table->where('id', $user['id'])->update($data);
            return;
        }

        $data['created_at'] = $now;
        $table->insert($data);
    }
}

