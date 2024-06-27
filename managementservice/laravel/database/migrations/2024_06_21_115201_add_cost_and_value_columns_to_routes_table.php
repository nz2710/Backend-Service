<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCostAndValueColumnsToRoutesTable extends Migration
{
    public function up()
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->decimal('fee', 20, 2)->after('total_time_serving')->nullable();
            $table->decimal('moving_cost', 20, 2)->after('fee')->nullable();
            $table->decimal('labor_cost', 20, 2)->after('moving_cost')->nullable();
            $table->decimal('unloading_cost', 20, 2)->after('labor_cost')->nullable();
            $table->decimal('total_order_value', 20, 2)->after('unloading_cost')->nullable();
            $table->decimal('total_route_value', 20, 2)->after('total_order_value')->nullable();
            $table->decimal('profit', 20, 2)->after('total_route_value')->nullable();
            $table->boolean('alternative')->after('profit')->default(false);
        });
    }

    public function down()
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn(['fee','moving_cost', 'labor_cost', 'unloading_cost', 'total_order_value', 'total_route_value', 'profit', 'alternative']);
        });
    }
}
