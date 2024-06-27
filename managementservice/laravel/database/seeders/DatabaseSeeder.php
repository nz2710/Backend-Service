<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\DepotsTableSeeder;
use Database\Seeders\OrdersTableSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        // $this->call(OrdersTableSeeder::class);
        $this->call(DepotsTableSeeder::class);
        // $this->call(PartnerSeeder::class);
        // $this->call(ProductSeeder::class);
        // $this->call(OrderProductTableSeeder::class);
        // $this->call(OrderAddressSeeder::class);
    }
}
