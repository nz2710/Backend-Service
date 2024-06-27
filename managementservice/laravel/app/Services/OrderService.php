<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Services\PartnerService;
use App\Services\ProductService;
use GuzzleHttp\Client;

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
        $order = Order::with('partner', 'products')->find($orderId);

        if ($order) {
            // Kiểm tra nếu đơn hàng đã bị hủy trước đó
            if ($order->status === 'Cancelled') {
                return $order;
            }

            $partner = $order->partner;
            $products = $order->products;

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

            // Cập nhật trạng thái đơn hàng thành "cancelled"
            $order->status = 'Cancelled';
            $order->save();

            return $order;
        }

        return null;
    }

    public function deleteOrder($orderId)
    {
        $order = Order::with('partner', 'products')->find($orderId);

        if ($order) {
            $partner = $order->partner;
            $products = $order->products;

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

            // Xoá các bản ghi trong bảng trung gian order_product
            $order->products()->detach();

            // Xoá đơn hàng
            $order->delete();

            return $order;
        }

        return null;
    }

    public function createOrder(array $data, $partnerId)
    {
        $client = new Client();
        $address = $data['address'];
        $apiKey = env('GOONG_API_KEY');

        // Gọi Goong.io Geocoding API để lấy thông tin địa lý từ địa chỉ
        $response = $client->get("https://rsapi.goong.io/geocode?address=" . urlencode($address) . "&api_key=$apiKey");
        $responseBody = json_decode($response->getBody(), true);

        if (empty($responseBody['results'])) {
            throw new \Exception('Address does not exist');
        }

        $location = $responseBody['results'][0]['geometry']['location'];
        $latitude = $location['lat'];
        $longitude = $location['lng'];

        // Bắt đầu transaction
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
            $order->discount = $order->partner->discount;
            $order->save();

            // Kiểm tra số lượng, giá và trạng thái sản phẩm trước khi thêm vào đơn hàng
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
            }

            // Thêm sản phẩm vào đơn hàng
            foreach ($data['products'] as $product) {
                $order->products()->attach($product['id'], [
                    'quantity' => $product['quantity'],
                    'price' => $product['price']
                ]);

                $productModel = Product::findOrFail($product['id']);
                $this->productService->updateProductQuantity($productModel, $product['quantity']);
            }

            $order->price = $order->calculateTotalPrice();
            $order->save();

            $this->partnerService->updatePartnerOnNewOrder($order->partner, $order->price);

            // Commit transaction nếu không có lỗi
            DB::commit();

            return $order;
        } catch (\Exception $e) {
            // Rollback transaction nếu có lỗi
            DB::rollBack();

            throw $e;
        }
    }
}
