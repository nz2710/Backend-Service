<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Console\Command;

class UpdateTotalCostandProfit extends Command
{
    protected $signature = 'orders:update-total-cost-profit';
    protected $description = 'Update total_cost and profit for existing orders';

    public function handle()
    {
        $this->info('Updating orders...');

        Order::chunk(100, function ($orders) {
            foreach ($orders as $order) {
                $totalCost = OrderProduct::where('order_id', $order->id)
                    ->join('products', 'order_product.product_id', '=', 'products.id')
                    ->sum(\DB::raw('order_product.quantity * products.cost'));

                $profit = $order->total_base_price - $totalCost;

                $order->update([
                    'total_cost' => $totalCost,
                    'profit' => $profit
                ]);

                $this->info("Updated order ID: {$order->id}");
            }
        });

        $this->info('All orders have been updated.');
    }
}
