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
        Schema::create('t_users_address', function (Blueprint $table) {
            $table->id('Id');
            $table->string('UserId', 36)->index();
            $table->unsignedBigInteger('CountryId')->index();
            $table->unsignedBigInteger('StateId')->index();
            $table->string('City', 255);
            $table->string('PostalCode', 10);
            $table->string('Street', 255);
            $table->string('ExternalNumber', 20);
            $table->string('InternalNumber', 20)->nullable();
            $table->string('Reference', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_users_address');
    }
};
