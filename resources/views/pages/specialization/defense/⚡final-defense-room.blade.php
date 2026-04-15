<?php

use App\Models\ArSys\EventApplicantFinalDefense;
use App\Models\ArSys\EventSession;
use App\Models\ArSys\EventSpace;
use App\Models\ArSys\FinalDefenseExaminer;
use App\Models\ArSys\FinalDefenseRoom;
use App\Models\ArSys\Staff;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Room Detail')] class extends Component
{
    use Toast;

    public int    $eventId        = 0;
    public int    $roomId         = 0;
    public bool   $spaceSheet  = false;
    public bool   $sessionSheet = false;
    public bool   $addExSheet  = false;
    public string $staffSearch = '';
    public int    $spaceId     = 0;
    public int    $sessionId   = 0;
    public int    $moderatorId = 0;

    public function mount(int $eventId, int $roomId): void
    {
        $this->eventId = $eventId;
        $this->roomId  = $roomId;

        $room = FinalDefenseRoom::find($roomId);
        $this->spaceId     = $room?->space_id     ?? 0;
        $this->sessionId   = $room?->session_id   ?? 0;
        $this->moderatorId = $room?->moderator_id ?? 0;
    }

    private function getProgramId(): ?int
    {
        return auth()->user()->staff?->program_id;
    }

    private function getRoom(): ?FinalDefenseRoom
    {
        return FinalDefenseRoom::whereHas('event', fn($q) => $q->where('program_id', $this->getProgramId()))
            ->find($this->roomId);
    }

    public function saveSpace(int $spaceId): void
    {
        $room = $this->getRoom();
        if (!$room) return;
        $room->update(['space_id' => $spaceId ?: null]);
        $this->spaceId    = $spaceId;
        $this->spaceSheet = false;
        $this->success('Room updated.', position: 'toast-bottom');
    }

    public function saveSession(int $sessionId): void
    {
        $room = $this->getRoom();
        if (!$room) return;
        $room->update(['session_id' => $sessionId ?: null]);
        $this->sessionId    = $sessionId;
        $this->sessionSheet = false;
        $this->success('Session updated.', position: 'toast-bottom');
    }

    public function publish(): void
    {
        $room = $this->getRoom();
        if (!$room) return;

        EventApplicantFinalDefense::where('room_id', $room->id)->update(['publish' => 1]);
        $this->success('Schedules published.', position: 'toast-bottom');
    }

    public function setModerator(int $staffId): void
    {
        $room = $this->getRoom();
        if (!$room) return;

        if ($room->moderator_id === $staffId) {
            $room->update(['moderator_id' => null]);
            $this->moderatorId = 0;
            $this->warning('Moderator removed.', position: 'toast-bottom');
        } else {
            $room->update(['moderator_id' => $staffId]);
            $this->moderatorId = $staffId;
            $this->success('Moderator assigned.', position: 'toast-bottom');
        }
    }

    public function addExaminer(int $staffId): void
    {
        $room = $this->getRoom();
        if (!$room) { $this->error('Room not found.', position: 'toast-bottom'); return; }

        if (FinalDefenseExaminer::where('room_id', $room->id)->where('examiner_id', $staffId)->exists()) {
            $this->warning('Staff already assigned as examiner.', position: 'toast-bottom'); return;
        }

        $eventDate     = $room->event->event_date;
        $existingCount = FinalDefenseExaminer::where('examiner_id', $staffId)
            ->whereHas('room.event', fn($q) => $q->where('event_date', $eventDate))
            ->count();

        if ($existingCount >= 2) {
            $this->warning('Examiner already scheduled in 2 rooms on this date.', position: 'toast-bottom'); return;
        }

        FinalDefenseExaminer::create([
            'room_id'     => $room->id,
            'event_id'    => $room->event_id,
            'examiner_id' => $staffId,
        ]);

        $this->addExSheet  = false;
        $this->staffSearch = '';
        $this->success('Examiner added.' . ($existingCount === 1 ? ' (Note: already in 1 other room today)' : ''), position: 'toast-bottom');
    }

    public function removeExaminer(int $staffId): void
    {
        FinalDefenseExaminer::whereHas('room.event', fn($q) => $q->where('program_id', $this->getProgramId()))
            ->where('room_id', $this->roomId)
            ->where('examiner_id', $staffId)
            ->delete();

        $this->success('Examiner removed.', position: 'toast-bottom');
    }

    private function searchStaff(string $query): \Illuminate\Support\Collection
    {
        $programId = $this->getProgramId();
        if (!$programId || strlen(trim($query)) < 2) return collect();

        return Staff::where('program_id', $programId)
            ->where(fn($q) => $q
                ->where('first_name', 'like', "%{$query}%")
                ->orWhere('last_name',  'like', "%{$query}%")
                ->orWhere('code',       'like', "%{$query}%"))
            ->limit(10)->get()
            ->map(fn($s) => ['id' => $s->id, 'name' => trim($s->first_name . ' ' . $s->last_name), 'code' => $s->code ?? '', 'initial' => strtoupper(substr($s->first_name ?? 'S', 0, 1))]);
    }

    public function with(): array
    {
        $room = FinalDefenseRoom::with([
            'space', 'session', 'moderator',
            'examiner.staff',
            'applicant.research.student',
            'event',
        ])->whereHas('event', fn($q) => $q->where('program_id', $this->getProgramId()))
          ->find($this->roomId);

        if (!$room) {
            return ['room' => null, 'spaces' => collect(), 'sessions' => collect(), 'staffResults' => collect()];
        }

        $eventDate = $room->event?->event_date;

        $currentModeratorId = $room->moderator_id;

        $examiners = $room->examiner->map(function ($e) use ($eventDate, $currentModeratorId) {
            $otherRooms    = FinalDefenseExaminer::where('examiner_id', $e->examiner_id)
                ->where('room_id', '!=', $this->roomId)
                ->whereHas('room.event', fn($q) => $q->where('event_date', $eventDate))
                ->with('room.event.program')
                ->get();
            $otherPrograms = $otherRooms->map(fn($r) => $r->room?->event?->program?->abbrev)->filter()->unique()->values()->all();
            $name = trim(($e->staff?->first_name ?? '') . ' ' . ($e->staff?->last_name ?? ''));
            return [
                'id'             => $e->id,
                'staff_id'       => $e->examiner_id,
                'name'           => $name,
                'code'           => $e->staff?->code ?? '',
                'initial'        => strtoupper(substr($e->staff?->first_name ?? 'E', 0, 1)),
                'is_moderator'   => $e->examiner_id === $currentModeratorId,
                'other_programs' => $otherPrograms,
            ];
        })->values();

        $participants = $room->applicant->map(fn($p) => [
            'id'      => $p->id,
            'publish' => $p->publish,
            'name'    => trim(($p->research?->student?->first_name ?? '') . ' ' . ($p->research?->student?->last_name ?? '')),
            'number'  => $p->research?->student?->nim ?? '-',
            'title'   => $p->research?->title ?? '-',
        ])->values();

        $hasUnpublished = $participants->contains('publish', 0);

        return [
            'room' => [
                'id'           => $room->id,
                'space_id'     => $room->space_id,
                'space'        => $room->space?->code,
                'session_id'   => $room->session_id,
                'session'      => $room->session ? $room->session->time . ($room->session->day ? ' (' . $room->session->day . ')' : '') : null,
                'moderator_id' => $room->moderator_id,
                'moderator'    => $room->moderator ? trim($room->moderator->first_name . ' ' . $room->moderator->last_name) : null,
                'examiners'    => $examiners,
                'participants' => $participants,
            ],
            'spaces'         => EventSpace::orderBy('code')->get(['id', 'code']),
            'sessions'       => EventSession::orderBy('time')->get(['id', 'time', 'day']),
            'staffResults'   => $this->searchStaff($this->staffSearch),
            'hasUnpublished' => $hasUnpublished,
        ];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">Final Defense Room</p>

    @if(!$room)
        <div class="py-16 text-center">
            <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
            <p class="text-sm text-gray-500">Room not found.</p>
        </div>
    @else
        <div class="px-3 py-3 space-y-3 pb-28">

            {{-- ─── Room Info ─── --}}
            <div class="rounded-xl bg-white shadow-sm overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-2.5 bg-purple-50 border-b border-purple-100">
                    <x-icon name="o-cog-6-tooth" class="h-3.5 w-3.5 text-purple-500 shrink-0" />
                    <p class="text-xs font-bold text-purple-700">Room Settings</p>
                </div>

                {{-- Space (Room) --}}
                <button wire:click="$set('spaceSheet', true)"
                    class="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 active:bg-gray-100 transition-colors text-left border-b border-gray-100 border-l-4 {{ $room['space'] ? 'border-l-orange-400' : 'border-l-gray-200' }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Room / Space</p>
                        <p class="text-sm font-bold {{ $room['space'] ? 'text-gray-800' : 'text-gray-300' }} mt-0.5">
                            {{ $room['space'] ?? 'Tap to select' }}
                        </p>
                    </div>
                    <x-icon name="o-pencil-square" class="h-4 w-4 text-gray-300 shrink-0" />
                </button>

                {{-- Session --}}
                <button wire:click="$set('sessionSheet', true)"
                    class="w-full flex items-center gap-3 px-4 py-3.5 hover:bg-gray-50 active:bg-gray-100 transition-colors text-left border-b border-gray-100 border-l-4 {{ $room['session'] ? 'border-l-indigo-400' : 'border-l-gray-200' }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Session</p>
                        <p class="text-sm font-bold {{ $room['session'] ? 'text-gray-800' : 'text-gray-300' }} mt-0.5">
                            {{ $room['session'] ?? 'Tap to select' }}
                        </p>
                    </div>
                    <x-icon name="o-pencil-square" class="h-4 w-4 text-gray-300 shrink-0" />
                </button>

                {{-- Moderator (read-only, assigned via examiner icon tap) --}}
                <div class="flex items-start gap-3 px-4 py-3.5 border-l-4 {{ $room['moderator'] ? 'border-l-blue-400' : 'border-l-gray-200' }}">
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Moderator</p>
                        <p class="text-sm font-bold {{ $room['moderator'] ? 'text-gray-800' : 'text-gray-300' }} mt-0.5">
                            {{ $room['moderator'] ?? 'Not assigned' }}
                        </p>
                        <p class="text-[10px] text-gray-400 mt-1 italic">Tap on an examiner's icon to assign as moderator.</p>
                    </div>
                </div>
            </div>

            {{-- ─── Examiners ─── --}}
            <div class="rounded-xl bg-white shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2.5 bg-orange-50 border-b border-orange-100">
                    <div class="flex items-center gap-2">
                        <x-icon name="o-clipboard-document-check" class="h-3.5 w-3.5 text-orange-500 shrink-0" />
                        <p class="text-xs font-bold text-orange-700">Examiners</p>
                    </div>
                    <button wire:click="$set('addExSheet', true); $set('staffSearch', '')"
                        class="flex items-center gap-1 text-[11px] font-semibold text-orange-600 hover:text-orange-800">
                        <x-icon name="o-plus" class="h-3.5 w-3.5" />Add
                    </button>
                </div>
                @forelse($room['examiners'] as $ex)
                    <div wire:key="ex-{{ $ex['id'] }}" class="flex items-center gap-3 px-4 py-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                        {{-- Avatar icon: tappable to set/unset moderator --}}
                        <button wire:click="setModerator({{ $ex['staff_id'] }})"
                            class="shrink-0 flex h-9 w-9 items-center justify-center rounded-full transition-all active:scale-95
                                {{ $ex['is_moderator'] ? 'bg-blue-500 text-white shadow-md' : 'bg-gray-100 text-gray-400 hover:bg-blue-50 hover:text-blue-400' }}">
                            @if($ex['is_moderator'])
                                <span class="text-sm font-bold">{{ $ex['initial'] }}</span>
                            @else
                                <x-icon name="o-user" class="h-4 w-4" />
                            @endif
                        </button>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $ex['name'] ?: '—' }}</p>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                @if($ex['code'])
                                    <span class="text-[10px] text-gray-400">{{ $ex['code'] }}</span>
                                @endif
                                @if($ex['is_moderator'])
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-blue-100 text-blue-700">Moderator</span>
                                @endif
                                @foreach($ex['other_programs'] as $prog)
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700">{{ $prog }}</span>
                                @endforeach
                            </div>
                        </div>
                        <button wire:click="removeExaminer({{ $ex['staff_id'] }})"
                            class="shrink-0 flex h-7 w-7 items-center justify-center rounded-full bg-red-50 hover:bg-red-100">
                            <x-icon name="o-x-mark" class="h-3.5 w-3.5 text-red-400" />
                        </button>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center">
                        <p class="text-xs text-gray-400">No examiners assigned.</p>
                    </div>
                @endforelse
            </div>

            {{-- ─── Participants ─── --}}
            <div class="rounded-xl bg-white shadow-sm overflow-hidden">
                <div class="flex items-center gap-2 px-4 py-2.5 bg-indigo-50 border-b border-indigo-100">
                    <x-icon name="o-users" class="h-3.5 w-3.5 text-indigo-500 shrink-0" />
                    <p class="text-xs font-bold text-indigo-700">Participants ({{ $room['participants']->count() }})</p>
                </div>
                @forelse($room['participants'] as $p)
                    <div wire:key="p-{{ $p['id'] }}" class="flex items-start gap-3 px-4 py-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                        <div class="flex-1 min-w-0">
                            <p class="text-[10px] font-bold text-purple-600">{{ $p['number'] }}</p>
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $p['name'] ?: '—' }}</p>
                            <p class="text-[10px] text-gray-400 mt-0.5 uppercase line-clamp-1">{{ $p['title'] }}</p>
                        </div>
                        <span class="shrink-0 text-[9px] font-bold px-2 py-0.5 rounded-full {{ $p['publish'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $p['publish'] ? 'Pub' : 'Draft' }}
                        </span>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center">
                        <p class="text-xs text-gray-400">No participants in this room.</p>
                    </div>
                @endforelse
            </div>

        </div>
        {{-- ─── FAB: Publish ─── --}}
        @if($hasUnpublished && $room['participants']->isNotEmpty())
        <div class="fixed bottom-20 left-1/2 -translate-x-1/2 w-full max-w-sm z-30 flex flex-col items-end gap-3 pr-4 pointer-events-none">
            <div class="flex flex-col items-end gap-3 pointer-events-auto">
                <div class="flex items-center gap-2">
                    <span class="bg-white text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-full shadow-lg">Publish All</span>
                    <button wire:click="publish"
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-teal-500 text-white shadow-lg hover:bg-teal-600 active:scale-95 transition-all">
                        <x-icon name="o-paper-airplane" class="h-5 w-5" />
                    </button>
                </div>
            </div>
        </div>
        @endif

    @endif

    {{-- ─── Space Sheet ─── --}}
    <div x-data x-show="$wire.spaceSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('spaceSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.spaceSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl max-h-[60vh] flex flex-col">
        <div class="flex justify-center pt-3 pb-1 shrink-0"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2">
                <x-icon name="o-map-pin" class="h-4 w-4 text-orange-500" />
                <h3 class="text-base font-bold text-gray-800">Select Room</h3>
            </div>
            <button wire:click="$set('spaceSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="overflow-y-auto flex-1 pb-24">
            @foreach($spaces as $space)
                <button wire:click="saveSpace({{ $space->id }})"
                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-orange-50 active:bg-orange-100 transition-colors text-left border-b border-gray-50 last:border-0">
                    <p class="text-sm font-semibold {{ $spaceId === $space->id ? 'text-orange-600 font-bold' : 'text-gray-800' }}">{{ $space->code }}</p>
                    @if($spaceId === $space->id)
                        <x-icon name="s-check-circle" class="h-5 w-5 text-orange-500 shrink-0" />
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- ─── Session Sheet ─── --}}
    <div x-data x-show="$wire.sessionSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('sessionSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.sessionSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl max-h-[60vh] flex flex-col">
        <div class="flex justify-center pt-3 pb-1 shrink-0"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2">
                <x-icon name="o-clock" class="h-4 w-4 text-indigo-500" />
                <h3 class="text-base font-bold text-gray-800">Select Session</h3>
            </div>
            <button wire:click="$set('sessionSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="overflow-y-auto flex-1 pb-24">
            @foreach($sessions as $session)
                <button wire:click="saveSession({{ $session->id }})"
                    class="w-full flex items-center justify-between px-4 py-3 hover:bg-indigo-50 active:bg-indigo-100 transition-colors text-left border-b border-gray-50 last:border-0">
                    <p class="text-sm font-semibold {{ $sessionId === $session->id ? 'text-indigo-600 font-bold' : 'text-gray-800' }}">
                        {{ $session->time }}{{ $session->day ? ' (' . $session->day . ')' : '' }}
                    </p>
                    @if($sessionId === $session->id)
                        <x-icon name="s-check-circle" class="h-5 w-5 text-indigo-500 shrink-0" />
                    @endif
                </button>
            @endforeach
        </div>
    </div>

    {{-- ─── Add Examiner Bottom Sheet ─── --}}
    <div x-data x-show="$wire.addExSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('addExSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.addExSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl max-h-[70vh] flex flex-col">
        <div class="flex justify-center pt-3 pb-1 shrink-0"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2">
                <x-icon name="o-user-plus" class="h-4 w-4 text-orange-500" />
                <h3 class="text-base font-bold text-gray-800">Add Examiner</h3>
            </div>
            <button wire:click="$set('addExSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="px-4 pt-3 pb-2 shrink-0">
            <div class="relative">
                <x-icon name="o-magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input wire:model.live.debounce.300ms="staffSearch" type="text" placeholder="Search name or code..."
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-3 text-sm focus:border-orange-400 focus:outline-none" />
            </div>
        </div>
        <div class="overflow-y-auto flex-1 pb-24">
            @if(strlen(trim($staffSearch)) < 2)
                <div class="py-10 text-center">
                    <x-icon name="o-magnifying-glass" class="mx-auto mb-2 h-8 w-8 text-gray-200" />
                    <p class="text-xs text-gray-400">Type min. 2 chars to search.</p>
                </div>
            @elseif($staffResults->isEmpty())
                <div class="py-10 text-center">
                    <x-icon name="o-user-slash" class="mx-auto mb-2 h-8 w-8 text-gray-200" />
                    <p class="text-xs text-gray-400">No staff found.</p>
                </div>
            @else
                @foreach($staffResults as $staff)
                    <button wire:click="addExaminer({{ $staff['id'] }})"
                        class="w-full flex items-center gap-3 px-4 py-3 hover:bg-orange-50 active:bg-orange-100 transition-colors text-left border-b border-gray-50 last:border-0">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-orange-100 text-sm font-bold text-orange-600">
                            {{ strtoupper(substr($staff['name'] ?: 'S', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $staff['name'] }}</p>
                            @if($staff['code'])
                                <p class="text-[11px] text-gray-400">{{ $staff['code'] }}</p>
                            @endif
                        </div>
                        <x-icon name="o-plus-circle" class="h-5 w-5 text-orange-400 shrink-0" />
                    </button>
                @endforeach
            @endif
        </div>
    </div>
</div>
