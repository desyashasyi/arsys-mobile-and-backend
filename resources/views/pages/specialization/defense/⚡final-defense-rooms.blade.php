<?php

use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantFinalDefense;
use App\Models\ArSys\FinalDefenseRoom;
use App\Models\ArSys\Program;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Final Defense Rooms')] class extends Component
{
    use Toast;

    public int   $eventId               = 0;
    public bool  $participantSheet      = false;
    public bool  $transferSheet         = false;
    public int   $selectedParticipantId = 0;
    public bool  $selectedIsAssigned    = false;
    public array $selectedParticipant   = [];

    public function mount(int $id): void
    {
        $this->eventId = $id;
    }

    private function getProgramId(): ?int
    {
        return auth()->user()->staff?->program_id;
    }

    public function addRoom(): void
    {
        $event = Event::where('id', $this->eventId)->where('program_id', $this->getProgramId())->first();
        if (!$event) { $this->error('Event not found.', position: 'toast-bottom'); return; }

        FinalDefenseRoom::create(['event_id' => $event->id]);
        $this->success('Room added.', position: 'toast-bottom');
    }

    public function deleteRoom(int $roomId): void
    {
        $room = FinalDefenseRoom::whereHas('event', fn($q) => $q->where('program_id', $this->getProgramId()))
            ->find($roomId);
        if (!$room) return;

        if ($room->examiner()->exists() || $room->applicant()->exists()) {
            $this->warning('Cannot delete room with participants or examiners.', position: 'toast-bottom'); return;
        }

        $room->delete();
        $this->success('Room deleted.', position: 'toast-bottom');
    }

    public function openParticipantSheet(int $participantId, bool $isAssigned): void
    {
        $p = EventApplicantFinalDefense::with('research.student')->find($participantId);
        if (!$p) return;

        $this->selectedParticipantId = $participantId;
        $this->selectedIsAssigned    = $isAssigned;
        $this->selectedParticipant   = [
            'name'   => trim(($p->research?->student?->first_name ?? '') . ' ' . ($p->research?->student?->last_name ?? '')),
            'number' => $p->research?->student?->nim ?? '-',
            'title'  => $p->research?->title ?? '',
        ];
        $this->participantSheet = true;
    }

    public function assignToRoom(int $roomId): void
    {
        $room = FinalDefenseRoom::whereHas('event', fn($q) => $q->where('program_id', $this->getProgramId()))
            ->find($roomId);
        if (!$room) return;

        EventApplicantFinalDefense::where('event_id', $room->event_id)
            ->where('id', $this->selectedParticipantId)
            ->update(['room_id' => $room->id]);

        $this->participantSheet = false;
        $this->success('Participant assigned to room.', position: 'toast-bottom');
    }

    public function unassignSelected(): void
    {
        EventApplicantFinalDefense::whereHas('room.event', fn($q) => $q->where('program_id', $this->getProgramId()))
            ->where('id', $this->selectedParticipantId)
            ->update(['room_id' => null]);

        $this->participantSheet = false;
        $this->success('Participant unassigned.', position: 'toast-bottom');
    }

    public function openTransferSheet(): void
    {
        $this->participantSheet = false;
        $this->transferSheet    = true;
    }

    public function transferParticipant(int $targetEventId): void
    {
        $p = EventApplicantFinalDefense::whereHas('event', fn($q) => $q->where('program_id', $this->getProgramId()))
            ->find($this->selectedParticipantId);
        if (!$p) { $this->error('Participant not found.', position: 'toast-bottom'); return; }

        $p->update(['event_id' => $targetEventId, 'room_id' => null, 'publish' => 0]);

        $this->transferSheet = false;
        $this->success('Participant transferred.', position: 'toast-bottom');
    }

    public function publish(): void
    {
        $event = Event::where('id', $this->eventId)->where('program_id', $this->getProgramId())->first();
        if (!$event) { $this->error('Event not found.', position: 'toast-bottom'); return; }

        EventApplicantFinalDefense::where('event_id', $event->id)
            ->whereNotNull('room_id')
            ->update(['publish' => 1]);

        $this->success('Schedules published.', position: 'toast-bottom');
    }

    public function with(): array
    {
        $programId = $this->getProgramId();

        $event = Event::where('id', $this->eventId)
            ->where('program_id', $programId)
            ->whereHas('type', fn($q) => $q->where('code', 'PUB'))
            ->first();

        if (!$event) {
            return ['event' => null, 'rooms' => collect(), 'unassigned' => collect(), 'eventLabel' => '', 'eventDate' => '', 'hasUnpublished' => false, 'upcomingEvents' => collect()];
        }

        $eventLabel = 'PUB-' . Carbon::parse($event->event_date)->format('dmy') . '-' . $event->id;
        $eventDate  = Carbon::parse($event->event_date)->isoFormat('dddd, D MMMM YYYY');

        $rooms = FinalDefenseRoom::where('event_id', $this->eventId)
            ->with(['space', 'session', 'moderator', 'examiner.staff', 'applicant.research.student'])
            ->get()
            ->map(fn($r, $i) => [
                'id'             => $r->id,
                'index'          => $i,
                'space'          => $r->space?->code,
                'session'        => $r->session?->time,
                'moderator'      => $r->moderator ? trim($r->moderator->first_name . ' ' . $r->moderator->last_name) : null,
                'examiner_count' => $r->examiner->count(),
                'can_delete'     => $r->examiner->isEmpty() && $r->applicant->isEmpty(),
                'participants'   => $r->applicant->map(fn($p) => [
                    'id'      => $p->id,
                    'publish' => $p->publish,
                    'name'    => trim(($p->research?->student?->first_name ?? '') . ' ' . ($p->research?->student?->last_name ?? '')),
                    'number'  => $p->research?->student?->nim ?? '-',
                    'title'   => $p->research?->title ?? '',
                ])->values(),
            ]);

        $unassigned = EventApplicantFinalDefense::where('event_id', $this->eventId)
            ->whereNull('room_id')
            ->with(['research.student'])
            ->get()
            ->map(fn($p) => [
                'id'     => $p->id,
                'name'   => trim(($p->research?->student?->first_name ?? '') . ' ' . ($p->research?->student?->last_name ?? '')),
                'number' => $p->research?->student?->nim ?? '-',
                'title'  => $p->research?->title ?? '',
            ]);

        $hasUnpublished = EventApplicantFinalDefense::where('event_id', $this->eventId)
            ->whereNotNull('room_id')->where('publish', 0)->exists();

        // Upcoming PUB events for transfer (same program, future dates, excluding current)
        $upcomingEvents = Event::where('program_id', $programId)
            ->whereHas('type', fn($q) => $q->where('code', 'PUB'))
            ->where('id', '!=', $this->eventId)
            ->where('event_date', '>=', now()->format('Y-m-d'))
            ->orderBy('event_date')
            ->get()
            ->map(fn($e) => [
                'id'    => $e->id,
                'label' => 'PUB-' . Carbon::parse($e->event_date)->format('dmy') . '-' . $e->id,
                'date'  => Carbon::parse($e->event_date)->isoFormat('D MMMM YYYY'),
            ]);

        return [
            'event'          => $event,
            'eventLabel'     => $eventLabel,
            'eventDate'      => $eventDate,
            'rooms'          => $rooms,
            'unassigned'     => $unassigned,
            'hasUnpublished' => $hasUnpublished,
            'upcomingEvents' => $upcomingEvents,
        ];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">{{ $eventDate }}</p>

    @if(!$event)
        <div class="py-16 text-center">
            <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
            <p class="text-sm text-gray-500">Event not found.</p>
        </div>
    @else

        {{-- Unpublished warning banner --}}
        @if($hasUnpublished)
            <div class="mx-3 mt-3 flex items-center gap-2 rounded-xl bg-amber-50 border border-amber-200 px-3 py-2.5">
                <x-icon name="o-exclamation-triangle" class="h-4 w-4 text-amber-500 shrink-0" />
                <p class="text-xs font-semibold text-amber-700">Some participant schedules are not yet published.</p>
            </div>
        @endif

        {{-- ─── Rooms ─── --}}
        @php
            $roomColors = [
                ['border' => 'border-l-purple-500',  'header' => 'bg-purple-50 border-purple-100',  'text' => 'text-purple-700',  'icon' => 'text-purple-400',  'swap' => 'text-purple-400'],
                ['border' => 'border-l-indigo-500',  'header' => 'bg-indigo-50 border-indigo-100',  'text' => 'text-indigo-700',  'icon' => 'text-indigo-400',  'swap' => 'text-indigo-400'],
                ['border' => 'border-l-violet-500',  'header' => 'bg-violet-50 border-violet-100',  'text' => 'text-violet-700',  'icon' => 'text-violet-400',  'swap' => 'text-violet-400'],
                ['border' => 'border-l-fuchsia-500', 'header' => 'bg-fuchsia-50 border-fuchsia-100','text' => 'text-fuchsia-700', 'icon' => 'text-fuchsia-400', 'swap' => 'text-fuchsia-400'],
            ];
        @endphp

        <div class="px-3 py-3 space-y-3 pb-36">
            @forelse($rooms as $room)
                @php $c = $roomColors[$room['index'] % 4]; @endphp
                <div class="rounded-2xl bg-white shadow-sm overflow-hidden border-l-4 {{ $c['border'] }}">
                    {{-- Room header --}}
                    <div class="px-4 py-3 {{ $c['header'] }} border-b flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="font-bold text-sm {{ $c['text'] }}">Room {{ $room['index'] + 1 }}</p>
                                @if($room['participants']->isNotEmpty())
                                    @php $unpub = $room['participants']->where('publish', 0)->count(); @endphp
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full {{ $unpub === 0 ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                        {{ $unpub === 0 ? 'Published' : $unpub . ' unpublished' }}
                                    </span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2 mt-1.5">
                                <div class="flex items-center gap-1">
                                    <x-icon name="o-map-pin" class="h-3 w-3 {{ $room['space'] ? $c['icon'] : 'text-gray-300' }}" />
                                    <span class="text-[10px] font-semibold {{ $room['space'] ? $c['text'] : 'text-gray-300' }}">{{ $room['space'] ?? '—' }}</span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <x-icon name="o-clock" class="h-3 w-3 {{ $room['session'] ? $c['icon'] : 'text-gray-300' }}" />
                                    <span class="text-[10px] font-semibold {{ $room['session'] ? $c['text'] : 'text-gray-300' }}">{{ $room['session'] ?? '—' }}</span>
                                </div>
                                @if($room['moderator'])
                                    <div class="flex items-center gap-1">
                                        <x-icon name="o-user" class="h-3 w-3 text-blue-400" />
                                        <span class="text-[10px] font-semibold text-blue-600">{{ $room['moderator'] }}</span>
                                    </div>
                                @endif
                                <span class="text-[10px] font-medium text-gray-400">
                                    {{ $room['examiner_count'] }} examiners · {{ $room['participants']->count() }} participants
                                </span>
                            </div>
                        </div>
                        <div class="shrink-0 flex items-center gap-1.5">
                            @if($room['can_delete'])
                                <button wire:click="deleteRoom({{ $room['id'] }})"
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-red-50 hover:bg-red-100">
                                    <x-icon name="o-trash" class="h-3.5 w-3.5 text-red-400" />
                                </button>
                            @endif
                            <a href="{{ route('specialization.defense.final-defense.room', [$eventId, $room['id']]) }}"
                               class="flex h-7 w-7 items-center justify-center rounded-full bg-white/60 hover:bg-white border border-gray-200">
                                <x-icon name="o-cog-6-tooth" class="h-3.5 w-3.5 text-gray-500" />
                            </a>
                        </div>
                    </div>

                    {{-- Participants in room — tappable cards --}}
                    <div class="divide-y divide-gray-50">
                        @if($room['participants']->isEmpty())
                            <p class="text-xs text-gray-400 italic px-4 py-3 text-center">No participants — tap an unassigned participant below</p>
                        @else
                            @foreach($room['participants'] as $p)
                                <button wire:click="openParticipantSheet({{ $p['id'] }}, true)"
                                    class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 active:bg-gray-100 transition-colors text-left">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[10px] font-bold text-purple-600">{{ $p['number'] }}</p>
                                        <p class="text-xs font-semibold text-gray-800 truncate">{{ $p['name'] ?: '—' }}</p>
                                        @if($p['title'])
                                            <p class="text-[10px] text-gray-400 uppercase truncate">{{ $p['title'] }}</p>
                                        @endif
                                    </div>
                                    <div class="shrink-0 flex items-center gap-1.5">
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full {{ $p['publish'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                            {{ $p['publish'] ? 'Pub' : 'Draft' }}
                                        </span>
                                        <x-icon name="o-arrows-right-left" class="h-3.5 w-3.5 {{ $c['swap'] }}" />
                                    </div>
                                </button>
                            @endforeach
                        @endif
                    </div>
                </div>
            @empty
                <div class="py-12 text-center">
                    <x-icon name="o-inbox" class="mx-auto mb-3 h-10 w-10 text-gray-200" />
                    <p class="text-sm text-gray-400">No rooms yet. Tap + to add a room.</p>
                </div>
            @endforelse

            {{-- ─── Unassigned Participants ─── --}}
            @if($unassigned->isNotEmpty())
                <div class="rounded-2xl bg-white shadow-sm overflow-hidden border-l-4 border-l-amber-400">
                    <div class="flex items-center gap-2 px-4 py-2.5 bg-amber-50 border-b border-amber-100">
                        <x-icon name="o-exclamation-triangle" class="h-3.5 w-3.5 text-amber-500 shrink-0" />
                        <p class="text-xs font-bold text-amber-700">Unassigned ({{ $unassigned->count() }})</p>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($unassigned as $p)
                            <button wire:click="openParticipantSheet({{ $p['id'] }}, false)"
                                class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-amber-50 active:bg-amber-100 transition-colors text-left">
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-bold text-purple-600">{{ $p['number'] }}</p>
                                    <p class="text-xs font-semibold text-gray-800 truncate">{{ $p['name'] ?: '—' }}</p>
                                    @if($p['title'])
                                        <p class="text-[10px] text-gray-400 uppercase truncate">{{ $p['title'] }}</p>
                                    @endif
                                </div>
                                <x-icon name="o-arrow-right" class="h-4 w-4 text-amber-400 shrink-0" />
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- ─── Column FAB (always visible) ─── --}}
        <div class="fixed bottom-20 left-1/2 -translate-x-1/2 w-full max-w-sm z-30 flex flex-col items-end gap-3 pr-4 pointer-events-none">
            <div class="flex flex-col items-end gap-3 pointer-events-auto">

                {{-- PDF Export --}}
                <div class="flex items-center gap-2">
                    <span class="bg-white/90 text-gray-700 text-[11px] font-semibold px-3 py-1.5 rounded-full shadow-md border border-gray-100">Export PDF</span>
                    <a href="{{ route('specialization.defense.final-defense.rooms.pdf', $eventId) }}" target="_blank"
                       class="flex h-12 w-12 items-center justify-center rounded-full bg-rose-500 text-white shadow-lg hover:bg-rose-600 active:scale-95 transition-all">
                        <x-icon name="o-document-arrow-down" class="h-5 w-5" />
                    </a>
                </div>

                {{-- Publish --}}
                @if($hasUnpublished)
                    <div class="flex items-center gap-2">
                        <span class="bg-white/90 text-gray-700 text-[11px] font-semibold px-3 py-1.5 rounded-full shadow-md border border-gray-100">Publish All</span>
                        <button wire:click="publish"
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-teal-500 text-white shadow-lg hover:bg-teal-600 active:scale-95 transition-all">
                            <x-icon name="o-paper-airplane" class="h-5 w-5" />
                        </button>
                    </div>
                @endif

                {{-- Add Room --}}
                <button wire:click="addRoom" wire:loading.attr="disabled"
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-purple-600 text-white shadow-xl hover:bg-purple-700 active:scale-95 transition-all disabled:opacity-60">
                    <span wire:loading.remove wire:target="addRoom"><x-icon name="o-plus" class="h-6 w-6" /></span>
                    <span wire:loading wire:target="addRoom" class="loading loading-spinner loading-sm"></span>
                </button>

            </div>
        </div>

    @endif

    {{-- ─── Participant Action Sheet ─── --}}
    <div x-data x-show="$wire.participantSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('participantSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.participantSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl max-h-[75vh] flex flex-col">
        <div class="flex justify-center pt-3 pb-1 shrink-0"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-purple-100">
                    <x-icon name="o-building-office" class="h-4 w-4 text-purple-600" />
                </div>
                <h3 class="text-base font-bold text-gray-800">Select Room</h3>
            </div>
            <button wire:click="$set('participantSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>

        {{-- Participant info --}}
        @if(!empty($selectedParticipant))
            <div class="mx-4 mt-3 flex items-center gap-3 rounded-xl bg-purple-50 px-3 py-2.5 shrink-0">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-purple-200 text-sm font-bold text-purple-700">
                    {{ strtoupper(substr($selectedParticipant['name'] ?? 'P', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] font-semibold text-purple-500">{{ $selectedParticipant['number'] ?? '-' }}</p>
                    <p class="text-sm font-bold text-gray-800 truncate">{{ $selectedParticipant['name'] ?: '—' }}</p>
                    @if(!empty($selectedParticipant['title']))
                        <p class="text-[10px] text-gray-500 uppercase truncate">{{ $selectedParticipant['title'] }}</p>
                    @endif
                </div>
            </div>
        @endif

        {{-- Action buttons --}}
        <div class="flex gap-2 px-4 py-3 shrink-0">
            @if($selectedIsAssigned)
                <button wire:click="unassignSelected"
                    class="flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-xl border border-amber-300 text-amber-600 text-xs font-semibold hover:bg-amber-50 active:scale-95 transition-all">
                    <x-icon name="o-minus-circle" class="h-4 w-4" />Unassign
                </button>
            @endif
            @if(!$selectedIsAssigned)
            <button wire:click="openTransferSheet"
                class="flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-xl border border-indigo-300 text-indigo-600 text-xs font-semibold hover:bg-indigo-50 active:scale-95 transition-all">
                <x-icon name="o-arrows-right-left" class="h-4 w-4" />Transfer
            </button>
            @endif
        </div>

        <div class="border-t border-gray-100 shrink-0"></div>

        {{-- Room list --}}
        <div class="overflow-y-auto flex-1 pb-24">
            @if($rooms->isEmpty())
                <div class="py-8 text-center">
                    <x-icon name="o-inbox" class="mx-auto mb-2 h-8 w-8 text-gray-200" />
                    <p class="text-xs text-gray-400">No rooms available. Add a room first.</p>
                </div>
            @else
                @foreach($rooms as $room)
                    <button wire:click="assignToRoom({{ $room['id'] }})"
                        class="w-full flex items-center gap-3 px-4 py-3 hover:bg-purple-50 active:bg-purple-100 transition-colors text-left border-b border-gray-50 last:border-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-purple-100 text-sm font-bold text-purple-700">
                            {{ $room['index'] + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800">Room {{ $room['index'] + 1 }}</p>
                            <p class="text-[11px] text-gray-400">
                                {{ $room['space'] ?? 'No room' }} · {{ $room['session'] ?? 'No time' }} · {{ $room['participants']->count() }} participant(s)
                            </p>
                        </div>
                        <x-icon name="o-arrow-right" class="h-4 w-4 text-purple-400 shrink-0" />
                    </button>
                @endforeach
            @endif
        </div>
    </div>

    {{-- ─── Transfer Sheet ─── --}}
    <div x-data x-show="$wire.transferSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('transferSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.transferSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl max-h-[60vh] flex flex-col">
        <div class="flex justify-center pt-3 pb-1 shrink-0"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100">
                    <x-icon name="o-arrows-right-left" class="h-4 w-4 text-indigo-600" />
                </div>
                <h3 class="text-base font-bold text-gray-800">Transfer to Event</h3>
            </div>
            <button wire:click="$set('transferSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="overflow-y-auto flex-1 pb-24">
            @if($upcomingEvents->isEmpty())
                <div class="py-10 text-center">
                    <x-icon name="o-calendar-days" class="mx-auto mb-2 h-8 w-8 text-gray-200" />
                    <p class="text-xs text-gray-400">No upcoming final defense events found.</p>
                </div>
            @else
                @foreach($upcomingEvents as $ev)
                    <button wire:click="transferParticipant({{ $ev['id'] }})"
                        class="w-full flex items-center gap-3 px-4 py-3 hover:bg-indigo-50 active:bg-indigo-100 transition-colors text-left border-b border-gray-50 last:border-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100">
                            <x-icon name="o-calendar-days" class="h-4 w-4 text-indigo-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 uppercase">{{ $ev['label'] }}</p>
                            <p class="text-[11px] text-gray-400">{{ $ev['date'] }}</p>
                        </div>
                        <x-icon name="o-arrow-right" class="h-4 w-4 text-indigo-400 shrink-0" />
                    </button>
                @endforeach
            @endif
        </div>
    </div>
</div>
