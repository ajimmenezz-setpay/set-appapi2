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
        Schema::create('t_users_additional_data', function (Blueprint $table) {
            $table->id('Id');
            $table->uuid('UserId')->index();
            $table->string('RFC')->nullable();
            $table->string('CURP')->nullable();
            $table->string('VoterCode')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_users_additional_data');
    }
};
