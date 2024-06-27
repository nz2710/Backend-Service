<?php

namespace Database\Seeders;

use App\Models\Order;
use League\Csv\Reader;
use App\Models\Partner;
use App\Models\Product;
use Faker\Factory as Faker;
use App\Models\OrderProduct;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class OrderProductTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Lấy tất cả các order_id từ bảng orders
        $orderIds = Order::pluck('id')->all();

        // Lấy tất cả các product từ bảng products
        $products = Product::all();

        // Khởi tạo Faker
        $faker = Faker::create();

        foreach ($orderIds as $orderId) {
            // Random số lượng product cho mỗi order (từ 1 đến 5)
            $numProducts = $faker->numberBetween(1, 5);

            // Lấy ngẫu nhiên các product
            $randomProducts = $products->random($numProducts);

            $totalPrice = 0; // Khởi tạo biến để lưu tổng giá trị đơn hàng

            foreach ($randomProducts as $product) {
                // Random quantity (từ 1 đến 5)
                $quantity = $faker->numberBetween(1, 5);

                // Random giá bán (từ price của product đến price của product + 100000)
                $price = $faker->numberBetween($product->price, $product->price + 100000);

                // Tính giá trị đơn hàng (price * quantity) và cộng vào tổng giá trị đơn hàng
                $totalPrice += $price * $quantity;

                // Lấy created_at của order
                $createdAt = Order::find($orderId)->created_at;

                // Tạo bản ghi trong bảng order_product
                OrderProduct::create([
                    'order_id' => $orderId,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            // Cập nhật trường price và discount của order
            $order = Order::find($orderId);
            $order->price = $totalPrice;
            $order->discount = 10;
            $order->save();
        }

        // Lấy tất cả các partner_id từ bảng partners
        $partnerIds = Partner::pluck('id')->all();

        foreach ($partnerIds as $partnerId) {
            // Tính tổng revenue của partner
            $revenue = Order::where('partner_id', $partnerId)->sum('price');

            // Tính commission (10% của revenue)
            $commission = $revenue * 0.1;

            // Đếm số lượng đơn hàng của partner
            $numberOfOrders = Order::where('partner_id', $partnerId)->count();

            // Cập nhật thông tin partner
            $partner = Partner::find($partnerId);
            $partner->revenue = $revenue;
            $partner->commission = $commission;
            $partner->number_of_order = $numberOfOrders;
            $partner->save();
        }
    }
}
