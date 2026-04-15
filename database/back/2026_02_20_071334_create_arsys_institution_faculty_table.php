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
        Schema::create('arsys_institution_faculty', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('code', 10)->nullable();
            $table->string('name', 50)->nullable();
            $table->integer('university_id')->nullable();
            $table->string('description_eng', 50)->nullable();
            $table->timestamp('name_eng')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_institution_faculty');
    }
};
