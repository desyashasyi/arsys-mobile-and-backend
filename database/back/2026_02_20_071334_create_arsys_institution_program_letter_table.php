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
        Schema::create('arsys_institution_program_letter', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('program_id')->nullable();
            $table->integer('program_letter_base_id')->nullable();
            $table->string('number', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_institution_program_letter');
    }
};
