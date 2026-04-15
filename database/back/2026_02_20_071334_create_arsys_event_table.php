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
        Schema::create('arsys_event', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('program_id')->nullable();
            $table->integer('event_type_id')->nullable();
            $table->dateTime('application_deadline')->nullable();
            $table->dateTime('event_date')->nullable();
            $table->dateTime('draft_deadline')->nullable();
            $table->integer('quota')->nullable();
            $table->integer('current')->nullable();
            $table->integer('status')->nullable();
            $table->integer('completed')->nullable();
            $table->timestamp('created_at')->useCurrentOnUpdate()->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_event');
    }
};
