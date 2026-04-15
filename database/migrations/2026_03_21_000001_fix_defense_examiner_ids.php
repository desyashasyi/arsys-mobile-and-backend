<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('arsys_defense_examiner') || !Schema::hasTable('arsys_defense_examiner_presence')) {
            return;
        }

        // --- arsys_defense_examiner ---
        $maxId = DB::table('arsys_defense_examiner')->max('id') ?? 0;
        $nullRows = DB::table('arsys_defense_examiner')->whereNull('id')->get();
        foreach ($nullRows as $row) {
            $maxId++;
            DB::statement(
                "UPDATE arsys_defense_examiner SET id = ? WHERE id IS NULL AND applicant_id = ? AND examiner_id = ? AND event_id = ? LIMIT 1",
                [$maxId, $row->applicant_id, $row->examiner_id, $row->event_id]
            );
        }
        $indexes = DB::select("SHOW INDEX FROM arsys_defense_examiner WHERE Key_name = 'PRIMARY'");
        if (!empty($indexes)) {
            DB::statement("ALTER TABLE arsys_defense_examiner MODIFY id INT NOT NULL");
            DB::statement("ALTER TABLE arsys_defense_examiner DROP PRIMARY KEY");
        }
        DB::statement("ALTER TABLE arsys_defense_examiner MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY");

        // --- arsys_defense_examiner_presence ---
        $maxId = DB::table('arsys_defense_examiner_presence')->max('id') ?? 0;
        $nullRows = DB::table('arsys_defense_examiner_presence')->whereNull('id')->get();
        foreach ($nullRows as $row) {
            $maxId++;
            DB::statement(
                "UPDATE arsys_defense_examiner_presence SET id = ? WHERE id IS NULL AND defense_examiner_id = ? LIMIT 1",
                [$maxId, $row->defense_examiner_id]
            );
        }
        $indexes = DB::select("SHOW INDEX FROM arsys_defense_examiner_presence WHERE Key_name = 'PRIMARY'");
        if (!empty($indexes)) {
            DB::statement("ALTER TABLE arsys_defense_examiner_presence MODIFY id INT NOT NULL");
            DB::statement("ALTER TABLE arsys_defense_examiner_presence DROP PRIMARY KEY");
        }
        DB::statement("ALTER TABLE arsys_defense_examiner_presence MODIFY id INT NOT NULL AUTO_INCREMENT PRIMARY KEY");
    }

    public function down(): void {}
};
