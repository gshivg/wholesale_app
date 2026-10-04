<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            HubSeeder::class,
            StaffSeeder::class,
            CatalogSeeder::class,
            OfferSeeder::class,
            DemoShopSeeder::class,
        ]);
    }
}
