<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\ArSys\DefenseModel;
use App\Models\ArSys\DefenseScoreGuide;
use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantFinalDefense;
use App\Models\ArSys\FinalDefenseExaminer;
use App\Models\ArSys\FinalDefenseExaminerPresence;
use App\Models\ArSys\FinalDefenseRoom;
use App\Models\ArSys\FinalDefenseSupervisorPresence;
use App\Models\ArSys\ResearchSupervisor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinalDefenseController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => [], 'message' => 'User is not a staff member.'], 200);
        }
        $staffId = $user->staff->id;

        $events = Event::where('status', 1)
            ->where('event_type_id', 2)
            ->whereHas('finaldefenseApplicantPublish', function($query) use ($staffId) {
                $query->whereHas('research', function($query) use ($staffId) {
                    $query->whereHas('supervisor', function($query) use ($staffId) {
                        $query->where('supervisor_id', $staffId);
                    });
                })
                ->orWhereHas('room', function($query) use ($staffId) {
                    $query->whereHas('examiner', function($query) use ($staffId) {
                        $query->where('examiner_id', $staffId);
                    });
                });
            })
            ->with('program')
            ->orderBy('event_date', 'DESC')
            ->get();

        $transformedData = $events->map(function ($event) {
            $eventDate = new \DateTime($event->event_date);
            $formattedDate = $eventDate->format('dmy');
            return [
                'id' => $event->id,
                'name' => $event->name,
                'event_date' => $event->event_date,
                'event_code' => 'PUB-' . $formattedDate . '-' . $event->id,
                'program_code' => $event->program->code ?? null,
                'program_abbrev' => $event->program->abbrev ?? null,
            ];
        });

        return response()->json(['data' => $transformedData]);
    }

    public function getRooms(Request $request, $eventId)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => [], 'message' => 'User is not a staff member.'], 200);
        }
        $staffId = $user->staff->id;

        $rooms = FinalDefenseRoom::where('event_id', $eventId)
            ->where(function ($query) use ($staffId) {
                $query->whereHas('examiner', function ($q) use ($staffId) {
                    $q->where('examiner_id', $staffId);
                })->orWhereHas('applicant.research.supervisor', function ($q) use ($staffId) {
                    $q->where('supervisor_id', $staffId);
                });
            })
            ->with([
                'space',
                'session',
                'moderator',
                'examiner.staff',
                'applicant.research.student.program',
                'applicant.research.supervisor.staff',
                'applicant.research.supervisor.finaldefenseSupervisorPresence',
                'applicant.research.milestone',
            ])
            ->get();

        $allApplicantIds = $rooms->flatMap(fn($room) => $room->applicant->pluck('id'))->unique();
        $allExaminerIds = $rooms->flatMap(fn($room) => $room->examiner->pluck('id'))->unique();

        $allPresenceRecords = FinalDefenseExaminerPresence::whereIn('seminar_examiner_id', $allExaminerIds)
            ->get()
            ->groupBy('seminar_examiner_id');

        $myExaminerEntry = FinalDefenseExaminer::where('event_id', $eventId)
            ->where('examiner_id', $staffId)
            ->first();
        $myExaminerScores = collect();
        if ($myExaminerEntry) {
            $myExaminerScores = FinalDefenseExaminerPresence::where('seminar_examiner_id', $myExaminerEntry->id)
                ->whereIn('applicant_id', $allApplicantIds)
                ->get()
                ->keyBy('applicant_id');
        }

        $transformedData = $rooms->map(function ($room) use ($staffId, $allPresenceRecords, $myExaminerScores) {
            $isExaminer = $room->examiner->contains('examiner_id', $staffId);
            $isModerator = $room->moderator_id == $staffId;

            $supervisedApplicantIds = $room->applicant->filter(function ($applicant) use ($staffId) {
                return $applicant->research->supervisor->contains('supervisor_id', $staffId);
            })->pluck('id');

            return [
                'id' => $room->id,
                'room_name' => $room->space->code ?? 'N/A',
                'session_time' => $room->session->time ?? 'N/A',
                'is_examiner_or_moderator' => $isExaminer || $isModerator,
                'is_current_user_moderator' => $isModerator,
                'supervised_applicant_ids' => $supervisedApplicantIds,
                'moderator' => $room->moderator ? [
                    'id' => $room->moderator->id,
                    'name' => trim($room->moderator->first_name . ' ' . $room->moderator->last_name),
                    'code' => $room->moderator->code ?? 'N/A',
                ] : null,
                'examiners' => $room->examiner->map(function ($examiner) use ($allPresenceRecords) {
                    $staff = $examiner->staff;
                    return [
                        'id' => $examiner->id,
                        'staff_id' => $staff->id ?? null,
                        'name' => $staff ? trim($staff->first_name . ' ' . $staff->last_name) : 'Unknown',
                        'code' => $staff->code ?? 'N/A',
                        'is_present' => $allPresenceRecords->has($examiner->id),
                    ];
                }),
                'applicants' => $room->applicant->map(function ($applicant) use ($staffId, $myExaminerScores) {
                    $student = $applicant->research->student;
                    $myExaminerScoreRecord = $myExaminerScores->get($applicant->id);

                    $mySupervisorInfo = $applicant->research->supervisor->firstWhere('supervisor_id', $staffId);
                    $mySupervisorScoreRecord = $mySupervisorInfo?->finaldefenseSupervisorPresence;

                    $milestoneCode = $applicant->research->milestone?->code;
                    $milestonePhase = $applicant->research->milestone?->phase;
                    $milestoneParts = [];
                    if ($milestoneCode) {
                        $milestoneParts[] = $milestoneCode;
                    }
                    if ($milestonePhase) {
                        $milestoneParts[] = $milestonePhase;
                    }
                    $milestoneName = !empty($milestoneParts) ? implode(' | ', $milestoneParts) : 'N/A';

                    return [
                        'id' => $applicant->id,
                        'presence_id' => $myExaminerScoreRecord?->id,
                        'research_supervisor_id' => $mySupervisorInfo?->id,
                        'student_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                        'student_nim' => $student->nim ?? 'N/A',
                        'milestone_name' => $milestoneName,
                        'my_examiner_score' => $myExaminerScoreRecord?->score,
                        'my_examiner_remark' => $myExaminerScoreRecord?->remark,
                        'my_supervisor_score' => $mySupervisorScoreRecord?->score,
                        'my_supervisor_remark' => $mySupervisorScoreRecord?->remark,
                    ];
                }),
            ];
        });

        return response()->json(['data' => $transformedData]);
    }

    public function switchModerator(Request $request, $roomId)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $room = FinalDefenseRoom::find($roomId);
        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }

        if ($room->moderator_id !== $user->staff->id) {
            return response()->json(['message' => 'Only the current moderator can switch roles.'], 403);
        }

        $validated = $request->validate([
            'new_moderator_id' => 'required|integer',
        ]);

        $newModeratorId = $validated['new_moderator_id'];

        $isExaminer = FinalDefenseExaminer::where('event_id', $room->event_id)
            ->where('examiner_id', $newModeratorId)
            ->exists();

        if (!$isExaminer) {
            return response()->json(['message' => 'The selected staff is not a valid examiner for this event.'], 422);
        }

        $room->update(['moderator_id' => $newModeratorId]);

        return response()->json(['message' => 'Moderator switched successfully.']);
    }

    public function toggleExaminerPresence(Request $request, $roomId, $examinerId)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $room = FinalDefenseRoom::with('applicant.research.supervisor')->find($roomId);
        if (!$room || $room->moderator_id !== $user->staff->id) {
            return response()->json(['message' => 'Only the moderator of this room can update presence.'], 403);
        }

        $examiner = FinalDefenseExaminer::find($examinerId);
        if (!$examiner) {
            return response()->json(['message' => 'Examiner not found.'], 404);
        }
        $examinerStaffId = $examiner->examiner_id;

        $existingPresence = FinalDefenseExaminerPresence::where('seminar_examiner_id', $examinerId)
            ->where('room_id', $roomId)
            ->exists();

        DB::beginTransaction();
        try {
            if ($existingPresence) {
                FinalDefenseExaminerPresence::where('seminar_examiner_id', $examinerId)
                    ->where('room_id', $roomId)
                    ->delete();
                $message = 'Presence removed.';
            } else {
                $presenceData = [];
                $pubDefenseModelId = DefenseModel::where('code', 'PUB')->first()->id;

                foreach ($room->applicant as $applicant) {
                    $isSupervisor = $applicant->research->supervisor->contains('supervisor_id', $examinerStaffId);

                    $presenceData[] = [
                        'event_id' => $room->event_id,
                        'room_id' => $roomId,
                        'seminar_examiner_id' => $examinerId,
                        'applicant_id' => $applicant->id,
                        'defense_model_id' => $pubDefenseModelId,
                        'score' => $isSupervisor ? -1 : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if (!empty($presenceData)) {
                    FinalDefenseExaminerPresence::insert($presenceData);
                }
                $message = 'Presence marked.';
            }
            DB::commit();
            return response()->json(['message' => $message]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Database error: ' . $e->getMessage()], 500);
        }
    }

    public function submitExaminerScore(Request $request, $presenceId)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'score' => 'required|numeric|min:1|max:400',
            'remark' => 'nullable|string',
        ]);

        $presence = FinalDefenseExaminerPresence::find($presenceId);
        if (!$presence) {
            return response()->json(['message' => 'Presence record not found.'], 404);
        }

        $examiner = FinalDefenseExaminer::find($presence->seminar_examiner_id);
        if (!$examiner || $examiner->examiner_id !== $user->staff->id) {
            return response()->json(['message' => 'You are not authorized to score this applicant.'], 403);
        }

        $presence->update([
            'score' => $validated['score'],
            'remark' => $validated['remark'],
        ]);

        return response()->json(['message' => 'Score submitted successfully.']);
    }

    public function submitSupervisorScore(Request $request, $supervisorId)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'score' => 'required|numeric|min:1|max:400',
            'remark' => 'nullable|string',
        ]);

        $supervisor = ResearchSupervisor::find($supervisorId);
        if (!$supervisor || $supervisor->supervisor_id !== $user->staff->id) {
            return response()->json(['message' => 'You are not authorized to score this student.'], 403);
        }

        FinalDefenseSupervisorPresence::updateOrCreate(
            ['research_supervisor_id' => $supervisorId],
            [
                'score' => $validated['score'],
                'remark' => $validated['remark'],
            ]
        );

        return response()->json(['message' => 'Score submitted successfully.']);
    }

    public function getScoreGuide()
    {
        $scoreGuide = DefenseScoreGuide::orderBy('sequence', 'ASC')->get();
        return response()->json($scoreGuide);
    }

    public function getRoomDetail(Request $request, $roomId)
    {
        // This can be deprecated or used for a different purpose later
        // For now, we leave it as is.
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => [], 'message' => 'User is not a staff member.'], 200);
        }
        $staffId = $user->staff->id;

        $room = FinalDefenseRoom::with([
            'space',
            'session',
            'moderator.staff',
            'examiner.staff',
            'examiner.finalDefenseExaminerPresence',
            'applicant.research.student.program',
            'applicant.research.supervisor.staff',
        ])->find($roomId);

        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }

        $isModerator = $room->moderator_id == $staffId;

        $examiners = $room->examiner->map(function ($examiner) {
            $staff = $examiner->staff;
            return [
                'id' => $examiner->id,
                'name' => $staff ? trim($staff->first_name . ' ' . $staff->last_name) : 'Unknown Examiner',
                'code' => $staff->code ?? 'N/A',
                'is_present' => $examiner->finalDefenseExaminerPresence->isNotEmpty(),
            ];
        });

        $applicants = $room->applicant->map(function ($applicant) {
            $student = $applicant->research->student;
            return [
                'id' => $applicant->id,
                'student_name' => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'student_nim' => $student->nim ?? 'N/A',
                'program_code' => $student->program->code ?? 'N/A',
                'research_title' => $applicant->research->title,
            ];
        });

        $data = [
            'room' => [
                'name' => $room->space->code ?? 'N/A',
                'session_time' => $room->session->time ?? 'N/A',
                'moderator' => $room->moderator ? [
                    'name' => trim($room->moderator->first_name . ' ' . $room->moderator->last_name),
                    'code' => $room->moderator->code ?? 'N/A',
                ] : null,
                'examiners' => $examiners,
                'applicants' => $applicants,
            ],
            'is_moderator' => $isModerator,
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }
}
