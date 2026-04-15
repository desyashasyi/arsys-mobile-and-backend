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
        Schema::create('arsys_research_review_discussion', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('research_id')->nullable();
            $table->integer('discussant_id')->nullable();
            $table->integer('discussant_type')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('created_at')->useCurrentOnUpdate()->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_research_review_discussion');
    }
};
