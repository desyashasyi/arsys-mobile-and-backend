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
        Schema::create('arsys_seminar_supervisor_presence', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('defense_model_id')->nullable();
            $table->integer('event_id')->nullable();
            $table->integer('research_supervisor_id')->nullable();
            $table->integer('supervisor_id')->nullable();
            $table->integer('research_id')->nullable();
            $table->integer('score')->nullable();
            $table->text('remark')->nullable();
            $table->timestamp('created_at')->useCurrentOnUpdate()->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_seminar_supervisor_presence');
    }
};
