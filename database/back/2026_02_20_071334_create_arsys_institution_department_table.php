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
        Schema::create('arsys_institution_department', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('faculty_id')->nullable();
            $table->string('code', 5)->nullable();
            $table->string('description', 50)->nullable();
            $table->string('description_eng', 50)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_institution_department');
    }
};
