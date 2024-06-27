<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->decimal('total_demand', 16, 2);
            $table->decimal('total_distance', 20, 10);
            $table->decimal('total_time_serving', 20, 10);
            $table->decimal('total_demand_without_allocating_vehicles', 16, 2);
            $table->decimal('total_distance_without_allocating_vehicles', 20, 10);
            $table->decimal('total_time_serving_without_allocating_vehicles', 20, 10);
            $table->decimal('fee', 20, 2 )->nullable();
            $table->integer('total_vehicle_used')->nullable();
            $table->integer('total_num_customer_served')->nullable();
            $table->integer('total_num_customer_not_served')->nullable();
            $table->string('status')->default('Pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plans');
    }
}
