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
        Schema::table('smtp_accounts', function (Blueprint $table) {
            $table->unsignedInteger('fail_count')->default(0)->after('active');
            $table->timestamp('disabled_until')->nullable()->after('fail_count');
            $table->text('last_error')->nullable()->after('disabled_until');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('smtp_accounts', function (Blueprint $table) {
            $table->dropColumn(['fail_count', 'disabled_until', 'last_error']);
        });
    }
};
