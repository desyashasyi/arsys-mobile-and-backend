<?php

use App\Models\ArSys\Event;
use App\Models\ArSys\FinalDefenseRoom;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mobile-app')] #[Title('Final Defense Scores')] class extends Component
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
            ->whereHas('type', fn($q) => $q->where('code', 'PUB'))
            ->whereHas('finaldefenseApplicantPublish')
            ->with(['type'])
            ->orderBy('event_date', 'DESC')
            ->paginate(10)
            ->through(function ($event) {
                $rooms = FinalDefenseRoom::where('event_id', $event->id)
                    ->with([
                        'examiner.finaldefenseExaminerPresence',
                        'applicant.research.supervisor.finaldefenseSupervisorPresence',
                    ])
                    ->get();

                $hasMissing = false;
                foreach ($rooms as $room) {
                    foreach ($room->applicant as $app) {
                        if (!$app->research) continue;
                        $supervisorIds = $app->research->supervisor->pluck('supervisor_id')->toArray();
                        foreach ($room->examiner as $ex) {
                            if (in_array($ex->examiner_id, $supervisorIds)) continue;
                            $presence = $ex->finaldefenseExaminerPresence->where('applicant_id', $app->id)->first();
                            // Only missing if present (presence record exists) but not yet scored
                            if ($presence !== null && $presence->score === null) {
                                $hasMissing = true;
                                break 3;
                            }
                        }
                        foreach ($app->research->supervisor as $sup) {
                            $p = $sup->finaldefenseSupervisorPresence;
                            if ($p !== null && $p->score === null) {
                                $hasMissing = true;
                                break 3;
                            }
                        }
                    }
                }

                $formattedDate = Carbon::parse($event->event_date)->format('dmy');
                $eventIdString = sprintf('%s-%s-%s', $event->type->code ?? 'PUB', $formattedDate, $event->id);

                return [
                    'id'              => $event->id,
                    'event_id_string' => $eventIdString,
                    'event_date'      => Carbon::parse($event->event_date)->isoFormat('D MMM YYYY'),
                    'room_count'      => $rooms->count(),
                    'has_missing'     => $hasMissing,
                ];
            });

        return ['events' => $events];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">Score monitoring by event</p>

    <div class="px-3 py-3 space-y-2">
        @forelse($events as $event)
            <a href="{{ route('program.final-defense.event', $event['id']) }}"
               class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $event['has_missing'] ? 'border-red-400' : 'border-green-400' }} p-4 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <x-icon name="o-trophy" class="h-5 w-5 shrink-0 text-purple-500" />
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
                <p class="text-sm text-gray-400">No final defense events found.</p>
            </div>
        @endforelse
    </div>

    @if($events->hasPages())
        <div class="px-3 pb-4">{{ $events->links() }}</div>
    @endif
</div>
