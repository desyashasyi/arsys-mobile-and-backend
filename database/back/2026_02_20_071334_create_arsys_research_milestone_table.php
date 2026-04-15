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
        Schema::create('arsys_research_milestone', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->string('code', 20)->nullable();
            $table->integer('research_model_id')->nullable();
            $table->integer('defense_model_id')->nullable();
            $table->string('phase', 50)->nullable();
            $table->integer('sequence')->nullable();
            $table->string('description', 50)->nullable();
            $table->string('message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_research_milestone');
    }
};
