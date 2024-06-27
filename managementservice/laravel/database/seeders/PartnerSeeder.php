<?php

namespace Database\Seeders;
use App\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    public function run()
    {
        Partner::factory()->count(100)->create();
    }
}
