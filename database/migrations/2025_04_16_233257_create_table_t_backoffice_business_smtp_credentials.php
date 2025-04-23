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
        Schema::create('t_backoffice_business_smtp_credentials', function (Blueprint $table) {
            $table->id('Id');
            $table->uuid('BusinessId')->index();
            $table->string('SmtpHost', 255);
            $table->string('SmtpPort', 10);
            $table->string('SmtpUser', 255);
            $table->string('SmtpPassword', 255);
            $table->string('SmtpEncryption', 10);
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_backoffice_business_smtp_credentials');
    }
};
