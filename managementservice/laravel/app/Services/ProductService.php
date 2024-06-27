<?php

namespace App\Services;

use App\Models\Product;

class ProductService
{
    public function updateProductQuantity(Product $product, $quantity)
    {
        $product->quantity -= $quantity;
        $product->save();
    }
}
