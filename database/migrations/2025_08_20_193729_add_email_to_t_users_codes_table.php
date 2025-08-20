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
        Schema::table('t_users_codes', function (Blueprint $table) {
            $table->string('email')->after('UserId')->nullable()->comment('Email of the user associated with the code');
            $table->index('email', 'idx_email'); // Adding an index for faster look
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_users_codes', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
