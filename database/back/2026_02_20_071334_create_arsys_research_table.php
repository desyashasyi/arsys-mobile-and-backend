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
        Schema::create('arsys_research', function (Blueprint $table) {
            $table->bigInteger('id', true);
            $table->integer('milestone_id')->nullable();
            $table->integer('academic_year_id')->nullable();
            $table->integer('type_id')->nullable();
            $table->bigInteger('student_id')->nullable();
            $table->char('code', 15)->nullable();
            $table->bigInteger('status')->nullable();
            $table->text('title')->nullable();
            $table->text('abstract')->nullable();
            $table->text('file')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_research');
    }
};
