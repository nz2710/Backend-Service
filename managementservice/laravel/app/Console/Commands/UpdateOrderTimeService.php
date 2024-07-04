<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class UpdateOrderTimeService extends Command
{
    protected $signature = 'orders:update-time-service';
    protected $description = 'Update time_service for all orders based on mass_of_order';

    public function handle()
    {
        $this->info('Updating order time_service...');

        DB::table('orders')
            ->update([
                'time_service' => DB::raw('0.02 * 60 * mass_of_order')
            ]);

        $this->info('Order time_service updated successfully.');
    }
}