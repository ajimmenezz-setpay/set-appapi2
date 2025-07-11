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
        Schema::create('cat_permissions', function (Blueprint $table) {
            $table->id('Id');
            $table->bigInteger('CategoryId')->unsigned()->index();
            $table->bigInteger('ModuleId')->unsigned()->index();
            $table->string('Key', 50)->index();
            $table->string('Name', 100);
            $table->text('Description')->nullable();
            $table->boolean('Flag')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cat_permissions');
    }
};
