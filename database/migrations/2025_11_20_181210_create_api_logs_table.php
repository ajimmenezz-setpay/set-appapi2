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
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id('Id');
            $table->uuid('user_id')->nullable()->index();
            $table->string('method', 10);
            $table->string('url', 500);
            $table->string('ip', 45);
            $table->text('request_headers')->nullable();
            $table->longText('request_body')->nullable();
            $table->integer('response_code')->nullable();
            $table->longText('response_body')->nullable();
            $table->decimal('execution_time_ms', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
