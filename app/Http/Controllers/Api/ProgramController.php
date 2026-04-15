<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArSys\DefenseApproval;
use App\Models\ArSys\DefenseRole;
use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantDefense;
use App\Models\ArSys\EventApplicantFinalDefense;
use App\Models\ArSys\FinalDefenseRoom;
use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchMilestone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProgramController extends Controller
{
    private function getProgramId()
    {
        $user = Auth::user();
        if (!$user || !$user->staff) return null;
        return $user->staff->program_id;
    }

    // ========================
    // Pre-Defense Scores
    // ========================

    public function preDefenseEvents(Request $request)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['data' => [], 'message' => 'Not authorized'], 200);
        }

        $events = Event::where('program_id', $programId)
            ->where('status', 1)
            ->whereHas('type', fn($q) => $q->where('code', 'PRE'))
            ->whereHas('defenseApplicantPublish')
            ->with(['type'])
            ->orderBy('event_date', 'DESC')
            ->get()
            ->map(function ($event) {
                $applicants = EventApplicantDefense::where('event_id', $event->id)
                    ->where('publish', 1)
                    ->with([
                        'defenseExaminer.defenseExaminerPresence',
                        'research.supervisor.defenseSupervisorPresence',
                    ])
                    ->get();

                $hasMissing = false;
                foreach ($applicants as $app) {
                    $supervisorIds = $app->research->supervisor->pluck('supervisor_id')->toArray();
                    foreach ($app->defenseExaminer as $examiner) {
                        // Skip examiner who is own supervisor
                        if (in_array($examiner->examiner_id, $supervisorIds)) continue;
                        $p = $examiner->defenseExaminerPresence;
                        if ($p !== null && $p->score === null) {
                            $hasMissing = true;
                            break 2;
                        }
                    }
                    foreach ($app->research->supervisor as $sup) {
                        $presence = $sup->defenseSupervisorPresence;
                        if ($presence !== null && $presence->score === null) {
                            $hasMissing = true;
                            break 2;
                        }
                    }
                }

                $formattedDate = \Carbon\Carbon::parse($event->event_date)->format('dmy');
                return [
                    'id' => $event->id,
                    'event_id_string' => sprintf('%s-%s-%s', $event->type->code ?? 'PRE', $formattedDate, $event->id),
                    'name' => $event->name,
                    'event_date' => \Carbon\Carbon::parse($event->event_date)->isoFormat('dddd, D MMM YYYY'),
                    'applicant_count' => $applicants->count(),
                    'has_missing_scores' => $hasMissing,
                ];
            });

        return response()->json(['data' => $events]);
    }

    public function preDefenseDetail($eventId)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['data' => [], 'message' => 'Not authorized'], 200);
        }

        $applicants = EventApplicantDefense::where('event_id', $eventId)
            ->where('publish', 1)
            ->whereHas('event', fn($q) => $q->where('program_id', $programId))
            ->with([
                'research.student.program',
                'research.supervisor.staff',
                'research.supervisor.defenseSupervisorPresence',
                'defenseExaminer.staff',
                'defenseExaminer.defenseExaminerPresence',
                'space', 'session',
            ])
            ->get()
            ->map(function ($app) use ($eventId) {
                $supervisors = $app->research->supervisor->map(function ($sup) {
                    $presence = $sup->defenseSupervisorPresence;
                    $score    = $presence?->score;
                    return [
                        'staff_code' => $sup->staff->code ?? 'N/A',
                        'staff_name' => trim(($sup->staff->first_name ?? '') . ' ' . ($sup->staff->last_name ?? '')),
                        'role' => ($sup->order ?? 1) <= 1 ? 'SPV' : 'Co-SPV',
                        'score' => $score,
                        'has_scored' => $score !== null,
                        'is_present' => $presence !== null,
                    ];
                });

                // Map supervisor_id => supervisor score for cross-reference
                $supervisorMap = [];
                foreach ($app->research->supervisor as $sup) {
                    $p = $sup->defenseSupervisorPresence;
                    $supervisorMap[$sup->supervisor_id] = $p?->score;
                }

                $examiners = $app->defenseExaminer->map(function ($ex) use ($supervisorMap) {
                    $presence = $ex->defenseExaminerPresence;
                    $examinerScore = $presence?->score;
                    $isOwnSupervisor = array_key_exists($ex->examiner_id, $supervisorMap);
                    $isPresent = $presence !== null;
                    // If examiner hasn't scored but is own supervisor, use supervisor score
                    $score = $examinerScore;
                    $scoredAsSpv = false;
                    if (($score === null) && $isOwnSupervisor && $supervisorMap[$ex->examiner_id] !== null) {
                        $score = $supervisorMap[$ex->examiner_id];
                        $scoredAsSpv = true;
                    }
                    return [
                        'staff_code' => $ex->staff->code ?? 'N/A',
                        'staff_name' => trim(($ex->staff->first_name ?? '') . ' ' . ($ex->staff->last_name ?? '')),
                        'score' => $score,
                        'has_scored' => $examinerScore !== null,
                        'is_own_supervisor' => $isOwnSupervisor,
                        'scored_as_spv' => $scoredAsSpv,
                        'is_present' => $isPresent,
                    ];
                });

                $student = $app->research->student;

                // Only count as missing if present but not yet scored (absent = ineligible, not missing)
                $hasMissingExaminer = $examiners->contains(fn($e) => $e['is_present'] && !$e['has_scored'] && !$e['is_own_supervisor']);
                $hasMissingSupervisor = $supervisors->contains(fn($s) => $s['is_present'] && !$s['has_scored']);

                return [
                    'id' => $app->id,
                    'student_nim' => $student?->nim ?? 'N/A',
                    'student_name' => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                    'research_title' => $app->research->title ?? 'No Title',
                    'room_name' => $app->space?->code ?? 'N/A',
                    'session_time' => $app->session?->time ?? 'N/A',
                    'supervisors' => $supervisors,
                    'examiners' => $examiners,
                    'has_missing_scores' => $hasMissingExaminer || $hasMissingSupervisor,
                ];
            });

        return response()->json(['data' => $applicants]);
    }

    // ========================
    // Final-Defense Scores
    // ========================

    public function finalDefenseEvents(Request $request)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['data' => [], 'message' => 'Not authorized'], 200);
        }

        $events = Event::where('program_id', $programId)
            ->where('status', 1)
            ->whereHas('type', fn($q) => $q->where('code', 'PUB'))
            ->whereHas('finaldefenseApplicantPublish')
            ->with(['type'])
            ->orderBy('event_date', 'DESC')
            ->get()
            ->map(function ($event) {
                $rooms = FinalDefenseRoom::where('event_id', $event->id)
                    ->with([
                        'examiner.presence',
                        'applicant.research.supervisor.finaldefenseSupervisorPresence',
                    ])
                    ->get();

                $hasMissing = false;
                foreach ($rooms as $room) {
                    foreach ($room->applicant as $app) {
                        if (!$app->research) continue;
                        $supervisorIds = $app->research->supervisor->pluck('supervisor_id')->toArray();
                        // Check examiner scores per applicant
                        foreach ($room->examiner as $ex) {
                            if (in_array($ex->examiner_id, $supervisorIds)) continue;
                            $presence = $ex->finaldefenseExaminerPresence
                                ->where('applicant_id', $app->id)
                                ->first();
                            if ($presence !== null && $presence->score === null) {
                                $hasMissing = true;
                                break 3;
                            }
                        }
                        // Check supervisor scores
                        foreach ($app->research->supervisor as $sup) {
                            $presence = $sup->finaldefenseSupervisorPresence;
                            if ($presence !== null && $presence->score === null) {
                                $hasMissing = true;
                                break 3;
                            }
                        }
                    }
                }

                $formattedDate = \Carbon\Carbon::parse($event->event_date)->format('dmy');
                return [
                    'id' => $event->id,
                    'event_id_string' => sprintf('PUB-%s-%s', $formattedDate, $event->id),
                    'name' => $event->name,
                    'event_date' => \Carbon\Carbon::parse($event->event_date)->isoFormat('dddd, D MMM YYYY'),
                    'room_count' => $rooms->count(),
                    'has_missing_scores' => $hasMissing,
                ];
            });

        return response()->json(['data' => $events]);
    }

    public function finalDefenseRooms($eventId)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['data' => []], 200);
        }

        $rooms = FinalDefenseRoom::where('event_id', $eventId)
            ->whereHas('event', fn($q) => $q->where('program_id', $programId))
            ->with([
                'space', 'session', 'moderator',
                'examiner.staff',
                'examiner.finaldefenseExaminerPresence',
                'applicant.research.supervisor.finaldefenseSupervisorPresence',
            ])
            ->get()
            ->map(function ($room) {
                $hasMissing = false;

                foreach ($room->applicant as $app) {
                    if (!$app->research) continue;
                    $supervisorIds = $app->research->supervisor->pluck('supervisor_id')->toArray();
                    foreach ($room->examiner as $ex) {
                        // Skip examiner who is own supervisor
                        if (in_array($ex->examiner_id, $supervisorIds)) continue;
                        $presence = $ex->finaldefenseExaminerPresence
                            ->where('applicant_id', $app->id)
                            ->first();
                        if ($presence !== null && $presence->score === null) {
                            $hasMissing = true;
                            break 2;
                        }
                    }
                    foreach ($app->research->supervisor as $sup) {
                        $p = $sup->finaldefenseSupervisorPresence;
                        if ($p !== null && $p->score === null) {
                            $hasMissing = true;
                            break 2;
                        }
                    }
                }

                return [
                    'id' => $room->id,
                    'room_name' => $room->space?->code ?? 'N/A',
                    'session_time' => $room->session?->time ?? 'N/A',
                    'moderator_name' => $room->moderator ? trim($room->moderator->first_name . ' ' . $room->moderator->last_name) : null,
                    'applicant_count' => $room->applicant->count(),
                    'has_missing_scores' => $hasMissing,
                ];
            });

        return response()->json(['data' => $rooms]);
    }

    public function finalDefenseRoomDetail($eventId, $roomId)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['data' => []], 200);
        }

        $room = FinalDefenseRoom::where('id', $roomId)
            ->where('event_id', $eventId)
            ->whereHas('event', fn($q) => $q->where('program_id', $programId))
            ->with([
                'space', 'session', 'moderator',
                'examiner.staff',
                'examiner.finaldefenseExaminerPresence',
                'applicant.research.student.program',
                'applicant.research.supervisor.staff',
                'applicant.research.supervisor.finaldefenseSupervisorPresence',
            ])
            ->first();

        if (!$room) {
            return response()->json(['data' => null, 'message' => 'Room not found'], 404);
        }

        $examiners = $room->examiner->map(function ($ex) {
            return [
                'id' => $ex->id,
                'staff_code' => $ex->staff->code ?? 'N/A',
                'staff_name' => trim(($ex->staff->first_name ?? '') . ' ' . ($ex->staff->last_name ?? '')),
            ];
        });

        $applicants = $room->applicant->map(function ($app) use ($room) {
            $student = $app->research->student;

            // Map supervisor_id => supervisor score for cross-reference
            $supervisorMap = [];
            foreach ($app->research->supervisor as $sup) {
                $p = $sup->finaldefenseSupervisorPresence;
                $supervisorMap[$sup->supervisor_id] = ($p && $p->score !== null) ? $p->score : null;
            }

            $examinerScores = $room->examiner->map(function ($ex) use ($app, $supervisorMap) {
                $presence = $ex->finaldefenseExaminerPresence
                    ->where('applicant_id', $app->id)
                    ->first();
                $isPresent = $presence !== null;
                $examinerScore = ($presence && $presence->score !== null && $presence->score != -1) ? $presence->score : null;
                $isOwnSupervisor = array_key_exists($ex->examiner_id, $supervisorMap);
                // If examiner hasn't scored but is own supervisor, use supervisor score
                $score = $examinerScore;
                $scoredAsSpv = false;
                if ($score === null && $isOwnSupervisor && $supervisorMap[$ex->examiner_id] !== null) {
                    $score = $supervisorMap[$ex->examiner_id];
                    $scoredAsSpv = true;
                }
                return [
                    'examiner_code' => $ex->staff->code ?? 'N/A',
                    'examiner_name' => trim(($ex->staff->first_name ?? '') . ' ' . ($ex->staff->last_name ?? '')),
                    'score' => $score,
                    'has_scored' => $examinerScore !== null,
                    'is_own_supervisor' => $isOwnSupervisor,
                    'scored_as_spv' => $scoredAsSpv,
                    'is_present' => $isPresent,
                ];
            });

            $supervisorScores = $app->research->supervisor->map(function ($sup) {
                $presence = $sup->finaldefenseSupervisorPresence;
                return [
                    'staff_code' => $sup->staff->code ?? 'N/A',
                    'staff_name' => trim(($sup->staff->first_name ?? '') . ' ' . ($sup->staff->last_name ?? '')),
                    'role' => ($sup->order ?? 1) <= 1 ? 'SPV' : 'Co-SPV',
                    'score' => $presence?->score,
                    'has_scored' => $presence && $presence->score !== null,
                    'is_present' => $presence !== null,
                ];
            });

            // Only count as missing if present but not yet scored (absent = ineligible, not missing)
            $hasMissingExaminer = $examinerScores->contains(fn($e) => $e['is_present'] && !$e['has_scored'] && !$e['is_own_supervisor']);
            $hasMissingSupervisor = $supervisorScores->contains(fn($s) => $s['is_present'] && !$s['has_scored']);

            return [
                'id' => $app->id,
                'student_nim' => $student?->nim ?? 'N/A',
                'student_name' => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                'research_title' => $app->research->title ?? 'No Title',
                'examiner_scores' => $examinerScores,
                'supervisor_scores' => $supervisorScores,
                'has_missing_scores' => $hasMissingExaminer || $hasMissingSupervisor,
            ];
        });

        return response()->json([
            'data' => [
                'room_name' => $room->space?->code ?? 'N/A',
                'session_time' => $room->session?->time ?? 'N/A',
                'moderator_name' => $room->moderator ? trim($room->moderator->first_name . ' ' . $room->moderator->last_name) : null,
                'examiners' => $examiners,
                'applicants' => $applicants,
            ],
        ]);
    }

    // ========================
    // Final-Defense Approval
    // ========================

    public function approvalList()
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => []], 200);
        }

        $staffId = $user->staff->id;
        $prgRoleId = DefenseRole::where('code', 'PRG')->first()?->id;

        if (!$prgRoleId) {
            return response()->json(['data' => [], 'message' => 'PRG role not found'], 200);
        }

        $approvals = DefenseApproval::where('approver_id', $staffId)
            ->where('approver_role', $prgRoleId)
            ->whereHas('defenseModel', fn($q) => $q->where('code', 'PUB'))
            ->with([
                'research.student.program',
                'research.supervisor.staff',
                'defenseModel',
            ])
            ->orderBy('created_at', 'DESC')
            ->get()
            ->map(function ($approval) {
                $research = $approval->research;
                $student = $research?->student;

                // Check if all approvals for this research are complete
                $totalApprovals = $research ? $research->finaldefenseApproval->count() : 0;
                $approvedCount = $research ? $research->finaldefenseApproved->count() : 0;
                $isAllApproved = $totalApprovals > 0 && $totalApprovals === $approvedCount;

                return [
                    'id' => $approval->id,
                    'research_id' => $approval->research_id,
                    'student_nim' => $student?->nim ?? 'N/A',
                    'student_name' => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                    'research_title' => $research?->title ?? 'No Title',
                    'defense_model' => $approval->defenseModel?->name ?? 'Final Defense',
                    'decision' => $approval->decision,
                    'approval_date' => $approval->approval_date,
                    'is_approved' => $approval->decision !== null,
                    'is_all_approved' => $isAllApproved,
                ];
            });

        return response()->json(['data' => $approvals]);
    }

    public function approvalDetail($id)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => null], 200);
        }

        $approval = DefenseApproval::with(['research.student.program'])->findOrFail($id);
        $research = $approval->research;
        $student = $research?->student;

        // Get all approvals for this research's final defense
        $allApprovals = DefenseApproval::where('research_id', $approval->research_id)
            ->whereHas('defenseModel', fn($q) => $q->where('code', 'PUB'))
            ->with(['staff', 'defenseRole'])
            ->get()
            ->map(function ($a) use ($research) {
                $roleName = $a->defenseRole?->description ?? 'Approver';
                // Distinguish Supervisor from Co-Supervisor using order column
                if ($a->defenseRole?->code === 'SPV' && $research) {
                    $sup = $research->supervisor()->where('supervisor_id', $a->approver_id)->first();
                    if ($sup && $sup->order > 1) {
                        $roleName = 'Co-Supervisor';
                    }
                }
                return [
                    'id' => $a->id,
                    'role_name' => $roleName,
                    'staff_name' => $a->staff ? trim($a->staff->first_name . ' ' . $a->staff->last_name) : 'N/A',
                    'staff_code' => $a->staff?->code ?? 'N/A',
                    'decision' => $a->decision,
                    'approval_date' => $a->approval_date,
                    'is_approved' => $a->decision !== null,
                ];
            });

        $supervisors = $research?->supervisor->map(function ($sup) {
            return [
                'name' => trim(($sup->staff->first_name ?? '') . ' ' . ($sup->staff->last_name ?? '')),
                'code' => $sup->staff->code ?? 'N/A',
            ];
        }) ?? collect();

        $defenseModelName = $approval->defenseModel?->name ?? 'Final Defense';

        return response()->json([
            'data' => [
                'approval_id' => $approval->id,
                'student_nim' => $student?->nim ?? 'N/A',
                'student_name' => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                'research_title' => $research?->title ?? 'No Title',
                'defense_model' => $defenseModelName,
                'supervisors' => $supervisors,
                'all_approvals' => $allApprovals,
                'my_decision' => $approval->decision,
                'is_approved' => $approval->decision !== null,
                'can_approve' => $approval->decision === null && $approval->approver_id === $user->staff->id,
            ],
        ]);
    }

    public function approve($id)
    {
        $user = Auth::user();
        $approval = DefenseApproval::findOrFail($id);

        if ($approval->approver_id !== $user->staff->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $approval->update([
            'decision' => 1,
            'approval_date' => Carbon::now(),
        ]);

        // Check if all approvals are done for this research's final defense
        $research = Research::find($approval->research_id);
        $totalApprovals = $research->finaldefenseApproval->count();
        $approvedCount = $research->finaldefenseApproved->count();

        if ($totalApprovals == $approvedCount) {
            $milestone = ResearchMilestone::where('code', 'Final-defense')
                ->where('phase', 'Approved')
                ->first();
            if ($milestone) {
                $research->update(['milestone_id' => $milestone->id]);
            }
        } else {
            $milestone = ResearchMilestone::where('code', 'Final-defense')
                ->where('phase', 'Submitted')
                ->first();
            if ($milestone) {
                $research->update(['milestone_id' => $milestone->id]);
            }
        }

        return response()->json(['message' => 'Approved successfully']);
    }
}
