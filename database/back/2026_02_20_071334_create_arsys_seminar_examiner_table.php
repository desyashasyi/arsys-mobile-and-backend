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
        Schema::create('arsys_seminar_examiner', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('event_id')->nullable();
            $table->integer('room_id')->nullable();
            $table->integer('examiner_id')->nullable();
            $table->tinyInteger('additional')->nullable();
            $table->timestamp('created_at')->useCurrentOnUpdate()->nullable()->useCurrent();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_seminar_examiner');
    }
};
