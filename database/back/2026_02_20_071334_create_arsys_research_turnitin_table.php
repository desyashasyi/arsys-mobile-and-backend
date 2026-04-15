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
        Schema::create('arsys_research_turnitin', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('research_id')->nullable();
            $table->integer('event_type')->nullable();
            $table->integer('approval')->nullable();
            $table->integer('score')->nullable();
            $table->dateTime('approval_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_research_turnitin');
    }
};
