<?php

use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantDefense;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile-app')] #[Title('Pre-Defense Participants')] class extends Component
{
    public int $eventId = 0;

    public function mount(int $id): void
    {
        $this->eventId = $id;
    }

    public function with(): array
    {
        $staff = auth()->user()->staff;
        if (!$staff) {
            return ['participants' => collect(), 'event' => null, 'noStaff' => true];
        }

        $staffId = $staff->id;

        $event = Event::find($this->eventId);

        $participants = EventApplicantDefense::where('event_id', $this->eventId)
            ->where(function ($q) use ($staffId) {
                $q->whereHas('research.supervisor', fn($sq) => $sq->where('supervisor_id', $staffId))
                  ->orWhereHas('defenseExaminer', fn($sq) => $sq->where('examiner_id', $staffId));
            })
            ->with(['research.student.program', 'space', 'session', 'research.milestone'])
            ->get();

        return ['participants' => $participants, 'event' => $event, 'noStaff' => false];
    }
};
?>

<div>
    @if(isset($event) && $event && $event->date)
        <p class="px-4 py-2 text-xs text-purple-600 font-medium">{{ \Carbon\Carbon::parse($event->date)->format('d M Y') }}</p>
    @endif

    <div class="px-3 py-3 space-y-2">

        @if($noStaff)
            <div class="py-16 text-center">
                <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
                <p class="text-sm font-semibold text-gray-500">No staff record linked to your account.</p>
            </div>

        @elseif($participants->isEmpty())
            <div class="py-16 text-center">
                <x-icon name="o-user-group" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No participants found.</p>
            </div>

        @else
            @foreach($participants as $p)
                <a href="{{ route('staff.pre-defense.applicant', $p->id) }}" class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4 border-orange-400 hover:bg-orange-50 transition-colors">
                    <div class="p-3">

                        {{-- Room + Session row --}}
                        <div class="flex items-center gap-3 mb-2">
                            @if($p->space)
                                <div class="flex items-center gap-1">
                                    <x-icon name="o-map-pin" class="h-3.5 w-3.5 text-orange-400 shrink-0" />
                                    <span class="text-[11px] font-semibold text-gray-600">{{ $p->space->code }}</span>
                                </div>
                            @endif
                            @if($p->session)
                                <div class="flex items-center gap-1">
                                    <x-icon name="o-clock" class="h-3.5 w-3.5 text-orange-400 shrink-0" />
                                    <span class="text-[11px] text-gray-500">{{ $p->session->time ?? $p->session->name }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- NIM --}}
                        <p class="text-xs font-semibold text-purple-600 mb-0.5">
                            {{ $p->research?->student?->nim ?? '—' }}
                        </p>

                        {{-- Student name + chevron row --}}
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold text-gray-800 leading-tight">
                                {{ trim(($p->research?->student?->first_name ?? '') . ' ' . ($p->research?->student?->last_name ?? '')) ?: '—' }}
                            </p>
                            <x-icon name="o-chevron-right" class="h-4 w-4 text-gray-300 shrink-0 ml-2" />
                        </div>

                        {{-- Research title --}}
                        @if($p->research?->title)
                            <p class="text-[11px] text-gray-400 uppercase mt-1 line-clamp-2 leading-snug">
                                {{ $p->research->title }}
                            </p>
                        @endif

                    </div>
                </a>
            @endforeach
        @endif

    </div>
</div>
