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
        Schema::create('arsys_research_defense_type', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('program_id')->nullable();
            $table->integer('research_type_base_id')->nullable();
            $table->integer('supervisor_number')->nullable();
            $table->boolean('status')->nullable()->default(true);
            $table->integer('week_of_supervise')->nullable();
            $table->integer('enable_week_of_supervise')->nullable()->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_research_defense_type');
    }
};
