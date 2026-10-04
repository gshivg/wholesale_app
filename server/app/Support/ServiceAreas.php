<?php

namespace App\Support;

use App\Models\Hub;

class ServiceAreas
{
    /** @return array{district:string,pinPrefix:string,hubCode:string}|null */
    public static function match(string $pincode): ?array
    {
        foreach (config('shared.serviceAreas') as $area) {
            if (str_starts_with($pincode, $area['pinPrefix'])) {
                return $area;
            }
        }

        return null;
    }

    public static function hubFor(string $pincode): ?Hub
    {
        $area = self::match($pincode);

        return $area ? Hub::where('code', $area['hubCode'])->first() : null;
    }

    public static function districts(): array
    {
        return array_column(config('shared.serviceAreas'), 'district');
    }
}
