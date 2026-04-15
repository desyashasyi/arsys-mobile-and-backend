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
        Schema::create('arsys_event_letter', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('event_id')->nullable();
            $table->integer('program_id')->nullable();
            $table->char('number', 10)->nullable();
            $table->dateTime('date')->nullable();
            $table->integer('type_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_event_letter');
    }
};
