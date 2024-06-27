<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition()

    {
        $gender = $this->faker->randomElement(['Male', 'Female']);
        $status = $this->faker->randomElement(['1', '0']);

        // Using a local faker for Vietnamese
        $fakerVN = \Faker\Factory::create('vi_VN');

        return [
            'name' => $fakerVN->name($gender === 'Male' ? 'male' : 'female'),
            'register_date' => $this->faker->dateTimeBetween('2020-01-01', 'now')->format('Y-m-d'),
            'address' => $fakerVN->address(),
            'phone' => $fakerVN->numerify('##########'), // Generates a 10-digit phone number
            'status' => 'Active', // Set status as Active for all entries
            'gender' => $gender,
            'date_of_birth' => $this->faker->date(),
        ];
    }
}
