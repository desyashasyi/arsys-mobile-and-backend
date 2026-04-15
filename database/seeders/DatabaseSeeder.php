<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Production seeders (safe to run on live data):
     *   - DefenseModelSeeder  — idempotent, inserts/updates defense type lookup rows
     *   - UpdateUserPasswordSeeder — resets passwords for dev/staging use only
     *
     * Development-only seeders (do NOT run on production):
     *   - DummyDataSeeder     — generates bulk fake data
     *   - DefenseApprovalSeeder — requires existing defense events
     */
    public function run(): void
    {
        // ── Production ─────────────────────────────────────────────────────────
        $this->call([
            DefenseModelSeeder::class,
        ]);

        // ── Development / staging only ──────────────────────────────────────────
        // Uncomment as needed; never run on live data.
        // $this->call(UpdateUserPasswordSeeder::class);
        // $this->call(DummyDataSeeder::class);
        // $this->call(DefenseApprovalSeeder::class);
    }
}
