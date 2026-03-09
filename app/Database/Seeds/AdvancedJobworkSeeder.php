<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class AdvancedJobworkSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db;

        $warehouses = ['VAULT', 'STORE', 'WIP_STORE', 'FG_STORE', 'SHOWROOM', 'BRANCH_STORE'];
        foreach ($warehouses as $code) {
            $row = $db->table('warehouses')->where('warehouse_code', $code)->get()->getRowArray();
            if (! $row) {
                $id = (int) $db->table('warehouses')->insert([
                    'warehouse_code' => $code,
                    'name' => ucwords(strtolower(str_replace('_', ' ', $code))),
                    'warehouse_type' => $code,
                    'is_active' => 1,
                ], true);
            } else {
                $id = (int) $row['id'];
            }

            $bin = $db->table('bins')->where('warehouse_id', $id)->where('bin_code', 'MAIN')->get()->getRowArray();
            if (! $bin) {
                $db->table('bins')->insert([
                    'warehouse_id' => $id,
                    'bin_code' => 'MAIN',
                    'name' => 'Main Bin',
                    'is_active' => 1,
                ]);
            }
        }

        foreach ([
            ['OWNER', 'Owner'],
            ['ADMIN', 'Admin'],
            ['STORE_MANAGER', 'Store Manager'],
            ['ACCOUNTS', 'Accounts'],
        ] as $role) {
            if (! $db->table('roles')->where('name', $role[0])->get()->getRowArray()) {
                $db->table('roles')->insert(['name' => $role[0], 'description' => $role[1]]);
            }
        }

        foreach ([
            ['order.manage', 'Manage Orders'],
            ['voucher.post', 'Post Voucher'],
            ['voucher.reverse', 'Reverse Voucher'],
            ['qc.approve', 'QC Approve'],
            ['invoice.create', 'Create Invoice'],
            ['reports.view', 'View Reports'],
        ] as $perm) {
            if (! $db->table('permissions')->where('code', $perm[0])->get()->getRowArray()) {
                $db->table('permissions')->insert(['code' => $perm[0], 'name' => $perm[1]]);
            }
        }

        if (! $db->table('gold_purities')->where('purity_code', '22K')->get()->getRowArray()) {
            $db->table('gold_purities')->insert([
                'purity_code' => '22K',
                'purity_percent' => 91.666,
                'color_name' => 'YG',
                'is_active' => 1,
            ]);
        }

        if (! $db->table('gold_purities')->where('purity_code', '18K')->get()->getRowArray()) {
            $db->table('gold_purities')->insert([
                'purity_code' => '18K',
                'purity_percent' => 75.000,
                'color_name' => 'YG',
                'is_active' => 1,
            ]);
        }
    }
}
