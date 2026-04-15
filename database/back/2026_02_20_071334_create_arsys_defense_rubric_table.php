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
        Schema::create('arsys_defense_rubric', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('rubric_id')->nullable();
            $table->integer('weight')->nullable();
            $table->char('role', 3)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_defense_rubric');
    }
};
