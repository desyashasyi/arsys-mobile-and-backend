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
        Schema::create('arsys_research_type_base', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->char('code', 3)->nullable();
            $table->string('color', 10)->nullable();
            $table->integer('research_model_id')->nullable();
            $table->string('description', 30)->nullable();
            $table->string('level_id', 20)->nullable();
            $table->integer('supervisor_number')->nullable();
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
        Schema::dropIfExists('arsys_research_type_base');
    }
};
