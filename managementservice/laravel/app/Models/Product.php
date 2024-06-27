<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    public function orders()
    {
        return $this->belongsToMany(Order::class)->withPivot('quantity', 'price')->withTimestamps();;
    }

    // ...

    public function generateSku($productName)
    {
        $prefix = $this->generatePrefix($productName);
        $sequence = $this->getNextSequence($prefix);
        $sku = $prefix . '-' . $sequence;
        return $sku;
    }

    private function generatePrefix($productName)
    {
        $words = explode(' ', $productName);
        $prefix = '';
        foreach ($words as $word) {
            $prefix .= mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8');
        }
        return $prefix;
    }

    private function getNextSequence($prefix)
    {
        $lastProduct = self::where('sku', 'like', $prefix . '-%')->orderBy('sku', 'desc')->first();
        $lastSequence = $lastProduct ? intval(substr($lastProduct->sku, -4)) : 0;
        return str_pad($lastSequence + 1, 4, '0', STR_PAD_LEFT);
    }
}
