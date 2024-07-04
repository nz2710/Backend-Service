<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Partner;
use App\Models\CommissionRule;
use App\Models\PartnerMonthlyStat;

class PartnerService
{
    public function updatePartnerOnNewOrder(Partner $partner, Order $order)
    {
        //Update Partner Monthly Stat
        $statDate = Carbon::parse($order->created_at)->startOfMonth()->format('Y-m-d');

        $partnerMonthlyStat = PartnerMonthlyStat::firstOrNew([
            'partner_id' => $partner->id,
            'stat_date' => $statDate
        ]);

        $partnerMonthlyStat->total_base_price += $order->total_base_price;
        $partnerMonthlyStat->revenue += $order->price;
        $partnerMonthlyStat->commission += $order->commission;
        $partnerMonthlyStat->order_count += 1;

        // Lấy tổng revenue của partner trong tháng
        $totalRevenueInMonth = $partnerMonthlyStat->revenue;

        // Tìm mốc revenue_milestone cao nhất mà partner đạt được
        $commissionRule = CommissionRule::where('revenue_milestone', '<=', $totalRevenueInMonth)
            ->orderBy('revenue_milestone', 'desc')
            ->first();

        if ($commissionRule) {
            $partnerMonthlyStat->bonus = $commissionRule->bonus_amount;
        } else {
            $partnerMonthlyStat->bonus = 0;
        }

        // Cập nhật cột total_amount
        $partnerMonthlyStat->total_amount = $partnerMonthlyStat->commission + $partnerMonthlyStat->bonus;

        $partnerMonthlyStat->save();

        //Update Partner
        $partner->revenue += $order->price;
        $partner->number_of_order += 1;
        $partner->commission += $order->commission;
        $partner->bonus += $partnerMonthlyStat->bonus;
        $partner->save();
    }
}
