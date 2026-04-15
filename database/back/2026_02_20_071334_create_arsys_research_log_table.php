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
        Schema::create('arsys_research_log', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('type_id')->nullable();
            $table->integer('research_id')->nullable();
            $table->integer('loger_id')->nullable();
            $table->string('message')->nullable();
            $table->integer('status')->nullable()->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_research_log');
    }
};
