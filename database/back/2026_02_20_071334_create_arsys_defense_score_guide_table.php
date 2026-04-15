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
        Schema::create('arsys_defense_score_guide', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('code', 20)->nullable();
            $table->string('value', 20)->nullable();
            $table->string('description', 30)->nullable();
            $table->integer('sequence');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_defense_score_guide');
    }
};
