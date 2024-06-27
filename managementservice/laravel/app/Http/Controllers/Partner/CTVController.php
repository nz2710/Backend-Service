<?php

namespace App\Http\Controllers\Partner;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Services\OrderService;
use App\Services\PartnerService;
use App\Services\ProductService;
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
        $partnerId = $request->user['partner_id'];
        $orders = Order::where('partner_id', $partnerId)
            ->with('products')
            ->paginate(10);

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
            'mass_of_order' => 'required',
            'time_service' => 'required|numeric',
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $partnerId = $request->user['partner_id'];

        $order = $this->orderService->createOrder($request->all(), $partnerId);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order->load('products')
        ]);
    }

    public function show(Request $request, $id)
    {
        $partnerId = $request->user['partner_id'];
        $order = Order::where('partner_id', $partnerId)
            ->with('products')
            ->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order details',
            'data' => $order
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $partnerId = $request->user['partner_id'];
        $order = Order::where('partner_id', $partnerId)->find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
                'data' => null
            ]);
        }

        $order = $this->orderService->cancelOrder($order);

        return response()->json([
            'success' => true,
            'message' => 'Order canceled successfully',
            'data' => $order
        ]);
    }
}
