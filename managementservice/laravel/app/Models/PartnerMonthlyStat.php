<?php

namespace App\Models;

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
        return $this->partner->orders()->whereYear('created_at', $this->stat_date->format('Y'))
                                        ->whereMonth('created_at', $this->stat_date->format('m'));
    }
}
