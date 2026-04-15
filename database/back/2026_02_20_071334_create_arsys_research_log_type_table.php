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
        Schema::create('arsys_research_log_type', function (Blueprint $table) {
            $table->integer('id', true);
            $table->char('code', 15)->nullable();
            $table->string('description', 50)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_a')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_research_log_type');
    }
};
