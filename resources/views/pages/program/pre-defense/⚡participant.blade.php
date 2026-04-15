<?php

use App\Models\ArSys\EventApplicantDefense;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile-app')] #[Title('Participant Detail')] class extends Component
{
    public int $eventId      = 0;
    public int $applicantId  = 0;

    public function mount(int $eventId, int $applicantId): void
    {
        $this->eventId     = $eventId;
        $this->applicantId = $applicantId;
    }

    public function with(): array
    {
        $programId = auth()->user()->staff?->program_id;

        $app = EventApplicantDefense::where('id', $this->applicantId)
            ->where('event_id', $this->eventId)
            ->whereHas('event', fn($q) => $q->where('program_id', $programId)
                ->whereHas('type', fn($q2) => $q2->where('code', 'PRE')))
            ->with([
                'event.type',
                'research.student.program',
                'research.milestone',
                'research.supervisor.staff',
                'research.supervisor.defenseSupervisorPresence',
                'defenseExaminer.staff',
                'defenseExaminer.defenseExaminerPresence',
            ])
            ->first();

        if (!$app || !$app->research) {
            return ['applicant' => null];
        }

        $student = $app->research->student;

        $supervisorMap = [];
        foreach ($app->research->supervisor as $sup) {
            $supervisorMap[$sup->supervisor_id] = $sup->defenseSupervisorPresence?->score;
        }

        $supervisors = $app->research->supervisor->sortBy('order')->map(function ($sup) {
            $presence = $sup->defenseSupervisorPresence;
            $score    = $presence?->score;
            return [
                'name'       => trim(($sup->staff?->first_name ?? '') . ' ' . ($sup->staff?->last_name ?? '')),
                'role'       => ($sup->order ?? 1) <= 1 ? 'SPV' : 'Co-SPV',
                'score'      => $score,
                'has_scored' => $score !== null,
                'is_present' => $presence !== null,
            ];
        });

        $examiners = $app->defenseExaminer->map(function ($ex) use ($supervisorMap) {
            $presence      = $ex->defenseExaminerPresence;
            $isPresent     = $presence !== null;
            $examinerScore = $presence?->score;
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

        $hasMissing = $examiners->contains(fn($e) => $e['is_present'] && !$e['has_scored'])
            || $supervisors->contains(fn($s) => $s['is_present'] && !$s['has_scored']);

        return [
            'applicant' => [
                'nim'         => $student?->nim ?? 'N/A',
                'name'        => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                'program'     => $student?->program?->name ?? '',
                'title'       => $app->research->title ?? 'No Title',
                'milestone'   => $app->research->milestone,
                'supervisors' => $supervisors,
                'examiners'   => $examiners,
                'has_missing' => $hasMissing,
            ],
        ];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">PRE-DEFENSE SCORE DETAIL</p>

    @if(!$applicant)
        <div class="py-16 text-center">
            <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
            <p class="text-sm text-gray-500">Participant not found.</p>
        </div>
    @else
        <div class="px-3 py-3 space-y-3">

            {{-- Student & Research Info --}}
            <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4
                        {{ $applicant['has_missing'] ? 'border-red-400' : 'border-green-400' }} p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-semibold text-purple-600">{{ $applicant['nim'] }}</p>
                        <p class="font-bold text-base text-gray-800 leading-tight">{{ $applicant['name'] ?: '—' }}</p>
                    </div>
                    <span class="shrink-0 text-[10px] font-bold px-2 py-1 rounded-full
                                 {{ $applicant['has_missing'] ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-700' }}">
                        {{ $applicant['has_missing'] ? 'Incomplete' : 'Complete' }}
                    </span>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-500 mb-1">Research Title</p>
                    <p class="text-sm text-gray-700 leading-snug">{{ $applicant['title'] }}</p>
                </div>
                @if($applicant['milestone'])
                    <div class="mt-2">
                        <x-milestone-chip :code="$applicant['milestone']->code" :phase="$applicant['milestone']->phase" />
                    </div>
                @endif
            </div>

            {{-- Examiner Scores --}}
            @if($applicant['examiners']->isNotEmpty())
                <div class="rounded-xl bg-white shadow-sm p-4">
                    <p class="text-xs font-bold text-orange-600 uppercase tracking-wide mb-3">Examiner Scores</p>
                    <div class="space-y-2">
                        @foreach($applicant['examiners'] as $ex)
                            @php $scored = $ex['has_scored'] || ($ex['scored_as_spv'] && $ex['score'] !== null); @endphp
                            <div class="flex items-center justify-between rounded-lg px-3 py-2.5
                                        {{ $scored ? 'bg-gray-50' : ($ex['is_present'] ? 'bg-red-50' : 'bg-gray-50') }}">
                                <p class="text-sm text-gray-700 truncate flex-1">{{ $ex['name'] ?: '—' }}</p>
                                @if($scored)
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        @if($ex['scored_as_spv'])
                                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-700">SPV</span>
                                        @endif
                                        <span class="rounded-full bg-green-100 text-green-800 px-3 py-0.5 text-sm font-bold">{{ $ex['score'] }}</span>
                                    </div>
                                @elseif(!$ex['is_present'])
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-400">Ineligible</span>
                                @else
                                    <div class="flex items-center gap-1 shrink-0">
                                        <x-icon name="o-bell" class="h-3.5 w-3.5 text-red-500" />
                                        <span class="text-xs font-medium text-red-600">Not scored</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Supervisor Scores --}}
            @if($applicant['supervisors']->isNotEmpty())
                <div class="rounded-xl bg-white shadow-sm p-4">
                    <p class="text-xs font-bold text-purple-600 uppercase tracking-wide mb-3">Supervisor Scores</p>
                    <div class="space-y-2">
                        @foreach($applicant['supervisors'] as $spv)
                            <div class="flex items-center justify-between rounded-lg px-3 py-2.5
                                        {{ $spv['has_scored'] ? 'bg-gray-50' : ($spv['is_present'] ? 'bg-red-50' : 'bg-gray-50') }}">
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <p class="text-sm text-gray-700 truncate">{{ $spv['name'] ?: '—' }}</p>
                                    <span class="shrink-0 text-[9px] font-bold px-1.5 py-0.5 rounded
                                                 {{ $spv['role'] === 'SPV' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-teal-50 text-teal-700 border border-teal-200' }}">
                                        {{ $spv['role'] }}
                                    </span>
                                </div>
                                @if($spv['has_scored'])
                                    <span class="rounded-full bg-green-100 text-green-800 px-3 py-0.5 text-sm font-bold shrink-0">{{ $spv['score'] }}</span>
                                @elseif(!$spv['is_present'])
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-gray-100 text-gray-400 shrink-0">Ineligible</span>
                                @else
                                    <div class="flex items-center gap-1 shrink-0">
                                        <x-icon name="o-bell" class="h-3.5 w-3.5 text-red-500" />
                                        <span class="text-xs font-medium text-red-600">Not scored</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    @endif
</div>
