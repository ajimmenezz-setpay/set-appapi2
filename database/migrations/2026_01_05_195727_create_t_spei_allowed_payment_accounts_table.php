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
        Schema::create('t_spei_allowed_payment_accounts', function (Blueprint $table) {
            $table->id('Id');
            $table->uuid('StpAccountId')->index();
            $table->string('Description', 100);
            $table->string('AccountNumber', 20)->index();
            $table->boolean('Active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_spei_allowed_payment_accounts');
    }
};
