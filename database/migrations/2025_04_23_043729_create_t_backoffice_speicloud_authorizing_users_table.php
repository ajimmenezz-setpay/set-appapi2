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
        Schema::create('t_backoffice_speicloud_authorizing_users', function (Blueprint $table) {
            $table->id('Id');
            $table->string('BusinessId', 36)->index();
            $table->string('UserId', 36)->index();
            $table->string('CreatedBy', 36)->index();
            $table->string('Active', 1)->default('1')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_backoffice_speicloud_authorizing_users');
    }
};
