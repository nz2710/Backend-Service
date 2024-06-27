<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'capacity',
        'speed',
        'total_vehicles',
        'fuel_consumption',
        'fuel_cost',
        'hourly_rate',
        'shipping_rate',
        'status',
        'created_at',
        'updated_at'
    ];
}
