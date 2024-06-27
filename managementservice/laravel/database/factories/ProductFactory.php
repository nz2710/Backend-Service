<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    // protected $model = Product::class;

    public function definition()
    {
        $name = implode(' ', $this->faker->words(2));
        $product = new Product();
        $sku = $product->generateSku($name);

        return [
            'name' => $name,
            'sku' => $sku,
            'price' => $this->faker->randomFloat(2, 5, 500),
            'cost' => $this->faker->randomFloat(2, 10, 1000),
            'quantity' => $this->faker->numberBetween(0, 200),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }
}
