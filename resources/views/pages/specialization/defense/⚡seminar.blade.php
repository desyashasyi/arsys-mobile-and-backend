<?php

use App\Models\ArSys\Event;
use App\Models\ArSys\Program;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mobile-app')] #[Title('Seminar Events')] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        $programId = auth()->user()->staff?->program_id;

        if (!$programId) {
            return ['events' => collect()];
        }

        $program    = Program::find($programId);
        $clusterIds = Program::where('faculty_id', $program?->faculty_id)->pluck('id');

        $events = Event::with(['program'])
            ->whereIn('program_id', $clusterIds)
            ->whereHas('type', fn($q) => $q->where('code', 'SSP'))
            ->withCount('seminarApplicant as applicant_count')
            ->orderBy('event_date', 'desc')
            ->paginate(10)
            ->through(fn($e) => [
                'id'              => $e->id,
                'event_date'      => Carbon::parse($e->event_date)->isoFormat('D MMM YYYY'),
                'event_label'     => 'SSP-' . Carbon::parse($e->event_date)->format('dmy') . '-' . $e->id,
                'applicant_count' => $e->applicant_count,
                'program_code'    => $e->program ? ($e->program->code . '.' . $e->program->abbrev) : '-',
                'is_own'          => $e->program_id === $programId,
            ]);

        return ['events' => $events];
    }
};
?>

<div>
    <div class="flex items-center justify-between px-4 py-2">
        <p class="text-xs text-purple-600 font-medium">Research proposal seminars</p>
        <a href="{{ route('specialization.defense.events') }}"
           class="flex items-center gap-1 text-xs font-semibold text-purple-600 hover:text-purple-800">
            <x-icon name="o-plus" class="h-4 w-4" />
            Add via Events
        </a>
    </div>

    <div class="px-3 py-3 space-y-2">
        @forelse($events as $event)
            <div class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $event['is_own'] ? 'border-blue-400' : 'border-gray-300' }} p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">SSP</span>
                            <span class="text-[10px] font-semibold text-gray-500">{{ $event['program_code'] }}</span>
                            @if(!$event['is_own'])
                                <span class="text-[9px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-400">other</span>
                            @endif
                        </div>
                        <p class="font-bold text-sm text-gray-800 uppercase">{{ $event['event_label'] }}</p>
                        <div class="flex items-center gap-1 mt-0.5">
                            <x-icon name="o-calendar-days" class="h-3 w-3 text-gray-400 shrink-0" />
                            <p class="text-xs text-gray-500">{{ $event['event_date'] }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 text-[9px] font-bold px-2 py-0.5 rounded-full {{ $event['is_own'] ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-400' }}">
                        {{ $event['applicant_count'] }} participants
                    </span>
                </div>
            </div>
        @empty
            <div class="py-16 text-center">
                <x-icon name="o-inbox" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No seminar events found.</p>
                <a href="{{ route('specialization.defense.events') }}"
                   class="mt-3 inline-block text-xs font-semibold text-purple-600 hover:text-purple-800">
                    + Add via List of Events
                </a>
            </div>
        @endforelse
    </div>

    @if($events->hasPages())
        <div class="px-3 pb-4">{{ $events->links() }}</div>
    @endif
</div>
