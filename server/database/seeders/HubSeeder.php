<?php

namespace Database\Seeders;

use App\Models\Hub;
use Illuminate\Database\Seeder;

/** Umbrella setup: one main warehouse (Prayagraj) feeding distribution centres. */
class HubSeeder extends Seeder
{
    public function run(): void
    {
        $main = Hub::updateOrCreate(['code' => 'PRG'], [
            'name' => 'Prayagraj Main Warehouse', 'type' => 'main', 'city' => 'Prayagraj', 'district' => 'Prayagraj',
            'pincode' => '211008', 'address' => 'Industrial Area, Naini, Prayagraj',
        ]);
        foreach ([
            ['VNS', 'Varanasi Distribution Centre', 'Varanasi', '221106', 'Ramnagar Industrial Area, Varanasi'],
            ['KNP', 'Kanpur Distribution Centre', 'Kanpur', '208012', 'Panki Industrial Area, Kanpur'],
            ['LKO', 'Lucknow Distribution Centre', 'Lucknow', '226008', 'Amausi Industrial Area, Lucknow'],
        ] as [$code, $name, $city, $pin, $addr]) {
            Hub::updateOrCreate(['code' => $code], [
                'name' => $name, 'type' => 'dc', 'city' => $city, 'district' => $city,
                'pincode' => $pin, 'address' => $addr, 'parent_hub_id' => $main->id,
            ]);
        }
    }
}
