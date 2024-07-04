<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PartnerMonthlyStats extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('partner_monthly_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_id');
            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->date('stat_date');  // Ngày đầu tiên của tháng, ví dụ: 2023-06-01
            $table->decimal('total_base_price', 20, 2)->default(0);
            $table->decimal('revenue', 20, 2)->default(0);
            $table->decimal('commission', 20, 2)->default(0);
            $table->decimal('bonus', 20, 2)->default(0);
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->integer('order_count')->default(0);
            $table->timestamps();

            $table->unique(['partner_id', 'stat_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('partner_monthly_stats');
    }
}
