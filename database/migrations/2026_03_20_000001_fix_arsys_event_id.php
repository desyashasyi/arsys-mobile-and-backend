<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('arsys_event')) {
            return;
        }

        // Fill null IDs with sequential values
        $maxId = DB::table('arsys_event')->max('id') ?? 0;
        $nullRows = DB::table('arsys_event')->whereNull('id')->get();

        foreach ($nullRows as $row) {
            $maxId++;
            DB::statement("UPDATE arsys_event SET id = ? WHERE id IS NULL LIMIT 1", [$maxId]);
        }

        // Fix id column: NOT NULL AUTO_INCREMENT PRIMARY KEY
        $indexes = DB::select("SHOW INDEX FROM arsys_event WHERE Key_name = 'PRIMARY'");
        if (!empty($indexes)) {
            DB::statement("ALTER TABLE arsys_event MODIFY id BIGINT NOT NULL");
            DB::statement("ALTER TABLE arsys_event DROP PRIMARY KEY");
        }

        DB::statement("ALTER TABLE arsys_event MODIFY id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY");
    }

    public function down(): void
    {
        // Not meaningfully reversible
    }
};
