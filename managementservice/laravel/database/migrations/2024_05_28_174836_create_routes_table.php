<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoutesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('routes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('depot_id');
            $table->json('route');
            $table->decimal('total_demand',16,2);
            $table->decimal('total_distance',20,10);
            $table->decimal('total_time_serving',20,10);
            $table->decimal('fee', 20, 2)->nullable();
            $table->decimal('moving_cost', 20, 2)->nullable();
            $table->decimal('labor_cost', 20, 2)->nullable();
            $table->decimal('unloading_cost', 20, 2)->nullable();
            $table->decimal('total_order_value', 20, 2)->nullable();
            $table->decimal('total_order_profit', 20, 2)->nullable();
            $table->decimal('total_route_value', 20, 2)->nullable();
            $table->decimal('profit', 20, 2)->nullable();
            $table->boolean('alternative')->default(false);
            $table->boolean('is_served')->nullable();  // Mặc định là được phục vụ
            $table->timestamps();
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            $table->foreign('depot_id')->references('id')->on('depots')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('routes');
    }
}
