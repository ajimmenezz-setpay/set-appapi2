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
        Schema::create('t_card_cloud_spei_accounts', function (Blueprint $table) {
            $table->id('Id');
            $table->char('CardId', 36)->unique()->index()->collation('utf8mb4_general_ci');
            $table->char('Clabe', 18)->unique()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_card_cloud_spei_accounts');
    }
};
