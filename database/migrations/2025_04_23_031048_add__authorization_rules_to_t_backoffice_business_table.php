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
        Schema::table('t_backoffice_business', function (Blueprint $table) {
            $table->boolean('AuthorizationRules')->default(false)->after('TemplateFile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_backoffice_business', function (Blueprint $table) {
            $table->dropColumn('AuthorizationRules');
        });
    }
};
