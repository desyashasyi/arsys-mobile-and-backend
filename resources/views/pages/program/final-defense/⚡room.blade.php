<?php

use App\Models\ArSys\FinalDefenseRoom;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile-app')] #[Title('Room Detail')] class extends Component
{
    public int $eventId = 0;
    public int $roomId  = 0;

    public function mount(int $eventId, int $roomId): void
    {
        $this->eventId = $eventId;
        $this->roomId  = $roomId;
    }

    public function with(): array
    {
        $programId = auth()->user()->staff?->program_id;

        $room = FinalDefenseRoom::where('id', $this->roomId)
            ->where('event_id', $this->eventId)
            ->whereHas('event', fn($q) => $q->where('program_id', $programId))
            ->with([
                'space', 'session', 'moderator',
                'examiner.staff',
                'examiner.finaldefenseExaminerPresence',
                'applicant.research.student.program',
                'applicant.research.supervisor.staff',
                'applicant.research.supervisor.finaldefenseSupervisorPresence',
            ])
            ->first();

        if (!$room) {
            return ['room' => null, 'applicants' => collect()];
        }

        $applicants = $room->applicant->map(function ($app) use ($room) {
            if (!$app->research) return null;
            $student = $app->research->student;

            $supervisorMap = [];
            foreach ($app->research->supervisor as $sup) {
                $p = $sup->finaldefenseSupervisorPresence;
                $supervisorMap[$sup->supervisor_id] = ($p && $p->score !== null) ? $p->score : null;
            }

            $examinerScores = $room->examiner->map(function ($ex) use ($app, $supervisorMap) {
                $presence      = $ex->finaldefenseExaminerPresence->where('applicant_id', $app->id)->first();
                $isPresent     = $presence !== null;
                $examinerScore = ($presence && $presence->score !== null && $presence->score != -1) ? $presence->score : null;
                $isOwnSpv      = array_key_exists($ex->examiner_id, $supervisorMap);
                $score         = $examinerScore;
                $scoredAsSpv   = false;
                if ($score === null && $isOwnSpv && $supervisorMap[$ex->examiner_id] !== null) {
                    $score       = $supervisorMap[$ex->examiner_id];
                    $scoredAsSpv = true;
                }
                return [
                    'name'         => trim(($ex->staff?->first_name ?? '') . ' ' . ($ex->staff?->last_name ?? '')),
                    'score'        => $score,
                    'has_scored'   => $examinerScore !== null,
                    'is_own_spv'   => $isOwnSpv,
                    'scored_as_spv'=> $scoredAsSpv,
                    'is_present'   => $isPresent,
                ];
            })->filter(fn($e) => !$e['is_own_spv'])->values();

            $supervisorScores = $app->research->supervisor->sortBy('order')->map(function ($sup) {
                $presence = $sup->finaldefenseSupervisorPresence;
                $score    = $presence?->score;
                return [
                    'name'        => trim(($sup->staff?->first_name ?? '') . ' ' . ($sup->staff?->last_name ?? '')),
                    'role'        => ($sup->order ?? 1) <= 1 ? 'SPV' : 'Co-SPV',
                    'score'       => $score,
                    'has_scored'  => $score !== null,
                    'is_present'  => $presence !== null,
                ];
            });

            $hasMissing = $examinerScores->contains(fn($e) => $e['is_present'] && !$e['has_scored'])
                || $supervisorScores->contains(fn($s) => $s['is_present'] && !$s['has_scored']);

            return [
                'id'           => $app->id,
                'nim'          => $student?->nim ?? 'N/A',
                'name'         => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                'title'        => $app->research->title ?? 'No Title',
                'examiners'    => $examinerScores,
                'supervisors'  => $supervisorScores,
                'has_missing'  => $hasMissing,
            ];
        })->filter()->values();

        return ['room' => $room, 'applicants' => $applicants];
    }
};
?>

<div>
    @if($room?->session)
        <p class="px-4 py-2 text-xs text-purple-600 font-medium">{{ $room->session->time }}</p>
    @endif

    @if(!$room)
        <div class="py-16 text-center">
            <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
            <p class="text-sm text-gray-500">Room not found.</p>
        </div>
    @else
        <div class="px-3 py-3 space-y-2">
            @forelse($applicants as $ap)
                <a href="{{ route('program.final-defense.participant', [$eventId, $ap['id']]) }}"
                   class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4
                          {{ $ap['has_missing'] ? 'border-red-400' : 'border-green-400' }}
                          p-3 flex items-center gap-3 hover:shadow-md transition-shadow">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $ap['has_missing'] ? 'bg-red-50' : 'bg-green-50' }}">
                        <x-icon name="{{ $ap['has_missing'] ? 'o-exclamation-triangle' : 'o-check' }}"
                                class="h-4 w-4 {{ $ap['has_missing'] ? 'text-red-500' : 'text-green-600' }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-semibold text-purple-600">{{ $ap['nim'] }}</p>
                        <p class="font-semibold text-sm text-gray-800 leading-tight truncate">{{ $ap['name'] ?: '—' }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2 leading-snug">{{ $ap['title'] }}</p>
                    </div>
                    <x-icon name="o-chevron-right" class="h-4 w-4 shrink-0 text-gray-300" />
                </a>
            @empty
                <div class="py-12 text-center">
                    <x-icon name="o-inbox" class="mx-auto mb-3 h-10 w-10 text-gray-200" />
                    <p class="text-sm text-gray-400">No participants in this room.</p>
                </div>
            @endforelse
        </div>
    @endif
</div>
