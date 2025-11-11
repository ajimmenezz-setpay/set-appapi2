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
        Schema::create('t_push_notifications', function (Blueprint $table) {
            $table->id('Id');
            $table->uuid('UserId')->index();
            $table->string('Token', 255)->index();
            $table->uuid('CardCloudId')->nullable()->index();
            $table->string('Title', 120);
            $table->string('Body', 250);
            $table->string('Type', 50)->index();
            $table->string('Description', 500)->nullable();
            $table->boolean('IsSent')->default(false)->index();
            $table->boolean('IsFailed')->default(false)->index();
            $table->longText('FailureReason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_push_notifications');
    }
};
