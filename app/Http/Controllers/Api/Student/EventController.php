<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\ArSys\Event;
use App\Models\ArSys\EventType;
use App\Models\ArSys\EventApplicantDefense;
use App\Models\ArSys\EventApplicantSeminar;
use App\Models\ArSys\EventApplicantFinalDefense;
use App\Models\ArSys\FinalDefenseRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EventController extends Controller
{
    /**
     * List published events relevant to the student's program.
     * Accepts optional ?type= query parameter to filter by examination_type (Defense, Seminar, Final-defense).
     * Past events without participants are hidden.
     * Past events with participants are auto-marked as completed.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $student = $user->student;

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Student profile not found.'], 404);
        }

        $programId = $student->program_id;
        $typeFilter = $request->query('type'); // Defense, Seminar, Final-defense

        $query = Event::where('program_id', $programId)
            ->where('status', 1)
            ->with(['type', 'program'])
            ->orderBy('event_date', 'desc');

        if ($typeFilter) {
            $query->whereHas('type', function ($q) use ($typeFilter) {
                $q->where('examination_type', $typeFilter);
            });
        }

        $today = Carbon::today();

        $events = $query->get()
            ->map(function ($event) use ($today) {
                $exType = $event->type?->examination_type;

                // Count actual published applicants
                $applicantCount = 0;
                if ($exType === 'Defense') {
                    $applicantCount = $event->defenseApplicantPublish()->count();
                } elseif ($exType === 'Final-defense') {
                    $applicantCount = $event->finaldefenseApplicantPublish()->count();
                } elseif ($exType === 'Seminar') {
                    $applicantCount = $event->seminarApplicantPublish()->count();
                }

                $isPast = $event->event_date && Carbon::parse($event->event_date)->lt($today);
                $completed = $event->completed ? true : ($isPast && $applicantCount > 0);

                return [
                    'id' => $event->id,
                    'type_code' => $event->type?->code,
                    'type_description' => $event->type?->description,
                    'examination_type' => $exType,
                    'event_date' => $event->event_date,
                    'application_deadline' => $event->application_deadline,
                    'draft_deadline' => $event->draft_deadline,
                    'quota' => $event->quota,
                    'current' => $applicantCount,
                    'completed' => $completed,
                    'program_name' => $event->program?->name,
                    '_is_past' => $isPast,
                ];
            })
            ->filter(function ($e) {
                // Future events: always show
                if (!$e['_is_past']) {
                    return true;
                }
                // Past events: show only if has participants or already marked completed
                return $e['current'] > 0 || $e['completed'];
            })
            ->map(function ($e) {
                unset($e['_is_past']);
                return $e;
            })
            ->values();

        // Return available examination types for the dropdown (only Defense and Final-defense)
        $availableTypes = Event::where('program_id', $programId)
            ->where('status', 1)
            ->with('type')
            ->get()
            ->pluck('type.examination_type')
            ->filter()
            ->unique()
            ->filter(fn($t) => in_array($t, ['Defense', 'Final-defense']))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $events,
            'available_types' => $availableTypes,
        ]);
    }

    /**
     * Show event detail with applicants/schedule.
     */
    public function show($id)
    {
        $user = Auth::user();
        $student = $user->student;

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Student profile not found.'], 404);
        }

        $event = Event::where('id', $id)
            ->where('status', 1)
            ->with(['type', 'program'])
            ->first();

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event not found.'], 404);
        }

        $examinationType = $event->type?->examination_type;

        // Count actual published applicants
        $applicantCount = 0;
        if ($examinationType === 'Defense') {
            $applicantCount = $event->defenseApplicantPublish()->count();
        } elseif ($examinationType === 'Final-defense') {
            $applicantCount = $event->finaldefenseApplicantPublish()->count();
        }

        $today = Carbon::today();
        $isPast = $event->event_date && Carbon::parse($event->event_date)->lt($today);
        $completed = $event->completed ? true : ($isPast && $applicantCount > 0);

        $data = [
            'id' => $event->id,
            'type_code' => $event->type?->code,
            'type_description' => $event->type?->description,
            'examination_type' => $examinationType,
            'event_date' => $event->event_date,
            'application_deadline' => $event->application_deadline,
            'draft_deadline' => $event->draft_deadline,
            'quota' => $event->quota,
            'current' => $applicantCount,
            'completed' => $completed,
            'program_name' => $event->program?->name,
        ];

        // Load data based on event type — both use room-based structure
        if ($examinationType === 'Defense') {
            $data['rooms'] = $this->getDefenseRooms($event);
        } elseif ($examinationType === 'Final-defense') {
            $data['rooms'] = $this->getFinalDefenseRooms($event);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Pre-defense: group applicants by session+space into virtual rooms.
     */
    private function getDefenseRooms(Event $event)
    {
        $applicants = EventApplicantDefense::where('event_id', $event->id)
            ->where('publish', 1)
            ->with([
                'research.student.program',
                'research.supervisor.staff',
                'research.milestone',
                'examiner.staff',
                'space',
                'session',
            ])
            ->orderBy('session_id', 'asc')
            ->get();

        // Group by session_id + space_id
        $grouped = $applicants->groupBy(function ($a) {
            return ($a->session_id ?? 0) . '-' . ($a->space_id ?? 0);
        });

        $index = 0;
        return $grouped->map(function ($group) use (&$index) {
            $index++;
            $first = $group->first();
            // Collect unique examiners across all applicants in this group
            $allExaminers = $group->flatMap(function ($a) {
                return ($a->examiner ?? collect())->map(function ($e) {
                    return [
                        'name' => $e->staff ? trim($e->staff->first_name . ' ' . $e->staff->last_name) : 'N/A',
                        'code' => $e->staff?->code,
                    ];
                });
            })->unique('code')->values();

            return [
                'id' => $index,
                'label' => 'P-' . $index,
                'space' => $first->space?->description,
                'session' => $first->session?->time,
                'moderator' => null,
                'examiners' => $allExaminers,
                'applicants' => $group->map(function ($a) {
                    $student = $a->research?->student;
                    $programCode = $student?->program?->code;
                    $number = $student?->number;
                    $displayNumber = ($programCode && $number) ? ($programCode . '.' . $number) : ($number ?? '');
                    return [
                        'id' => $a->id,
                        'student_name' => $student ? trim($student->first_name . ' ' . $student->last_name) : 'N/A',
                        'student_number' => $displayNumber,
                        'research_title' => strip_tags($a->research?->title ?? ''),
                        'supervisors' => ($a->research?->supervisor ?? collect())->map(function ($s) {
                            return [
                                'name' => $s->staff ? trim($s->staff->first_name . ' ' . $s->staff->last_name) : 'N/A',
                                'code' => $s->staff?->code,
                            ];
                        }),
                    ];
                })->values(),
            ];
        })->values();
    }

    /**
     * Final-defense: room-based structure with space/time, moderator, examiners, applicants.
     */
    private function getFinalDefenseRooms(Event $event)
    {
        $rooms = FinalDefenseRoom::where('event_id', $event->id)
            ->with([
                'space',
                'session',
                'moderator',
                'examiner.staff',
                'applicant.research.student.program',
                'applicant.research.supervisor.staff',
            ])
            ->get();

        return $rooms->map(function ($room, $index) {
            return [
                'id' => $room->id,
                'label' => 'P-' . ($index + 1),
                'space' => $room->space?->description,
                'session' => $room->session?->time,
                'moderator' => $room->moderator ? [
                    'name' => trim($room->moderator->first_name . ' ' . $room->moderator->last_name),
                    'code' => $room->moderator->code,
                ] : null,
                'examiners' => ($room->examiner ?? collect())->map(function ($e) {
                    return [
                        'name' => $e->staff ? trim($e->staff->first_name . ' ' . $e->staff->last_name) : 'N/A',
                        'code' => $e->staff?->code,
                    ];
                }),
                'applicants' => ($room->applicant ?? collect())->map(function ($a) {
                    $student = $a->research?->student;
                    $programCode = $student?->program?->code;
                    $number = $student?->number;
                    $displayNumber = ($programCode && $number) ? ($programCode . '.' . $number) : ($number ?? '');
                    return [
                        'id' => $a->id,
                        'student_name' => $student ? trim($student->first_name . ' ' . $student->last_name) : 'N/A',
                        'student_number' => $displayNumber,
                        'research_title' => strip_tags($a->research?->title ?? ''),
                        'supervisors' => ($a->research?->supervisor ?? collect())->map(function ($s) {
                            return [
                                'name' => $s->staff ? trim($s->staff->first_name . ' ' . $s->staff->last_name) : 'N/A',
                                'code' => $s->staff?->code,
                            ];
                        }),
                    ];
                }),
            ];
        });
    }
}
