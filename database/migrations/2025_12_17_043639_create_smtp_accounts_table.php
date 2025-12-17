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
        Schema::create('smtp_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('port');
            $table->string('encryption')->nullable();
            $table->text('username')->nullable();
            $table->text('password')->nullable();
            $table->string('from_address');
            $table->string('from_name');

            $table->unsignedInteger('daily_limit')->default(2000);
            $table->unsignedInteger('sent_today')->default(0);
            $table->boolean('is_next')->default(false);
            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('smtp_accounts');
    }
};
