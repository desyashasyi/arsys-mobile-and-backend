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
        Schema::create('arsys_staff_structure', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('position_id')->nullable();
            $table->string('structure', 20)->nullable();
            $table->string('classification', 5)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_staff_structure');
    }
};
