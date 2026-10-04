<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case OrderManager = 'order_manager';
    case Packaging = 'packaging';
    case Warehouse = 'warehouse';
    case Delivery = 'delivery';
    case Support = 'support';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::OrderManager => 'Order Manager',
            self::Packaging => 'Packaging Station',
            self::Warehouse => 'Warehouse',
            self::Delivery => 'Delivery Agent',
            self::Support => 'Customer Support',
            self::Customer => 'Shopkeeper',
        };
    }

    /** Roles that belong to a single hub / warehouse. */
    public function isHubScoped(): bool
    {
        return in_array($this, [self::Packaging, self::Warehouse, self::Delivery], true);
    }

    public function isStaff(): bool
    {
        return $this !== self::Customer;
    }

    /** Where this role lands after login on the web. */
    public function home(): string
    {
        return match ($this) {
            self::SuperAdmin => '/staff/admin',
            self::OrderManager => '/staff/orders',
            self::Packaging => '/staff/packaging',
            self::Warehouse => '/staff/warehouse',
            self::Delivery => '/staff/delivery',
            self::Support => '/staff/support',
            self::Customer => '/portal',
        };
    }
}
