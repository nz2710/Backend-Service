<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCostColumnsToPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('moving_cost', 20, 2)->after('fee')->nullable();
            $table->decimal('labor_cost', 20, 2)->after('moving_cost')->nullable();
            $table->decimal('unloading_cost', 20, 2)->after('labor_cost')->nullable();
            $table->decimal('total_order_value', 20, 2)->after('unloading_cost')->nullable();
            $table->decimal('total_plan_value', 20, 2)->after('total_order_value')->nullable();
            $table->decimal('profit', 20, 2)->after('total_plan_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('moving_cost');
            $table->dropColumn('labor_cost');
            $table->dropColumn('unloading_cost');
            $table->dropColumn('total_order_value');
            $table->dropColumn('total_plan_value');
            $table->dropColumn('profit');
        });
    }
}
