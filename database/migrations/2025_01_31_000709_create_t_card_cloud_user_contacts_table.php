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
        Schema::create('t_card_cloud_user_contacts', function (Blueprint $table) {
            $table->id('Id');
            $table->uuid('UUID')->index();
            $table->uuid('UserId')->index();
            $table->string('Alias');
            $table->string('ClientId')->index();
            $table->boolean('Active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_card_cloud_user_contacts');
    }
};
