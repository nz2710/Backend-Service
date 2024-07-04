<?php

namespace App\Http\Controllers\Admin;

use App\Models\Partner;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class PartnerMonthlyStatController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));
        $filterType = $request->input('filter_type', 'month');
        $perPage = $request->input('pageSize', 10);
        $orderBy = $request->input('order_by', 'id');
        $sortBy = $request->input('sort_by', 'asc');
        $search = $request->input('search', '');

        $query = Partner::with(['monthlyStats' => function ($query) use ($year, $month, $filterType) {
            $query->whereYear('stat_date', $year);
            if ($filterType === 'month') {
                $query->whereMonth('stat_date', $month);
            }
        }]);

        // Apply search
        if (!empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Apply sorting
        if ($orderBy === 'id' || $orderBy === 'name') {
            $query->orderBy($orderBy, $sortBy);
        } else {
            $query->orderBy('id', 'asc');
        }

        $partnerStats = $query->get()->map(function ($partner) use ($year, $month, $filterType) {
            $monthlyStats = $partner->monthlyStats;

            if ($filterType === 'year') {
                $totalBasePrice = $monthlyStats->sum('total_base_price');
                $revenue = $monthlyStats->sum('revenue');
                $commission = $monthlyStats->sum('commission');
                $bonus = $monthlyStats->sum('bonus');
                $totalAmount = $monthlyStats->sum('total_amount');
                $orderCount = $monthlyStats->sum('order_count');
            } else {
                $monthlyStat = $monthlyStats->first();
                $totalBasePrice = $monthlyStat ? $monthlyStat->total_base_price : 0;
                $revenue = $monthlyStat ? $monthlyStat->revenue : 0;
                $commission = $monthlyStat ? $monthlyStat->commission : 0;
                $bonus = $monthlyStat ? $monthlyStat->bonus : 0;
                $totalAmount = $monthlyStat ? $monthlyStat->total_amount : 0;
                $orderCount = $monthlyStat ? $monthlyStat->order_count : 0;
            }

            return [
                'partner_id' => $partner->id,
                'partner_name' => $partner->name,
                'total_base_price' => $totalBasePrice,
                'revenue' => $revenue,
                'commission' => $commission,
                'bonus' => $bonus,
                'total_amount' => $totalAmount,
                'order_count' => $orderCount,
            ];
        });

        // Apply sorting for other fields
        if (!in_array($orderBy, ['id', 'name'])) {
            $partnerStats = $partnerStats->sortBy($orderBy, SORT_REGULAR, $sortBy === 'desc')->values();
        }

        // Manual pagination using slice()
        $page = $request->input('page', 1);
        $total = $partnerStats->count();
        $lastPage = ceil($total / $perPage);
        $offset = ($page - 1) * $perPage;
        $items = $partnerStats->slice($offset, $perPage)->values();

        // Filter out unnecessary query parameters
        $queryParams = array_filter([
            'year' => $year,
            'month' => $month,
            'filter_type' => $filterType,
            'pageSize' => $perPage,
            'order_by' => $orderBy,
            'sort_by' => $sortBy,
            'search' => $search,
        ]);

        $paginatedResult = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $queryParams
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $filterType === 'year' ? 'Yearly stats of partners' : 'Monthly stats of partners',
            'data' => $paginatedResult
        ]);
    }
    public function show($partnerId, Request $request)
    {
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));
        $filterType = $request->input('filter_type', 'month');

        $partner = Partner::with(['monthlyStats' => function ($query) use ($year, $month, $filterType) {
            $query->whereYear('stat_date', $year);
            if ($filterType === 'month') {
                $query->whereMonth('stat_date', $month);
            }
        }])->find($partnerId);

        if (!$partner) {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
                'data' => null
            ]);
        }

        $monthlyStats = $partner->monthlyStats;

        if ($filterType === 'year') {
            $totalBasePrice = $monthlyStats->sum('total_base_price');
            $revenue = $monthlyStats->sum('revenue');
            $commission = $monthlyStats->sum('commission');
            $bonus = $monthlyStats->sum('bonus');
            $totalAmount = $monthlyStats->sum('total_amount');
            $orderCount = $monthlyStats->sum('order_count');

            $partnerStat = [
                'partner_id' => $partner->id,
                'partner_name' => $partner->name,
                'year' => $year,
                'total_base_price' => $totalBasePrice,
                'revenue' => $revenue,
                'commission' => $commission,
                'bonus' => $bonus,
                'total_amount' => $totalAmount,
                'order_count' => $orderCount,
            ];
        } else {
            $monthlyStat = $monthlyStats->first();

            $partnerStat = [
                'partner_id' => $partner->id,
                'partner_name' => $partner->name,
                'year' => $year,
                'month' => $month,
                'total_base_price' => $monthlyStat->total_base_price ?? 0,
                'revenue' => $monthlyStat->revenue ?? 0,
                'commission' => $monthlyStat->commission ?? 0,
                'bonus' => $monthlyStat->bonus ?? 0,
                'total_amount' => $monthlyStat->total_amount ?? 0,
                'order_count' => $monthlyStat->order_count ?? 0,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => $filterType === 'year' ? 'Yearly stat of partner' : 'Monthly stat of partner',
            'data' => $partnerStat
        ]);
    }

    public function updateMonthlyStats(Request $request)
    {
        Artisan::call('partner:update-monthly-stats');

        return response()->json(['message' => 'Partner monthly stats updated successfully.']);
    }
}
