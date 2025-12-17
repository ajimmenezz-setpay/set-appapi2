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
        Schema::create('mail_delivery_logs', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('smtp_account_id')->nullable()->index();
            $table->string('to_email')->nullable()->index();
            $table->string('mailable')->nullable()->index();
            $table->string('subject')->nullable();
            $table->string('message_id')->nullable()->index();
            $table->string('status')->index(); // queued|sent|failed|retried
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_delivery_logs');
    }
};
