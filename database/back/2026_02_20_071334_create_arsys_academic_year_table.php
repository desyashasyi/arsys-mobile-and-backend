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
        Schema::create('arsys_academic_year', function (Blueprint $table) {
            $table->integer('id', true);
            $table->char('academic_year', 11)->nullable();
            $table->dateTime('letter_date')->nullable();
            $table->string('semester')->nullable();
            $table->integer('numbering')->nullable();
            $table->dateTime('start')->nullable();
            $table->dateTime('end')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_a')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_academic_year');
    }
};
