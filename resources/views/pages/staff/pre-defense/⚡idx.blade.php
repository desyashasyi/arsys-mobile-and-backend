<?php

use App\Models\ArSys\Event;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mobile-app')] #[Title('Pre-Defense')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $staff = auth()->user()->staff;

        if (!$staff) {
            return ['events' => collect(), 'noStaff' => true];
        }

        $staffId = $staff->id;
        $search  = trim($this->search);

        $events = Event::where('status', 1)
            ->whereHas('type', function ($q) { $q->where('code', 'PRE'); })
            ->where(function ($q) use ($staffId) {
                $q->whereHas('examiners', function ($q) use ($staffId) {
                    $q->where('examiner_id', $staffId);
                })->orWhereHas('defenseApplicant.research.supervisor', function ($q) use ($staffId) {
                    $q->where('supervisor_id', $staffId);
                });
            })
            ->when($search, function ($q) use ($search) {
                $q->whereHas('defenseApplicant.research.student', function ($q2) use ($search) {
                    $q2->where(function ($q3) use ($search) {
                        $q3->where('first_name', 'like', "%{$search}%")
                           ->orWhere('last_name', 'like', "%{$search}%")
                           ->orWhere('number', 'like', "%{$search}%");
                    });
                });
            })
            ->with(['program', 'type', 'defenseApplicantPublish.research.student.program'])
            ->orderByDesc('id')
            ->paginate(10);

        return ['events' => $events, 'noStaff' => false];
    }


};
?>

<div>
    <p class="px-4 pt-2 text-xs text-purple-600 font-medium">Active pre-defense events</p>
    <div class="px-3 pt-2 pb-1">
        <input wire:model.live.debounce.300ms="search" type="search"
               placeholder="Search by student name or NIM..."
               class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:outline-none focus:border-purple-400" />
    </div>

    <div class="px-3 py-3 space-y-2">

        @if($noStaff)
            <div class="py-16 text-center">
                <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
                <p class="text-sm font-semibold text-gray-500">No staff record linked to your account.</p>
            </div>

        @else
        @forelse($events as $event)
            @php
                $applicants = $event->defenseApplicantPublish;
                $count = $applicants->count();
                $eventDate = $event->date ? \Carbon\Carbon::parse($event->date)->format('d M Y') : '—';
            @endphp

            <a href="{{ route('staff.pre-defense.event', $event->id) }}" class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4 border-orange-400 hover:bg-orange-50 transition-colors">
                <div class="p-3">

                    {{-- Event Code + Date --}}
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-mono font-bold text-sm text-gray-800">
                            PRE-{{ \Carbon\Carbon::parse($event->date ?? now())->format('dmy') }}-{{ $event->id }}
                        </span>
                        <span class="text-[11px] text-gray-400">{{ $eventDate }}</span>
                    </div>

                    {{-- Program --}}
                    @if($event->program)
                        <p class="text-xs font-semibold text-gray-600">{{ $event->program->code }}-{{ $event->program->abbrev }}</p>
                    @endif

                    {{-- Description --}}
                    @if($event->description)
                        <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $event->description }}</p>
                    @endif

                    {{-- Participant count + chevron --}}
                    <div class="mt-2 flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <x-icon name="o-user-group" class="h-3.5 w-3.5 text-orange-400 shrink-0" />
                            <span class="text-[11px] text-gray-500">{{ $count }} participant{{ $count !== 1 ? 's' : '' }}</span>

                            {{-- Student names preview --}}
                            @if($count > 0)
                                <span class="text-[11px] text-gray-400 truncate">
                                    — {{ $applicants->take(2)->map(fn($a) => $a->research?->student?->nim)->filter()->implode(', ') }}{{ $count > 2 ? ', ...' : '' }}
                                </span>
                            @endif
                        </div>
                        <x-icon name="o-chevron-right" class="h-4 w-4 text-gray-300 shrink-0" />
                    </div>

                </div>
            </a>

        @empty
            <div class="py-16 text-center">
                <x-icon name="o-scale" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No active pre-defense events.</p>
            </div>
        @endforelse
    @endif

</div>

    @if(isset($events) && $events instanceof \Illuminate\Pagination\LengthAwarePaginator && $events->hasPages())
        <div class="px-3 pb-4">{{ $events->links() }}</div>
    @endif
</div>
