<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'id',
        'route_id',
        'partner_id',
        'code_order',
        'customer_name',
        'phone',
        'price',
        'total_base_price',
        'commission',
        'total_cost',
        'profit',
        'mass_of_order',
        'address',
        'longitude',
        'latitude',
        'time_service',
        'expected_date',
        'status',
        'created_at',
        'updated_at'
    ];
    public function partner()
    {
        return $this->belongsTo('App\Models\Partner');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity', 'price')->withTimestamps();
    }


    public function calculateTotalBasePrice()
    {
        return $this->products->sum(function ($product) {
            return $product->price * $product->pivot->quantity;
        });
    }


    public function calculateTotalPrice()
    {
        return $this->products->sum(function ($product) {
            return $product->pivot->price * $product->pivot->quantity;
        });
    }

    public function calculateTotalCost()
    {
        return $this->products->sum(function ($product) {
            return $product->cost * $product->pivot->quantity;
        });
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function generateCodeOrder($partnerId)
    {
        $year = Carbon::now()->year;
        $month = Carbon::now()->format('m');
        $day = Carbon::now()->format('d');
        $partner = Partner::find($partnerId);
        $partnerName = $partner->name;

        // Tạo partnerPrefix từ ký tự đầu tiên của mỗi từ trong tên đối tác
        $words = explode(' ', $partnerName);
        $prefixChars = array_map(function ($word) {
            return Str::upper(Str::limit($word, 1, ''));
        }, $words);
        $partnerPrefix = implode('', $prefixChars);



        $codeOrder = $partnerPrefix . $partnerId . '-' . $year . $month . $day . '-' . Str::random(5);

        return $codeOrder;
    }
}
