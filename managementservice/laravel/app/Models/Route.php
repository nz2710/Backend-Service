<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;
    protected $fillable = [
        'plan_id',
        'depot_id',
        'route',
        'total_demand',
        'total_distance',
        'total_time_serving',
        'fee',
        'moving_cost',
        'labor_cost',
        'unloading_cost',
        'total_order_value',
        'total_route_value',
        'profit',
        'alternative',
        'is_served',
        'created_at',
        'updated_at'
    ];
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
    public function depot()
    {
        return $this->belongsTo(Depot::class);
    }
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
