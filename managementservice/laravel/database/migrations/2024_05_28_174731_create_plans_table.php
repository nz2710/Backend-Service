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
            $table->unsignedBigInteger('vehicle_id');
            $table->foreign('vehicle_id')->references('id')->on('vehicles')->onDelete('cascade');
            $table->string('name')->nullable();
            $table->date('expected_date')->nullable();
            $table->decimal('total_demand', 16, 2);
            $table->decimal('total_distance', 20, 10);
            $table->decimal('total_time_serving', 20, 10);
            $table->decimal('total_demand_without_allocating_vehicles', 16, 2);
            $table->decimal('total_distance_without_allocating_vehicles', 20, 10);
            $table->decimal('total_time_serving_without_allocating_vehicles', 20, 10);
            $table->decimal('fee', 20, 2 )->nullable();
            $table->decimal('moving_cost', 20, 2)->nullable();
            $table->decimal('labor_cost', 20, 2)->nullable();
            $table->decimal('unloading_cost', 20, 2)->nullable();
            $table->decimal('total_order_value', 20, 2)->nullable();
            $table->decimal('total_order_profit', 20, 2)->nullable();
            $table->decimal('total_plan_value', 20, 2)->nullable();
            $table->decimal('profit', 20, 2)->nullable();
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
