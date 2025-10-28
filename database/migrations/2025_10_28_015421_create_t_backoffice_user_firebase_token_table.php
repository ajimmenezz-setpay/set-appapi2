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
        Schema::create('t_backoffice_user_firebase_token', function (Blueprint $table) {
            $table->id('Id');
            $table->uuid('UserId')->index();
            $table->string('FirebaseToken', 512)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_backoffice_user_firebase_token');
    }
};
