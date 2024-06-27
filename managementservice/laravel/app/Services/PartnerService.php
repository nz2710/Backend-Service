<?php

namespace App\Services;

use App\Models\Partner;

class PartnerService
{
    public function updatePartnerOnNewOrder(Partner $partner, $orderPrice)
    {
        $partner->revenue += $orderPrice;
        $partner->number_of_order += 1;

        $commissionRate = $partner->discount / 100;
        $orderCommission = $orderPrice * $commissionRate;
        $partner->commission += $orderCommission;

        $partner->save();
    }

    // PartnerService.php

    // public function updatePartnerOnOrderUpdate(Partner $partner, $priceDifference, $newPrice)
    // {
    //     $partner->revenue += $priceDifference;

    //     $commissionRate = $partner->discount / 100;
    //     $orderCommission = $newPrice * $commissionRate;
    //     $partner->commission = $orderCommission;

    //     $partner->save();
    // }
}
