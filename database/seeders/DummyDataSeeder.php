<?php

namespace Database\Seeders;

use App\Models\ArSys\DefenseApproval;
use App\Models\ArSys\DefenseExaminer;
use App\Models\ArSys\DefenseModel;
use App\Models\ArSys\DefenseRole;
use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantDefense;
use App\Models\ArSys\EventApplicantFinalDefense;

use App\Models\ArSys\EventSession;
use App\Models\ArSys\EventSpace;
use App\Models\ArSys\EventType;
use App\Models\ArSys\FinalDefenseExaminer;
use App\Models\ArSys\FinalDefenseRoom;
use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchLog;
use App\Models\ArSys\ResearchLogType;
use App\Models\ArSys\ResearchMilestone;
use App\Models\ArSys\ResearchReview;
use App\Models\ArSys\ResearchReviewDecisionType;
use App\Models\ArSys\ResearchSupervisor;
use App\Models\ArSys\Staff;
use App\Models\ArSys\Student;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // ─── Get all staff and students ───
        $allStaff = Staff::all();
        $allStudents = Student::all();

        if ($allStaff->count() < 4) {
            $this->command->error('Not enough staff in the system. Need at least 4 for proper seeding. Aborting.');
            return;
        }
        if ($allStudents->count() < 15) {
            $this->command->error('Not enough students in the system. Need at least 15 for proper seeding. Aborting.');
            return;
        }

        // ─── Get reference data ───
        $preDefenseModel = DefenseModel::where('code', 'PRE')->first();
        $finalDefenseModel = DefenseModel::where('code', 'PUB')->first();
        $preEventType = EventType::where('code', 'PRE')->first();
        $pubEventType = EventType::where('code', 'PUB')->first();

        if (!$preDefenseModel || !$finalDefenseModel || !$preEventType || !$pubEventType) {
            $this->command->error('Missing defense models or event types. Run DefenseModelSeeder first.');
            return;
        }

        // Get milestones, log types, decision types, and roles
        $milestoneProposalReview = ResearchMilestone::firstOrCreate(['code' => 'Proposal', 'phase' => 'Review']);
        $milestonePreDefSubmitted = ResearchMilestone::firstOrCreate(['code' => 'Pre-defense', 'phase' => 'Submitted']);
        $milestonePreDefDone = ResearchMilestone::firstOrCreate(['code' => 'Pre-defense', 'phase' => 'Done']);
        $milestoneFinalDefSubmitted = ResearchMilestone::firstOrCreate(['code' => 'Final-defense', 'phase' => 'Submitted']);
        $milestoneFinalDefDone = ResearchMilestone::firstOrCreate(['code' => 'Final-defense', 'phase' => 'Done']);
        $actLogType = ResearchLogType::where('code', 'ACT')->first();
        $revLogType = ResearchLogType::where('code', 'REV')->first();
        $approveDecision = ResearchReviewDecisionType::where('code', 'APP')->first();
        $spvRole = DefenseRole::where('code', 'SPV')->first();
        $prgRole = DefenseRole::where('code', 'PRG')->first();

        // Get or create event spaces and sessions
        $spaces = EventSpace::all();
        if ($spaces->isEmpty()) {
            for ($i = 1; $i <= 5; $i++) {
                $spaces->push(EventSpace::firstOrCreate(['code' => "R-{$i}"]));
            }
        }
        $sessions = EventSession::all();
        if ($sessions->isEmpty()) {
            $sessionTimes = ['08:00', '10:00', '13:00'];
            foreach ($sessionTimes as $time) {
                $sessions->push(EventSession::firstOrCreate(['time' => $time], ['name' => "Sesi {$time}"]));
            }
        }

        // Helper functions
        $createResearch = function (Student $student, $milestoneId, $title = null) use ($faker, $actLogType) {
            $research = Research::create([
                'student_id' => $student->id,
                'title' => $title ?? $faker->sentence(8),
                'milestone_id' => $milestoneId,
                'type_id' => 1,
            ]);
            if ($actLogType) {
                ResearchLog::create([
                    'research_id' => $research->id, 'type_id' => $actLogType->id, 'loger_id' => $student->user_id ?? 1,
                    'message' => 'Research activated', 'status' => 1,
                ]);
            }
            return $research;
        };

        $assignSupervisors = function (Research $research, $supervisorId, $coSupervisorId = null) {
            ResearchSupervisor::create(['research_id' => $research->id, 'supervisor_id' => $supervisorId, 'order' => 1]);
            if ($coSupervisorId) {
                ResearchSupervisor::create(['research_id' => $research->id, 'supervisor_id' => $coSupervisorId, 'order' => 2]);
            }
        };

        DB::beginTransaction();
        try {
            foreach ($allStaff as $currentStaff) {
                $programId = $currentStaff->program_id;
                $this->command->info("Processing staff: {$currentStaff->first_name} {$currentStaff->last_name} (ID: {$currentStaff->id})");

                // Get students, prioritizing the same program, but fall back to any student
                $students = $allStudents->where('program_id', $programId)->shuffle()->take(15);
                if ($students->count() < 15) {
                    $this->command->warn("Not enough students in program {$programId}. Taking random students from any program.");
                    $students = $allStudents->shuffle()->take(15);
                }

                $otherStaff = $allStaff->where('id', '!=', $currentStaff->id)->values();
                $kaprodiStaff = Staff::whereHas('user', function ($q) {
                    $q->role('program');
                })->where('program_id', $programId)->first();

                // Student groups
                $groupA = $students->slice(0, 3)->values();
                $groupB = $students->slice(3, 3)->values();
                $groupC = $students->slice(6, 3)->values();
                $groupD = $students->slice(9, 3)->values();
                $groupE = $students->slice(12, 3)->values();

                // 1. PRE-DEFENSE
                $preDefEvent = Event::create(['event_date' => Carbon::now()->subDays(7), 'event_type_id' => $preEventType->id, 'program_id' => $programId, 'status' => 1]);

                // Scenario A: Current staff is supervisor
                foreach ($groupA as $student) {
                    $coSupervisor = $otherStaff->random();
                    $research = $createResearch($student, $milestonePreDefDone->id, 'Bimbingan ' . $currentStaff->first_name);
                    $assignSupervisors($research, $currentStaff->id, $coSupervisor->id);
                    $applicant = EventApplicantDefense::create(['event_id' => $preDefEvent->id, 'research_id' => $research->id, 'space_id' => $spaces->random()->id, 'session_id' => $sessions->random()->id, 'publish' => 1]);
                    // Examiners cannot be supervisors
                    $examiners = $otherStaff->whereNotIn('id', [$currentStaff->id, $coSupervisor->id])->random(2);
                    DefenseExaminer::create(['event_id' => $preDefEvent->id, 'applicant_id' => $applicant->id, 'examiner_id' => $examiners->first()->id, 'order' => 1]);
                    DefenseExaminer::create(['event_id' => $preDefEvent->id, 'applicant_id' => $applicant->id, 'examiner_id' => $examiners->last()->id, 'order' => null]);
                }

                // Scenario B: Current staff is examiner
                foreach ($groupB as $student) {
                    $supervisors = $otherStaff->random(2);
                    $research = $createResearch($student, $milestonePreDefDone->id, 'Ujian dengan ' . $currentStaff->first_name);
                    $assignSupervisors($research, $supervisors->first()->id, $supervisors->last()->id);
                    $applicant = EventApplicantDefense::create(['event_id' => $preDefEvent->id, 'research_id' => $research->id, 'space_id' => $spaces->random()->id, 'session_id' => $sessions->random()->id, 'publish' => 1]);
                    $otherExaminer = $otherStaff->whereNotIn('id', [$supervisors->first()->id, $supervisors->last()->id, $currentStaff->id])->random();
                    DefenseExaminer::create(['event_id' => $preDefEvent->id, 'applicant_id' => $applicant->id, 'examiner_id' => $currentStaff->id, 'order' => 1]);
                    DefenseExaminer::create(['event_id' => $preDefEvent->id, 'applicant_id' => $applicant->id, 'examiner_id' => $otherExaminer->id, 'order' => null]);
                }

                // 2. FINAL-DEFENSE
                $finalDefEvent = Event::create(['event_date' => Carbon::now()->subDays(5), 'event_type_id' => $pubEventType->id, 'program_id' => $programId, 'status' => 1]);
                $room = FinalDefenseRoom::create(['event_id' => $finalDefEvent->id, 'space_id' => $spaces->random()->id, 'session_id' => $sessions->random()->id, 'moderator_id' => $currentStaff->id]);

                // Add current staff as an examiner in their moderated room
                FinalDefenseExaminer::create(['event_id' => $finalDefEvent->id, 'room_id' => $room->id, 'examiner_id' => $currentStaff->id]);

                foreach ($groupC as $student) {
                    $coSupervisor = $otherStaff->random();
                    $research = $createResearch($student, $milestoneFinalDefDone->id, 'Sidang Akhir ' . $currentStaff->first_name);
                    $assignSupervisors($research, $currentStaff->id, $coSupervisor->id);
                    EventApplicantFinalDefense::create(['event_id' => $finalDefEvent->id, 'research_id' => $research->id, 'room_id' => $room->id, 'publish' => 1]);
                    // Ensure other examiners in the room are not supervisors
                    $otherExaminers = $otherStaff->whereNotIn('id', [$currentStaff->id, $coSupervisor->id])->random(2);
                    FinalDefenseExaminer::create(['event_id' => $finalDefEvent->id, 'room_id' => $room->id, 'examiner_id' => $otherExaminers->first()->id]);
                    FinalDefenseExaminer::create(['event_id' => $finalDefEvent->id, 'room_id' => $room->id, 'examiner_id' => $otherExaminers->last()->id]);
                }

                // 3. APPROVALS
                // Pre-defense approval for current staff
                $researchForPreDefApproval = $createResearch($groupD->first(), $milestonePreDefSubmitted->id, 'Approval Pra-Sidang ' . $currentStaff->first_name);
                $assignSupervisors($researchForPreDefApproval, $currentStaff->id);
                DefenseApproval::create(['research_id' => $researchForPreDefApproval->id, 'defense_model_id' => $preDefenseModel->id, 'approver_id' => $currentStaff->id, 'approver_role' => $spvRole?->id, 'decision' => null, 'approval_date' => null]);

                // Final-defense approval for current staff
                $researchForFinalDefApproval = $createResearch($groupD->last(), $milestoneFinalDefSubmitted->id, 'Approval Sidang ' . $currentStaff->first_name);
                $assignSupervisors($researchForFinalDefApproval, $currentStaff->id);
                DefenseApproval::create(['research_id' => $researchForFinalDefApproval->id, 'defense_model_id' => $finalDefenseModel->id, 'approver_id' => $currentStaff->id, 'approver_role' => $spvRole?->id, 'decision' => null, 'approval_date' => null]);

                // Kaprodi approval
                if ($kaprodiStaff) {
                    $researchForKaprodi = $createResearch($groupE->first(), $milestoneFinalDefSubmitted->id, 'Menunggu Approval Kaprodi');
                    $supervisors = $otherStaff->random(2);
                    $assignSupervisors($researchForKaprodi, $supervisors->first()->id, $supervisors->last()->id);
                    DefenseApproval::create(['research_id' => $researchForKaprodi->id, 'defense_model_id' => $finalDefenseModel->id, 'approver_id' => $supervisors->first()->id, 'approver_role' => $spvRole?->id, 'decision' => 1, 'approval_date' => Carbon::now()->subDays(3)]);
                    DefenseApproval::create(['research_id' => $researchForKaprodi->id, 'defense_model_id' => $finalDefenseModel->id, 'approver_id' => $supervisors->last()->id, 'approver_role' => $spvRole?->id, 'decision' => 1, 'approval_date' => Carbon::now()->subDays(2)]);
                    DefenseApproval::create(['research_id' => $researchForKaprodi->id, 'defense_model_id' => $finalDefenseModel->id, 'approver_id' => $kaprodiStaff->id, 'approver_role' => $prgRole?->id, 'decision' => null, 'approval_date' => null]);
                }

                // 4. REVIEW
                $researchForReview = $createResearch($groupE->last(), $milestoneProposalReview->id, 'Review Proposal ' . $currentStaff->first_name);
                $assignSupervisors($researchForReview, $otherStaff->random()->id);
                if ($revLogType) {
                    ResearchLog::create(['research_id' => $researchForReview->id, 'type_id' => $revLogType->id, 'loger_id' => $groupE->last()->user_id ?? 1, 'message' => 'Submitted for review', 'status' => null]);
                }
                ResearchReview::create(['research_id' => $researchForReview->id, 'reviewer_id' => $currentStaff->id, 'decision_id' => null, 'approval_date' => null]);
                ResearchReview::create(['research_id' => $researchForReview->id, 'reviewer_id' => $otherStaff->random()->id, 'decision_id' => $approveDecision?->id, 'approval_date' => Carbon::now()->subDays(1)]);
            }

            DB::commit();
            $this->command->newLine();
            $this->command->info('═══════════════════════════════════════════');
            $this->command->info('  DUMMY DATA SEEDER COMPLETED FOR ALL STAFF');
            $this->command->info('═══════════════════════════════════════════');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Seeder failed: ' . $e->getMessage());
            $this->command->error($e->getTraceAsString());
        }
    }
}
