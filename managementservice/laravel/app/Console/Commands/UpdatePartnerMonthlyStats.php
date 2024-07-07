<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Partner;
use App\Models\Order;
use App\Models\CommissionRule;
use Carbon\Carbon;
use DB;

class UpdatePartnerMonthlyStats extends Command
{
    protected $signature = 'partner:update-monthly-stats';
    protected $description = 'Update monthly stats for partners';

    public function handle()
    {
        $firstOrderDate = Order::whereIn('status', ['Pending', 'Success', 'Delivery'])
            ->orderBy('created_at')
            ->first()->created_at ?? now();

        $currentDate = Carbon::now();

        $months = [];
        $date = Carbon::parse($firstOrderDate)->startOfMonth();

        while ($date <= $currentDate) {
            $months[] = $date->format('Y-m');
            $date->addMonth();
        }

        $partnerIds = Partner::pluck('id');
        $commissionRules = CommissionRule::orderBy('revenue_milestone')->get();

        foreach ($partnerIds as $partnerId) {
            // Xóa tất cả dữ liệu cũ của đối tác này
            DB::table('partner_monthly_stats')->where('partner_id', $partnerId)->delete();

            foreach ($months as $month) {
                $startDate = Carbon::parse($month)->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();

                $stats = Order::where('partner_id', $partnerId)
                    ->whereIn('status', ['Pending', 'Success', 'Delivery'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->select(
                        DB::raw('SUM(total_base_price) as total_base_price'),
                        DB::raw('SUM(price) as revenue'),
                        DB::raw('SUM(commission) as commission'),
                        DB::raw('COUNT(*) as order_count')
                    )
                    ->first();

                $revenue = $stats->revenue ?? 0;
                $commission = $stats->commission ?? 0;

                // Chỉ tạo bản ghi nếu có dữ liệu
                if ($revenue > 0 || $commission > 0 || ($stats->order_count ?? 0) > 0) {
                    $bonus = 0;
                    foreach ($commissionRules as $rule) {
                        if ($revenue > $rule->revenue_milestone) {
                            $bonus = $rule->bonus_amount;
                        } else {
                            break;
                        }
                    }

                    $totalAmount = $commission + $bonus;

                    DB::table('partner_monthly_stats')->insert([
                        'partner_id' => $partnerId,
                        'stat_date' => $startDate->format('Y-m-d'),
                        'total_base_price' => $stats->total_base_price ?? 0,
                        'revenue' => $revenue,
                        'commission' => $commission,
                        'bonus' => $bonus,
                        'total_amount' => $totalAmount,
                        'order_count' => $stats->order_count ?? 0,
                    ]);
                }
            }

            // Cập nhật thông tin tổng hợp cho từng đối tác
            $totalStats = DB::table('partner_monthly_stats')
                ->where('partner_id', $partnerId)
                ->select(
                    DB::raw('SUM(revenue) as total_revenue'),
                    DB::raw('SUM(commission) as total_commission'),
                    DB::raw('SUM(bonus) as total_bonus'),
                    DB::raw('SUM(order_count) as total_order_count')
                )
                ->first();

            Partner::where('id', $partnerId)->update([
                'revenue' => $totalStats->total_revenue ?? 0,
                'commission' => $totalStats->total_commission ?? 0,
                'bonus' => $totalStats->total_bonus ?? 0,
                'number_of_order' => $totalStats->total_order_count ?? 0,
            ]);
        }

        $this->info('Partner monthly stats updated successfully.');
    }
}
