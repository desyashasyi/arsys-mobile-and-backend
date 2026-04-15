<?php

use App\Mail\ScoringReminderMail;
use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantDefense;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Pre-Defense Detail')] class extends Component
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
            ->whereHas('type', fn($q) => $q->where('code', 'PRE'))
            ->with(['type', 'program'])
            ->first();

        if (!$event) {
            $this->error('Event not found.', position: 'toast-bottom');
            return;
        }

        $formattedDate    = Carbon::parse($event->event_date)->format('dmy');
        $eventLabel       = sprintf('%s-%s-%s', $event->type->code ?? 'PRE', $formattedDate, $event->id);
        $eventDate        = Carbon::parse($event->event_date)->isoFormat('D MMMM YYYY');
        $organizerProgram = trim(($event->program?->name ?? '') . ($event->program?->code ? ' (' . $event->program->code . ')' : ''));
        $kaprodiStaff     = auth()->user()->staff;
        $kaprodiName      = trim(($kaprodiStaff?->front_title ? $kaprodiStaff->front_title . ' ' : '') . ($kaprodiStaff?->first_name ?? '') . ' ' . ($kaprodiStaff?->last_name ?? '') . ($kaprodiStaff?->rear_title ? ', ' . $kaprodiStaff->rear_title : ''));
        $kaprodiNip       = $kaprodiStaff?->employee_id ?? '';

        $applicants = EventApplicantDefense::where('event_id', $this->eventId)
            ->where('publish', 1)
            ->with([
                'research.student.program',
                'research.supervisor.staff.user',
                'research.supervisor.defenseSupervisorPresence',
                'defenseExaminer.staff.user',
                'defenseExaminer.defenseExaminerPresence',
            ])
            ->get();

        $fcm      = app(FcmService::class);
        $notifMap = []; // staff_id => ['token', 'email', 'name', 'participants' => [...]]

        foreach ($applicants as $app) {
            if (!$app->research) continue;
            $student     = $app->research->student;
            $studentInfo = [
                'nim'     => $student?->nim ?? 'N/A',
                'name'    => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                'program' => trim(($student?->program?->name ?? '') . ($student?->program?->code ? ' (' . $student->program->code . ')' : '')),
                'title'   => $app->research->title ?? '',
                'room'    => '',
            ];
            $supervisorIds = $app->research->supervisor->pluck('supervisor_id')->toArray();

            // Examiners who are present (have presence record) but haven't scored
            foreach ($app->defenseExaminer as $ex) {
                if (in_array($ex->examiner_id, $supervisorIds)) continue;
                if ($ex->defenseExaminerPresence === null) continue; // absent
                if ($ex->defenseExaminerPresence->score !== null) continue;
                $notifMap[$ex->examiner_id] ??= [
                    'token' => $ex->staff?->user?->fcm_token,
                    'email' => $ex->staff?->user?->email,
                    'name'  => trim(($ex->staff?->first_name ?? '') . ' ' . ($ex->staff?->last_name ?? '')),
                    'participants' => [],
                ];
                $notifMap[$ex->examiner_id]['participants'][] = $studentInfo;
            }

            // Supervisors who are present but haven't scored
            foreach ($app->research->supervisor as $sup) {
                if ($sup->defenseSupervisorPresence === null) continue; // absent
                if ($sup->defenseSupervisorPresence->score !== null) continue;
                $notifMap[$sup->supervisor_id] ??= [
                    'token' => $sup->staff?->user?->fcm_token,
                    'email' => $sup->staff?->user?->email,
                    'name'  => trim(($sup->staff?->first_name ?? '') . ' ' . ($sup->staff?->last_name ?? '')),
                    'participants' => [],
                ];
                $notifMap[$sup->supervisor_id]['participants'][] = $studentInfo;
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
                    ->map(fn($p) => "{$p['nim']} {$p['name']}")
                    ->implode(', ');
                $fcmTitle = "Score Reminder – Pre-Defense";
                $fcmBody  = "[{$eventLabel}] {$organizerProgram}\nPending: {$participantList}";
                if ($fcm->send($entry['token'], $fcmTitle, $fcmBody, [
                    'type'     => 'pre_defense_scoring',
                    'event_id' => (string) $this->eventId,
                ])) {
                    $sentFcm++;
                }
            } elseif ($entry['email']) {
                Mail::to($entry['email'])->send(new ScoringReminderMail(
                    recipientName:    $entry['name'] ?: $entry['email'],
                    eventLabel:       $eventLabel,
                    defenseType:      'Pre-Defense',
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
            ->whereHas('type', fn($q) => $q->where('code', 'PRE'))
            ->with(['type'])
            ->first();

        if (!$event) {
            return ['event' => null, 'eventCode' => '', 'applicants' => collect()];
        }

        $formattedDate = Carbon::parse($event->event_date)->format('dmy');
        $eventCode     = sprintf('%s-%s-%s', $event->type->code ?? 'PRE', $formattedDate, $event->id);

        $applicants = EventApplicantDefense::where('event_id', $this->eventId)
            ->where('publish', 1)
            ->with([
                'research.student.program',
                'research.supervisor.staff',
                'research.supervisor.defenseSupervisorPresence',
                'defenseExaminer.staff',
                'defenseExaminer.defenseExaminerPresence',
            ])
            ->get()
            ->map(function ($app) {
                if (!$app->research) {
                    return null;
                }

                $student = $app->research->student;

                $supervisorMap = [];
                foreach ($app->research->supervisor as $sup) {
                    $supervisorMap[$sup->supervisor_id] = $sup->defenseSupervisorPresence?->score;
                }

                $supervisors = $app->research->supervisor->sortBy('order')->map(function ($sup) {
                    $presence = $sup->defenseSupervisorPresence;
                    $score    = $presence?->score;
                    return [
                        'name'       => trim(($sup->staff?->first_name ?? '') . ' ' . ($sup->staff?->last_name ?? '')),
                        'role'       => ($sup->order ?? 1) <= 1 ? 'SPV' : 'Co-SPV',
                        'score'      => $score,
                        'has_scored' => $score !== null,
                        'is_present' => $presence !== null,
                    ];
                });

                $examiners = $app->defenseExaminer->map(function ($ex) use ($supervisorMap) {
                    $presence      = $ex->defenseExaminerPresence;
                    $isPresent     = $presence !== null;
                    $examinerScore = $presence?->score;
                    $isOwnSpv      = array_key_exists($ex->examiner_id, $supervisorMap);
                    $score         = $examinerScore;
                    $scoredAsSpv   = false;
                    if ($score === null && $isOwnSpv && $supervisorMap[$ex->examiner_id] !== null) {
                        $score       = $supervisorMap[$ex->examiner_id];
                        $scoredAsSpv = true;
                    }
                    return [
                        'name'         => trim(($ex->staff?->first_name ?? '') . ' ' . ($ex->staff?->last_name ?? '')),
                        'score'        => $score,
                        'has_scored'   => $examinerScore !== null,
                        'is_own_spv'   => $isOwnSpv,
                        'scored_as_spv'=> $scoredAsSpv,
                        'is_present'   => $isPresent,
                    ];
                })->filter(fn($e) => !$e['is_own_spv'])->values();

                $hasMissing = $examiners->contains(fn($e) => $e['is_present'] && !$e['has_scored'])
                    || $supervisors->contains(fn($s) => $s['is_present'] && !$s['has_scored']);

                return [
                    'id'         => $app->id,
                    'nim'        => $student?->nim ?? 'N/A',
                    'name'       => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                    'title'      => $app->research->title ?? 'No Title',
                    'supervisors'=> $supervisors,
                    'examiners'  => $examiners,
                    'has_missing'=> $hasMissing,
                ];
            })
            ->filter()
            ->values();

        return ['event' => $event, 'eventCode' => $eventCode, 'applicants' => $applicants];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">PRE-DEFENSE SCORE DETAIL</p>

    @if(!$event)
        <div class="py-16 text-center">
            <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
            <p class="text-sm text-gray-500">Event not found.</p>
        </div>
    @else
        {{-- Broadcast button (only if there are missing scores) --}}
        @if($applicants->contains('has_missing', true))
            <div class="px-3 pt-3">
                <button wire:click="broadcast" wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-xl !bg-amber-500 !text-white font-bold text-sm shadow hover:!bg-amber-600 active:scale-95 disabled:opacity-60 transition-all">
                    <span wire:loading.remove wire:target="broadcast">
                        <x-icon name="o-bell-alert" class="h-4 w-4 inline -mt-0.5 mr-1" />
                        Notify Pending Scorers
                    </span>
                    <span wire:loading wire:target="broadcast" class="loading loading-spinner loading-sm"></span>
                </button>
            </div>
        @endif

        <div class="px-3 py-3 space-y-2">
            @forelse($applicants as $ap)
                <a href="{{ route('program.pre-defense.participant', [$eventId, $ap['id']]) }}"
                   class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4
                          {{ $ap['has_missing'] ? 'border-red-400' : 'border-green-400' }}
                          p-3 flex items-center gap-3 hover:shadow-md transition-shadow">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $ap['has_missing'] ? 'bg-red-50' : 'bg-green-50' }}">
                        <x-icon name="{{ $ap['has_missing'] ? 'o-exclamation-triangle' : 'o-check' }}"
                                class="h-4 w-4 {{ $ap['has_missing'] ? 'text-red-500' : 'text-green-600' }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-semibold text-purple-600">{{ $ap['nim'] }}</p>
                        <p class="font-semibold text-sm text-gray-800 leading-tight truncate">{{ $ap['name'] ?: '—' }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 leading-snug line-clamp-2">{{ $ap['title'] }}</p>
                    </div>
                    <x-icon name="o-chevron-right" class="h-4 w-4 shrink-0 text-gray-300" />
                </a>
            @empty
                <div class="py-12 text-center">
                    <x-icon name="o-inbox" class="mx-auto mb-3 h-10 w-10 text-gray-200" />
                    <p class="text-sm text-gray-400">No participants found.</p>
                </div>
            @endforelse
        </div>
    @endif
</div>
