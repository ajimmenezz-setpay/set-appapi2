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
        Schema::table('t_card_cloud_user_contacts', function (Blueprint $table) {
            $table->string('Name')->nullable()->after('UserID');
            $table->string('Institution')->nullable()->after('Alias');
            $table->string('Account')->nullable()->after('Institution');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_card_cloud_user_contacts', function (Blueprint $table) {
            $table->dropColumn('Name');
            $table->dropColumn('Institution');
            $table->dropColumn('Account');
        });
    }
};
