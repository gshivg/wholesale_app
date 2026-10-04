<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Demo staff — all use password "password". */
class StaffSeeder extends Seeder
{
    public function run(): void
    {
        $hubs = Hub::pluck('id', 'code');
        $staff = [
            ['Super Admin', '9000000001', 'admin@royalsnacks.example', Role::SuperAdmin, null],
            ['Order Manager', '9000000002', 'orders@royalsnacks.example', Role::OrderManager, null],
            ['Support Agent Asha', '9000000003', 'asha@royalsnacks.example', Role::Support, null],
            ['Support Agent Imran', '9000000004', 'imran@royalsnacks.example', Role::Support, null],
            // Prayagraj main warehouse
            ['Packer Prayagraj', '9000000010', null, Role::Packaging, 'PRG'],
            ['Warehouse Prayagraj', '9000000011', null, Role::Warehouse, 'PRG'],
            ['Driver Ravi (PRG)', '9000000012', null, Role::Delivery, 'PRG'],
            ['Driver Sunil (PRG)', '9000000013', null, Role::Delivery, 'PRG'],
            // Varanasi
            ['Packer Varanasi', '9000000020', null, Role::Packaging, 'VNS'],
            ['Warehouse Varanasi', '9000000021', null, Role::Warehouse, 'VNS'],
            ['Driver Manoj (VNS)', '9000000022', null, Role::Delivery, 'VNS'],
            // Kanpur
            ['Packer Kanpur', '9000000030', null, Role::Packaging, 'KNP'],
            ['Warehouse Kanpur', '9000000031', null, Role::Warehouse, 'KNP'],
            ['Driver Deepak (KNP)', '9000000032', null, Role::Delivery, 'KNP'],
            // Lucknow
            ['Packer Lucknow', '9000000040', null, Role::Packaging, 'LKO'],
            ['Warehouse Lucknow', '9000000041', null, Role::Warehouse, 'LKO'],
            ['Driver Arif (LKO)', '9000000042', null, Role::Delivery, 'LKO'],
        ];
        foreach ($staff as [$name, $phone, $email, $role, $hub]) {
            User::updateOrCreate(['phone' => $phone], [
                'name' => $name, 'email' => $email, 'password' => 'password', 'role' => $role,
                'hub_id' => $hub ? $hubs[$hub] : null, 'phone_verified_at' => now(), 'is_active' => true,
            ]);
        }
    }
}
