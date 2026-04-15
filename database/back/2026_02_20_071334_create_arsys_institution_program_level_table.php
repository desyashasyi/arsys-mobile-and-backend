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
        Schema::create('arsys_institution_program_level', function (Blueprint $table) {
            $table->integer('id', true);
            $table->char('code', 10)->nullable();
            $table->string('name', 50)->nullable();
            $table->string('name_eng', 50)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_a')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_institution_program_level');
    }
};
