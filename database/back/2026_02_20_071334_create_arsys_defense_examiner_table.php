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
        Schema::create('arsys_defense_examiner', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('event_id')->nullable();
            $table->integer('examiner_id')->nullable();
            $table->integer('applicant_id')->nullable();
            $table->integer('order')->nullable();
            $table->integer('additional')->nullable();
            $table->timestamp('created_at')->useCurrentOnUpdate()->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_defense_examiner');
    }
};
