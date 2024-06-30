<?php

namespace App\Services;

use App\Models\Order;
use GuzzleHttp\Client;
use App\Models\Partner;
use App\Models\Product;
use App\Services\PartnerService;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;

class OrderService
{

    protected $partnerService;
    protected $productService;

    public function __construct(PartnerService $partnerService, ProductService $productService)
    {
        $this->partnerService = $partnerService;
        $this->productService = $productService;
    }
    public function cancelOrder($orderId)
    {
        $order = Order::with('partner', 'products')->findOrFail($orderId);

        if ($order->status === 'Cancelled') {
            return $order;
        }

        if ($order->status === 'Success') {
            throw new \Exception('Cannot cancel a successful order');
        }

        DB::beginTransaction();

        try {
            $partner = $order->partner;
            $products = $order->products;

            switch ($order->status) {
                case 'Waiting':
                    // Không cần cập nhật partner hay sản phẩm
                    break;
                case 'Pending':
                case 'Delivery':
                    // Cập nhật revenue, number_of_order và commission của partner
                    $partner->revenue -= $order->price;
                    $partner->number_of_order -= 1;
                    $partner->commission -= $order->price * ($order->discount / 100);
                    $partner->save();

                    // Cập nhật quantity của các sản phẩm trong đơn hàng
                    foreach ($products as $product) {
                        $product->quantity += $product->pivot->quantity;
                        $product->save();
                    }
                    break;
            }

            $order->status = 'Cancelled';
            $order->save();

            DB::commit();

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteOrder($orderId)
    {
        $order = Order::with('partner', 'products')->findOrFail($orderId);

        if ($order->status === 'Success') {
            throw new \Exception('Cannot delete a successful order');
        }

        DB::beginTransaction();

        try {
            $partner = $order->partner;
            $products = $order->products;

            switch ($order->status) {
                case 'Waiting':
                    // Không cần cập nhật partner hay sản phẩm
                    break;
                case 'Pending':
                case 'Delivery':
                    // Cập nhật revenue, number_of_order và commission của partner
                    $partner->revenue -= $order->price;
                    $partner->number_of_order -= 1;
                    $partner->commission -= $order->price * ($order->discount / 100);
                    $partner->save();

                    // Cập nhật quantity của các sản phẩm trong đơn hàng
                    foreach ($products as $product) {
                        $product->quantity += $product->pivot->quantity;
                        $product->save();
                    }
                    break;
                case 'Cancelled':
                    // Không cần cập nhật partner hay sản phẩm vì đã được cập nhật khi hủy đơn hàng
                    break;
            }

            // Xoá các bản ghi trong bảng trung gian order_product
            $order->products()->detach();

            // Xoá đơn hàng
            $order->delete();

            DB::commit();

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function createOrder(array $data, $partnerId)
    {
        $client = new Client();
        $address = $data['address'];
        $apiKey = env('GOONG_API_KEY');

        $response = $client->get("https://rsapi.goong.io/geocode?address=" . urlencode($address) . "&api_key=$apiKey");
        $responseBody = json_decode($response->getBody(), true);

        if (empty($responseBody['results'])) {
            throw new \Exception('Address does not exist');
        }

        $location = $responseBody['results'][0]['geometry']['location'];
        $latitude = $location['lat'];
        $longitude = $location['lng'];

        DB::beginTransaction();

        try {
            $order = new Order();
            $order->code_order = $order->generateCodeOrder($partnerId);
            $order->partner_id = $partnerId;
            $order->customer_name = $data['customer_name'];
            $order->phone = $data['phone'];
            $order->mass_of_order = $data['mass_of_order'];
            $order->address = $data['address'];
            $order->longitude = $longitude;
            $order->latitude = $latitude;
            $order->time_service = $data['time_service'];
            $order->discount = Partner::find($partnerId)->discount;
            $order->status = 'Waiting';
            $order->save();

            foreach ($data['products'] as $product) {
                $productModel = Product::findOrFail($product['id']);

                if ($product['quantity'] > $productModel->quantity) {
                    throw new \Exception('Số lượng sản phẩm vượt quá số lượng trong kho');
                }

                if ($product['price'] < $productModel->price) {
                    throw new \Exception('Giá sản phẩm không hợp lệ');
                }

                if ($productModel->status !== 'Active') {
                    throw new \Exception('Sản phẩm không ở trạng thái active');
                }

                $order->products()->attach($product['id'], [
                    'quantity' => $product['quantity'],
                    'price' => $product['price']
                ]);
            }

            $order->price = $order->calculateTotalPrice();
            $order->save();

            DB::commit();

            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateOrder($orderId, array $data, $partnerId)
    {
        $order = Order::where('partner_id', $partnerId)->findOrFail($orderId);

        if ($order->status !== 'Waiting') {
            throw new \Exception('Only orders with Waiting status can be updated');
        }

        DB::beginTransaction();

        try {
            // Update address if provided
            if (isset($data['address'])) {
                $client = new Client();
                $apiKey = env('GOONG_API_KEY');

                $response = $client->get("https://rsapi.goong.io/geocode?address=" . urlencode($data['address']) . "&api_key=$apiKey");
                $responseBody = json_decode($response->getBody(), true);

                if (empty($responseBody['results'])) {
                    throw new \Exception('Address does not exist');
                }

                $location = $responseBody['results'][0]['geometry']['location'];
                $order->latitude = $location['lat'];
                $order->longitude = $location['lng'];
                $order->address = $data['address'];
            }

            // Update order details
            $order->customer_name = $data['customer_name'] ?? $order->customer_name;
            $order->phone = $data['phone'] ?? $order->phone;
            $order->mass_of_order = $data['mass_of_order'] ?? $order->mass_of_order;
            $order->time_service = $data['time_service'] ?? $order->time_service;

            // Update products if provided
            if (isset($data['products'])) {
                $order->products()->detach();

                foreach ($data['products'] as $product) {
                    $productModel = Product::findOrFail($product['id']);

                    if ($product['quantity'] > $productModel->quantity) {
                        throw new \Exception('Số lượng sản phẩm vượt quá số lượng trong kho');
                    }

                    if ($product['price'] < $productModel->price) {
                        throw new \Exception('Giá sản phẩm không hợp lệ');
                    }

                    if ($productModel->status !== 'Active') {
                        throw new \Exception('Sản phẩm không ở trạng thái active');
                    }

                    $order->products()->attach($product['id'], [
                        'quantity' => $product['quantity'],
                        'price' => $product['price']
                    ]);
                }

                $order->price = $order->calculateTotalPrice();
            }

            $order->save();

            DB::commit();

            return $order->load('products');
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
