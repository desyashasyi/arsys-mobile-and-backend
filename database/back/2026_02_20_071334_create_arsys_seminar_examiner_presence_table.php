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
        Schema::create('arsys_seminar_examiner_presence', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('defense_model_id')->nullable();
            $table->integer('room_id')->nullable();
            $table->integer('event_id')->nullable();
            $table->integer('applicant_id')->nullable();
            $table->integer('seminar_examiner_id')->nullable();
            $table->integer('examiner_id')->nullable();
            $table->integer('score')->nullable();
            $table->text('remark')->nullable();
            $table->integer('decision')->nullable();
            $table->timestamp('created_at')->useCurrentOnUpdate()->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_seminar_examiner_presence');
    }
};
