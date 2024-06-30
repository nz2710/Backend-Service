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
    public function partner()
    {
        return $this->belongsTo('App\Models\Partner');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity', 'price')->withTimestamps();
    }


    public function calculateTotalPrice()
    {
        return $this->products->sum(function ($product) {
            return $product->pivot->price * $product->pivot->quantity;
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
    // protected static function booted()
    // {
    //     static::created(function ($order) {
    //         $order->partner->increment('revenue', $order->price);
    //         $order->partner->increment('number_of_order');
    //         $order->partner->update(['commission' => $order->partner->revenue * ($order->partner->discount / 100)]);
    //     });

    //     static::updated(function ($order) {
    //         $order->partner->decrement('revenue', $order->getOriginal('price'));
    //         $order->partner->increment('revenue', $order->price);
    //         $order->partner->update(['commission' => $order->partner->revenue * ($order->partner->discount / 100)]);
    //     });

    //     static::deleted(function ($order) {
    //         $order->partner->decrement('revenue', $order->price);
    //         $order->partner->decrement('number_of_order');
    //         $order->partner->update(['commission' => $order->partner->revenue * ($order->partner->discount / 100)]);
    //     });
    // }
}
