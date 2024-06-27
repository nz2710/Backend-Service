<?php

namespace App\Http\Controllers\Admin;

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

    public function getTopProducts(Request $request)
    {
        $year = $request->input('year');
        $month = $request->input('month');
        $filterType = $request->input('filter_type', 'month');
        $metricType = $request->input('metric_type', 'sale'); // Mặc định là 'revenue'

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
            'metric_type' => 'in:sale,quantity' // Đảm bảo chỉ có 'revenue' hoặc 'amount'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        if ($filterType === 'year') {
            $query = OrderProduct::with('product')
            ->whereYear('created_at', $year)
            ->groupBy('product_id');

            if ($metricType === 'sale') {
                $query->selectRaw('product_id, SUM(price*quantity) as total_metric')
                    ->orderByDesc('total_metric');
            } else {
                $query->selectRaw('partner_id, SUM(quantity) as total_metric')
                    ->orderByDesc('total_metric');
            }

            $data = $query->take(10)->get()->map(function ($item) use ($year) {
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

            $query = OrderProduct::with('product')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('product_id');

            if ($metricType === 'sale') {
                $query->selectRaw('product_id, SUM(price*quantity) as total_metric')
                    ->orderByDesc('total_metric');
            } else {
                $query->selectRaw('product_id, SUM(quantity) as total_metric')
                    ->orderByDesc('total_metric');
            }

            $data = $query->take(10)->get()->map(function ($item) use ($year, $month) {
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
        $metricType = $request->input('metric_type', 'revenue'); // Mặc định là 'revenue'

        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2000|max:' . date('Y'),
            'month' => 'integer|min:1|max:12',
            'metric_type' => 'in:revenue,amount' // Đảm bảo chỉ có 'revenue' hoặc 'amount'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 400);
        }

        if ($filterType === 'year') {
            $query = Order::with('partner')
                ->whereYear('created_at', $year)
                ->groupBy('partner_id');

            if ($metricType === 'revenue') {
                $query->selectRaw('partner_id, SUM(price) as total_metric')
                    ->orderByDesc('total_metric');
            } else {
                $query->selectRaw('partner_id, COUNT(*) as total_metric')
                    ->orderByDesc('total_metric');
            }

            $data = $query->take(10)->get()->map(function ($item) use ($year) {
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

            $query = Order::with('partner')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('partner_id');

            if ($metricType === 'revenue') {
                $query->selectRaw('partner_id, SUM(price) as total_metric')
                    ->orderByDesc('total_metric');
            } else {
                $query->selectRaw('partner_id, COUNT(*) as total_metric')
                    ->orderByDesc('total_metric');
            }

            $data = $query->take(10)->get()->map(function ($item) use ($year, $month) {
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
                $itemSold = OrderProduct::whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->sum('quantity');

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
                $itemSold = OrderProduct::whereDate('created_at', Carbon::create($year, $month, $day))
                    ->sum('quantity');

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
}
