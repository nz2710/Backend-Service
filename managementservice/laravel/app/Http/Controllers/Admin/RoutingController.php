<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\Plan;
use App\Models\Depot;
use App\Models\Order;
use App\Models\Route;
use GuzzleHttp\Client;
use App\Models\Partner;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class RoutingController extends Controller
{
    public function generateFile(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'num_vehicles' => 'nullable',
                'time_limit' => 'nullable',
                'vehicle_id' => 'required|exists:vehicles,id',
                'order_ids' => 'required_if:select_all_orders,false|array',
                'order_ids.*' => 'exists:orders,id',
                'depot_ids' => 'required_if:select_all_depots,false|array',
                'depot_ids.*' => 'exists:depots,id',
                'select_all_orders' => 'required|boolean',
                'select_all_depots' => 'required|boolean',
                'expected_date' => 'required|date',
            ]);

            $selectedVehicleId = $validatedData['vehicle_id'];
            $selectedVehicle = Vehicle::findOrFail($selectedVehicleId);
            $numVehicles =  $validatedData['num_vehicles'] ?? $selectedVehicle->total_vehicles;
            $timeLimit = $validatedData['time_limit'] ?? 999999;
            $weightLimit = $selectedVehicle->capacity;
            $VValue = $selectedVehicle->speed;
            $expectedDate = $validatedData['expected_date'];

            // Fetch orders
            if ($validatedData['select_all_orders']) {
                $orders = Order::where('status', 'Pending')
                    ->select('id', 'longitude', 'latitude', 'time_service', 'mass_of_order')
                    ->get();
            } else {
                $selectedOrderIds = $validatedData['order_ids'];
                $orders = Order::whereIn('id', $selectedOrderIds)
                    ->where('status', 'Pending')
                    ->select('id', 'longitude', 'latitude', 'time_service', 'mass_of_order')
                    ->get();
            }

            $numOrders = $orders->count();
            if ($numOrders == 0) {
                throw new \Exception("No valid orders found");
            }

            // Fetch depots
            if ($validatedData['select_all_depots']) {
                $depots = Depot::where('status', 'Active')
                    ->select('id', 'longitude', 'latitude')
                    ->get();
            } else {
                $selectedDepotIds = $validatedData['depot_ids'];
                $depots = Depot::whereIn('id', $selectedDepotIds)
                    ->where('status', 'Active')
                    ->select('id', 'longitude', 'latitude')
                    ->get();
            }

            $numDepots = $depots->count();
            if ($numDepots == 0) {
                throw new \Exception("No valid depots found");
            }

            // Generate file content
            $fileContent = "6 $numVehicles $numOrders $numDepots\n";
            $fileContent .= str_repeat("$timeLimit $weightLimit\n", $numDepots);

            $index = 1;
            foreach ($orders as $order) {
                $fileContent .= "$index {$order->latitude} {$order->longitude} {$order->time_service} {$order->mass_of_order} {$order->id}\n";
                $index++;
            }

            foreach ($depots as $depot) {
                $fileContent .= "$index {$depot->latitude} {$depot->longitude} 0 0 {$depot->id}\n";
                $index++;
            }

            $filename = 'mdvrp_' . now()->format('Y-m-d_His') . '.txt';
            $filePath = 'routing/' . $filename;
            Storage::put($filePath, $fileContent);

            $responseData = $this->processFile($filePath, $filename, $VValue, $selectedVehicleId, $expectedDate);

            return response()->json($responseData);
        } catch (\Exception $e) {
            // \Log::error('Error in generateFile: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function processFile($filePath, $filename, $VValue, $selectedVehicleId, $expectedDate)
    {
        try {
            $client = new Client();
            $response = $client->post('http://routingservice:5000/mvrp', [
                'multipart' => [
                    [
                        'name' => 'mvrpFile',
                        'contents' => Storage::get($filePath),
                        'filename' => $filename
                    ],
                    [
                        'name' => 'V',
                        'contents' => $VValue
                    ]
                ]
            ]);

            $responseData = json_decode($response->getBody(), true);
            // Lưu trữ dữ liệu vào cơ sở dữ liệu
            DB::transaction(function () use ($responseData, $selectedVehicleId, $expectedDate) {
                // Lấy thông tin của vehicle được chọn
                $vehicle = Vehicle::find($selectedVehicleId);
                $fuelCost = $vehicle->fuel_cost;
                $fuelConsumption = $vehicle->fuel_consumption;
                $hourly_rate = $vehicle->hourly_rate;
                $shipping_rate = $vehicle->shipping_rate;

                // Tính toán fee dựa trên công thức
                $plan_moving_cost = $fuelCost * $fuelConsumption * $responseData['total_distance_served'];
                $plan_labor_cost = $hourly_rate * ($responseData['total_time_serving_served'] / 60);
                $plan_unloading_cost = $shipping_rate * $responseData['total_demand_served'];
                $plan_fee = $plan_moving_cost + $plan_labor_cost;

                // Khởi tạo các biến tính toán
                $total_order_value = 0;
                $total_order_profit = 0;

                // Lưu thông tin kế hoạch giao hàng vào bảng plans
                $plan = Plan::create([
                    'name' => 'Delivery Plan' . ' ' . now()->format('Y-m-d H:i:s'),
                    'vehicle_id' => $selectedVehicleId,
                    'expected_date' => $expectedDate,
                    'total_demand' => $responseData['total_demand_served'],
                    'total_distance' => $responseData['total_distance_served'],
                    'total_time_serving' => $responseData['total_time_serving_served'],
                    'total_demand_without_allocating_vehicles' => $responseData['total_demand_without_allocating_vehicles'],
                    'total_distance_without_allocating_vehicles' => $responseData['total_distance_without_allocating_vehicles'],
                    'total_time_serving_without_allocating_vehicles' => $responseData['total_time_serving_without_allocating_vehicles'],
                    'total_vehicle_used' => $responseData['total_vehicle_used'],
                    'total_num_customer_served' => $responseData['total_num_customer_served'],
                    'total_num_customer_not_served' => $responseData['total_num_customer_not_served'],
                    'fee' => $plan_fee,
                    'moving_cost' => $plan_moving_cost,
                    'labor_cost' => $plan_labor_cost,
                    'unloading_cost' => $plan_unloading_cost,
                    'status' => 'Pending',
                ]);

                // Lưu thông tin các tuyến đường vào bảng routes và tính toán các giá trị
                foreach ($responseData['route_served_List_return_id'] as $route) {
                    $route_moving_cost = $fuelCost * $fuelConsumption * $route['total_distance'];
                    $route_labor_cost = $hourly_rate * ($route['total_time_serving'] / 60);
                    $route_unloading_cost = $shipping_rate * $route['total_demand'];

                    $depotId = substr($route['depot_origin'], 6);

                    // Lấy danh sách order_id từ mảng route
                    $order_ids = array_filter($route['route'], function ($value) {
                        return is_numeric($value);
                    });

                    // Tính tổng giá trị các đơn hàng
                    $routeStats = Order::whereIn('id', $order_ids)
                        ->selectRaw('SUM(price) as route_order_value, SUM(profit) as total_order_profit')
                        ->first();
                    $route_order_value = $routeStats->route_order_value;
                    $route_order_profit = $routeStats->total_order_profit;
                    $route_total_route_value = $route_order_value + $route_unloading_cost;
                    $route_fee =  $route_labor_cost + $route_moving_cost;
                    $route_profit = $route_order_profit + $route_unloading_cost - $route_fee;

                    // Cộng dồn vào các biến tính toán
                    $total_order_value += $route_order_value;
                    $total_order_profit += $route_order_profit;

                    Route::create([
                        'plan_id' => $plan->id,
                        'depot_id' => $depotId,
                        'route' => json_encode($route['route']),
                        'total_demand' => $route['total_demand'],
                        'total_distance' => $route['total_distance'],
                        'total_time_serving' => $route['total_time_serving'],
                        'fee' => $route_fee,
                        'moving_cost' => $route_moving_cost,
                        'labor_cost' => $route_labor_cost,
                        'unloading_cost' => $route_unloading_cost,
                        'total_order_value' => $route_order_value,
                        'total_order_profit' => $route_order_profit,
                        'total_route_value' => $route_total_route_value,
                        'profit' => $route_profit,
                        'alternative' => $route['alternative'],
                        'is_served' => true,
                    ]);
                }
                $total_plan_value = $total_order_value + $plan_unloading_cost;
                $plan_profit = $total_order_profit + $plan_unloading_cost - $plan_fee;

                // Cập nhật thông tin kế hoạch giao hàng với các giá trị đã tính toán
                $plan->update([
                    'total_order_value' => $total_order_value,
                    'total_order_profit' => $total_order_profit,
                    'total_plan_value' => $total_plan_value,
                    'profit' => $plan_profit,
                ]);

                // Lưu thông tin các tuyến đường không được phục vụ vào bảng routes
                foreach ($responseData['route_not_served_List_return_id'] as $route) {
                    $route_moving_cost = $fuelCost * $fuelConsumption * $route['total_distance'];
                    $route_labor_cost = $hourly_rate * ($route['total_time_serving'] / 60);
                    $route_unloading_cost = $shipping_rate * $route['total_demand'];

                    $depotId = substr($route['depot_origin'], 6);

                    // Lấy danh sách order_id từ mảng route
                    $order_ids = array_filter($route['route'], function ($value) {
                        return is_numeric($value);
                    });

                    // Tính tổng giá trị các đơn hàng
                    $routeStats = Order::whereIn('id', $order_ids)
                        ->selectRaw('SUM(price) as route_order_value, SUM(profit) as total_order_profit')
                        ->first();
                    $route_order_value = $routeStats->route_order_value;
                    $route_order_profit = $routeStats->total_order_profit;
                    $route_total_route_value = $route_order_value + $route_unloading_cost;
                    $route_fee =  $route_labor_cost + $route_moving_cost;
                    $route_profit = $route_order_profit + $route_unloading_cost - $route_fee;

                    Route::create([
                        'plan_id' => $plan->id,
                        'depot_id' => $depotId,
                        'route' => json_encode($route['route']),
                        'total_demand' => $route['total_demand'],
                        'total_distance' => $route['total_distance'],
                        'total_time_serving' => $route['total_time_serving'],
                        'fee' => $route_fee,
                        'moving_cost' => $route_moving_cost,
                        'labor_cost' => $route_labor_cost,
                        'unloading_cost' => $route_unloading_cost,
                        'total_order_value' => $route_order_value,
                        'total_order_profit' => $route_order_profit,
                        'total_route_value' => $route_total_route_value,
                        'profit' => $route_profit,
                        'alternative' => $route['alternative'],
                        'is_served' => false,
                    ]);
                }
            });
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Log the error or handle it accordingly
            return response()->json(['error' => 'Unable to process the request'], 500);
        }

        return $responseData;
    }

    public function index(Request $request)
    {
        $date = $request->input('date');
        $name = $request->input('name');
        $orderBy = $request->input('order_by', 'id');
        $sortBy = $request->input('sort_by', 'asc');

        $plan = Plan::orderBy($orderBy, $sortBy);

        if ($date) {
            $startDate = Carbon::parse($date)->startOfDay();
            $endDate = Carbon::parse($date)->endOfDay();
            $plan = $plan->whereBetween('updated_at', [$startDate, $endDate]);
        }

        if ($name) {
            $plan = $plan->where('name', 'like', '%' . $name . '%');
        }

        $plan = $plan->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'List of all plans',
            'data' => $plan
        ]);
    }

    public function destroy($id)
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        }

        $plan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Plan deleted successfully'
        ]);
    }

    public function show(Request $request, $id)
    {
        $perPage = $request->input('pageSize', 10);
        $page = $request->input('page', 1);

        $plan = Plan::with('vehicle')->find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        }

        $routes = $plan->routes()->with('depot')->paginate($perPage, ['*'], 'page', $page);

        $planData = [
            'id' => $plan->id,
            'name' => $plan->name,
            'vehicle_id ' => $plan->vehicle_id,
            'vehicle_name' => $plan->vehicle->name ?? 'N/A', // Thêm tên vehicle
            'expected_date' => $plan->expected_date,
            'total_demand' => $plan->total_demand,
            'total_distance' => $plan->total_distance,
            'total_time_serving' => $plan->total_time_serving,
            'total_demand_without_allocating_vehicles' => $plan->total_demand_without_allocating_vehicles,
            'total_distance_without_allocating_vehicles' => $plan->total_distance_without_allocating_vehicles,
            'total_time_serving_without_allocating_vehicles' => $plan->total_time_serving_without_allocating_vehicles,
            'status' => $plan->status,
            'fee' => $plan->fee,
            'moving_cost' => $plan->moving_cost,
            'labor_cost' => $plan->labor_cost,
            'unloading_cost' => $plan->unloading_cost,
            'total_order_value' => $plan->total_order_value,
            'total_order_profit' => $plan->total_order_profit,
            'total_plan_value' => $plan->total_plan_value,
            'profit' => $plan->profit,
            'total_vehicle_used' => $plan->total_vehicle_used,
            'total_num_customer_served' => $plan->total_num_customer_served,
            'total_num_customer_not_served' => $plan->total_num_customer_not_served,
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at,
            'total_routes' => $routes->total(),
            'routes' => [],

        ];

        foreach ($routes as $route) {
            $routeData = [
                'id' => $route->id,
                'route' => json_decode($route->route),
                'total_demand' => $route->total_demand,
                'total_distance' => $route->total_distance,
                'total_time_serving' => $route->total_time_serving,
                'depot_id' => $route->depot_id,
                'depot_name' => $route->depot->name, // Thêm trường depot_name
                'fee' => $route->fee,
                'total_route_value' => $route->total_route_value,
                'profit' => $route->profit,
                'alternative' => $route->alternative,
                'is_served' => $route->is_served
            ];

            $planData['routes'][] = $routeData;
        }

        return response()->json([
            'success' => true,
            'message' => 'Plan details',
            'data' => $planData,
            'current_page' => $routes->currentPage(),
            'last_page' => $routes->lastPage(),

        ]);
    }

    public function showRoute($routeId)
    {
        $route = Route::with('depot')->find($routeId);
        if (!$route) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found.'
            ], 404);
        }

        $orderIds = json_decode($route->route, true);
        $orders = Order::whereIn('id', $orderIds)->get();

        $routeData = [
            'id' => $route->id,
            'plan_id' => $route->plan_id,
            'depot_id' => $route->depot_id,
            'depot_name' => $route->depot->name,
            'route' => [],
            'total_demand' => $route->total_demand,
            'total_distance' => $route->total_distance,
            'total_time_serving' => $route->total_time_serving,
            'fee' => $route->fee,
            'moving_cost' => $route->moving_cost,
            'labor_cost' => $route->labor_cost,
            'unloading_cost' => $route->unloading_cost,
            'total_order_value' => $route->total_order_value,
            'total_order_profit' => $route->total_order_profit,
            'total_route_value' => $route->total_route_value,
            'profit' => $route->profit,
            'alternative' => $route->alternative,
            'is_served' => $route->is_served,
            'created_at' => $route->created_at,
            'updated_at' => $route->updated_at,
        ];

        foreach ($orderIds as $orderId) {
            if (strpos($orderId, 'depot_') === 0) {
                $depotId = substr($orderId, 6);
                $depot = Depot::find($depotId);
                if ($depot) {
                    $routeData['route'][] = [
                        'id' => $depot->id,
                        'depot_name' => $depot->name,
                        'address' => $depot->address,
                        'phone' => $depot->phone,
                        'longitude' => $depot->longitude,
                        'latitude' => $depot->latitude
                    ];
                }
            } else {
                $order = $orders->firstWhere('id', $orderId);
                if ($order) {
                    $routeData['route'][] = [
                        'id' => $order->id,
                        'code_order' => $order->code_order,
                        'customer_name' => $order->customer_name,
                        'address' => $order->address,
                        'phone' => $order->phone,
                        'price' => $order->price,
                        'longitude' => $order->longitude,
                        'latitude' => $order->latitude
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Route found.',
            'data' => $routeData
        ]);
    }

    // public function getRoutes(Request $request, $planId)
    // {
    //     $plan = Plan::findOrFail($planId);

    //     if ($plan->status !== 'Delivery') {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'This plan is not in Delivery status.',
    //         ], 400);
    //     }

    //     $perPage = $request->input('per_page', 10);
    //     $page = $request->input('page', 1);

    //     $routes = $plan->routes()
    //         ->where('is_served', true)
    //         ->select('id', 'route')
    //         ->paginate($perPage, ['*'], 'page', $page);

    //     $allOrderIds = $routes->pluck('route')
    //         ->map(function ($route) {
    //             return array_filter(json_decode($route, true), 'is_numeric');
    //         })
    //         ->flatten()
    //         ->unique();

    //     $orderDetails = Order::whereIn('id', $allOrderIds)
    //         ->select('id', 'code_order')
    //         ->get()
    //         ->keyBy('id');

    //     $formattedRoutes = $routes->map(function ($route) use ($orderDetails) {
    //         $routeData = json_decode($route->route, true);
    //         $orders = array_values(array_filter($routeData, function($item) {
    //             return is_numeric($item);
    //         }));

    //         $ordersWithCodes = array_map(function($orderId) use ($orderDetails) {
    //             $order = $orderDetails->get($orderId);
    //             return [
    //                 'id' => $orderId,
    //                 'code_order' => $order ? $order->code_order : null
    //             ];
    //         }, $orders);

    //         return [
    //             'route_id' => $route->id,
    //             'orders' => $ordersWithCodes,
    //         ];
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'plan_id' => $plan->id,
    //         'plan_name' => $plan->name,
    //         'routes' => $formattedRoutes,
    //         'current_page' => $routes->currentPage(),
    //         'per_page' => $routes->perPage(),
    //         'total' => $routes->total(),
    //         'last_page' => $routes->lastPage(),
    //     ]);
    // }

    public function confirmPlan($planId)
    {
        try {
            $plan = Plan::with(['routes' => function ($query) {
                $query->where('is_served', true);
            }])->findOrFail($planId);

            if ($plan->routes->isEmpty()) {
                return $this->jsonResponse(false, 'No valid routes found for this plan.', 400);
            }

            $orderIds = $this->getOrderIdsFromPlan($plan);

            $deliveredOrdersCount = Order::whereIn('id', $orderIds)
                ->where('status', 'Delivery')
                ->count();

            if ($deliveredOrdersCount > 0) {
                return $this->jsonResponse(false, 'Cannot confirm plan. Some orders are already delivered.', 400);
            }

            DB::transaction(function () use ($plan, $orderIds) {
                $plan->status = 'Delivery';
                $plan->save();

                foreach ($plan->routes as $route) {
                    $routeOrderIds = $this->getOrderIdsFromRouteData(json_decode($route->route, true));
                    Order::whereIn('id', $routeOrderIds)->update([
                        'expected_date' => $plan->expected_date,
                        'status' => 'Delivery',
                        'route_id' => $route->id
                    ]);
                }
            });

            return $this->jsonResponse(true, 'Plan confirmed successfully');
        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'An error occurred: ' . $e->getMessage(), 500);
        }
    }

    public function completePlan(Request $request, $planId)
    {
        try {
            $plan = Plan::with(['routes' => function ($query) {
                $query->where('is_served', true);
            }])->findOrFail($planId);

            if ($plan->status !== 'Delivery') {
                return $this->jsonResponse(false, 'This plan is not in Delivery status.', 400);
            }

            DB::transaction(function () use ($plan) {
                $orderIds = $this->getOrderIdsFromPlan($plan);

                Order::whereIn('id', $orderIds)->update(['status' => 'Success']);

                $plan->status = 'Success';
                $plan->save();
            });

            return $this->jsonResponse(true, 'Plan completed successfully', 200, ['plan_status' => $plan->status]);
        } catch (\Exception $e) {
            return $this->jsonResponse(false, 'An error occurred: ' . $e->getMessage(), 500);
        }
    }

    private function getOrderIdsFromPlan($plan)
    {
        return $plan->routes->flatMap(function ($route) {
            return $this->getOrderIdsFromRouteData(json_decode($route->route, true));
        })->unique()->values();
    }

    private function getOrderIdsFromRouteData($routeData)
    {
        if (!empty($routeData) && str_starts_with($routeData[0], 'depot_')) {
            array_shift($routeData);
        }
        if (!empty($routeData) && str_starts_with(end($routeData), 'depot_')) {
            array_pop($routeData);
        }

        return array_values(array_filter($routeData, 'is_numeric'));
    }

    private function jsonResponse($success, $message, $status = 200, $extraData = [])
    {
        return response()->json(array_merge([
            'success' => $success,
            'message' => $message
        ], $extraData), $status);
    }
}
