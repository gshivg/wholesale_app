<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;

class StaffNav
{
    /** @return list<array{href:string,icon:string,label:string,match:string}> */
    public static function for(User $u): array
    {
        $all = [
            'admin' => ['/staff/admin', 'dashboard', 'Overview', 'staff/admin'],
            'orders' => ['/staff/orders', 'receipt_long', 'Orders', 'staff/orders*'],
            'packaging' => ['/staff/packaging', 'inventory_2', 'Packaging station', 'staff/packaging'],
            'warehouse' => ['/staff/warehouse', 'warehouse', 'Warehouse', 'staff/warehouse'],
            'delivery' => ['/staff/delivery', 'local_shipping', 'Deliveries', 'staff/delivery'],
            'shops' => ['/staff/shops', 'storefront', 'Shopkeepers', 'staff/shops*'],
            'support' => ['/staff/support', 'support_agent', 'Support tickets', 'staff/support*'],
            'products' => ['/staff/products', 'category', 'Products', 'staff/products'],
            'inventory' => ['/staff/inventory', 'stacks', 'Inventory & hubs', 'staff/inventory'],
            'offers' => ['/staff/offers', 'local_offer', 'Offers & coupons', 'staff/offers'],
            'team' => ['/staff/team', 'groups', 'Team', 'staff/team'],
            'audit' => ['/staff/audit', 'history', 'Audit log', 'staff/audit'],
        ];
        $keys = match ($u->role) {
            Role::SuperAdmin => array_keys($all),
            Role::OrderManager => ['orders', 'shops'],
            Role::Packaging => ['packaging'],
            Role::Warehouse => ['warehouse'],
            Role::Delivery => ['delivery'],
            Role::Support => ['support'],
            default => [],
        };

        return array_map(fn ($k) => ['href' => $all[$k][0], 'icon' => $all[$k][1], 'label' => $all[$k][2], 'match' => $all[$k][3]], $keys);
    }
}
