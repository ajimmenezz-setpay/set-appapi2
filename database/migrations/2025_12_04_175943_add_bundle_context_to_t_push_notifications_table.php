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
        Schema::table('t_push_notifications', function (Blueprint $table) {
            $table->string('BundleContext', 100)->after('CardCloudId')->index()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_push_notifications', function (Blueprint $table) {
            $table->dropColumn('BundleContext');
        });
    }
};
