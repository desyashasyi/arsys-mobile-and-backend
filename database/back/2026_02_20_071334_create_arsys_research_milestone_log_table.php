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
        Schema::create('arsys_research_milestone_log', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('research_id')->nullable();
            $table->integer('research_model_id')->nullable();
            $table->integer('milestone_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_research_milestone_log');
    }
};
