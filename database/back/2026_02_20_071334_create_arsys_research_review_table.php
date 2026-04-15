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
        Schema::create('arsys_research_review', function (Blueprint $table) {
            $table->integer('id', true);
            $table->bigInteger('research_id')->nullable();
            $table->integer('reviewer_id')->nullable();
            $table->integer('decision_id')->nullable();
            $table->text('comment')->nullable();
            $table->dateTime('approval_date')->nullable();
            $table->timestamp('created_at')->useCurrentOnUpdate()->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_research_review');
    }
};
