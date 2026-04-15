<?php

use App\Models\ArSys\Event;
use App\Models\ArSys\FinalDefenseRoom;
use App\Models\ArSys\FinalDefenseExaminer;
use App\Models\ArSys\FinalDefenseExaminerPresence;
use App\Models\ArSys\FinalDefenseSupervisorPresence;
use App\Models\ArSys\ResearchSupervisor;
use App\Models\ArSys\DefenseScoreGuide;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Final Defense Rooms')] class extends Component
{
    use Toast;

    public int $eventId = 0;
    public bool $scoreModal = false;
    public bool $guideModal = false;
    public string $scoreFor = '';       // 'examiner' or 'supervisor'
    public int $scorePresenceId = 0;    // FinalDefenseExaminerPresence.id (for examiner)
    public int $scoreSupervisorId = 0;  // ResearchSupervisor.id (for supervisor)
    public string $scoreStudentLabel = '';
    public string $scoreInput = '';
    public string $remarkInput = '';

    public function mount(int $id): void
    {
        $this->eventId = $id;
    }

    public function openExaminerScoreModal(int $presenceId, string $studentLabel, ?int $currentScore, ?string $currentRemark): void
    {
        $this->scoreFor = 'examiner';
        $this->scorePresenceId = $presenceId;
        $this->scoreStudentLabel = $studentLabel;
        $this->scoreInput = ($currentScore !== null && $currentScore !== -1) ? (string)$currentScore : '';
        $this->remarkInput = $currentRemark ?? '';
        $this->scoreModal = true;
    }

    public function openSupervisorScoreModal(int $supervisorId, string $studentLabel, ?int $currentScore, ?string $currentRemark): void
    {
        $this->scoreFor = 'supervisor';
        $this->scoreSupervisorId = $supervisorId;
        $this->scoreStudentLabel = $studentLabel;
        $this->scoreInput = ($currentScore !== null && $currentScore !== -1) ? (string)$currentScore : '';
        $this->remarkInput = $currentRemark ?? '';
        $this->scoreModal = true;
    }

    public function submitScore(): void
    {
        $score = intval($this->scoreInput);
        if ($score < 1 || $score > 400) {
            $this->error('Score must be between 1 and 400.', position: 'toast-bottom');
            return;
        }

        $staff = auth()->user()->staff;
        if (!$staff) return;

        if ($this->scoreFor === 'examiner') {
            $presence = FinalDefenseExaminerPresence::find($this->scorePresenceId);
            if (!$presence) return;
            $examiner = FinalDefenseExaminer::find($presence->seminar_examiner_id);
            if (!$examiner || $examiner->examiner_id !== $staff->id) {
                $this->error('Not authorized to score this applicant.', position: 'toast-bottom');
                return;
            }
            $presence->update(['score' => $score, 'remark' => $this->remarkInput]);
        } elseif ($this->scoreFor === 'supervisor') {
            $supervisor = ResearchSupervisor::where('id', $this->scoreSupervisorId)
                ->where('supervisor_id', $staff->id)
                ->first();
            if (!$supervisor) return;
            FinalDefenseSupervisorPresence::updateOrCreate(
                ['research_supervisor_id' => $supervisor->id],
                ['score' => $score, 'remark' => $this->remarkInput]
            );
        }

        $this->scoreModal = false;
        $this->scoreInput = '';
        $this->remarkInput = '';
        $this->success('Score submitted successfully.', position: 'toast-bottom');
    }

    public function toggleExaminerPresence(int $roomId, int $examinerId): void
    {
        $staff = auth()->user()->staff;
        $room = FinalDefenseRoom::with('applicant.research.supervisor')->find($roomId);
        if (!$room || $room->moderator_id !== $staff->id) {
            $this->error('Only the room moderator can update presence.', position: 'toast-bottom');
            return;
        }

        $examiner = FinalDefenseExaminer::find($examinerId);
        if (!$examiner) return;

        $exists = FinalDefenseExaminerPresence::where('seminar_examiner_id', $examinerId)
            ->where('room_id', $roomId)
            ->exists();

        if ($exists) {
            FinalDefenseExaminerPresence::where('seminar_examiner_id', $examinerId)
                ->where('room_id', $roomId)
                ->delete();
            $this->warning('Presence removed.', position: 'toast-bottom');
        } else {
            $pubModel = \App\Models\ArSys\DefenseModel::where('code', 'PUB')->first();
            $examinerStaffId = $examiner->examiner_id;
            $presenceData = [];
            foreach ($room->applicant as $applicant) {
                $isSupervisor = $applicant->research->supervisor->contains('supervisor_id', $examinerStaffId);
                $presenceData[] = [
                    'event_id' => $room->event_id,
                    'room_id' => $roomId,
                    'seminar_examiner_id' => $examinerId,
                    'applicant_id' => $applicant->id,
                    'defense_model_id' => $pubModel?->id,
                    'score' => $isSupervisor ? -1 : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($presenceData)) {
                FinalDefenseExaminerPresence::insert($presenceData);
            }
            $this->success('Presence marked.', position: 'toast-bottom');
        }
    }

    public function with(): array
    {
        $staff = auth()->user()->staff;
        if (!$staff) {
            return ['rooms' => collect(), 'event' => null, 'noStaff' => true, 'scoreGuide' => collect()];
        }

        $staffId = $staff->id;
        $event = Event::find($this->eventId);

        $rooms = FinalDefenseRoom::where('event_id', $this->eventId)
            ->where(function ($q) use ($staffId) {
                $q->whereHas('examiner', fn($sq) => $sq->where('examiner_id', $staffId))
                  ->orWhereHas('applicant.research.supervisor', fn($sq) => $sq->where('supervisor_id', $staffId));
            })
            ->with([
                'space',
                'session',
                'moderator',
                'examiner.staff',
                'applicant.research.student.program',
                'applicant.research.supervisor.staff',
                'applicant.research.supervisor.finaldefenseSupervisorPresence',
                'applicant.research.milestone',
            ])
            ->get();

        // Load examiner presence records
        $allExaminerIds = $rooms->flatMap(fn($r) => $r->examiner->pluck('id'))->unique();
        $allApplicantIds = $rooms->flatMap(fn($r) => $r->applicant->pluck('id'))->unique();

        $presenceRecords = FinalDefenseExaminerPresence::whereIn('seminar_examiner_id', $allExaminerIds)
            ->get()
            ->groupBy('seminar_examiner_id');

        $myExaminerEntry = FinalDefenseExaminer::where('event_id', $this->eventId)
            ->where('examiner_id', $staffId)
            ->first();

        $myExaminerScores = collect();
        if ($myExaminerEntry) {
            $myExaminerScores = FinalDefenseExaminerPresence::where('seminar_examiner_id', $myExaminerEntry->id)
                ->whereIn('applicant_id', $allApplicantIds)
                ->get()
                ->keyBy('applicant_id');
        }

        $scoreGuide = DefenseScoreGuide::orderBy('sequence')->get();

        return [
            'rooms' => $rooms,
            'event' => $event,
            'noStaff' => false,
            'presenceRecords' => $presenceRecords,
            'myExaminerScores' => $myExaminerScores,
            'staffId' => $staffId,
            'scoreGuide' => $scoreGuide,
        ];
    }
};
?>

<div>
    @if(isset($event) && $event && $event->date)
        <p class="px-4 py-2 text-xs text-purple-600 font-medium">{{ \Carbon\Carbon::parse($event->date)->format('d M Y') }}</p>
    @endif

    <div class="px-3 py-3 space-y-4">

        @if($noStaff)
            <div class="py-16 text-center">
                <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
                <p class="text-sm font-semibold text-gray-500">No staff record linked to your account.</p>
            </div>

        @elseif($rooms->isEmpty())
            <div class="py-16 text-center">
                <x-icon name="o-trophy" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No rooms found for you.</p>
            </div>

        @else
            @foreach($rooms as $room)
                @php
                    $isExaminer  = $room->examiner->contains('examiner_id', $staffId);
                    $isModerator = $room->moderator_id == $staffId;
                    $isExaminerOrModerator = $isExaminer || $isModerator;
                    $supervisedApplicants  = $room->applicant
                        ->filter(fn($a) => $a->research?->supervisor->contains('supervisor_id', $staffId));
                    $isSupervisorInRoom = $supervisedApplicants->isNotEmpty();
                    // Merge into one card when staff has both roles in this room
                    $showCombined = $isExaminerOrModerator && $isSupervisorInRoom;
                @endphp

                {{-- ─── Examiner / Moderator Card (+ merged supervisor section if applicable) ─── --}}
                @if($isExaminerOrModerator)
                <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 border-indigo-600">

                    {{-- Room Header --}}
                    <div class="flex items-center gap-3 bg-indigo-50 px-3 py-2.5 border-b border-indigo-100">
                        <x-icon name="o-building-office" class="h-4 w-4 text-indigo-500 shrink-0" />
                        <div class="flex-1">
                            <span class="text-sm font-bold text-indigo-700">{{ $room->space?->code ?? 'Room' }}</span>
                            @if($room->session)
                                <span class="ml-2 text-[11px] text-indigo-400">{{ $room->session->time ?? $room->session->name }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-1">
                            @if($isModerator)
                                <span class="rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-bold text-white">Moderator</span>
                            @else
                                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-600">Examiner</span>
                            @endif
                            @if($showCombined)
                                <span class="rounded-full bg-purple-100 px-2 py-0.5 text-[10px] font-semibold text-purple-600">+ Supervisor</span>
                            @endif
                        </div>
                    </div>

                    <div class="p-3 space-y-3">

                        {{-- Examiners & Moderator --}}
                        <div>
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1.5">Examiners &amp; Moderator</p>

                            @if($room->moderator)
                                <div class="flex items-center gap-2.5 py-1.5">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600">
                                        {{ strtoupper(substr($room->moderator->name ?? 'M', 0, 1)) }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-gray-800 truncate">{{ $room->moderator->name }}</p>
                                        <p class="text-[10px] text-gray-400">{{ $room->moderator->code ?? '' }}</p>
                                    </div>
                                    <span class="rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-bold text-white">Moderator</span>
                                </div>
                            @endif

                            @foreach($room->examiner as $examiner)
                                @if($room->moderator_id != $examiner->examiner_id)
                                    @php
                                        $examPresenceRows = $presenceRecords->get($examiner->id, collect());
                                        $examIsPresent = $examPresenceRows->where('room_id', $room->id)->isNotEmpty();
                                    @endphp
                                    <div class="flex items-center gap-2.5 py-1.5">
                                        @if($isModerator)
                                            <button
                                                wire:click="toggleExaminerPresence({{ $room->id }}, {{ $examiner->id }})"
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $examIsPresent ? 'bg-green-100 text-green-600' : 'bg-gray-100 text-gray-400' }} hover:opacity-80 transition-opacity">
                                                <x-icon name="{{ $examIsPresent ? 'o-check' : 'o-plus' }}" class="h-4 w-4" />
                                            </button>
                                        @else
                                            @if($examIsPresent)
                                                <x-icon name="o-check-circle" class="h-8 w-8 text-green-400 shrink-0" />
                                            @else
                                                <x-icon name="o-x-circle" class="h-8 w-8 text-gray-300 shrink-0" />
                                            @endif
                                        @endif
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-semibold text-gray-800 truncate">{{ $examiner->staff?->name ?? '—' }}</p>
                                            <p class="text-[10px] text-gray-400">{{ $examiner->staff?->code ?? '' }}</p>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        {{-- Participants (with supervisor score merged in if applicable) --}}
                        <div>
                            <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1.5">Participants</p>
                            @foreach($room->applicant as $applicant)
                                @php
                                    $isSupervised    = $supervisedApplicants->contains('id', $applicant->id);
                                    $applicantName   = trim(($applicant->research?->student?->first_name ?? '') . ' ' . ($applicant->research?->student?->last_name ?? ''));
                                    $nim             = $applicant->research?->student?->nim ?? '—';
                                    $myScore         = $myExaminerScores->get($applicant->id);
                                    $hasExamScore    = $myScore && $myScore->score !== null && $myScore->score !== -1;
                                    $spvRecord       = $isSupervised ? $applicant->research->supervisor->firstWhere('supervisor_id', $staffId) : null;
                                    $spvPresence     = $spvRecord?->finaldefenseSupervisorPresence;
                                    $spvScore        = $spvPresence?->score;
                                    $hasSpvScore     = $spvScore !== null && $spvScore !== -1;
                                @endphp
                                <div class="flex items-center gap-2.5 py-1.5 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full
                                                {{ $isSupervised ? ($hasSpvScore ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-600') : ($hasExamScore ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-400') }}
                                                text-xs font-bold">
                                        {{ strtoupper(substr($applicantName ?: 'S', 0, 1)) }}
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <p class="text-[10px] font-semibold text-purple-600">{{ $nim }}</p>
                                        <p class="text-xs font-semibold text-gray-800 truncate">{{ $applicantName ?: '—' }}</p>
                                    </div>

                                    @if($isSupervised && $spvRecord)
                                        {{-- Supervisor score badge --}}
                                        <div class="flex items-center gap-1">
                                            <span class="text-[9px] font-bold px-1 py-0.5 rounded bg-purple-100 text-purple-700">SPV</span>
                                            @if($hasSpvScore)
                                                <button
                                                    wire:click="openSupervisorScoreModal({{ $spvRecord->id }}, '{{ addslashes($nim . ' ' . $applicantName) }}', {{ $spvScore ?? 'null' }}, '{{ addslashes($spvPresence?->remark ?? '') }}')"
                                                    class="rounded-full bg-green-500 px-2.5 py-0.5 text-[11px] font-bold text-white hover:opacity-80">
                                                    {{ $spvScore }}
                                                </button>
                                            @else
                                                <button
                                                    wire:click="openSupervisorScoreModal({{ $spvRecord->id }}, '{{ addslashes($nim . ' ' . $applicantName) }}', null, '')"
                                                    class="rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-semibold text-red-500 hover:bg-red-200">
                                                    Unscored
                                                </button>
                                            @endif
                                        </div>
                                    @elseif(!$isSupervised && $myScore)
                                        {{-- Examiner score badge --}}
                                        @if($hasExamScore)
                                            <button
                                                wire:click="openExaminerScoreModal({{ $myScore->id }}, '{{ addslashes($nim . ' ' . $applicantName) }}', {{ $myScore->score ?? 'null' }}, '{{ addslashes($myScore->remark ?? '') }}')"
                                                class="rounded-full bg-green-500 px-2.5 py-0.5 text-[11px] font-bold text-white hover:opacity-80">
                                                {{ $myScore->score }}
                                            </button>
                                        @else
                                            <button
                                                wire:click="openExaminerScoreModal({{ $myScore->id }}, '{{ addslashes($nim . ' ' . $applicantName) }}', null, '')"
                                                class="rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-semibold text-red-500 hover:bg-red-200">
                                                Unscored
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            @endforeach
                        </div>

                    </div>
                </div>

                {{-- ─── Supervisor-only Card (shown only when NOT also examiner/moderator) ─── --}}
                @elseif($isSupervisorInRoom)
                <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 border-purple-300">

                    <div class="flex items-center gap-3 bg-purple-50 px-3 py-2.5 border-b border-purple-100">
                        <x-icon name="o-building-office" class="h-4 w-4 text-purple-400 shrink-0" />
                        <div class="flex-1">
                            <span class="text-sm font-bold text-purple-700">{{ $room->space?->code ?? 'Room' }}</span>
                            @if($room->session)
                                <span class="ml-2 text-[11px] text-purple-400">{{ $room->session->time ?? $room->session->name }}</span>
                            @endif
                        </div>
                        <span class="rounded-full bg-purple-200 px-2 py-0.5 text-[10px] font-bold text-purple-700">Supervisor</span>
                    </div>

                    <div class="p-3 space-y-2">
                        <p class="text-[11px] font-bold text-gray-500 uppercase tracking-wide mb-1.5">My Supervised Participants</p>

                        @foreach($supervisedApplicants as $applicant)
                            @php
                                $mySupervisorRecord = $applicant->research->supervisor->firstWhere('supervisor_id', $staffId);
                                $spvPresence = $mySupervisorRecord?->finaldefenseSupervisorPresence;
                                $spvScore    = $spvPresence?->score;
                                $hasSpvScore = $spvScore !== null && $spvScore !== -1;
                                $nim         = $applicant->research?->student?->nim ?? '—';
                                $studentName = trim(($applicant->research?->student?->first_name ?? '') . ' ' . ($applicant->research?->student?->last_name ?? '')) ?: '—';
                            @endphp
                            <div class="flex items-center gap-2.5 py-1.5 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $hasSpvScore ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-600' }} text-xs font-bold">
                                    {{ strtoupper(substr($studentName, 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[10px] font-semibold text-purple-600">{{ $nim }}</p>
                                    <p class="text-xs font-semibold text-gray-800 truncate">{{ $studentName }}</p>
                                </div>
                                @if($mySupervisorRecord)
                                    @if($hasSpvScore)
                                        <button
                                            wire:click="openSupervisorScoreModal({{ $mySupervisorRecord->id }}, '{{ addslashes($nim . ' ' . $studentName) }}', {{ $spvScore ?? 'null' }}, '{{ addslashes($spvPresence?->remark ?? '') }}')"
                                            class="rounded-full bg-green-500 px-2.5 py-0.5 text-[11px] font-bold text-white hover:opacity-80">
                                            {{ $spvScore }}
                                        </button>
                                    @else
                                        <button
                                            wire:click="openSupervisorScoreModal({{ $mySupervisorRecord->id }}, '{{ addslashes($nim . ' ' . $studentName) }}', null, '')"
                                            class="rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-semibold text-red-500 hover:bg-red-200">
                                            Unscored
                                        </button>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

            @endforeach
        @endif

    </div>

    {{-- ─── Score Bottom Sheet ─── --}}
    {{-- Backdrop --}}
    <div x-data x-show="$wire.scoreModal"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         wire:click="$set('scoreModal', false)"
         class="fixed inset-0 z-30 bg-black/50">
    </div>

    {{-- Sheet panel --}}
    <div x-data x-show="$wire.scoreModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-full"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 transform rounded-t-2xl bg-white shadow-2xl max-h-[85vh] overflow-y-auto">

        {{-- Handle --}}
        <div class="flex justify-center pt-3 pb-1">
            <div class="h-1 w-10 rounded-full bg-gray-300"></div>
        </div>

        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-800">Submit Score</h3>
            <button wire:click="$set('scoreModal', false)"
                class="flex items-center justify-center h-7 w-7 rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>

        {{-- Content --}}
        <div class="px-4 py-4 space-y-4">
            @if($scoreStudentLabel)
                <div class="rounded-xl bg-purple-50 px-3 py-2.5">
                    <p class="text-sm font-bold text-gray-800">{{ $scoreStudentLabel }}</p>
                </div>
            @endif

            <x-input
                wire:model="scoreInput"
                label="Score"
                type="number"
                min="1"
                max="400"
                hint="Range: 1–400"
                placeholder="e.g. 350" />

            <x-textarea
                wire:model="remarkInput"
                label="Remark"
                rows="3"
                placeholder="Optional remark..." />

            <button
                wire:click="$set('guideModal', true)"
                class="text-xs font-medium text-purple-600 hover:underline">
                View Scoring Guide
            </button>
        </div>

        {{-- Actions --}}
        <div class="flex gap-3 px-4 pb-24 pt-2 border-t border-gray-100">
            <button wire:click="$set('scoreModal', false)"
                class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 active:opacity-80">
                Cancel
            </button>
            <button wire:click="submitScore" wire:loading.attr="disabled"
                class="flex-1 py-3 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 active:opacity-90 disabled:opacity-60">
                <span wire:loading.remove wire:target="submitScore">Submit</span>
                <span wire:loading wire:target="submitScore" class="loading loading-spinner loading-sm"></span>
            </button>
        </div>

    </div>

    {{-- ─── Score Guide Bottom Sheet ─── --}}
    <div x-data x-show="$wire.guideModal"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         wire:click="$set('guideModal', false)"
         class="fixed inset-0 z-30 bg-black/50">
    </div>

    <div x-data x-show="$wire.guideModal"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-full"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 transform rounded-t-2xl bg-white shadow-2xl max-h-[75vh] flex flex-col">

        <div class="flex justify-center pt-3 pb-1 shrink-0">
            <div class="h-1 w-10 rounded-full bg-gray-300"></div>
        </div>

        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <h3 class="text-base font-bold text-gray-800">Scoring Guide</h3>
            <button wire:click="$set('guideModal', false)"
                class="flex items-center justify-center h-7 w-7 rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>

        <div class="overflow-y-auto flex-1 px-4 py-3">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs text-gray-500">
                        <th class="pb-2 pr-3 font-semibold">Grade</th>
                        <th class="pb-2 pr-3 font-semibold">Range</th>
                        <th class="pb-2 font-semibold">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($scoreGuide as $guide)
                        <tr>
                            <td class="py-2 pr-3 font-semibold text-purple-600">{{ $guide->code ?? '—' }}</td>
                            <td class="py-2 pr-3 text-gray-600 text-xs">{{ $guide->value ?? '—' }}</td>
                            <td class="py-2 text-gray-500 text-xs">{{ $guide->description ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-6 text-center text-gray-400 text-xs">No scoring guide available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 pb-8 pt-2 border-t border-gray-100 shrink-0">
            <button wire:click="$set('guideModal', false)"
                class="w-full py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                Close
            </button>
        </div>
    </div>
</div>
