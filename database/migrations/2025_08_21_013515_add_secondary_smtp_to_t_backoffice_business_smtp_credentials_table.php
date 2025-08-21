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
        Schema::table('t_backoffice_business_smtp_credentials', function (Blueprint $table) {
            $table->string('SmtpPort', 255)->change();
            $table->string('SmtpEncryption', 255)->change();

            $table->string('SmtpHost2',255)->nullable()->after('SmtpEncryption');
            $table->string('SmtpPort2',255)->nullable()->after('SmtpHost2');
            $table->string('SmtpUser2',255)->nullable()->after('SmtpPort2');
            $table->string('SmtpPassword2',255)->nullable()->after('SmtpUser2');
            $table->string('SmtpEncryption2',255)->nullable()->after('SmtpPassword2');
            $table->boolean('LastUsedMain')->default(false)->after('SmtpEncryption2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_backoffice_business_smtp_credentials', function (Blueprint $table) {
            $table->string('SmtpPort', 10)->change();
            $table->string('SmtpEncryption', 10)->change();
            $table->dropColumn('SmtpHost2');
            $table->dropColumn('SmtpPort2');
            $table->dropColumn('SmtpUser2');
            $table->dropColumn('SmtpPassword2');
            $table->dropColumn('SmtpEncryption2');
            $table->dropColumn('LastUsedMain');
        });
    }
};
