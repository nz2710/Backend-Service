<?php

namespace App\Http\Controllers\Admin;

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
            ]);

            $selectedVehicleId = $validatedData['vehicle_id'];
            $selectedVehicle = Vehicle::findOrFail($selectedVehicleId);
            $numVehicles =  $validatedData['num_vehicles'] ?? $selectedVehicle->total_vehicles;
            $timeLimit = $validatedData['time_limit'] ?? 999999;
            $weightLimit = $selectedVehicle->capacity;
            $VValue = $selectedVehicle->speed;

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

            $responseData = $this->processFile($filePath, $filename, $VValue, $selectedVehicleId);

            return response()->json($responseData);
        } catch (\Exception $e) {
            // \Log::error('Error in generateFile: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function processFile($filePath, $filename, $VValue, $selectedVehicleId)
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
            DB::transaction(function () use ($responseData, $selectedVehicleId) {
                // Lấy thông tin của vehicle được chọn
                $vehicle = Vehicle::find($selectedVehicleId);
                $fuelCost = $vehicle->fuel_cost;
                $fuelConsumption = $vehicle->fuel_consumption;
                $hourly_rate = $vehicle->hourly_rate;
                $shipping_rate = $vehicle->shipping_rate;

                // Tính toán fee dựa trên công thức
                $plan_moving_cost = $fuelCost * $fuelConsumption * $responseData['total_distance_served'];
                $plan_labor_cost = $hourly_rate * $responseData['total_time_serving_served'];
                $plan_unloading_cost = $shipping_rate * $responseData['total_demand_served'];
                $plan_fee = $plan_moving_cost + $plan_labor_cost;

                // Khởi tạo các biến tính toán
                $total_order_value = 0;

                // Lưu thông tin kế hoạch giao hàng vào bảng plans
                $plan = Plan::create([
                    'name' => 'Delivery Plan' . ' ' . now()->format('Y-m-d H:i:s'),
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
                    $route_labor_cost = $hourly_rate * $route['total_time_serving'];
                    $route_unloading_cost = $shipping_rate * $route['total_demand'];

                    $depotId = substr($route['depot_origin'], 6);

                    // Lấy danh sách order_id từ mảng route
                    $order_ids = array_filter($route['route'], function ($value) {
                        return is_numeric($value);
                    });

                    // Tính tổng giá trị các đơn hàng
                    $route_order_value = Order::whereIn('id', $order_ids)->sum('price');
                    $route_total_route_value = $route_order_value + $route_unloading_cost;
                    $route_fee =  $route_labor_cost + $route_moving_cost;
                    $route_profit = $route_total_route_value - $route_fee;

                    // Cộng dồn vào các biến tính toán
                    $total_order_value += $route_order_value;

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
                        'total_route_value' => $route_total_route_value,
                        'profit' => $route_profit,
                        'alternative' => $route['alternative'],
                        'is_served' => true,
                    ]);
                }
                $total_plan_value = $total_order_value + $plan_unloading_cost;
                $plan_profit = $total_plan_value - $plan_fee;

                // Cập nhật thông tin kế hoạch giao hàng với các giá trị đã tính toán
                $plan->update([
                    'total_order_value' => $total_order_value,
                    'total_plan_value' => $total_plan_value,
                    'profit' => $plan_profit,
                ]);

                // Lưu thông tin các tuyến đường không được phục vụ vào bảng routes
                foreach ($responseData['route_not_served_List_return_id'] as $route) {
                    $route_moving_cost = $fuelCost * $fuelConsumption * $route['total_distance'];
                    $route_labor_cost = $hourly_rate * $route['total_time_serving'];
                    $route_unloading_cost = $shipping_rate * $route['total_demand'];

                    $depotId = substr($route['depot_origin'], 6);

                    // Lấy danh sách order_id từ mảng route
                    $order_ids = array_filter($route['route'], function ($value) {
                        return is_numeric($value);
                    });

                    // Tính tổng giá trị các đơn hàng
                    $route_order_value = Order::whereIn('id', $order_ids)->sum('price');
                    $route_total_route_value = $route_order_value + $route_unloading_cost;
                    $route_fee =  $route_labor_cost + $route_moving_cost;
                    $route_profit = $route_total_route_value - $route_fee;

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
        $name = $request->input('name');
        $orderBy = $request->input('order_by', 'id');
        $sortBy = $request->input('sort_by', 'asc');

        $plan = Plan::orderBy($orderBy, $sortBy);

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

        $plan = Plan::with('routes')->find($id);

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

    public function confirmPlan($planId)
    {
        $plan = Plan::find($planId);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        }

        // Lấy tất cả các route của plan có is_served = true
        $routes = $plan->routes()->where('is_served', true)->get();

        // Lấy danh sách order_id từ tất cả các route
        $orderIds = [];
        foreach ($routes as $route) {
            $routeOrderIds = json_decode($route->route, true);
            $routeOrderIds = array_filter($routeOrderIds, function ($value) {
                return is_numeric($value);
            });
            $orderIds = array_merge($orderIds, $routeOrderIds);
        }

        // Kiểm tra xem có bất kỳ order nào đã có trạng thái "Delivery" không
        $deliveredOrders = Order::whereIn('id', $orderIds)
            ->where('status', 'Delivery')
            ->exists();

        if ($deliveredOrders) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot confirm plan. Some orders are already delivered.'
            ], 400);
        }

        DB::transaction(function () use ($plan, $routes, $orderIds) {
            // Cập nhật trạng thái của plan thành "Delivery"
            $plan->status = 'Delivery';
            $plan->save();

            foreach ($routes as $route) {
                // Cập nhật trạng thái của các order thành "Delivery" và cập nhật route_id
                Order::whereIn('id', $orderIds)->update([
                    'status' => 'Delivery',
                    'route_id' => $route->id
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Plan confirmed successfully'
        ]);
    }

    public function completePlan($planId)
{
    $plan = Plan::find($planId);

    if (!$plan) {
        return response()->json([
            'success' => false,
            'message' => 'Plan not found'
        ], 404);
    }

    // Kiểm tra xem plan đã ở trạng thái "Delivery" hay chưa
    if ($plan->status !== 'Delivery') {
        return response()->json([
            'success' => false,
            'message' => 'Cannot complete plan. The plan is not in Delivery status.'
        ], 400);
    }

    // Lấy tất cả các route của plan có is_served = true
    $routes = $plan->routes()->where('is_served', true)->get();

    // Lấy danh sách order_id từ tất cả các route
    $orderIds = [];
    foreach ($routes as $route) {
        $routeOrderIds = json_decode($route->route, true);
        $routeOrderIds = array_filter($routeOrderIds, function ($value) {
            return is_numeric($value);
        });
        $orderIds = array_merge($orderIds, $routeOrderIds);
    }

    DB::transaction(function () use ($plan, $routes, $orderIds) {
        // Cập nhật trạng thái của plan thành "Success"
        $plan->status = 'Success';
        $plan->save();

        foreach ($routes as $route) {
            // Cập nhật trạng thái của các order thành "Success"
            Order::whereIn('id', $orderIds)->update([
                'status' => 'Success'
            ]);
        }
    });

    return response()->json([
        'success' => true,
        'message' => 'Plan completed successfully'
    ]);
}
}
