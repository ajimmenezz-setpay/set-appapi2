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
        Schema::create('t_card_cloud_credits', function (Blueprint $table) {
            $table->id('Id');
            $table->uuid('UUID')->unique()->index();
            $table->uuid('ExternalId')->unique()->index();
            $table->uuid('CompanyId')->index();
            $table->uuid('UserId')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_card_cloud_credits');
    }
};
