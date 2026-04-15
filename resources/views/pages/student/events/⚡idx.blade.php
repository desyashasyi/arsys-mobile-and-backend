<?php

use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantDefense;
use App\Models\ArSys\EventApplicantFinalDefense;
use App\Models\ArSys\EventType;
use App\Models\ArSys\Research;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile-app')] #[Title('Events')] class extends Component
{
    public string $filter = 'all';

    public function with(): array
    {
        $student = auth()->user()->student;

        if (!$student) {
            return ['events' => collect(), 'eventTypes' => collect()];
        }

        // All research IDs for this student
        $researchIds = Research::where('student_id', $student->id)->pluck('id');

        // Student's pre-defense applicant records, keyed by event_id
        $preApplicants = $researchIds->isNotEmpty()
            ? EventApplicantDefense::whereIn('research_id', $researchIds)
                ->with(['space', 'session'])
                ->get()
                ->keyBy('event_id')
            : collect();

        // Student's final-defense applicant records, keyed by event_id
        $finalApplicants = $researchIds->isNotEmpty()
            ? EventApplicantFinalDefense::whereIn('research_id', $researchIds)
                ->with(['room.space', 'room.session'])
                ->get()
                ->keyBy('event_id')
            : collect();

        // All events for student's program, filtered by type
        $query = Event::where('program_id', $student->program_id)
            ->with(['type', 'program']);

        if ($this->filter !== 'all') {
            $query->whereHas('type', fn($q) => $q->where('code', $this->filter));
        }

        $events = $query->orderByDesc('event_date')->get()->map(function ($event) use ($preApplicants, $finalApplicants) {
            $pre   = $preApplicants->get($event->id);
            $final = $finalApplicants->get($event->id);

            $isApplicant   = $pre !== null || $final !== null;
            $applicantType = $pre !== null ? 'pre' : ($final !== null ? 'final' : null);
            $isPublished   = $pre?->publish || $final?->publish;

            $space = match($applicantType) {
                'pre'   => $pre?->space?->code,
                'final' => $final?->room?->space?->code,
                default => null,
            };
            $session = match($applicantType) {
                'pre'   => $pre?->session?->time,
                'final' => $final?->room?->session?->time,
                default => null,
            };

            $deadline     = $event->application_deadline ? Carbon::parse($event->application_deadline) : null;
            $deadlinePast = $deadline ? $deadline->isPast() : null;

            return [
                'id'            => $event->id,
                'date'          => $event->event_date
                    ? Carbon::parse($event->event_date)->isoFormat('D MMM YYYY')
                    : '—',
                'deadline'      => $deadline ? $deadline->isoFormat('D MMM YYYY') : null,
                'deadline_past' => $deadlinePast,
                'type_code'     => $event->type?->code,
                'type_name'     => $event->type?->description,
                'program'       => $event->program?->abbrev ?? $event->program?->code ?? '—',
                'status'        => $event->status,
                'completed'     => $event->completed,
                'is_applicant'  => $isApplicant,
                'applicant_type'=> $applicantType,
                'is_published'  => $isPublished,
                'space'         => $space,
                'session'       => $session,
            ];
        });

        $eventTypes = EventType::orderBy('id')->get(['id', 'code', 'description']);

        return compact('events', 'eventTypes');
    }
};
?>

<div class="px-3 py-3 pb-24 space-y-4">

    {{-- ─── Filter (dropdown) ─── --}}
    <div class="flex items-center gap-2">
        <x-icon name="o-funnel" class="h-4 w-4 text-gray-400 shrink-0" />
        <select wire:model.live="filter"
                class="flex-1 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-300">
            <option value="all">All Events</option>
            @foreach($eventTypes as $type)
                <option value="{{ $type->code }}">{{ $type->description }}</option>
            @endforeach
        </select>
    </div>

    {{-- ─── Event List ─── --}}
    @forelse($events as $event)
    @php
        $typeColor = match($event['type_code'] ?? '') {
            'PRE'  => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'border' => 'border-l-orange-400', 'icon' => 'o-scale'],
            'PUB'  => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-l-purple-400', 'icon' => 'o-trophy'],
            'SST'  => ['bg' => 'bg-blue-100',   'text' => 'text-blue-700',   'border' => 'border-l-blue-400',   'icon' => 'o-presentation-chart-bar'],
            'SSP'  => ['bg' => 'bg-teal-100',   'text' => 'text-teal-700',   'border' => 'border-l-teal-400',   'icon' => 'o-megaphone'],
            'PRO'  => ['bg' => 'bg-green-100',  'text' => 'text-green-700',  'border' => 'border-l-green-400',  'icon' => 'o-document-text'],
            'PUS'  => ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-700', 'border' => 'border-l-indigo-400', 'icon' => 'o-globe-alt'],
            default => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'border' => 'border-l-gray-300', 'icon' => 'o-calendar-days'],
        };
    @endphp

    <div class="rounded-2xl bg-white shadow-sm overflow-hidden border-l-4 {{ $typeColor['border'] }} p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">

                {{-- Type + Program + Applicant status --}}
                <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
                    <span class="text-[9px] font-bold px-2 py-0.5 rounded-full {{ $typeColor['bg'] }} {{ $typeColor['text'] }}">
                        {{ $event['type_code'] }}
                    </span>
                    <span class="text-[10px] text-gray-400">{{ $event['program'] }}</span>
                    @if($event['is_applicant'])
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full {{ $event['is_published'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $event['is_published'] ? 'Published' : 'Registered' }}
                        </span>
                    @endif
                </div>

                {{-- Date --}}
                <p class="text-xs font-bold {{ $typeColor['text'] }}">{{ $event['date'] }}</p>

                {{-- Type name --}}
                <p class="text-[11px] text-gray-500 mt-0.5">{{ $event['type_name'] }}</p>

                {{-- Deadline --}}
                @if($event['deadline'])
                    <div class="flex items-center gap-1 mt-1.5">
                        <x-icon name="o-clock" class="h-3 w-3 shrink-0 {{ $event['deadline_past'] ? 'text-red-400' : 'text-amber-400' }}" />
                        <span class="text-[10px] font-semibold {{ $event['deadline_past'] ? 'text-red-500' : 'text-amber-600' }}">
                            {{ $event['deadline_past'] ? 'Closed' : 'Deadline' }}: {{ $event['deadline'] }}
                        </span>
                    </div>
                @endif

                {{-- Space & session (only if applicant and published) --}}
                @if($event['is_applicant'] && $event['is_published'])
                    <div class="flex items-center gap-3 mt-2">
                        <div class="flex items-center gap-1.5">
                            <div class="flex h-5 w-5 items-center justify-center rounded-full {{ $event['space'] ? 'bg-orange-100' : 'bg-gray-100' }}">
                                <x-icon name="o-map-pin" class="h-3 w-3 {{ $event['space'] ? 'text-orange-500' : 'text-gray-300' }}" />
                            </div>
                            <span class="text-[11px] font-bold {{ $event['space'] ? 'text-orange-700' : 'text-gray-300' }}">
                                {{ $event['space'] ?? '—' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="flex h-5 w-5 items-center justify-center rounded-full {{ $event['session'] ? 'bg-blue-100' : 'bg-gray-100' }}">
                                <x-icon name="o-clock" class="h-3 w-3 {{ $event['session'] ? 'text-blue-500' : 'text-gray-300' }}" />
                            </div>
                            <span class="text-[11px] font-bold {{ $event['session'] ? 'text-blue-700' : 'text-gray-300' }}">
                                {{ $event['session'] ?? '—' }}
                            </span>
                        </div>
                    </div>
                @endif

            </div>

            {{-- Type icon --}}
            <div class="shrink-0 flex h-10 w-10 items-center justify-center rounded-full {{ $typeColor['bg'] }}">
                <x-icon name="{{ $typeColor['icon'] }}" class="h-5 w-5 {{ $typeColor['text'] }}" />
            </div>
        </div>
    </div>

    @empty
    <div class="py-12 text-center rounded-2xl bg-white shadow-sm">
        <x-icon name="o-calendar-days" class="mx-auto mb-2 h-10 w-10 text-gray-200" />
        <p class="text-sm font-semibold text-gray-400">No events found</p>
        <p class="text-xs text-gray-300 mt-1">No events for your program yet.</p>
    </div>
    @endforelse

</div>
