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
        Schema::create('arsys_defense_rubric_base', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('program_id')->nullable();
            $table->integer('defense_model_id')->nullable();
            $table->string('item')->nullable();
            $table->integer('sequence')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_defense_rubric_base');
    }
};
