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
        Schema::create('arsys_student', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('user_id')->nullable();
            $table->integer('program_id')->nullable();
            $table->char('number', 10)->nullable();
            $table->string('code', 10)->nullable();
            $table->integer('specialization_id')->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->integer('supervisor_id')->nullable();
            $table->char('GPA', 4)->nullable();
            $table->char('status', 15)->nullable();
            $table->char('phone', 15)->nullable();
            $table->string('email', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arsys_student');
    }
};
