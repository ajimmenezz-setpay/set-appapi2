<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cat_countries', function (Blueprint $table) {
            $table->id('Id');
            $table->string('name', 255);
            $table->string('official_name', 255)->nullable();
            $table->char('iso2', 2);
            $table->char('iso3', 3)->nullable();
            $table->string('phone_code', 10)->nullable();
            $table->char('currency_code_alpha', 3)->nullable();
            $table->char('currency_code_numeric', 3)->nullable();
            $table->string('currency_name', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cat_countries');
    }
};
