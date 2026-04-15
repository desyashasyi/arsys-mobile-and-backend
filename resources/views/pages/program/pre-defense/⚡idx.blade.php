<?php

use App\Models\ArSys\Event;
use App\Models\ArSys\EventApplicantDefense;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mobile-app')] #[Title('Pre-Defense Scores')] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        $programId = auth()->user()->staff?->program_id;

        if (!$programId) {
            return ['events' => collect()];
        }

        $events = Event::where('program_id', $programId)
            ->where('status', 1)
            ->whereHas('type', fn($q) => $q->where('code', 'PRE'))
            ->whereHas('defenseApplicantPublish')
            ->with(['type'])
            ->orderBy('event_date', 'DESC')
            ->paginate(10)
            ->through(function ($event) {
                $applicants = EventApplicantDefense::where('event_id', $event->id)
                    ->where('publish', 1)
                    ->with([
                        'defenseExaminer.defenseExaminerPresence',
                        'research.supervisor.defenseSupervisorPresence',
                    ])
                    ->get();

                $hasMissing = false;
                foreach ($applicants as $app) {
                    if (!$app->research) continue;
                    $supervisorIds = $app->research->supervisor->pluck('supervisor_id')->toArray();
                    foreach ($app->defenseExaminer as $examiner) {
                        if (in_array($examiner->examiner_id, $supervisorIds)) continue;
                        $p = $examiner->defenseExaminerPresence;
                        if ($p !== null && $p->score === null) {
                            $hasMissing = true;
                            break 2;
                        }
                    }
                    foreach ($app->research->supervisor as $sup) {
                        $presence = $sup->defenseSupervisorPresence;
                        if ($presence !== null && $presence->score === null) {
                            $hasMissing = true;
                            break 2;
                        }
                    }
                }

                $formattedDate  = Carbon::parse($event->event_date)->format('dmy');
                $eventIdString  = sprintf('%s-%s-%s', $event->type->code ?? 'PRE', $formattedDate, $event->id);

                return [
                    'id'              => $event->id,
                    'event_id_string' => $eventIdString,
                    'event_date'      => Carbon::parse($event->event_date)->isoFormat('D MMM YYYY'),
                    'applicant_count' => $applicants->count(),
                    'has_missing'     => $hasMissing,
                ];
            });

        return ['events' => $events];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">SCORE MONITORING BY EVENT</p>

    <div class="px-3 py-3 space-y-2">
        @forelse($events as $event)
            <a href="{{ route('program.pre-defense.event', $event['id']) }}"
               class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $event['has_missing'] ? 'border-red-400' : 'border-green-400' }} p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <x-icon name="o-scale" class="h-5 w-5 shrink-0 text-orange-500" />
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm text-gray-800 uppercase truncate">{{ $event['event_id_string'] }}</p>
                        <div class="flex items-center gap-1 mt-0.5">
                            <x-icon name="o-calendar-days" class="h-3 w-3 text-gray-400 shrink-0" />
                            <p class="text-xs text-gray-500">{{ $event['event_date'] }}</p>
                        </div>
                    </div>
                    @if($event['has_missing'])
                        <x-icon name="o-exclamation-triangle" class="h-5 w-5 shrink-0 text-red-400" />
                    @else
                        <x-icon name="o-check-circle" class="h-5 w-5 shrink-0 text-green-400" />
                    @endif
                </div>
            </a>
        @empty
            <div class="py-16 text-center">
                <x-icon name="o-inbox" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No pre-defense events found.</p>
            </div>
        @endforelse
    </div>

    @if($events->hasPages())
        <div class="px-3 pb-4">{{ $events->links() }}</div>
    @endif
</div>
