<?php

namespace App\Http\Controllers\Partner;

use App\Models\Order;
use App\Models\Partner;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Services\OrderService;
use App\Services\PartnerService;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class CTVController extends Controller
{
    protected $orderService;
    protected $partnerService;
    protected $productService;

    public function __construct(OrderService $orderService, PartnerService $partnerService, ProductService $productService)
    {
        $this->orderService = $orderService;
        $this->partnerService = $partnerService;
        $this->productService = $productService;

        $this->middleware('authPartner');
    }

    public function index(Request $request)
    {
        $code_order = $request->input('code_order');
        $customer_name = $request->input('customer_name');
        $status = $request->input('status');
        $phone = $request->input('phone');
        $address = $request->input('address');
        $orderBy = $request->input('order_by', 'id');
        $sortBy = $request->input('sort_by', 'asc');
        $partnerId = $request->user['partner_id'];
        $orders = Order::where('partner_id', $partnerId)
            ->orderBy($orderBy, $sortBy);

        if ($customer_name) {
            $orders = $orders->where('customer_name', 'like', '%' . $customer_name . '%');
        }

        if ($address) {
            $orders = $orders->where('address', 'like', '%' . $address . '%');
        }

        if ($phone) {
            $orders = $orders->where('phone', 'like', '%' . $phone . '%');
        }
        if ($code_order) {
            $orders = $orders->where('code_order', 'like', '%' . $code_order . '%');
        }

        if ($status) {
            $orders = $orders->where('status', $status);
        }

        $orders = $orders->with('partner')->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'List of partner orders',
            'data' => $orders
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'address' => 'required|string',
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string',
            'mass_of_order' => 'required|numeric',
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $partnerId = $request->user['partner_id'];

        try {
            $order = $this->orderService->createOrder($request->all(), $partnerId);

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully with Waiting status',
                'data' => $order->load('products')
            ]);
        } catch (\Exception $e) {
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
        $partnerId = $request->user['partner_id'];
        $order = Order::where('partner_id', $partnerId)
            ->with('partner')
            ->find($id);
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
        $partnerId = $request->user['partner_id'];

        $request->validate([
            'address' => 'string',
            // 'partner_id' => 'exists:partners,id',
            'customer_name' => 'string|max:255',
            'phone' => 'string',
            'mass_of_order' => 'numeric',
            'products' => 'array',
            'products.*.id' => 'exists:products,id',
            'products.*.quantity' => ['integer', 'min:1'],
            'products.*.price' => ['numeric', 'min:0'],
        ]);

        try {
            $updatedOrder = $this->orderService->updateOrder($id, $request->all(), $partnerId);

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
                'data' => $updatedOrder->load('products')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function cancel(Request $request, $id)
    {
        $partnerId = $request->user['partner_id'];

        try {
            $cancelledOrder = $this->orderService->cancelOrder($id);

            // Kiểm tra xem đơn hàng có thuộc về CTV này không
            if ($cancelledOrder->partner_id != $partnerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to cancel this order',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order canceled successfully',
                'data' => $cancelledOrder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    // public function delete(Request $request, $id)
    // {
    //     $partnerId = $request->user['partner_id'];

    //     try {
    //         $deletedOrder = $this->orderService->deleteOrder($id);

    //         // Kiểm tra xem đơn hàng có thuộc về CTV này không
    //         if ($deletedOrder->partner_id != $partnerId) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'You are not authorized to delete this order',
    //             ], 403);
    //         }

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Order deleted successfully',
    //             'data' => $deletedOrder
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage(),
    //         ], 400);
    //     }
    // }


    //Products

    public function showProduct(Request $request)
    {
        $name = $request->input('name');
        $sku = $request->input('sku');
        // $vehicle_type = $request->input('vehicle_type');
        $orderBy = $request->input('order_by', 'id');
        $sortBy = $request->input('sort_by', 'asc');

        $product = Product::orderBy($orderBy, $sortBy);

        if ($name) {
            $product = $product->where('name', 'like', '%' . $name . '%');
        }

        if ($sku) {
            $product = $product->where('sku', 'like', '%' . $sku . '%');
        }

        $product = $product->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'List of all products',
            'data' => $product
        ]);
    }

    public function showProductDetail($id)
    {
        $product = Product::find($id);
        if ($product) {
            return response()->json([
                'success' => true,
                'message' => 'Product found',
                'data' => $product
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'data' => null
            ]);
        }
    }

    public function getAll()

    {
        $product = Product::where('status', 'Active')->get(['id', 'name', 'sku', 'price', 'quantity']);
        return response()->json([
            'success' => true,
            'message' => 'List of all products',
            'data' => $product
        ]);
    }

    public function getStats(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));
        $filterType = $request->input('filter_type', 'month');
        $orderBy = $request->input('order_by', 'stat_date');
        $sortBy = $request->input('sort_by', 'desc');

        $partnerId = $request->user['partner_id'];

        $query = Partner::where('id', $partnerId)->with(['monthlyStats' => function ($query) use ($year, $month, $filterType, $orderBy, $sortBy) {
            $query->whereYear('stat_date', $year);
            if ($filterType === 'month') {
                $query->whereMonth('stat_date', $month);
            }
            $query->orderBy($orderBy, $sortBy);
        }]);

        $partner = $query->first();

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
                'data' => null
            ], 404);
        }

        $stats = $partner->monthlyStats->map(function ($stat) {
            return [
                'stat_date' => $stat->stat_date,
                'total_base_price' => $stat->total_base_price,
                'revenue' => $stat->revenue,
                'commission' => $stat->commission,
                'bonus' => $stat->bonus,
                'total_amount' => $stat->total_amount,
                'order_count' => $stat->order_count,
            ];
        });

        if ($filterType === 'year') {
            $totalStats = [
                'total_base_price' => $stats->sum('total_base_price'),
                'revenue' => $stats->sum('revenue'),
                'commission' => $stats->sum('commission'),
                'bonus' => $stats->sum('bonus'),
                'total_amount' => $stats->sum('total_amount'),
                'order_count' => $stats->sum('order_count'),
            ];
        } else {
            $totalStats = $stats->first() ?: [
                'total_base_price' => 0,
                'revenue' => 0,
                'commission' => 0,
                'bonus' => 0,
                'total_amount' => 0,
                'order_count' => 0,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => $filterType === 'year' ? 'Yearly stats of partner' : 'Monthly stats of partner',
            'data' => [
                'partner_id' => $partner->id,
                'partner_name' => $partner->name,
                'filter_type' => $filterType,
                'year' => $year,
                'month' => $filterType === 'month' ? $month : null,
                'total_stats' => $totalStats,
                'detailed_stats' => $stats
            ]
        ]);
    }
}
