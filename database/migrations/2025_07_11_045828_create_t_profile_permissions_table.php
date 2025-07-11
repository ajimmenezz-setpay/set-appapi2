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
        Schema::create('t_profile_permissions', function (Blueprint $table) {
            $table->id('Id');
            $table->bigInteger('ProfileId')->index();
            $table->bigInteger('PermissionId')->unsigned()->index();
            $table->boolean('Active')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_profile_permissions');
    }
};
