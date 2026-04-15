<?php

namespace Database\Seeders;

use App\Models\ArSys\DefenseApproval;
use App\Models\ArSys\DefenseModel;
use App\Models\ArSys\Research;
use App\Models\ArSys\Staff;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DefenseApprovalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if there are any researches
        $researches = Research::limit(5)->get();

        if ($researches->isEmpty()) {
            $this->command->info('No researches found. Skipping DefenseApprovalSeeder.');
            return;
        }

        // Get some staff members for approvers
        $staffs = Staff::limit(3)->get();

        if ($staffs->isEmpty()) {
            $this->command->info('No staff found. Skipping DefenseApprovalSeeder.');
            return;
        }

        $preDefenseModel = DefenseModel::where('code', 'PRE')->first();
        $finalDefenseModel = DefenseModel::where('code', 'PUB')->first();
        $seminarModel = DefenseModel::where('code', 'SEM')->first();

        if (!$preDefenseModel || !$finalDefenseModel || !$seminarModel) {
            $this->command->error('Defense models not found. Please run DefenseModelSeeder first.');
            return;
        }

        // Create approvals for each research
        foreach ($researches as $research) {
            // Create pre-defense approval
            DefenseApproval::updateOrCreate(
                [
                    'research_id' => $research->id,
                    'defense_model_id' => $preDefenseModel->id,
                    'staff_id' => $staffs[0]->id,
                ],
                [
                    'decision' => null,
                    'approval_date' => null,
                ]
            );

            // Create final-defense approval
            if ($staffs->count() > 1) {
                DefenseApproval::updateOrCreate(
                    [
                        'research_id' => $research->id,
                        'defense_model_id' => $finalDefenseModel->id,
                        'staff_id' => $staffs[1]->id,
                    ],
                    [
                        'decision' => null,
                        'approval_date' => null,
                    ]
                );
            }

            // Create seminar approval
            if ($staffs->count() > 2) {
                DefenseApproval::updateOrCreate(
                    [
                        'research_id' => $research->id,
                        'defense_model_id' => $seminarModel->id,
                        'staff_id' => $staffs[2]->id,
                    ],
                    [
                        'decision' => null,
                        'approval_date' => null,
                    ]
                );
            }
        }

        $this->command->info('DefenseApprovalSeeder completed successfully.');
    }
}
