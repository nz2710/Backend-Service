<?php

namespace Database\Seeders;

use League\Csv\Reader;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Faker\Generator as Faker;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $faker = \Faker\Factory::create();
        $filePath = 'book_data.csv'; // Adjust the path as necessary
        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0); // Assuming the first row is the header

        $records = $csv->getRecords();
        foreach ($records as $record) {
            $product = new Product(); // Create an instance of Product
            $sku = $product->generateSku($record['title']); // Call the non-static method
            Product::create([
                'name' => $record['title'],
                'sku' => $sku, // Assuming generateSku is a method in your Product model
                'description' => $record['authors'] . ' - ' . $record['manufacturer'],
                'price' => $record['original_price'],
                'cost' => $record['current_price'],
                'quantity' => $record['quantity'],
                'created_at' => $faker->dateTimeBetween('2023-01-01', 'now'),
                'updated_at' => null // Assuming you want the updated_at to be the current time
            ]);
        }
    }
}
