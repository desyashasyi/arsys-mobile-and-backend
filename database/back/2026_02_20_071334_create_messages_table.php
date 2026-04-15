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
        Schema::create('messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile_country_code')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('IP')->nullable();
            $table->boolean('has_read')->default(false);
            $table->string('sender_type')->nullable();
            $table->string('sender_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
