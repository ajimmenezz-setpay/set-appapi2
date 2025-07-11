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
        Schema::table('cat_profile', function (Blueprint $table) {
            $table->uuid('BusinessId')->nullable()->after('Level')->index();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cat_profile', function (Blueprint $table) {
            $table->dropColumn('BusinessId');
        });
    }
};
