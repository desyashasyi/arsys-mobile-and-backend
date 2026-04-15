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
        Schema::create('arsys_institution_config_base', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('code', 50)->nullable();
            $table->string('description', 50)->nullable();
            $table->boolean('status')->nullable()->default(true);
            $table->integer('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_institution_config_base');
    }
};
