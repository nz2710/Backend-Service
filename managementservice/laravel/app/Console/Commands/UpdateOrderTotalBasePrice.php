<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Models\OrderProduct;

class UpdateOrderTotalBasePrice extends Command
{
    protected $signature = 'orders:update-total-base-price';
    protected $description = 'Update total_base_price and commission for existing orders';

    public function handle()
    {
        $this->info('Updating orders...');

        Order::chunk(100, function ($orders) {
            foreach ($orders as $order) {
                $totalBasePrice = OrderProduct::where('order_id', $order->id)
                    ->join('products', 'order_product.product_id', '=', 'products.id')
                    ->sum(\DB::raw('order_product.quantity * products.price'));

                $commission = $order->price - $totalBasePrice;

                $order->update([
                    'total_base_price' => $totalBasePrice,
                    'commission' => $commission
                ]);

                $this->info("Updated order ID: {$order->id}");
            }
        });

        $this->info('All orders have been updated.');
    }
}
