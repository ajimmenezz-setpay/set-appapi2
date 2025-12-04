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
        Schema::create('t_backoffice_business_bundle_context', function (Blueprint $table) {
            $table->uuid('BusinessId')->index();
            $table->string('BundleContext', 100)->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_backoffice_business_bundle_context');
    }
};
