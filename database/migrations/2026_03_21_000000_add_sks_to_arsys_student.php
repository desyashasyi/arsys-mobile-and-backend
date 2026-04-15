<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arsys_student', function (Blueprint $table) {
            $table->smallInteger('sks')->nullable()->after('GPA');
        });
    }

    public function down(): void
    {
        Schema::table('arsys_student', function (Blueprint $table) {
            $table->dropColumn('sks');
        });
    }
};
