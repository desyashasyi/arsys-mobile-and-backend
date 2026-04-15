<?php

use App\Mail\ScoringReminderMail;
use App\Models\ArSys\Event;
use App\Models\ArSys\FinalDefenseRoom;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Final Defense Rooms')] class extends Component
{
    use Toast;

    public int $eventId = 0;

    public function mount(int $id): void
    {
        $this->eventId = $id;
    }

    public function broadcast(): void
    {
        $programId = auth()->user()->staff?->program_id;

        $event = Event::where('id', $this->eventId)
            ->where('program_id', $programId)
            ->whereHas('type', fn($q) => $q->where('code', 'PUB'))
            ->with(['type', 'program'])
            ->first();

        if (!$event) {
            $this->error('Event not found.', position: 'toast-bottom');
            return;
        }

        $formattedDate    = Carbon::parse($event->event_date)->format('dmy');
        $eventLabel       = sprintf('%s-%s-%s', $event->type->code ?? 'PUB', $formattedDate, $event->id);
        $eventDate        = Carbon::parse($event->event_date)->isoFormat('D MMMM YYYY');
        $organizerProgram = trim(($event->program?->name ?? '') . ($event->program?->code ? ' (' . $event->program->code . ')' : ''));
        $kaprodiStaff     = auth()->user()->staff;
        $kaprodiName      = trim(($kaprodiStaff?->front_title ? $kaprodiStaff->front_title . ' ' : '') . ($kaprodiStaff?->first_name ?? '') . ' ' . ($kaprodiStaff?->last_name ?? '') . ($kaprodiStaff?->rear_title ? ', ' . $kaprodiStaff->rear_title : ''));
        $kaprodiNip       = $kaprodiStaff?->employee_id ?? '';

        $rooms = FinalDefenseRoom::where('event_id', $this->eventId)
            ->with([
                'space', 'session',
                'examiner.staff.user',
                'examiner.finaldefenseExaminerPresence',
                'applicant.research.student.program',
                'applicant.research.supervisor.staff.user',
                'applicant.research.supervisor.finaldefenseSupervisorPresence',
            ])
            ->get();

        $fcm      = app(FcmService::class);
        $notifMap = []; // staff_id => ['token', 'email', 'name', 'participants' => [...]]

        foreach ($rooms as $room) {
            $roomLabel = ($room->space?->code ?? 'Room') . ($room->session ? ' · ' . $room->session->time : '');

            foreach ($room->applicant as $app) {
                if (!$app->research) continue;
                $student     = $app->research->student;
                $studentInfo = [
                    'nim'     => $student?->nim ?? 'N/A',
                    'name'    => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                    'program' => trim(($student?->program?->name ?? '') . ($student?->program?->code ? ' (' . $student->program->code . ')' : '')),
                    'title'   => $app->research->title ?? '',
                    'room'    => $roomLabel,
                ];
                $supervisorIds = $app->research->supervisor->pluck('supervisor_id')->toArray();

                foreach ($room->examiner as $ex) {
                    if (in_array($ex->examiner_id, $supervisorIds)) continue;
                    $presence = $ex->finaldefenseExaminerPresence->where('applicant_id', $app->id)->first();
                    if ($presence === null) continue; // absent
                    if ($presence->score !== null && $presence->score != -1) continue;
                    $notifMap[$ex->examiner_id] ??= [
                        'token' => $ex->staff?->user?->fcm_token,
                        'email' => $ex->staff?->user?->email,
                        'name'  => trim(($ex->staff?->first_name ?? '') . ' ' . ($ex->staff?->last_name ?? '')),
                        'participants' => [],
                    ];
                    $notifMap[$ex->examiner_id]['participants'][] = $studentInfo;
                }

                foreach ($app->research->supervisor as $sup) {
                    $p = $sup->finaldefenseSupervisorPresence;
                    if ($p === null) continue; // absent
                    if ($p->score !== null) continue;
                    $notifMap[$sup->supervisor_id] ??= [
                        'token' => $sup->staff?->user?->fcm_token,
                        'email' => $sup->staff?->user?->email,
                        'name'  => trim(($sup->staff?->first_name ?? '') . ' ' . ($sup->staff?->last_name ?? '')),
                        'participants' => [],
                    ];
                    $notifMap[$sup->supervisor_id]['participants'][] = $studentInfo;
                }
            }
        }

        if (empty($notifMap)) {
            $this->warning('No pending scorers found.', position: 'toast-bottom');
            return;
        }

        $sentFcm = 0; $sentEmail = 0;
        foreach ($notifMap as $entry) {
            if ($entry['token']) {
                $participantList = collect($entry['participants'])
                    ->map(fn($p) => "{$p['nim']} {$p['name']}" . ($p['room'] ? " ({$p['room']})" : ''))
                    ->implode(', ');
                $fcmTitle = "Score Reminder – Final Defense";
                $fcmBody  = "[{$eventLabel}] {$organizerProgram}\nPending: {$participantList}";
                if ($fcm->send($entry['token'], $fcmTitle, $fcmBody, [
                    'type'     => 'final_defense_scoring',
                    'event_id' => (string) $this->eventId,
                ])) {
                    $sentFcm++;
                }
            } elseif ($entry['email']) {
                Mail::to($entry['email'])->send(new ScoringReminderMail(
                    recipientName:    $entry['name'] ?: $entry['email'],
                    eventLabel:       $eventLabel,
                    defenseType:      'Final Defense',
                    eventDate:        $eventDate,
                    organizerProgram: $organizerProgram,
                    participants:     $entry['participants'],
                    kaprodiName:      $kaprodiName,
                    kaprodiNip:       $kaprodiNip,
                ));
                $sentEmail++;
            }
        }

        $total = count($notifMap);
        $this->success("Sent to {$total} scorer(s): {$sentFcm} push, {$sentEmail} email.", position: 'toast-bottom');
    }

    public function with(): array
    {
        $programId = auth()->user()->staff?->program_id;

        $event = Event::where('id', $this->eventId)
            ->where('program_id', $programId)
            ->whereHas('type', fn($q) => $q->where('code', 'PUB'))
            ->first();

        if (!$event) {
            return ['event' => null, 'rooms' => collect()];
        }

        $rooms = FinalDefenseRoom::where('event_id', $this->eventId)
            ->with([
                'space', 'session', 'moderator',
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
                        if (in_array($ex->examiner_id, $supervisorIds)) continue;
                        $presence = $ex->finaldefenseExaminerPresence
                            ->where('applicant_id', $app->id)->first();
                        // Only missing if present (has record) but not yet scored
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
                    'id'             => $room->id,
                    'room_name'      => $room->space?->code ?? 'N/A',
                    'session_time'   => $room->session?->time ?? 'N/A',
                    'moderator_name' => $room->moderator
                        ? trim($room->moderator->first_name . ' ' . $room->moderator->last_name)
                        : null,
                    'applicant_count'=> $room->applicant->count(),
                    'has_missing'    => $hasMissing,
                ];
            });

        return ['event' => $event, 'rooms' => $rooms];
    }
};
?>

<div>
    @if($event?->name)
        <p class="px-4 py-2 text-xs text-purple-600 font-medium">{{ $event->name }}</p>
    @endif

    @if(!$event)
        <div class="py-16 text-center">
            <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
            <p class="text-sm text-gray-500">Event not found.</p>
        </div>
    @else
        {{-- Broadcast button (only if any room has missing scores) --}}
        @if($rooms->contains('has_missing', true))
            <div class="px-3 pt-3">
                <button wire:click="broadcast" wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-xl bg-amber-500 text-white font-bold text-sm shadow hover:bg-amber-600 active:scale-95 disabled:opacity-60 transition-all">
                    <span wire:loading.remove wire:target="broadcast">
                        <x-icon name="o-bell-alert" class="h-4 w-4 inline -mt-0.5 mr-1" />
                        Notify Pending Scorers
                    </span>
                    <span wire:loading wire:target="broadcast" class="loading loading-spinner loading-sm"></span>
                </button>
            </div>
        @endif

        <div class="px-3 py-3 space-y-2">
            @forelse($rooms as $room)
                <a href="{{ route('program.final-defense.room', [$eventId, $room['id']]) }}"
                   class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $room['has_missing'] ? 'border-red-400' : 'border-green-400' }} p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <x-icon name="o-map-pin" class="h-4 w-4 text-purple-400 shrink-0" />
                                <p class="font-semibold text-sm text-gray-800">{{ $room['room_name'] }}</p>
                                <span class="text-xs text-gray-400">{{ $room['session_time'] }}</span>
                            </div>
                            @if($room['moderator_name'])
                                <p class="text-xs text-gray-400 mt-1">Moderator: {{ $room['moderator_name'] }}</p>
                            @endif
                            <p class="text-xs text-gray-400 mt-0.5">{{ $room['applicant_count'] }} participants</p>
                        </div>
                        <div class="shrink-0 flex flex-col items-end gap-1">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $room['has_missing'] ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-700' }}">
                                {{ $room['has_missing'] ? 'Incomplete' : 'Complete' }}
                            </span>
                            <x-icon name="o-chevron-right" class="h-4 w-4 text-gray-300" />
                        </div>
                    </div>
                </a>
            @empty
                <div class="py-12 text-center">
                    <x-icon name="o-inbox" class="mx-auto mb-3 h-10 w-10 text-gray-200" />
                    <p class="text-sm text-gray-400">No rooms found.</p>
                </div>
            @endforelse
        </div>
    @endif
</div>
