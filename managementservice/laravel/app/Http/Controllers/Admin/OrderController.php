<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use GuzzleHttp\Client;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\OrderService;
use App\Services\PartnerService;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Rules\ValidateProductQuantityPrice;

class OrderController extends Controller
{

    protected $productService;
    protected $partnerService;
    protected $orderService;

    public function __construct(ProductService $productService, PartnerService $partnerService, OrderService $orderService)
    {
        $this->productService = $productService;
        $this->partnerService = $partnerService;
        $this->orderService = $orderService;
    }

    public function index(Request $request)
    {
        $code_order = $request->input('code_order');
        $customer_name = $request->input('customer_name');
        $partner_name = $request->input('partner_name');
        $status = $request->input('status');
        $phone = $request->input('phone');
        $address = $request->input('address');
        $orderBy = $request->input('order_by', 'id');
        $sortBy = $request->input('sort_by', 'asc');

        $order = Order::orderBy($orderBy, $sortBy);

        if ($customer_name) {
            $order = $order->where('customer_name', 'like', '%' . $customer_name . '%');
        }

        if ($address) {
            $order = $order->where('address', 'like', '%' . $address . '%');
        }

        if ($phone) {
            $order = $order->where('phone', 'like', '%' . $phone . '%');
        }
        if ($code_order) {
            $order = $order->where('code_order', 'like', '%' . $code_order . '%');
        }

        if ($partner_name) {
            $order = $order->whereHas('partner', function ($query) use ($partner_name) {
                $query->where('name', 'like', '%' . $partner_name . '%');
            });
        }

        if ($status) {
            $order = $order->where('status', $status);
        }

        $order = $order->with('partner')->paginate(10);


        return response()->json([
            'success' => true,
            'message' => 'List of all orders',
            'data' => $order
        ]);
    }

    // {
    //     $request->validate([
    //         'address' => 'required|string',
    //         'partner_id' => 'required|exists:partners,id',
    //         'customer_name' => 'required|string|max:255',
    //         'phone' => 'required|string',
    //         'mass_of_order' => 'required',
    //         'time_service' => 'required|numeric',
    //         'products' => 'required|array',
    //         'products.*.id' => 'required|exists:products,id',
    //         'products.*.quantity' => ['required', 'integer', 'min:1'],
    //         'products.*.price' => ['required', 'numeric', 'min:0'],
    //     ]);

    //     $client = new Client();
    //     $address = $request->address;
    //     $apiKey = env('GOONG_API_KEY');

    //     // Gọi Goong.io Geocoding API để lấy thông tin địa lý từ địa chỉ
    //     $response = $client->get("https://rsapi.goong.io/geocode?address=" . urlencode($address) . "&api_key=$apiKey");
    //     $responseBody = json_decode($response->getBody(), true);

    //     if (empty($responseBody['results'])) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Address does not exist',
    //         ], 400);
    //     }

    //     $location = $responseBody['results'][0]['geometry']['location'];
    //     $latitude = $location['lat'];
    //     $longitude = $location['lng'];

    //     // Bắt đầu transaction
    //     DB::beginTransaction();

    //     try {
    //         $order = new Order();
    //         $order->code_order = $order->generateCodeOrder($request->partner_id);
    //         $order->partner_id = $request->partner_id;
    //         $order->customer_name = $request->customer_name;
    //         $order->phone = $request->phone;
    //         $order->mass_of_order = $request->mass_of_order;
    //         $order->address = $request->address;
    //         $order->longitude = $longitude;
    //         $order->latitude = $latitude;
    //         $order->time_service = $request->time_service;
    //         $order->discount = $order->partner->discount;
    //         $order->status = 'Pending';
    //         $order->save();

    //         // Kiểm tra số lượng, giá và trạng thái sản phẩm trước khi thêm vào đơn hàng
    //         foreach ($request->products as $product) {
    //             $productModel = Product::findOrFail($product['id']);

    //             if ($product['quantity'] > $productModel->quantity) {
    //                 throw new \Exception('Số lượng sản phẩm vượt quá số lượng trong kho');
    //             }

    //             if ($product['price'] < $productModel->price) {
    //                 throw new \Exception('Giá sản phẩm không hợp lệ');
    //             }

    //             if ($productModel->status !== 'Active') {
    //                 throw new \Exception('Sản phẩm không ở trạng thái active');
    //             }
    //         }

    //         // Thêm sản phẩm vào đơn hàng
    //         foreach ($request->products as $product) {
    //             $order->products()->attach($product['id'], [
    //                 'quantity' => $product['quantity'],
    //                 'price' => $product['price']
    //             ]);

    //             $productModel = Product::findOrFail($product['id']);
    //             $this->productService->updateProductQuantity($productModel, $product['quantity']);
    //         }

    //         $order->price = $order->calculateTotalPrice();
    //         $order->save();

    //         $this->partnerService->updatePartnerOnNewOrder($order->partner, $order->price);

    //         // Commit transaction nếu không có lỗi
    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Order created successfully',
    //             'data' => $order->load('products')
    //         ]);
    //     } catch (\Exception $e) {
    //         // Rollback transaction nếu có lỗi
    //         DB::rollBack();

    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage(),
    //         ], 400);
    //     }
    // }

    public function store(Request $request)
    {
        $request->validate([
            'address' => 'required|string',
            'partner_id' => 'required|exists:partners,id',
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string',
            'mass_of_order' => 'required',
            'time_service' => 'required|numeric',
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $client = new Client();
        $address = $request->address;
        $apiKey = env('GOONG_API_KEY');

        $response = $client->get("https://rsapi.goong.io/geocode?address=" . urlencode($address) . "&api_key=$apiKey");
        $responseBody = json_decode($response->getBody(), true);

        if (empty($responseBody['results'])) {
            return response()->json([
                'success' => false,
                'message' => 'Address does not exist',
            ], 400);
        }

        $location = $responseBody['results'][0]['geometry']['location'];
        $latitude = $location['lat'];
        $longitude = $location['lng'];

        DB::beginTransaction();

        try {
            $order = new Order();
            $order->code_order = $order->generateCodeOrder($request->partner_id);
            $order->partner_id = $request->partner_id;
            $order->customer_name = $request->customer_name;
            $order->phone = $request->phone;
            $order->mass_of_order = $request->mass_of_order;
            $order->address = $request->address;
            $order->longitude = $longitude;
            $order->latitude = $latitude;
            $order->time_service = $request->time_service;
            $order->discount = $order->partner->discount;
            $order->status = 'Waiting';
            $order->save();

            foreach ($request->products as $product) {
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

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully with Waiting status',
                'data' => $order->load('products')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }


    public function show(Request $request, $id)
    {
        $perPage = $request->input('pageSize', 5);
        $page = $request->input('page', 1);
        $order = Order::with('partner')->find($id);
        if ($order) {
            $products = $order->products()->paginate($perPage, ['*'], 'page', $page);
            $data = [
                'order' => $order,
                'products' => $products->items(),
            ];
            return response()->json([
                'success' => true,
                'message' => 'Order found',
                'data' => $data,
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'data' => null
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if ($order->status !== 'Waiting') {
            return response()->json([
                'success' => false,
                'message' => 'Only orders with Waiting status can be updated',
            ], 400);
        }

        $request->validate([
            'address' => 'string',
            // 'partner_id' => 'exists:partners,id',
            'customer_name' => 'string|max:255',
            'phone' => 'string',
            'mass_of_order' => 'numeric',
            'time_service' => 'numeric',
            'products' => 'array',
            'products.*.id' => 'exists:products,id',
            'products.*.quantity' => ['integer', 'min:1'],
            'products.*.price' => ['numeric', 'min:0'],
        ]);

        DB::beginTransaction();

        try {
            if ($request->has('address')) {
                $client = new Client();
                $address = $request->address;
                $apiKey = env('GOONG_API_KEY');

                $response = $client->get("https://rsapi.goong.io/geocode?address=" . urlencode($address) . "&api_key=$apiKey");
                $responseBody = json_decode($response->getBody(), true);

                if (empty($responseBody['results'])) {
                    throw new \Exception('Address does not exist');
                }

                $location = $responseBody['results'][0]['geometry']['location'];
                $order->latitude = $location['lat'];
                $order->longitude = $location['lng'];
                $order->address = $request->address;
            }

            // Update partner_id if provided
            // if ($request->has('partner_id')) {
            //     $order->partner_id = $request->partner_id;
            //     // Regenerate code_order if partner changes
            //     $order->code_order = $order->generateCodeOrder($request->partner_id);
            //     $order->discount = $order->partner->discount;
            // }

            $order->customer_name = $request->customer_name ?? $order->customer_name;
            $order->phone = $request->phone ?? $order->phone;
            $order->mass_of_order = $request->mass_of_order ?? $order->mass_of_order;
            $order->time_service = $request->time_service ?? $order->time_service;

            if ($request->has('products')) {
                $order->products()->detach();

                foreach ($request->products as $product) {
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

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'data' => $order->load('products', 'partner')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
    public function confirmOrder($id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        if ($order->status !== 'Waiting') {
            return response()->json([
                'success' => false,
                'message' => 'Only orders with Waiting status can be confirmed',
            ], 400);
        }

        DB::beginTransaction();

        try {
            $order->status = 'Pending';

            foreach ($order->products as $product) {
                $this->productService->updateProductQuantity($product, $product->pivot->quantity);
            }

            $this->partnerService->updatePartnerOnNewOrder($order->partner, $order->price);

            $order->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order confirmed successfully',
                'data' => $order->load('products')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy($id)
    {
        $order = $this->orderService->cancelOrder($id);

        if ($order) {
            return response()->json([
                'success' => true,
                'message' => 'Order cancelled successfully',
                'data' => $order
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'data' => null
            ]);
        }
    }

    public function delete($id)
    {
        $order = $this->orderService->deleteOrder($id);

        if ($order) {
            return response()->json([
                'success' => true,
                'message' => 'Order deleted successfully',
                'data' => $order
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'data' => null
            ]);
        }
    }
}
