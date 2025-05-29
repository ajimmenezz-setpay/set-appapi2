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
        Schema::table('cat_modules_category', function (Blueprint $table) {
            $table->unsignedBigInteger('ServiceId')->nullable()->after('Order')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cat_modules_category', function (Blueprint $table) {
            $table->dropColumn('ServiceId');
        });
    }
};
