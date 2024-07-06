<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class PartnerMonthlyStat extends Model
{
    use HasFactory;
    protected $table = 'partner_monthly_stats';
    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }
    public function orders()
    {
        {
            $statDate = Carbon::parse($this->stat_date);
            return $this->partner->orders()->whereYear('created_at', $statDate->format('Y'))
                                            ->whereMonth('created_at', $statDate->format('m'));
        }
    }
}
