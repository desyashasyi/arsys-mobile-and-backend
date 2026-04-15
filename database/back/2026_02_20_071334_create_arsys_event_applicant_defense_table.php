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
        Schema::create('arsys_event_applicant_defense', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('defense_model_id')->nullable();
            $table->bigInteger('research_id')->nullable();
            $table->integer('event_id')->nullable();
            $table->integer('session_id')->nullable();
            $table->integer('space_id')->nullable();
            $table->integer('status')->nullable();
            $table->integer('confirmed')->nullable();
            $table->integer('publish')->nullable();
            $table->text('report')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_event_applicant_defense');
    }
};
