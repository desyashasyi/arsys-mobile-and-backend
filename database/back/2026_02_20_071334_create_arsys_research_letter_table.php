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
        Schema::create('arsys_research_letter', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('research_id')->nullable();
            $table->integer('research_letter_base_id')->nullable();
            $table->integer('faculty_letter_id')->nullable();
            $table->integer('faculty_letter_number')->nullable();
            $table->dateTime('faculty_letter_date')->nullable();
            $table->string('faculty_letter_date_back', 50)->nullable();
            $table->integer('program_letter_id')->nullable();
            $table->integer('program_letter_number')->nullable();
            $table->dateTime('program_letter_date')->nullable();
            $table->string('program_letter_date_back', 50)->nullable();
            $table->integer('status')->nullable();
            $table->timestamps();
            $table->string('expire_date_back', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_research_letter');
    }
};
