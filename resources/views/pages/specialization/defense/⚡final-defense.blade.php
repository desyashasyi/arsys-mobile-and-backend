<?php

use App\Models\ArSys\Event;
use App\Models\ArSys\Program;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile-app')] #[Title('Final Defense Events')] class extends Component
{
    public function with(): array
    {
        $programId = auth()->user()->staff?->program_id;

        if (!$programId) {
            return ['grouped' => collect()];
        }

        $program    = Program::find($programId);
        $clusterIds = Program::where('faculty_id', $program?->faculty_id)->pluck('id');

        $events = Event::with(['program'])
            ->whereIn('program_id', $clusterIds)
            ->whereHas('type', fn($q) => $q->where('code', 'PUB'))
            ->withCount('finaldefenseApplicant as applicant_count')
            ->orderBy('event_date', 'desc')
            ->get()
            ->map(fn($e) => [
                'id'              => $e->id,
                'date_key'        => Carbon::parse($e->event_date)->format('Y-m-d'),
                'date_label'      => Carbon::parse($e->event_date)->isoFormat('dddd, D MMMM YYYY'),
                'event_label'     => 'PUB-' . Carbon::parse($e->event_date)->format('dmy') . '-' . $e->id,
                'applicant_count' => $e->applicant_count,
                'program_code'    => $e->program ? ($e->program->code . '.' . $e->program->abbrev) : '-',
                'is_own'          => $e->program_id === $programId,
            ]);

        $grouped = $events
            ->groupBy('date_key')
            ->map(fn($group) => $group->sortByDesc('is_own')->values())
            ->sortKeysDesc();

        return ['grouped' => $grouped];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">Manage rooms &amp; participants</p>

    <div class="px-3 py-3 space-y-4 pb-6">
        @forelse($grouped as $dateKey => $dayEvents)
            @php
                $hdr = match($loop->index % 3) {
                    0 => ['bg' => 'bg-purple-50 border-purple-100',  'text' => 'text-purple-800',  'icon' => 'text-purple-400'],
                    1 => ['bg' => 'bg-violet-50 border-violet-100',  'text' => 'text-violet-800',  'icon' => 'text-violet-400'],
                    2 => ['bg' => 'bg-indigo-50 border-indigo-100',  'text' => 'text-indigo-800',  'icon' => 'text-indigo-400'],
                };
            @endphp
            <div class="rounded-2xl bg-white shadow-sm overflow-hidden">
                {{-- Date header --}}
                <div class="flex items-center gap-2 px-4 py-2.5 {{ $hdr['bg'] }} border-b">
                    <x-icon name="o-calendar-days" class="h-3.5 w-3.5 {{ $hdr['icon'] }} shrink-0" />
                    <p class="text-xs font-bold {{ $hdr['text'] }}">{{ $dayEvents->first()['date_label'] }}</p>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach($dayEvents as $event)
                        @if($event['is_own'])
                            {{-- Own program: prominent purple row, clickable --}}
                            <a href="{{ route('specialization.defense.final-defense.rooms', $event['id']) }}"
                               class="flex items-center gap-3 px-4 py-3.5 hover:bg-purple-50 active:bg-purple-100 transition-colors">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">PUB</span>
                                        <span class="text-[10px] font-bold text-purple-700">{{ $event['program_code'] }}</span>
                                    </div>
                                    <p class="text-sm font-bold text-gray-800 uppercase">{{ $event['event_label'] }}</p>
                                    <p class="text-[10px] font-semibold text-gray-500 mt-1">
                                        {{ $event['applicant_count'] }} participants
                                    </p>
                                </div>
                                <x-icon name="o-chevron-right" class="h-4 w-4 text-gray-300 shrink-0" />
                            </a>
                        @else
                            {{-- Other program: compact gray row --}}
                            <div class="flex items-center gap-3 px-4 py-2.5 bg-gray-50/50">
                                <span class="shrink-0 text-[9px] font-bold px-1.5 py-0.5 rounded bg-gray-200 text-gray-500">PUB</span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-400 uppercase truncate">{{ $event['event_label'] }}</p>
                                </div>
                                <span class="shrink-0 text-[10px] font-semibold text-gray-400">{{ $event['program_code'] }}</span>
                                <span class="shrink-0 text-[10px] text-gray-400">{{ $event['applicant_count'] }}p</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @empty
            <div class="py-16 text-center">
                <x-icon name="o-inbox" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No final defense events found.</p>
                <a href="{{ route('specialization.defense.events') }}"
                   class="mt-3 inline-block text-xs font-semibold text-purple-600 hover:text-purple-800">
                    + Add via List of Events
                </a>
            </div>
        @endforelse
    </div>
</div>
