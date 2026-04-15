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
        Schema::create('arsys_research_type_examination', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('research_type_id')->nullable();
            $table->integer('defense_model_id')->nullable();
            $table->integer('event_type_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_research_type_examination');
    }
};
