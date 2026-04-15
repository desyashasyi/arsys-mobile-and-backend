<?php

namespace App\Http\Controllers\Api\Specialization;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\ArSys\DefenseExaminer;
use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantDefense;
use App\Models\ArSys\EventApplicantFinalDefense;
use App\Models\ArSys\EventSession;
use App\Models\ArSys\EventSpace;
use App\Models\ArSys\EventType;
use App\Models\ArSys\FinalDefenseExaminer;
use App\Models\ArSys\FinalDefenseRoom;
use App\Models\ArSys\Program;
use App\Models\ArSys\Staff;
use Illuminate\Http\Request;

class EventController extends Controller
{
    private function getProgramId(): ?int
    {
        return auth()->user()->staff?->program_id;
    }

    // GET /specialization/events?type=PRE|PUB|SSP
    public function index(Request $request)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['message' => 'Program not found.'], 403);
        }

        $program = Program::findOrFail($programId);
        $clusterIds = Program::where('faculty_id', $program->faculty_id)->pluck('id');

        $query = Event::with(['type', 'program'])
            ->whereIn('program_id', $clusterIds)
            ->orderBy('event_date', 'desc');

        if ($request->type) {
            $query->whereHas('type', fn($q) => $q->where('code', $request->type));
        }

        $events = $query->get();

        return response()->json([
            'data' => $events->map(fn($e) => [
                'id'                   => $e->id,
                'event_date'           => Carbon::parse($e->event_date)->format('Y-m-d'),
                'application_deadline' => $e->application_deadline,
                'draft_deadline'       => $e->draft_deadline,
                'quota'                => $e->quota,
                'status'               => $e->status,
                'type'                 => $e->type ? ['code' => $e->type->code, 'name' => $e->type->name] : null,
                'program_id'           => $e->program_id,
                'program_code'         => $e->program ? ($e->program->code . '.' . $e->program->abbrev) : null,
                'is_own'               => $e->program_id === $programId,
            ]),
        ]);
    }

    // POST /specialization/events
    public function store(Request $request)
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['message' => 'Program not found.'], 403);
        }

        $data = $request->validate([
            'type_code'            => 'required|string|exists:arsys_event_type,code',
            'event_date'           => 'required|date',
            'application_deadline' => 'nullable|date',
            'draft_deadline'       => 'nullable|date',
            'quota'                => 'nullable|integer|min:1',
        ]);

        $eventType = EventType::where('code', $data['type_code'])->firstOrFail();

        $event = Event::create([
            'program_id'           => $programId,
            'event_type_id'        => $eventType->id,
            'event_date'           => $data['event_date'],
            'application_deadline' => $data['application_deadline'] ?? null,
            'draft_deadline'       => $data['draft_deadline'] ?? null,
            'quota'                => $data['quota'] ?? null,
            'status'               => 1,
        ]);

        return response()->json(['message' => 'Event created.', 'data' => $event->load('type')], 201);
    }

    // PUT /specialization/events/{id}
    public function update(Request $request, $id)
    {
        $programId = $this->getProgramId();
        $event = Event::where('program_id', $programId)->findOrFail($id);

        $data = $request->validate([
            'event_date'           => 'required|date',
            'application_deadline' => 'nullable|date',
            'draft_deadline'       => 'nullable|date',
            'quota'                => 'nullable|integer|min:1',
        ]);

        $event->update($data);

        return response()->json(['message' => 'Event updated.', 'data' => $event->load('type')]);
    }

    // DELETE /specialization/events/{id}
    public function destroy($id)
    {
        $programId = $this->getProgramId();
        $event = Event::where('program_id', $programId)->findOrFail($id);

        $hasApplicants = $event->defenseApplicant()->exists()
            || $event->finaldefenseApplicant()->exists()
            || $event->seminarApplicant()->exists();

        if ($hasApplicants) {
            return response()->json(['message' => 'Cannot delete event with existing applicants.'], 422);
        }

        $event->delete();
        return response()->json(['message' => 'Event deleted.']);
    }

    // GET /specialization/events/pre-defense
    public function preDefenseList()
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['message' => 'Program not found.'], 403);
        }

        $program = Program::findOrFail($programId);
        $clusterIds = Program::where('faculty_id', $program->faculty_id)->pluck('id');

        $events = Event::with(['program'])
            ->whereIn('program_id', $clusterIds)
            ->whereHas('type', fn($q) => $q->where('code', 'PRE'))
            ->withCount('defenseApplicant')
            ->withCount(['defenseApplicant as unpublished_count' => fn($q) => $q->where('publish', 0)])
            ->orderBy('event_date', 'desc')
            ->get();

        return response()->json([
            'data' => $events->map(fn($e) => [
                'id'                      => $e->id,
                'event_date'              => Carbon::parse($e->event_date)->format('Y-m-d'),
                'defense_applicant_count' => $e->defense_applicant_count,
                'unpublished_count'       => $e->program_id === $programId ? ($e->unpublished_count ?? 0) : null,
                'program_id'              => $e->program_id,
                'program_code'            => $e->program ? ($e->program->code . '.' . $e->program->abbrev) : null,
                'program_label'           => $e->program ? ($e->program->code . '-' . $e->program->name) : null,
                'is_own'                  => $e->program_id === $programId,
            ]),
        ]);
    }

    // GET /specialization/events/pre-defense/{eventId}/participants
    public function preDefenseParticipants($eventId)
    {
        $programId = $this->getProgramId();
        $event = Event::where('program_id', $programId)->findOrFail($eventId);

        $participants = EventApplicantDefense::with([
            'research.student.program',
            'space',
            'session',
            'supervisor.staff',
            'defenseExaminer.staff',
        ])->where('event_id', $event->id)->get();

        return response()->json(['data' => $participants->map(fn($p) => $this->formatParticipant($p))]);
    }

    // GET /specialization/events/pre-defense/{eventId}/participants/view  (read-only, for cluster programs)
    public function viewPreDefenseParticipants($eventId)
    {
        $programId = $this->getProgramId();
        $ownProgram = Program::findOrFail($programId);

        $event = Event::whereHas('program', fn($q) => $q->where('faculty_id', $ownProgram->faculty_id))
            ->findOrFail($eventId);

        $participants = EventApplicantDefense::with([
            'research.student',
            'space',
            'session',
            'supervisor.staff',
            'defenseExaminer.staff',
        ])->where('event_id', $event->id)->get();

        return response()->json(['data' => $participants->map(fn($p) => $this->formatParticipant($p))]);
    }

    // GET /specialization/events/final-defense
    public function finalDefenseList()
    {
        $programId = $this->getProgramId();
        if (!$programId) {
            return response()->json(['message' => 'Program not found.'], 403);
        }

        // Include all programs in the same faculty (cluster)
        $program = Program::findOrFail($programId);
        $clusterIds = Program::where('faculty_id', $program->faculty_id)->pluck('id');

        $events = Event::with(['program'])
            ->whereIn('program_id', $clusterIds)
            ->whereHas('type', fn($q) => $q->where('code', 'PUB'))
            ->withCount('finaldefenseApplicant as defense_applicant_count')
            ->orderBy('event_date', 'desc')
            ->get();

        return response()->json([
            'data' => $events->map(fn($e) => [
                'id'                    => $e->id,
                'event_date'            => Carbon::parse($e->event_date)->format('Y-m-d'),
                'defense_applicant_count' => $e->defense_applicant_count,
                'program_id'            => $e->program_id,
                'program_code'          => $e->program ? ($e->program->code . '.' . $e->program->abbrev) : null,
                'program_abbrev'        => $e->program?->abbrev,
                'program_label'         => $e->program ? ($e->program->code . '-' . $e->program->name) : null,
                'is_own'               => $e->program_id === $programId,
            ]),
        ]);
    }

    // GET /specialization/events/final-defense/{eventId}/rooms/view  (read-only, for cluster programs)
    public function viewFinalDefenseRooms($eventId)
    {
        $programId = $this->getProgramId();
        $ownProgram = Program::findOrFail($programId);

        // Allow access if the event belongs to any program in the same faculty cluster
        $event = Event::whereHas('program', fn($q) => $q->where('faculty_id', $ownProgram->faculty_id))
            ->findOrFail($eventId);

        $rooms = FinalDefenseRoom::with(['space', 'session', 'moderator', 'examiner.staff'])
            ->where('event_id', $event->id)
            ->get();

        return response()->json([
            'data' => $rooms->map(fn($r) => $this->formatRoom($r)),
        ]);
    }

    // GET /specialization/events/final-defense/{eventId}/rooms
    public function finalDefenseRooms($eventId)
    {
        $programId = $this->getProgramId();
        $event = Event::where('program_id', $programId)->findOrFail($eventId);

        $rooms = FinalDefenseRoom::with(['space', 'session', 'moderator', 'examiner.staff', 'applicant.research.student'])
            ->where('event_id', $event->id)
            ->get();

        // Unassigned participants (room_id is null)
        $unassigned = EventApplicantFinalDefense::with(['research.student'])
            ->where('event_id', $event->id)
            ->whereNull('room_id')
            ->get()
            ->map(fn($p) => $this->formatFinalDefenseParticipantBasic($p));

        return response()->json([
            'data'       => $rooms->values()->map(fn($r) => array_merge($this->formatRoom($r), [
                'participants' => $r->applicant->map(fn($p) => $this->formatFinalDefenseParticipantBasic($p))->values()->all(),
            ]))->values(),
            'unassigned' => $unassigned,
        ]);
    }

    // POST /specialization/events/final-defense/{eventId}/rooms
    public function addFinalDefenseRoom($eventId)
    {
        $programId = $this->getProgramId();
        $event = Event::where('program_id', $programId)->findOrFail($eventId);

        $room = FinalDefenseRoom::create(['event_id' => $event->id]);
        // Refresh to ensure id is populated (handles DB configs where lastInsertId may not auto-fill)
        if (is_null($room->id)) {
            $room = FinalDefenseRoom::where('event_id', $event->id)->latest('id')->first();
        }
        return response()->json(['message' => 'Room added.', 'data' => $this->formatRoom($room->load(['space', 'session', 'moderator', 'examiner.staff']))], 201);
    }

    // DELETE /specialization/events/final-defense/rooms/{roomId}
    public function deleteFinalDefenseRoom($roomId)
    {
        $programId = $this->getProgramId();
        $room = FinalDefenseRoom::whereHas('event', fn($q) => $q->where('program_id', $programId))->findOrFail($roomId);

        if ($room->examiner()->exists() || $room->applicant()->exists()) {
            return response()->json(['message' => 'Cannot delete room with participants or examiners.'], 422);
        }

        $room->delete();
        return response()->json(['message' => 'Room deleted.']);
    }

    // GET /specialization/events/final-defense/rooms/{roomId}
    public function finalDefenseRoomDetail($roomId)
    {
        $programId = $this->getProgramId();
        $room = FinalDefenseRoom::with(['space', 'session', 'moderator', 'examiner.staff', 'applicant.research.student', 'event'])
            ->whereHas('event', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($roomId);

        $eventDate = $room->event->event_date;

        // Resolve examiner conflict info (which programs they're also scheduled in on this date)
        $examiners = $room->examiner->map(function ($e) use ($eventDate, $roomId) {
            $otherRooms = FinalDefenseExaminer::where('examiner_id', $e->examiner_id)
                ->where('room_id', '!=', $roomId)
                ->whereHas('room.event', fn($q) => $q->where('event_date', $eventDate))
                ->with('room.event.program')
                ->get();

            $otherPrograms = $otherRooms->map(fn($r) => $r->room?->event?->program?->abbrev)->filter()->unique()->values()->all();

            return [
                'id'             => $e->id,
                'name'           => $e->staff ? trim($e->staff->first_name . ' ' . $e->staff->last_name) : '-',
                'staff_id'       => $e->examiner_id,
                'other_programs' => $otherPrograms,
            ];
        })->values()->all();

        return response()->json([
            'data' => [
                'id'           => $room->id,
                'space'        => $room->space ? ['id' => $room->space->id, 'code' => $room->space->code, 'name' => $room->space->name ?? null] : null,
                'session'      => $room->session ? ['id' => $room->session->id, 'time' => $room->session->time, 'day' => $room->session->day] : null,
                'moderator'    => $room->moderator ? ['id' => $room->moderator->id, 'name' => trim($room->moderator->first_name . ' ' . $room->moderator->last_name)] : null,
                'examiners'    => $examiners,
                'participants' => $room->applicant->map(fn($p) => $this->formatFinalDefenseParticipantBasic($p))->values()->all(),
            ],
        ]);
    }

    // PUT /specialization/events/final-defense/rooms/{roomId}
    public function updateFinalDefenseRoom(Request $request, $roomId)
    {
        $programId = $this->getProgramId();
        $room = FinalDefenseRoom::whereHas('event', fn($q) => $q->where('program_id', $programId))->findOrFail($roomId);

        $data = $request->validate([
            'space_id'     => 'nullable|exists:arsys_event_space,id',
            'session_id'   => 'nullable|exists:arsys_event_session,id',
            'moderator_id' => 'nullable|exists:arsys_staff,id',
        ]);

        $room->update($data);
        return response()->json(['message' => 'Room updated.']);
    }

    // POST /specialization/events/final-defense/rooms/{roomId}/examiners
    public function addFinalDefenseRoomExaminer(Request $request, $roomId)
    {
        $programId = $this->getProgramId();
        $room = FinalDefenseRoom::with('event')->whereHas('event', fn($q) => $q->where('program_id', $programId))->findOrFail($roomId);

        $data = $request->validate(['staff_id' => 'required|exists:arsys_staff,id']);

        if (FinalDefenseExaminer::where('room_id', $room->id)->where('examiner_id', $data['staff_id'])->exists()) {
            return response()->json(['message' => 'Staff already assigned as examiner in this room.'], 422);
        }

        // Check how many final defense rooms this examiner is already scheduled in on this date (across all programs)
        $eventDate = $room->event->event_date;
        $existingRoomCount = FinalDefenseExaminer::where('examiner_id', $data['staff_id'])
            ->whereHas('room.event', fn($q) => $q->where('event_date', $eventDate))
            ->count();

        if ($existingRoomCount >= 2) {
            return response()->json(['message' => 'This examiner is already scheduled in 2 final defense rooms on this date and cannot be added again.'], 422);
        }

        FinalDefenseExaminer::create([
            'room_id'     => $room->id,
            'event_id'    => $room->event_id,
            'examiner_id' => $data['staff_id'],
        ]);

        $warning = $existingRoomCount === 1
            ? 'Examiner added. Note: This examiner is already scheduled in 1 other final defense room on this date.'
            : null;

        return response()->json(['message' => 'Examiner added.', 'warning' => $warning]);
    }

    // POST /specialization/events/pre-defense/{eventId}/publish
    public function publishPreDefenseSchedules($eventId)
    {
        $programId = $this->getProgramId();
        $event = Event::where('program_id', $programId)->findOrFail($eventId);

        EventApplicantDefense::where('event_id', $event->id)->update(['publish' => 1]);

        return response()->json(['message' => 'Schedules published successfully.']);
    }

    // POST /specialization/events/final-defense/{eventId}/publish
    public function publishFinalDefenseSchedules($eventId)
    {
        $programId = $this->getProgramId();
        $event = Event::where('program_id', $programId)->findOrFail($eventId);

        EventApplicantFinalDefense::where('event_id', $event->id)
            ->whereNotNull('room_id')
            ->update(['publish' => 1]);

        return response()->json(['message' => 'Schedules published successfully.']);
    }

    // GET /specialization/events/final-defense/upcoming
    public function upcomingFinalDefenseEvents()
    {
        $programId = $this->getProgramId();
        if (!$programId) return response()->json(['data' => []]);

        $events = Event::where('program_id', $programId)
            ->whereHas('type', fn($q) => $q->where('code', 'PUB'))
            ->where('event_date', '>', now()->toDateString())
            ->orderBy('event_date')
            ->get(['id', 'event_date']);

        return response()->json([
            'data' => $events->map(fn($e) => [
                'id'   => $e->id,
                'date' => $e->event_date,
                'label' => 'Final Defense | ' . \Carbon\Carbon::parse($e->event_date)->format('d M Y'),
            ])->values(),
        ]);
    }

    // POST /specialization/events/final-defense/participants/{participantId}/transfer
    public function transferFinalDefenseParticipant(Request $request, $participantId)
    {
        $programId = $this->getProgramId();
        $data = $request->validate(['event_id' => 'required|exists:arsys_event,id']);

        $participant = EventApplicantFinalDefense::whereHas('event', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($participantId);

        Event::where('program_id', $programId)->findOrFail($data['event_id']);

        $participant->update(['event_id' => $data['event_id'], 'room_id' => null, 'publish' => 0]);

        return response()->json(['message' => 'Participant transferred.']);
    }

    // DELETE /specialization/events/final-defense/rooms/examiners/{examinerId}
    public function removeFinalDefenseRoomExaminer($examinerId)
    {
        $programId = $this->getProgramId();
        $examiner = FinalDefenseExaminer::whereHas('room.event', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($examinerId);

        $examiner->delete();
        return response()->json(['message' => 'Examiner removed.']);
    }

    // POST /specialization/events/final-defense/rooms/{roomId}/assign/{participantId}
    public function assignParticipantToRoom($roomId, $participantId)
    {
        $programId = $this->getProgramId();
        $room = FinalDefenseRoom::whereHas('event', fn($q) => $q->where('program_id', $programId))->findOrFail($roomId);
        $participant = EventApplicantFinalDefense::where('event_id', $room->event_id)->findOrFail($participantId);

        $participant->update(['room_id' => $room->id]);
        return response()->json(['message' => 'Participant assigned.']);
    }

    // DELETE /specialization/events/final-defense/participants/{participantId}/unassign
    public function unassignParticipantFromRoom($participantId)
    {
        $programId = $this->getProgramId();
        $participant = EventApplicantFinalDefense::whereHas('room.event', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($participantId);

        $participant->update(['room_id' => null]);
        return response()->json(['message' => 'Participant unassigned.']);
    }

    // GET /specialization/events/pre-defense/participant/{id}
    public function participantDetail($id)
    {
        $programId = $this->getProgramId();

        $participant = EventApplicantDefense::with([
            'research.student.program',
            'space',
            'session',
            'supervisor.staff',
            'defenseExaminer.staff',
        ])->whereHas('event', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($id);

        return response()->json(['data' => $this->formatParticipant($participant)]);
    }

    // PUT /specialization/events/pre-defense/participant/{id}
    public function updateParticipant(Request $request, $id)
    {
        $programId = $this->getProgramId();
        $participant = EventApplicantDefense::whereHas('event', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($id);

        $data = $request->validate([
            'space_id'   => 'nullable|exists:arsys_event_space,id',
            'session_id' => 'nullable|exists:arsys_event_session,id',
        ]);

        $participant->update($data);
        return response()->json(['message' => 'Participant updated.']);
    }

    // POST /specialization/events/pre-defense/participant/{id}/examiners
    public function addExaminer(Request $request, $id)
    {
        $programId = $this->getProgramId();
        $participant = EventApplicantDefense::whereHas('event', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($id);

        $data = $request->validate(['staff_id' => 'required|exists:arsys_staff,id']);

        if (DefenseExaminer::where('applicant_id', $participant->id)->where('examiner_id', $data['staff_id'])->exists()) {
            return response()->json(['message' => 'Staff already assigned as examiner.'], 422);
        }

        $order = DefenseExaminer::where('applicant_id', $participant->id)->max('order');

        DefenseExaminer::create([
            'event_id'     => $participant->event_id,
            'applicant_id' => $participant->id,
            'examiner_id'  => $data['staff_id'],
            'order'        => $order ? $order + 1 : 1,
        ]);

        return response()->json(['message' => 'Examiner added.']);
    }

    // DELETE /specialization/events/pre-defense/examiners/{examinerId}
    public function removeExaminer($examinerId)
    {
        $programId = $this->getProgramId();
        $examiner = DefenseExaminer::whereHas('defenseApplicant.event', fn($q) => $q->where('program_id', $programId))
            ->findOrFail($examinerId);

        $examiner->delete();
        return response()->json(['message' => 'Examiner removed.']);
    }

    // GET /specialization/spaces
    public function spaces()
    {
        return response()->json(['data' => EventSpace::orderBy('code')->get(['id', 'code'])]);
    }

    // GET /specialization/sessions
    public function sessions()
    {
        return response()->json(['data' => EventSession::orderBy('time')->get(['id', 'time', 'day'])]);
    }

    // GET /specialization/staff/search?query=...
    public function searchStaff(Request $request)
    {
        $programId = $this->getProgramId();
        $query = $request->query('query', '');

        $staff = Staff::where('program_id', $programId)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%$query%")
                    ->orWhere('last_name', 'like', "%$query%")
                    ->orWhere('code', 'like', "%$query%");
            })
            ->get(['id', 'first_name', 'last_name', 'code']);

        return response()->json($staff->map(fn($s) => [
            'id'   => $s->id,
            'name' => trim($s->first_name . ' ' . $s->last_name),
            'code' => $s->code,
        ]));
    }

    // GET /specialization/event-types
    public function eventTypes()
    {
        $types = EventType::whereIn('code', ['PRE', 'PUB', 'SSP'])->get(['id', 'code', 'name']);
        return response()->json(['data' => $types]);
    }

    private function formatRoom(FinalDefenseRoom $r): array
    {
        return [
            'id'        => $r->id,
            'space'     => $r->space ? ['id' => $r->space->id, 'code' => $r->space->code, 'name' => $r->space->name ?? null] : null,
            'session'   => $r->session ? ['id' => $r->session->id, 'time' => $r->session->time, 'day' => $r->session->day] : null,
            'moderator' => $r->moderator ? ['id' => $r->moderator->id, 'name' => trim($r->moderator->first_name . ' ' . $r->moderator->last_name)] : null,
            'examiners' => $r->examiner->map(fn($e) => [
                'id'             => $e->id,
                'name'           => $e->staff ? trim($e->staff->first_name . ' ' . $e->staff->last_name) : '-',
                'staff_id'       => $e->examiner_id,
                'other_programs' => [],
            ])->values()->all(),
        ];
    }

    private function formatFinalDefenseParticipantBasic(EventApplicantFinalDefense $p): array
    {
        $student = $p->research?->student;
        return [
            'id'             => $p->id,
            'publish'        => $p->publish ?? 0,
            'student_number' => $student?->nim ?? '-',
            'student_name'   => $student ? trim($student->first_name . ' ' . $student->last_name) : '-',
            'research_title' => $p->research?->title ?? '-',
        ];
    }

    private function formatParticipant(EventApplicantDefense $p): array
    {
        $student = $p->research->student ?? null;
        return [
            'id'             => $p->id,
            'publish'        => $p->publish,
            'student_name'   => $student ? trim($student->first_name . ' ' . $student->last_name) : '-',
            'student_number' => $student?->nim ?? '-',
            'research_code'  => $p->research->code ?? null,
            'research_title' => $p->research->title ?? '-',
            'space'          => $p->space ? ['id' => $p->space->id, 'code' => $p->space->code] : null,
            'session'        => $p->session ? ['id' => $p->session->id, 'time' => $p->session->time, 'day' => $p->session->day] : null,
            'supervisors'    => $p->supervisor->map(fn($s) => [
                'code' => $s->staff?->code ?? '-',
                'name' => $s->staff ? trim($s->staff->first_name . ' ' . $s->staff->last_name) : '-',
            ])->values()->all(),
            'examiners'      => $p->defenseExaminer->map(fn($e) => [
                'id'       => $e->id,
                'staff_id' => $e->examiner_id,
                'code'     => $e->staff?->code ?? '-',
                'name'     => $e->staff ? ($e->staff->first_name . ' ' . $e->staff->last_name) : '-',
                'order'    => $e->order,
            ]),
        ];
    }
}