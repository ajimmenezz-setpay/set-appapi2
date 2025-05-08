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
        Schema::create('t_speicloud_authorization_rules', function (Blueprint $table) {
            $table->id('Id');
            $table->string('BusinessId', 36)->index();
            $table->integer('RuleType')->default(1)->index()->comment('1: Spei Out, 2: Spei In');
            $table->decimal('Amount', 10, 2)->nullable();
            $table->integer('DailyMovementsLimit')->nullable();
            $table->integer('MonthlyMovementsLimit')->nullable();
            $table->integer('Priority')->default(0)->comment('Bigger number means higher priority around the other rules');
            $table->string('CreatedBy', 36)->index();
            $table->boolean('Active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_speicloud_authorization_rules');
    }
};
