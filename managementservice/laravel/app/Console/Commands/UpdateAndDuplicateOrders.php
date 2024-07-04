<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\OrderProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateAndDuplicateOrders extends Command
{
    protected $signature = 'orders:update-and-copy';
    protected $description = 'Update order statuses and copy orders with modified data';

    public function handle()
    {
        DB::transaction(function () {
            // Update all orders to 'Success' status
            Order::query()->update(['status' => 'Success']);

            // Get all orders with 'Success' status
            $orders = Order::where('status', 'Success')->get();

            foreach ($orders as $order) {
                // Copy the order with modified data
                $newOrder = $order->replicate();
                $newOrder->status = 'Pending';
                $newOrder->created_at = $this->getRandomDateTime();
                $newOrder->updated_at = now();
                $newOrder->save();

                // Copy the associated order products
                $orderProducts = OrderProduct::where('order_id', $order->id)->get();
                foreach ($orderProducts as $orderProduct) {
                    $newOrderProduct = $orderProduct->replicate();
                    $newOrderProduct->order_id = $newOrder->id;
                    $newOrderProduct->created_at = $newOrder->created_at;
                    $newOrderProduct->updated_at = $newOrder->created_at;
                    $newOrderProduct->save();
                }
            }
        });

        $this->info('Orders updated and copied successfully.');
    }

    private function getRandomDateTime()
    {
        $startDateTime = now()->subDay()->startOfDay();
        $endDateTime = now();

        return $startDateTime->addSeconds(rand(0, $endDateTime->diffInSeconds($startDateTime)));
    }
}
