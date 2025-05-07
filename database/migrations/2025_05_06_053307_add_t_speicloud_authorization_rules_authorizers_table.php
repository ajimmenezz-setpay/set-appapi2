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
        Schema::create('t_speicloud_authorization_rules_authorizers', function (Blueprint $table) {
            $table->id('Id');
            $table->unsignedBigInteger('RuleId')->index();
            $table->string('UserId', 36)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_speicloud_authorization_rules_authorizers');
    }
};
