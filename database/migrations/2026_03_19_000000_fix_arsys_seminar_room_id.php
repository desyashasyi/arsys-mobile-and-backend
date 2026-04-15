<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure arsys_seminar_room.id is NOT NULL AUTO_INCREMENT with PRIMARY KEY.
     * This fixes the issue where room IDs are returned as null by the backend.
     */
    public function up(): void
    {
        if (!Schema::hasTable('arsys_seminar_room')) {
            return;
        }

        // Check if the primary key constraint already exists
        $indexes = DB::select("SHOW INDEX FROM arsys_seminar_room WHERE Key_name = 'PRIMARY'");

        if (empty($indexes)) {
            // No primary key — add it
            DB::statement('ALTER TABLE arsys_seminar_room MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY');
        } else {
            // Primary key exists but id might not be auto-increment
            DB::statement('ALTER TABLE arsys_seminar_room MODIFY id INT NOT NULL AUTO_INCREMENT');
        }
    }

    public function down(): void
    {
        // Not reversible in a meaningful way
    }
};
