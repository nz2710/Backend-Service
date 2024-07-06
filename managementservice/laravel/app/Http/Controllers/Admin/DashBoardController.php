<?php

namespace App\Http\Controllers\Admin;

use App\Models\Plan;
use App\Models\Depot;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Vehicle;
use App\Models\OrderProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class DashBoardController extends Controller
{
    public function getTotalAll()
    {
        $totalProduct = Product::count();
        $totalVehicles = Vehicle::sum('total_vehicles');
        $totalDepots = Depot::count();
        $totalPartners = Partner::count();
        $totalOrders = Order::count();

        return response()->json([
            'success' => true,
            'message' => 'Total of all',
            'totalProduct' => $totalProduct,
            'totalVehicles' => $totalVehicles,
            'totalDepots' => $totalDepots,
            'totalPartners' => $totalPartners,
            'totalOrders' => $totalOrders
        ]);
    }

    public function getOrderStatusCounts(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        $statusCounts = [];

        if ($filterType === 'year') {
            $query = Order::whereYear('created_at', $year)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status');

            $statusCounts = $query->get()->pluck('count', 'status')->toArray();
        } elseif ($filterType === 'month') {
            $startDate = Carbon::create($year, $month)->startOfMonth();
            $endDate = Carbon::create($year, $month)->endOfMonth();

            $query = Order::whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status');

            $statusCounts = $query->get()->pluck('count', 'status')->toArray();
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid filter type'], 400);
        }

        $data = [
            'Waiting' => $statusCounts['Waiting'] ?? 0,
            'Pending' => $statusCounts['Pending'] ?? 0,
            'Delivery' => $statusCounts['Delivery'] ?? 0,
            'Success' => $statusCounts['Success'] ?? 0,
            'Cancelled' => $statusCounts['Cancelled'] ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getTransportationCosts(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        $query = Plan::where('status', 'Success');

        if ($filterType === 'year') {
            $startDate = Carbon::create($year)->startOfYear();
            $endDate = Carbon::create($year)->endOfYear();
            $query->whereBetween('updated_at', [$startDate, $endDate]);

            $transportationCosts = $query->selectRaw('MONTH(updated_at) as month, SUM(moving_cost) as total_moving_cost, SUM(labor_cost) as total_labor_cost, SUM(fee) as total_cost')
                ->groupBy('month')
                ->get();

            $months = [];
            for ($month = 1; $month <= 12; $month++) {
                $months[$month] = [
                    'month' => Carbon::create($year, $month)->format('F'),
                    'moving_cost' => 0,
                    'labor_cost' => 0,
                    'total_cost' => 0,
                ];
            }

            foreach ($transportationCosts as $cost) {
                $months[$cost->month]['moving_cost'] = $cost->total_moving_cost;
                $months[$cost->month]['labor_cost'] = $cost->total_labor_cost;
                $months[$cost->month]['total_cost'] = $cost->total_cost;
            }

            $data = array_values($months);
        } elseif ($filterType === 'month') {
            $startDate = Carbon::create($year, $month)->startOfMonth();
            $endDate = Carbon::create($year, $month)->endOfMonth();
            $query->whereBetween('updated_at', [$startDate, $endDate]);

            $transportationCosts = $query->selectRaw('DATE(updated_at) as date, SUM(moving_cost) as total_moving_cost, SUM(labor_cost) as total_labor_cost, SUM(fee) as total_cost')
                ->groupBy('date')
                ->get();

            $dates = [];
            for ($date = $startDate; $date->lte($endDate); $date->addDay()) {
                $dateFormatted = $date->format('d-m-Y');
                $dates[$dateFormatted] = [
                    'date' => $dateFormatted,
                    'moving_cost' => 0,
                    'labor_cost' => 0,
                    'total_cost' => 0,
                ];
            }

            foreach ($transportationCosts as $cost) {
                $dateFormatted = Carbon::parse($cost->date)->format('d-m-Y');
                $dates[$dateFormatted]['moving_cost'] = $cost->total_moving_cost;
                $dates[$dateFormatted]['labor_cost'] = $cost->total_labor_cost;
                $dates[$dateFormatted]['total_cost'] = $cost->total_cost;
            }

            $data = array_values($dates);
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid filter type'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getAverageTransportationStats(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        $query = Plan::where('status', 'Success');

        if ($filterType === 'year') {
            $startDate = Carbon::create($year)->startOfYear();
            $endDate = Carbon::create($year)->endOfYear();
            $query->whereBetween('updated_at', [$startDate, $endDate]);

            $averageStats = $query->selectRaw('COALESCE(AVG(total_distance), 0) as avg_distance, COALESCE(AVG(total_time_serving), 0) as avg_duration, COALESCE(AVG(total_demand), 0) as avg_weight, COALESCE(AVG(total_vehicle_used), 0) as avg_vehicles')
                ->first();

            $data = [
                'year' => $year,
                'avg_distance' => $averageStats->avg_distance,
                'avg_duration' => $averageStats->avg_duration,
                'avg_weight' => $averageStats->avg_weight,
                'avg_vehicles' => $averageStats->avg_vehicles,
            ];
        } elseif ($filterType === 'month') {
            $startDate = Carbon::create($year, $month)->startOfMonth();
            $endDate = Carbon::create($year, $month)->endOfMonth();
            $query->whereBetween('updated_at', [$startDate, $endDate]);

            $averageStats = $query->selectRaw('COALESCE(AVG(total_distance), 0) as avg_distance, COALESCE(AVG(total_time_serving), 0) as avg_duration, COALESCE(AVG(total_demand), 0) as avg_weight, COALESCE(AVG(total_vehicle_used), 0) as avg_vehicles')
                ->first();

            $data = [
                'year' => $year,
                'month' => Carbon::create($year, $month)->format('F'),
                'avg_distance' => $averageStats->avg_distance,
                'avg_duration' => $averageStats->avg_duration,
                'avg_weight' => $averageStats->avg_weight,
                'avg_vehicles' => $averageStats->avg_vehicles,
            ];
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid filter type'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getTransportationMetrics(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        $query = Plan::where('status', 'Success');

        if ($filterType === 'year') {
            $startDate = Carbon::create($year)->startOfYear();
            $endDate = Carbon::create($year)->endOfYear();
            $query->whereBetween('updated_at', [$startDate, $endDate]);

            $metrics = $query->selectRaw('
            COALESCE(AVG(fee / total_num_customer_served), 0) as avg_shipping_cost_per_order,
            COALESCE(AVG(total_num_customer_served / (
                SELECT COUNT(*) FROM routes WHERE plan_id = plans.id AND is_served = true
            )), 0) as avg_orders_per_trip,
            COALESCE(COUNT(DISTINCT MONTH(updated_at)), 1) as num_months,
            COALESCE((SELECT COUNT(*) FROM routes WHERE plan_id IN (
                SELECT id FROM plans WHERE YEAR(updated_at) = ? AND status = ?
            ) AND is_served = true), 0) as total_trips,
            COALESCE(AVG(profit / total_plan_value), 0) as avg_efficiency
        ', [$year, 'Success'])
                ->first();

            $data = [
                'year' => $year,
                'avg_shipping_cost_per_order' => $metrics->avg_shipping_cost_per_order,
                'avg_orders_per_trip' => $metrics->avg_orders_per_trip,
                'avg_trips_per_month' => $metrics->total_trips / $metrics->num_months,
                'avg_efficiency' => $metrics->avg_efficiency,
            ];
        } elseif ($filterType === 'month') {
            $startDate = Carbon::create($year, $month)->startOfMonth();
            $endDate = Carbon::create($year, $month)->endOfMonth();
            $query->whereBetween('updated_at', [$startDate, $endDate]);

            $metrics = $query->selectRaw('
            COALESCE(AVG(fee / total_num_customer_served), 0) as avg_shipping_cost_per_order,
            COALESCE(AVG(total_num_customer_served / (
                SELECT COUNT(*) FROM routes WHERE plan_id = plans.id AND is_served = true
            )), 0) as avg_orders_per_trip,
            COALESCE((SELECT COUNT(*) FROM routes WHERE plan_id IN (
                SELECT id FROM plans WHERE YEAR(updated_at) = ? AND MONTH(updated_at) = ? AND status = ?
            ) AND is_served = true), 0) as total_trips,
            COALESCE(AVG(profit / total_plan_value), 0) as avg_efficiency
        ', [$year, $month, 'Success'])
                ->first();

            $data = [
                'year' => $year,
                'month' => Carbon::create($year, $month)->format('F'),
                'avg_shipping_cost_per_order' => $metrics->avg_shipping_cost_per_order,
                'avg_orders_per_trip' => $metrics->avg_orders_per_trip,
                'avg_trips_per_month' => $metrics->total_trips,
                'avg_efficiency' => $metrics->avg_efficiency,
            ];
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid filter type'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }


    public function getTopProducts(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');
        $metricType = $request->input('metric_type', 'sale');

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
            'metric_type' => 'in:sale,quantity'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        if ($filterType === 'year') {
            $query = OrderProduct::join('orders', 'order_product.order_id', '=', 'orders.id')
                ->where('orders.status', 'Success')
                ->whereYear('order_product.created_at', $year)
                ->groupBy('order_product.product_id');

            if ($metricType === 'sale') {
                $query->selectRaw('order_product.product_id, SUM(order_product.price * order_product.quantity) as total_metric')
                    ->orderByDesc('total_metric');
            } else {
                $query->selectRaw('order_product.product_id, SUM(order_product.quantity) as total_metric')
                    ->orderByDesc('total_metric');
            }

            $data = $query->with('product')->take(10)->get()->map(function ($item) use ($year) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'total' => $item->total_metric,
                    'year' => $year,
                ];
            });
        } elseif ($filterType === 'month') {
            $startDate = Carbon::create($year, $month)->startOfMonth();
            $endDate = Carbon::create($year, $month)->endOfMonth();

            $query = OrderProduct::join('orders', 'order_product.order_id', '=', 'orders.id')
                ->where('orders.status', 'Success')
                ->whereBetween('order_product.created_at', [$startDate, $endDate])
                ->groupBy('order_product.product_id');

            if ($metricType === 'sale') {
                $query->selectRaw('order_product.product_id, SUM(order_product.price * order_product.quantity) as total_metric')
                    ->orderByDesc('total_metric');
            } else {
                $query->selectRaw('order_product.product_id, SUM(order_product.quantity) as total_metric')
                    ->orderByDesc('total_metric');
            }

            $data = $query->with('product')->take(10)->get()->map(function ($item) use ($year, $month) {
                return [
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'total' => $item->total_metric,
                    'month' => $month,
                    'year' => $year,
                ];
            });
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid filter type'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getTopPartners(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');
        $metricType = $request->input('metric_type', 'revenue');

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
            'metric_type' => 'in:revenue,amount'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        if ($filterType === 'year') {
            $query = Order::where('status', 'Success')
                ->whereYear('created_at', $year)
                ->groupBy('partner_id');

            if ($metricType === 'revenue') {
                $query->selectRaw('partner_id, SUM(price) as total_metric')
                    ->orderByDesc('total_metric');
            } else {
                $query->selectRaw('partner_id, COUNT(*) as total_metric')
                    ->orderByDesc('total_metric');
            }

            $data = $query->with('partner')->take(10)->get()->map(function ($item) use ($year) {
                return [
                    'partner_id' => $item->partner_id,
                    'partner_name' => $item->partner->name,
                    'total' => $item->total_metric,
                    'year' => $year,
                ];
            });
        } elseif ($filterType === 'month') {
            $startDate = Carbon::create($year, $month)->startOfMonth();
            $endDate = Carbon::create($year, $month)->endOfMonth();

            $query = Order::where('status', 'Success')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('partner_id');

            if ($metricType === 'revenue') {
                $query->selectRaw('partner_id, SUM(price) as total_metric')
                    ->orderByDesc('total_metric');
            } else {
                $query->selectRaw('partner_id, COUNT(*) as total_metric')
                    ->orderByDesc('total_metric');
            }

            $data = $query->with('partner')->take(10)->get()->map(function ($item) use ($year, $month) {
                return [
                    'partner_id' => $item->partner_id,
                    'partner_name' => $item->partner->name,
                    'total' => $item->total_metric,
                    'month' => $month,
                    'year' => $year,
                ];
            });
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid filter type'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
    public function getRevenueSummary(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        if ($filterType === 'year') {
            $data = collect(range(1, 12))->map(function ($month) use ($year) {
                $revenue = Order::whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->where('status', 'Success')
                    ->sum('price');

                return [
                    'month' => $month,
                    'revenue' => $revenue
                ];
            });
        } elseif ($filterType === 'month') {
            $startDate = Carbon::create($year, $month)->startOfMonth();
            $endDate = Carbon::create($year, $month)->endOfMonth();
            $daysInMonth = $endDate->diffInDays($startDate) + 1;

            $data = collect(range(1, $daysInMonth))->map(function ($day) use ($year, $month) {
                $date = Carbon::create($year, $month, $day)->format('d-m-Y');
                $revenue = Order::whereDate('created_at', Carbon::create($year, $month, $day))
                    ->where('status', 'Success')
                    ->sum('price');

                return [
                    'date' => $date,
                    'revenue' => $revenue
                ];
            });
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid filter type'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
    public function getItemSoldSummary(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        if ($filterType === 'year') {
            $data = collect(range(1, 12))->map(function ($month) use ($year) {
                $itemSold = OrderProduct::join('orders', 'order_product.order_id', '=', 'orders.id')
                    ->whereYear('order_product.created_at', $year)
                    ->whereMonth('order_product.created_at', $month)
                    ->where('orders.status', 'Success')
                    ->sum('order_product.quantity');

                return [
                    'month' => $month,
                    'item sold' => $itemSold
                ];
            });
        } elseif ($filterType === 'month') {
            $startDate = Carbon::create($year, $month)->startOfMonth();
            $endDate = Carbon::create($year, $month)->endOfMonth();
            $daysInMonth = $endDate->diffInDays($startDate) + 1;

            $data = collect(range(1, $daysInMonth))->map(function ($day) use ($year, $month) {
                $date = Carbon::create($year, $month, $day)->format('d-m-Y');
                $itemSold = OrderProduct::join('orders', 'order_product.order_id', '=', 'orders.id')
                    ->whereDate('order_product.created_at', Carbon::create($year, $month, $day))
                    ->where('orders.status', 'Success')
                    ->sum('order_product.quantity');

                return [
                    'date' => $date,
                    'item sold' => $itemSold
                ];
            });
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid filter type'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getCostSummary(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        if ($filterType === 'year') {
            $data = collect(range(1, 12))->map(function ($month) use ($year) {
                $orders = Order::whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->where('status', 'Success')
                    ->selectRaw('SUM(total_cost) as total_cost, SUM(commission) as total_commission')
                    ->first();

                $cost = $orders->total_cost ?? 0;
                $commission = $orders->total_commission ?? 0;

                return [
                    'month' => $month,
                    'cost' => $cost,
                    'commission' => $commission,
                    'total' => $cost + $commission
                ];
            });
        } elseif ($filterType === 'month') {
            $startDate = Carbon::create($year, $month)->startOfMonth();
            $endDate = Carbon::create($year, $month)->endOfMonth();
            $daysInMonth = $endDate->diffInDays($startDate) + 1;

            $data = collect(range(1, $daysInMonth))->map(function ($day) use ($year, $month) {
                $date = Carbon::create($year, $month, $day)->format('d-m-Y');
                $orders = Order::whereDate('created_at', Carbon::create($year, $month, $day))
                    ->where('status', 'Success')
                    ->selectRaw('SUM(total_cost) as total_cost, SUM(commission) as total_commission')
                    ->first();

                $cost = $orders->total_cost ?? 0;
                $commission = $orders->total_commission ?? 0;

                return [
                    'date' => $date,
                    'cost' => $cost,
                    'commission' => $commission,
                    'total' => $cost + $commission
                ];
            });
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid filter type'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getProfitSummary(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        if ($filterType === 'year') {
            $data = collect(range(1, 12))->map(function ($month) use ($year) {
                $profit = Order::whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->where('status', 'Success')
                    ->sum('profit');

                return [
                    'month' => $month,
                    'profit' => $profit
                ];
            });
        } elseif ($filterType === 'month') {
            $startDate = Carbon::create($year, $month)->startOfMonth();
            $endDate = Carbon::create($year, $month)->endOfMonth();
            $daysInMonth = $endDate->diffInDays($startDate) + 1;

            $data = collect(range(1, $daysInMonth))->map(function ($day) use ($year, $month) {
                $date = Carbon::create($year, $month, $day)->format('d-m-Y');
                $profit = Order::whereDate('created_at', Carbon::create($year, $month, $day))
                    ->where('status', 'Success')
                    ->sum('profit');

                return [
                    'date' => $date,
                    'profit' => $profit
                ];
            });
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid filter type'], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function getTotalSummary(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
            'filter_type' => 'in:month,year',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        $query = Order::where('status', 'Success');

        if ($filterType === 'year') {
            $query->whereYear('created_at', $year);
        } elseif ($filterType === 'month') {
            $query->whereYear('created_at', $year)->whereMonth('created_at', $month);
        } else {
            return response()->json(['success' => false, 'message' => 'Invalid filter type'], 400);
        }

        $orderSummary = $query->selectRaw('
        SUM(price) as total_revenue,
        SUM(profit) as total_profit,
        SUM(total_cost) as total_cost,
        SUM(commission) as total_commission
    ')->first();

        $salesVolumeQuery = OrderProduct::join('orders', 'order_product.order_id', '=', 'orders.id')
            ->where('orders.status', 'Success');

        if ($filterType === 'year') {
            $salesVolumeQuery->whereYear('order_product.created_at', $year);
        } elseif ($filterType === 'month') {
            $salesVolumeQuery->whereYear('order_product.created_at', $year)
                ->whereMonth('order_product.created_at', $month);
        }

        $totalSalesVolume = $salesVolumeQuery->sum('order_product.quantity');

        $data = [
            'total_revenue' => $orderSummary->total_revenue ?? 0,
            'total_sales_volume' => $totalSalesVolume ?? 0,
            'total_profit' => $orderSummary->total_profit ?? 0,
            'total_cost' => ($orderSummary->total_cost + $orderSummary->total_commission) ?? 0,
        ];

        if ($filterType === 'year') {
            $data['year'] = $year;
        } elseif ($filterType === 'month') {
            $data['year'] = $year;
            $data['month'] = $month;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
