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
            $table->integer('month');
            $table->integer('year');
            $table->decimal('revenue', 20, 2)->default(0);
            $table->decimal('commission', 20, 2)->default(0);
            $table->timestamps();

            $table->unique(['partner_id', 'month', 'year']);
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
