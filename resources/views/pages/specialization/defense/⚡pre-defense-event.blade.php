<?php

use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantDefense;
use App\Models\ArSys\EventSession;
use App\Models\ArSys\EventSpace;
use App\Models\ArSys\Program;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Pre-Defense Participants')] class extends Component
{
    use Toast;

    public int $eventId = 0;

    public function mount(int $id): void
    {
        $this->eventId = $id;
    }

    private function getProgramId(): ?int
    {
        return auth()->user()->staff?->program_id;
    }

    private function getClusterIds(): \Illuminate\Support\Collection
    {
        $programId = $this->getProgramId();
        $program   = Program::find($programId);
        return Program::where('faculty_id', $program?->faculty_id)->pluck('id');
    }

    public function publish(): void
    {
        $programId = $this->getProgramId();
        $event = Event::where('id', $this->eventId)->where('program_id', $programId)->first();
        if (!$event) { $this->error('Not authorized.', position: 'toast-bottom'); return; }

        EventApplicantDefense::where('event_id', $event->id)->update(['publish' => 1]);
        $this->success('All schedules published.', position: 'toast-bottom');
    }

    public function randomizeSchedule(): void
    {
        $programId = $this->getProgramId();
        $event = Event::where('id', $this->eventId)->where('program_id', $programId)->first();
        if (!$event) { $this->error('Not authorized.', position: 'toast-bottom'); return; }

        $participants = EventApplicantDefense::where('event_id', $event->id)->get();
        if ($participants->isEmpty()) { $this->warning('No participants to assign.', position: 'toast-bottom'); return; }

        $spaces   = EventSpace::orderBy('code')->pluck('id')->toArray();
        $sessions = EventSession::orderBy('time')->pluck('id')->toArray();

        if (empty($spaces) || empty($sessions)) {
            $this->error('No rooms or sessions available.', position: 'toast-bottom'); return;
        }

        foreach ($participants as $index => $p) {
            $p->update([
                'space_id'   => $spaces[$index % count($spaces)],
                'session_id' => $sessions[$index % count($sessions)],
            ]);
        }

        $this->success('Schedules assigned to all participants.', position: 'toast-bottom');
    }

    public function with(): array
    {
        $programId  = $this->getProgramId();
        $clusterIds = $this->getClusterIds();

        $event = Event::where('id', $this->eventId)
            ->whereIn('program_id', $clusterIds)
            ->whereHas('type', fn($q) => $q->where('code', 'PRE'))
            ->with(['type', 'program'])
            ->first();

        if (!$event) {
            return ['event' => null, 'participants' => collect(), 'eventLabel' => '', 'hasUnpublished' => false, 'isOwn' => false];
        }

        $isOwn         = $event->program_id === $programId;
        $formattedDate = Carbon::parse($event->event_date)->format('dmy');
        $eventLabel    = 'PRE-' . $formattedDate . '-' . $event->id;
        $programLabel  = $event->program ? ($event->program->code . '.' . $event->program->abbrev) : '';

        $participants = EventApplicantDefense::where('event_id', $this->eventId)
            ->with([
                'research.student.program',
                'research.supervisor.staff',
                'defenseExaminer.staff',
                'space',
                'session',
            ])
            ->get()
            ->map(fn($p) => [
                'id'             => $p->id,
                'publish'        => $p->publish,
                'student_name'   => trim(($p->research?->student?->first_name ?? '') . ' ' . ($p->research?->student?->last_name ?? '')),
                'student_number' => $p->research?->student?->nim ?? '-',
                'title'          => $p->research?->title ?? '-',
                'space'          => $p->space?->code ?? null,
                'session'        => $p->session?->time ?? null,
                'examiners'      => $p->defenseExaminer->map(fn($e) => trim(($e->staff?->first_name ?? '') . ' ' . ($e->staff?->last_name ?? '')))->filter()->implode(', '),
            ]);

        $hasUnpublished = $isOwn && $participants->contains('publish', 0);

        return [
            'event'          => $event,
            'eventLabel'     => $eventLabel,
            'programLabel'   => $programLabel,
            'participants'   => $participants,
            'hasUnpublished' => $hasUnpublished,
            'isOwn'          => $isOwn,
        ];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">
        {{ $isOwn ? 'Pre-Defense Participants' : ($programLabel ?: 'Other Program') }}
    </p>

    @if(!$event)
        <div class="py-16 text-center">
            <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
            <p class="text-sm text-gray-500">Event not found.</p>
        </div>
    @else
        <div class="px-3 py-3 space-y-2 pb-28">
            @forelse($participants as $p)
                @php $cardClass = "block rounded-2xl bg-white shadow-sm overflow-hidden border-l-4 " . ($p['publish'] ? 'border-green-400' : 'border-amber-400') . " p-4 transition-shadow"; @endphp
                @if($isOwn)
                    <a href="{{ route('specialization.defense.pre-defense.participant', [$eventId, $p['id']]) }}"
                       class="{{ $cardClass }} hover:shadow-md active:scale-[0.99]">
                @else
                    <div class="{{ $cardClass }}">
                @endif
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <p class="text-[11px] font-bold text-purple-600">{{ $p['student_number'] }}</p>
                            <p class="font-bold text-sm text-gray-800 leading-tight truncate mt-0.5">{{ $p['student_name'] ?: '—' }}</p>
                            <p class="text-xs text-gray-400 mt-0.5 line-clamp-1 uppercase">{{ $p['title'] }}</p>
                            <div class="flex items-center gap-3 mt-2">
                                <div class="flex items-center gap-1.5">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full {{ $p['space'] ? 'bg-purple-100' : 'bg-gray-100' }}">
                                        <x-icon name="o-map-pin" class="h-3 w-3 {{ $p['space'] ? 'text-purple-500' : 'text-gray-300' }}" />
                                    </div>
                                    <span class="text-[11px] font-bold {{ $p['space'] ? 'text-purple-700' : 'text-gray-300' }}">
                                        {{ $p['space'] ?? '—' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full {{ $p['session'] ? 'bg-blue-100' : 'bg-gray-100' }}">
                                        <x-icon name="o-clock" class="h-3 w-3 {{ $p['session'] ? 'text-blue-500' : 'text-gray-300' }}" />
                                    </div>
                                    <span class="text-[11px] font-bold {{ $p['session'] ? 'text-blue-700' : 'text-gray-300' }}">
                                        {{ $p['session'] ?? '—' }}
                                    </span>
                                </div>
                            </div>
                            @if($p['examiners'])
                                <div class="flex items-center gap-1 mt-1.5">
                                    <x-icon name="o-clipboard-document-check" class="h-3 w-3 text-orange-400 shrink-0" />
                                    <span class="text-[10px] text-orange-600 font-medium line-clamp-1">{{ $p['examiners'] }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="shrink-0 flex flex-col items-end gap-2">
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full {{ $p['publish'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $p['publish'] ? 'Published' : 'Draft' }}
                            </span>
                            @if($isOwn)
                                <x-icon name="o-chevron-right" class="h-4 w-4 text-gray-300" />
                            @endif
                        </div>
                    </div>
                @if($isOwn)
                    </a>
                @else
                    </div>
                @endif
            @empty
                <div class="py-16 text-center">
                    <x-icon name="o-inbox" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                    <p class="text-sm text-gray-400">No participants found.</p>
                </div>
            @endforelse
        </div>

        @if($isOwn)
        {{-- ─── Always-visible FAB column ─── --}}
        <div class="fixed bottom-20 left-1/2 -translate-x-1/2 w-full max-w-sm z-30 flex flex-col items-end gap-3 pr-4 pointer-events-none">
        <div class="flex flex-col items-end gap-3 pointer-events-auto">

            {{-- Assign Schedule --}}
            <div class="flex items-center gap-2">
                <span class="bg-white text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-full shadow-lg">Capture</span>
                <button wire:click="randomizeSchedule"
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-500 text-white shadow-lg hover:bg-indigo-600 active:scale-95 transition-all">
                    <x-icon name="o-arrows-right-left" class="h-5 w-5" />
                </button>
            </div>

            {{-- Export PDF --}}
            <div class="flex items-center gap-2">
                <span class="bg-white text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-full shadow-lg">Export PDF</span>
                <a href="{{ route('specialization.defense.pre-defense.event.pdf', $eventId) }}" target="_blank"
                   class="flex h-12 w-12 items-center justify-center rounded-full bg-rose-500 text-white shadow-lg hover:bg-rose-600 active:scale-95 transition-all">
                    <x-icon name="o-document-arrow-down" class="h-5 w-5" />
                </a>
            </div>

            {{-- Publish All (only if has unpublished) --}}
            @if($hasUnpublished)
            <div class="flex items-center gap-2">
                <span class="bg-white text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-full shadow-lg">Publish All</span>
                <button wire:click="publish"
                    class="flex h-12 w-12 items-center justify-center rounded-full bg-teal-500 text-white shadow-lg hover:bg-teal-600 active:scale-95 transition-all">
                    <x-icon name="o-paper-airplane" class="h-5 w-5" />
                </button>
            </div>
            @endif

        </div>
        </div>
        @endif {{-- end isOwn FABs --}}

    @endif
</div>
