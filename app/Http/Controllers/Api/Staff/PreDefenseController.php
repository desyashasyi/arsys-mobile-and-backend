<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantDefense;
use App\Models\ArSys\DefenseExaminer;
use App\Models\ArSys\DefenseExaminerPresence;
use App\Models\ArSys\DefenseScoreGuide;
use App\Models\ArSys\Staff;
use App\Models\ArSys\DefenseSupervisorPresence;
use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchLog;
use App\Models\ArSys\ResearchLogType;
use App\Models\ArSys\ResearchMilestone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PreDefenseController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->staff) {
            return response()->json(['data' => [], 'message' => 'User is not a staff member.'], 200);
        }
        $staffId = $user->staff->id;

        $events = Event::where('status', 1)
            ->whereHas('type', function ($q) {
                $q->where('code', 'PRE');
            })
            ->whereHas('defenseApplicant', function ($q) use ($staffId) {
                $q->whereHas('research.supervisor', function ($subQuery) use ($staffId) {
                    $subQuery->where('supervisor_id', $staffId);
                })
                ->orWhereHas('defenseExaminer', function ($subQuery) use ($staffId) {
                    $subQuery->where('examiner_id', $staffId);
                });
            })
            ->with(['type', 'program', 'defenseApplicant.research.supervisor.staff', 'defenseApplicant.session'])
            ->orderBy('event_date', 'DESC')
            ->paginate($request->get('limit', 15));

        $transformedData = $events->getCollection()->map(function ($event) {
            $supervisorCodes = $event->defenseApplicant->flatMap(function ($applicant) {
                return $applicant->research->supervisor->pluck('staff.code');
            })->unique()->implode(', ');

            return [
                'id' => $event->id,
                'event_id_string' => sprintf('%s-%s-%s', $event->type->code ?? 'EVT', \Carbon\Carbon::parse($event->event_date)->format('dmy'), $event->id),
                'event_date' => \Carbon\Carbon::parse($event->event_date)->isoFormat('dddd, D MMM YYYY'),
                'program_code' => $event->program->code ?? '',
                'program_abbrev' => $event->program->abbrev ?? '',
                'supervisor_codes' => $supervisorCodes,
                'session' => $event->defenseApplicant->first()->session->name ?? 'N/A',
            ];
        });

        return response()->json([
            'data' => $transformedData,
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
        ]);
    }

    public function getParticipants($id)
    {
        $user = Auth::user();
        $staffId = $user->staff->id;

        $participants = EventApplicantDefense::where('event_id', $id)
            ->where(function ($query) use ($staffId) {
                $query->whereHas('research', function($q) use ($staffId) {
                    $q->whereHas('supervisor', function($sq) use ($staffId) {
                        $sq->where('supervisor_id', $staffId);
                    });
                })
                ->orWhereHas('defenseExaminer', function ($subQuery) use ($staffId) {
                    $subQuery->where('examiner_id', $staffId);
                });
            })
            ->with(['research.student.program', 'room', 'session', 'research.milestone'])
            ->get();

        $transformedData = $participants->map(function ($participant) {
            if (!$participant->research || !$participant->research->student) return null;

            $milestoneCode = $participant->research->milestone?->code;
            $milestonePhase = $participant->research->milestone?->phase;
            $milestoneParts = [];
            if ($milestoneCode) {
                $milestoneParts[] = $milestoneCode;
            }
            if ($milestonePhase) {
                $milestoneParts[] = $milestonePhase;
            }
            $milestoneName = !empty($milestoneParts) ? implode(' | ', $milestoneParts) : 'N/A';

            return [
                'id' => (int) $participant->id,
                'student_name' => trim(($participant->research->student->first_name ?? '') . ' ' . ($participant->research->student->last_name ?? '')),
                'student_nim' => $participant->research->student->nim ?? 'N/A',
                'program_code' => $participant->research->student->program->code ?? 'N/A',
                'research_title' => $participant->research->title,
                'milestone_name' => $milestoneName,
                'room_name' => $participant->space->code ?? 'N/A',
                'session_time' => $participant->session->time ?? 'N/A',
            ];
        })->filter();

        return response()->json(['data' => $transformedData->values()]);
    }

    public function getParticipantDetail($id)
    {
        $user = Auth::user();
        $staffId = $user->staff->id;

        $participant = EventApplicantDefense::with([
            'research.student.program',
            'research.supervisor.staff',
            'research.supervisor.defenseSupervisorPresence',
            'defenseExaminer.staff',
            'defenseExaminer.defenseExaminerPresence',
            'space',
            'session',
            'research.milestone',
        ])->find($id);

        if (!$participant) {
            return response()->json(['success' => false, 'message' => 'Participant not found'], 404);
        }

        $isSupervisor = $participant->research->supervisor->contains('supervisor_id', $staffId);
        $examiner = $participant->defenseExaminer->where('examiner_id', $staffId)->first();
        $isExaminer = $examiner ? true : false;
        $isExaminerPresent = $isExaminer && $examiner->defenseExaminerPresence;

        $mySupervisorScore = null;
        $mySupervisorRemark = null;
        $myExaminerScore = null;
        $myExaminerRemark = null;

        if ($isSupervisor) {
            $supervisor = $participant->research->supervisor->where('supervisor_id', $staffId)->first();
            $mySupervisorScore = $supervisor->defenseSupervisorPresence?->score ?? null;
            $mySupervisorRemark = $supervisor->defenseSupervisorPresence?->remark ?? null;
        }
        if ($isExaminerPresent) {
            $myExaminerScore = $examiner->defenseExaminerPresence->score ?? null;
            $myExaminerRemark = $examiner->defenseExaminerPresence->remark ?? null;
        }

        $supervisors = $participant->research->supervisor->map(function ($supervisor) {
            $staff = $supervisor->staff;
            $score = $supervisor->defenseSupervisorPresence?->score ?? null;
            return [
                'name' => $staff ? trim($staff->first_name . ' ' . $staff->last_name) : 'Unknown Supervisor',
                'code' => $staff->code ?? 'N/A',
                'score' => $score,
                'score_color' => is_null($score) ? 'danger' : 'success',
            ];
        });

        $examiners = $participant->defenseExaminer->map(function ($examiner) {
            $staff = $examiner->staff;
            $score = $examiner->defenseExaminerPresence?->score ?? null;
            return [
                'id' => $examiner->id,
                'name' => $staff ? trim($staff->first_name . ' ' . $staff->last_name) : 'Unknown Examiner',
                'code' => $staff->code ?? 'N/A',
                'is_present' => $examiner->defenseExaminerPresence ? true : false,
                'score' => $score,
                'score_color' => is_null($score) ? 'danger' : 'success',
            ];
        });

        $student = $participant->research->student;
        $milestoneCode = $participant->research->milestone?->code;
        $milestonePhase = $participant->research->milestone?->phase;
        $milestoneParts = [];
        if ($milestoneCode) {
            $milestoneParts[] = $milestoneCode;
        }
        if ($milestonePhase) {
            $milestoneParts[] = $milestonePhase;
        }
        $milestoneName = !empty($milestoneParts) ? implode(' | ', $milestoneParts) : 'N/A';

        $data = [
            'participant' => [
                'id' => $participant->id,
                'room_name' => $participant->space->code ?? 'N/A',
                'session_time' => $participant->session->time ?? 'N/A',
                'research' => [
                    'title' => $participant->research->title,
                    'student' => [
                        'first_name' => $student->first_name,
                        'last_name' => $student->last_name,
                        'number' => $student->number,
                        'program_code' => $student->program->code ?? 'N/A',
                    ],
                    'supervisor' => $supervisors,
                    'milestone_name' => $milestoneName,
                ],
                'defense_examiner' => $examiners,
            ],
            'is_supervisor' => $isSupervisor,
            'is_examiner' => $isExaminer,
            'is_examiner_present' => $isExaminerPresent,
            'my_supervisor_score' => $mySupervisorScore,
            'my_supervisor_remark' => $mySupervisorRemark,
            'my_examiner_score' => $myExaminerScore,
            'my_examiner_remark' => $myExaminerRemark,
            'my_score_color' => (is_null($mySupervisorScore) && is_null($myExaminerScore)) ? 'danger' : 'success',
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function toggleExaminerPresence(Request $request, $examinerId)
    {
        $examiner = DefenseExaminer::with('defenseExaminerPresence')->find($examinerId);
        if (!$examiner) {
            return response()->json(['success' => false, 'message' => 'Examiner not found.'], 404);
        }

        try {
            if ($examiner->defenseExaminerPresence) {
                $examiner->defenseExaminerPresence->delete();
                return response()->json(['success' => true, 'message' => 'Presence removed.']);
            }

            $examinerPresenceCount = DefenseExaminer::where('applicant_id', $examiner->applicant_id)
                ->has('defenseExaminerPresence')
                ->count();

            if ($examinerPresenceCount >= 3) {
                return response()->json(['success' => false, 'message' => 'The maximum number of examiners has been reached.'], 409);
            }

            DefenseExaminerPresence::create([
                'defense_examiner_id' => $examinerId,
                'event_id' => $examiner->event_id,
                'examiner_id' => $examiner->examiner_id,
            ]);

            $research = Research::with('supervisor.staff', 'supervisor.defenseSupervisorPresence', 'DEFDONE')
                ->find($examiner->defenseApplicant?->research_id);

            if ($research && $research->supervisor) {
                foreach ($research->supervisor as $supervisor) {
                    if (is_null($supervisor->defenseSupervisorPresence) && $supervisor->staff) {
                        DefenseSupervisorPresence::create([
                            'research_supervisor_id' => $supervisor->id,
                            'event_id' => $examiner->event_id,
                            'supervisor_id' => $supervisor->staff->id,
                            'research_id' => $research->id,
                        ]);
                    }
                }

                $doneMilestone = ResearchMilestone::where('code', 'Pre-defense')->where('phase', 'Done')->first();
                if ($doneMilestone) {
                    $research->update(['milestone_id' => $doneMilestone->id]);
                }

                $defDoneLogType = ResearchLogType::where('code', 'DEFDONE')->first();
                if (is_null($research->DEFDONE) && $defDoneLogType) {
                    ResearchLog::create([
                        'research_id' => $research->id,
                        'type_id' => $defDoneLogType->id,
                        'loger_id' => Auth::user()->id,
                        'message' => $defDoneLogType->description,
                        'status' => 1,
                    ]);
                }
            }

            return response()->json(['success' => true, 'message' => 'Presence marked.']);
        } catch (\Exception $e) {
            Log::error('toggleExaminerPresence error: ' . $e->getMessage(), [
                'examiner_id' => $examinerId,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    public function searchStaff(Request $request)
    {
        $query = $request->input('query');
        if (empty($query)) {
            return response()->json([]);
        }

        $staff = Staff::where('code', 'LIKE', "%{$query}%")
                      ->orWhere('first_name', 'LIKE', "%{$query}%")
                      ->orWhere('last_name', 'LIKE', "%{$query}%")
                      ->limit(10)
                      ->get(['id', 'code', 'first_name', 'last_name']);

        return response()->json($staff);
    }

    public function addExaminer(Request $request, $participantId)
    {
        $request->validate([
            'staff_id' => 'required|integer|exists:arsys_staff,id',
        ]);

        $participant = EventApplicantDefense::find($participantId);
        if (!$participant) {
            return response()->json(['success' => false, 'message' => 'Participant not found.'], 404);
        }

        $isExaminer = DefenseExaminer::where('applicant_id', $participantId)
                                     ->where('examiner_id', $request->staff_id)
                                     ->exists();

        if ($isExaminer) {
            return response()->json(['success' => false, 'message' => 'This staff member is already an examiner for this applicant.'], 409);
        }

        DefenseExaminer::create([
            'applicant_id' => $participantId,
            'examiner_id' => $request->staff_id,
            'event_id' => $participant->event_id,
            'additional' => 1,
        ]);

        return response()->json(['success' => true, 'message' => 'Examiner added successfully.']);
    }

    public function getScoreGuide()
    {
        $scoreGuide = DefenseScoreGuide::orderBy('sequence', 'ASC')->get();
        return response()->json($scoreGuide);
    }

    public function submitScore(Request $request, $participantId)
    {
        $user = Auth::user();
        $staffId = $user->staff->id;

        $participant = EventApplicantDefense::find($participantId);
        if (!$participant) {
            return response()->json(['success' => false, 'message' => 'Participant not found'], 404);
        }

        $supervisor = $participant->research->supervisor->where('supervisor_id', $staffId)->first();
        if ($supervisor) {
            $supervisor->defenseSupervisorPresence()->updateOrCreate(
                ['research_supervisor_id' => $supervisor->id],
                ['score' => $request->score, 'remark' => $request->remark]
            );
        }

        $examiner = $participant->defenseExaminer->where('examiner_id', $staffId)->first();
        if ($examiner && $examiner->defenseExaminerPresence) {
            $examiner->defenseExaminerPresence->score = $request->score;
            $examiner->defenseExaminerPresence->remark = $request->remark;
            $examiner->defenseExaminerPresence->save();
        }

        return response()->json(['success' => true]);
    }
}
