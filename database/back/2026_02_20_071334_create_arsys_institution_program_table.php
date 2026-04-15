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
        Schema::create('arsys_institution_program', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('faculty_id')->nullable();
            $table->integer('level_id')->nullable();
            $table->string('code', 10)->nullable();
            $table->string('abbrev', 10)->nullable();
            $table->string('name', 50)->nullable();
            $table->string('name_eng', 50)->nullable();
            $table->string('title')->nullable();
            $table->string('title_id')->nullable();
            $table->integer('staff_id')->nullable();
            $table->string('letter_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_institution_program');
    }
};
