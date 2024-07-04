<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('route_id')->nullable()->after('id');
            $table->foreign('route_id')->references('id')->on('routes')->onDelete('set null');
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->string('code_order')->nullable();
            $table->string('customer_name')->index()->nullable();
            $table->string('phone')->nullable();
            $table->decimal('price', 20, 2)->default(0);
            $table->decimal('total_base_price', 20, 2)->default(0);
            $table->decimal('commission', 20, 2)->default(0);
            $table->decimal('total_cost', 20, 2)->default(0);
            $table->decimal('profit', 20, 2)->default(0);
            $table->decimal('mass_of_order', 16, 2)->nullable();
            $table->string('address')->nullable();
            $table->decimal('longitude',20,16)->nullable();
            $table->decimal('latitude',20,16)->nullable();
            $table->bigInteger('time_service')->nullable();
            $table->date('expected_date')->nullable();
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
        Schema::dropIfExists('orders');
    }
}
