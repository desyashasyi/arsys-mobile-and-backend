<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix arsys_research_supervisor and arsys_research_review id columns:
     * ensure they are NOT NULL AUTO_INCREMENT with PRIMARY KEY.
     */
    public function up(): void
    {
        $this->fixTable('arsys_research_supervisor');
        $this->fixTable('arsys_research_review');
    }

    private function fixTable(string $table): void
    {
        if (!Schema::hasTable($table)) {
            return;
        }

        // 1. Assign sequential IDs to any rows where id IS NULL
        $maxId = DB::table($table)->max('id') ?? 0;
        $nullRows = DB::table($table)->whereNull('id')->get();

        foreach ($nullRows as $row) {
            $maxId++;
            // Update the first matching row that still has null id
            DB::statement(
                "UPDATE `$table` SET id = ? WHERE id IS NULL LIMIT 1",
                [$maxId]
            );
        }

        // 2. Drop existing primary key if any, then add NOT NULL AUTO_INCREMENT PK
        $indexes = DB::select("SHOW INDEX FROM `$table` WHERE Key_name = 'PRIMARY'");

        if (!empty($indexes)) {
            // Remove AUTO_INCREMENT first (required by MySQL before dropping PK)
            DB::statement("ALTER TABLE `$table` MODIFY id INT NOT NULL");
            DB::statement("ALTER TABLE `$table` DROP PRIMARY KEY");
        }

        DB::statement(
            "ALTER TABLE `$table` MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY"
        );
    }

    public function down(): void
    {
        // Not meaningfully reversible
    }
};
