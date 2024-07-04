<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'vehicle_id',
        'expected_date',
        'total_demand',
        'total_distance',
        'total_time_serving',
        'total_demand_without_allocating_vehicles',
        'total_distance_without_allocating_vehicles',
        'total_time_serving_without_allocating_vehicles',
        'fee',
        'moving_cost',
        'labor_cost',
        'unloading_cost',
        'total_order_value',
        'total_order_profit',
        'total_plan_value',
        'profit',
        'total_vehicle_used',
        'total_num_customer_served',
        'total_num_customer_not_served',
        'status',
        'created_at',
        'updated_at'
    ];
    public function routes()
    {
        return $this->hasMany(Route::class);
    }
    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
