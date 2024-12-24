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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id('Id');
            $table->string('ClickupListId');
            $table->string('ClickupTaskId');
            $table->uuid('UserId');
            $table->string('TicketName');
            $table->text('TicketDescription');
            $table->string('TicketStatus');
            $table->char('StatusColor', 7)->default('#000000');
            $table->char('MovementId', 36)->nullable();
            $table->timestamps(3);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
