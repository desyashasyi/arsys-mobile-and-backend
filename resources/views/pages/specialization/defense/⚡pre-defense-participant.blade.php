<?php

use App\Models\ArSys\DefenseExaminer;
use App\Models\ArSys\DefenseExaminerPresence;
use App\Models\ArSys\DefenseScoreGuide;
use App\Models\ArSys\DefenseSupervisorPresence;
use App\Models\ArSys\EventApplicantDefense;
use App\Models\ArSys\EventSession;
use App\Models\ArSys\EventSpace;
use App\Models\ArSys\ResearchSupervisor;
use App\Models\ArSys\Staff;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Participant Detail')] class extends Component
{
    use Toast;

    public int    $eventId          = 0;
    public int    $participantId    = 0;
    public bool   $addExaminerSheet = false;
    public bool   $scheduleSheet    = false;
    public bool   $scoreModal       = false;
    public bool   $guideModal       = false;
    public string $examinerSearch   = '';
    public int    $spaceId          = 0;
    public int    $sessionId        = 0;
    public string $scoreFor         = ''; // 'supervisor' | 'examiner'
    public int    $scoreForId       = 0;
    public string $scoreInput       = '';
    public string $remarkInput      = '';

    public function mount(int $eventId, int $participantId): void
    {
        $this->eventId       = $eventId;
        $this->participantId = $participantId;

        $p = EventApplicantDefense::find($participantId);
        $this->spaceId   = $p?->space_id   ?? 0;
        $this->sessionId = $p?->session_id ?? 0;
    }

    private function getProgramId(): ?int
    {
        return auth()->user()->staff?->program_id;
    }

    public function saveSchedule(): void
    {
        $p = EventApplicantDefense::whereHas('event', fn($q) => $q->where('program_id', $this->getProgramId()))
            ->find($this->participantId);
        if (!$p) { $this->error('Participant not found.', position: 'toast-bottom'); return; }

        $p->update([
            'space_id'   => $this->spaceId   ?: null,
            'session_id' => $this->sessionId ?: null,
        ]);

        $this->scheduleSheet = false;
        $this->success('Schedule updated.', position: 'toast-bottom');
    }

    public function toggleSupervisorPresence(int $supervisorRecordId): void
    {
        $staffId = auth()->user()->staff?->id;
        $spv = ResearchSupervisor::where('id', $supervisorRecordId)
            ->where('supervisor_id', $staffId)->first();
        if (!$spv) { $this->error('Not authorized.', position: 'toast-bottom'); return; }

        $presence = DefenseSupervisorPresence::where('research_supervisor_id', $supervisorRecordId)->first();
        if ($presence) {
            $presence->delete();
            $this->warning('Supervisor presence removed.', position: 'toast-bottom');
        } else {
            DefenseSupervisorPresence::create(['research_supervisor_id' => $supervisorRecordId]);
            $this->success('Supervisor marked as present.', position: 'toast-bottom');
        }
    }

    public function toggleExaminerPresence(int $examinerId): void
    {
        if ($examinerId <= 0) return;

        $staffId     = auth()->user()->staff?->id;
        $participant = EventApplicantDefense::with('research.supervisor')->find($this->participantId);
        if (!$participant || !$participant->research->supervisor->contains('supervisor_id', $staffId)) {
            $this->error('Only the supervisor can toggle examiner presence.', position: 'toast-bottom');
            return;
        }

        $examiner = DefenseExaminer::with('defenseExaminerPresence')->find($examinerId);
        if (!$examiner) return;

        if ($examiner->defenseExaminerPresence) {
            $examiner->defenseExaminerPresence->delete();
            $this->warning('Examiner presence removed.', position: 'toast-bottom');
        } else {
            $count = DefenseExaminer::where('applicant_id', $examiner->applicant_id)
                ->has('defenseExaminerPresence')->count();
            if ($count >= 3) { $this->error('Maximum 3 examiners allowed.', position: 'toast-bottom'); return; }
            DefenseExaminerPresence::create([
                'defense_examiner_id' => $examinerId,
                'event_id'            => $examiner->event_id,
                'examiner_id'         => $examiner->examiner_id,
            ]);
            $this->success('Examiner presence marked.', position: 'toast-bottom');
        }
    }

    public function addExaminer(int $staffId): void
    {
        $participant = EventApplicantDefense::whereHas('event', fn($q) => $q->where('program_id', $this->getProgramId()))
            ->find($this->participantId);
        if (!$participant) { $this->error('Participant not found.', position: 'toast-bottom'); return; }

        if (DefenseExaminer::where('applicant_id', $this->participantId)->where('examiner_id', $staffId)->exists()) {
            $this->warning('This person is already an examiner.', position: 'toast-bottom'); return;
        }

        DefenseExaminer::create([
            'event_id'     => $participant->event_id,
            'applicant_id' => $this->participantId,
            'examiner_id'  => $staffId,
            'additional'   => 1,
        ]);

        $this->addExaminerSheet = false;
        $this->examinerSearch   = '';
        $this->success('Examiner added.', position: 'toast-bottom');
    }

    public function removeExaminer(int $examinerId): void
    {
        $examiner = DefenseExaminer::whereHas('defenseApplicant.event', fn($q) => $q->where('program_id', $this->getProgramId()))
            ->find($examinerId);
        if (!$examiner) { $this->error('Not authorized.', position: 'toast-bottom'); return; }

        $examiner->defenseExaminerPresence?->delete();
        $examiner->delete();
        $this->success('Examiner removed.', position: 'toast-bottom');
    }

    public function openScoreModal(string $role, int $id, ?int $currentScore, ?string $currentRemark): void
    {
        $this->scoreFor    = $role;
        $this->scoreForId  = $id;
        $this->scoreInput  = ($currentScore !== null && $currentScore !== -1) ? (string)$currentScore : '';
        $this->remarkInput = $currentRemark ?? '';
        $this->scoreModal  = true;
    }

    public function submitScore(): void
    {
        $score = intval($this->scoreInput);
        if ($score < 1 || $score > 400) {
            $this->error('Score must be between 1 and 400.', position: 'toast-bottom'); return;
        }

        $staff = auth()->user()->staff;
        if (!$staff) return;

        if ($this->scoreFor === 'supervisor') {
            $spv = ResearchSupervisor::where('id', $this->scoreForId)
                ->where('supervisor_id', $staff->id)->first();
            if (!$spv) return;
            $spv->defenseSupervisorPresence()->updateOrCreate(
                ['research_supervisor_id' => $spv->id],
                ['score' => $score, 'remark' => $this->remarkInput]
            );
        } elseif ($this->scoreFor === 'examiner') {
            $examiner = DefenseExaminer::where('id', $this->scoreForId)
                ->where('examiner_id', $staff->id)->first();
            if (!$examiner || !$examiner->defenseExaminerPresence) {
                $this->error('Examiner presence not found. Mark attendance first.', position: 'toast-bottom'); return;
            }
            $examiner->defenseExaminerPresence->update([
                'score'  => $score,
                'remark' => $this->remarkInput,
            ]);
        }

        $this->scoreModal  = false;
        $this->scoreInput  = '';
        $this->remarkInput = '';
        $this->success('Score submitted.', position: 'toast-bottom');
    }

    public function with(): array
    {
        $participant = EventApplicantDefense::with([
            'research.student.program',
            'research.supervisor.staff',
            'research.supervisor.defenseSupervisorPresence',
            'research.milestone',
            'defenseExaminer.staff',
            'defenseExaminer.defenseExaminerPresence',
            'space',
            'session',
        ])->whereHas('event', fn($q) => $q->where('program_id', $this->getProgramId()))
          ->find($this->participantId);

        if (!$participant) {
            return [
                'participant'       => null,
                'isSupervisor'      => false,
                'isExaminer'        => false,
                'isExaminerPresent' => false,
                'myExaminer'        => null,
                'mySupervisor'      => null,
                'scoreGuide'        => collect(),
                'spaces'            => collect(),
                'sessions'          => collect(),
                'availableStaff'    => collect(),
            ];
        }

        $staffId           = auth()->user()->staff?->id;
        $isSupervisor      = $participant->research->supervisor->contains('supervisor_id', $staffId);
        $myExaminer        = $participant->defenseExaminer->firstWhere('examiner_id', $staffId);
        $isExaminer        = $myExaminer !== null;
        $isExaminerPresent = $isExaminer && $myExaminer->defenseExaminerPresence !== null;
        $mySupervisor      = $isSupervisor
            ? $participant->research->supervisor->firstWhere('supervisor_id', $staffId)
            : null;

        $assignedIds    = $participant->defenseExaminer->pluck('examiner_id');
        $availableStaff = $this->examinerSearch
            ? Staff::whereNotIn('id', $assignedIds)
                ->where(fn($q) => $q
                    ->where('first_name', 'like', "%{$this->examinerSearch}%")
                    ->orWhere('last_name',  'like', "%{$this->examinerSearch}%")
                    ->orWhere('code',       'like', "%{$this->examinerSearch}%"))
                ->orderBy('first_name')->limit(20)->get()
            : collect();

        return [
            'participant'       => $participant,
            'isSupervisor'      => $isSupervisor,
            'isExaminer'        => $isExaminer,
            'isExaminerPresent' => $isExaminerPresent,
            'myExaminer'        => $myExaminer,
            'mySupervisor'      => $mySupervisor,
            'scoreGuide'        => DefenseScoreGuide::orderBy('sequence')->get(),
            'spaces'            => EventSpace::orderBy('code')->get(['id', 'code']),
            'sessions'          => EventSession::orderBy('time')->get(['id', 'time', 'day']),
            'availableStaff'    => $availableStaff,
        ];
    }
};
?>

<div>

    <div class="px-3 py-3 space-y-4 pb-20">

        @if(!$participant)
            <div class="py-16 text-center">
                <x-icon name="o-exclamation-circle" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">Participant not found.</p>
            </div>
        @else

        {{-- ─── Student Info Card ─── --}}
        <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $participant->publish ? 'border-green-400' : 'border-amber-400' }}">
            <div class="p-3 space-y-2">

                {{-- Room + Session + Edit schedule --}}
                <div class="flex items-center gap-3">
                    @if($participant->space)
                        <div class="flex items-center gap-1">
                            <x-icon name="o-map-pin" class="h-3.5 w-3.5 text-purple-400 shrink-0" />
                            <span class="text-[11px] font-semibold text-gray-600">{{ $participant->space->code }}</span>
                        </div>
                    @else
                        <div class="flex items-center gap-1">
                            <x-icon name="o-map-pin" class="h-3.5 w-3.5 text-gray-300 shrink-0" />
                            <span class="text-[11px] text-gray-300">—</span>
                        </div>
                    @endif
                    @if($participant->session)
                        <div class="flex items-center gap-1">
                            <x-icon name="o-clock" class="h-3.5 w-3.5 text-purple-400 shrink-0" />
                            <span class="text-[11px] text-gray-500">{{ $participant->session->time }}</span>
                        </div>
                    @else
                        <div class="flex items-center gap-1">
                            <x-icon name="o-clock" class="h-3.5 w-3.5 text-gray-300 shrink-0" />
                            <span class="text-[11px] text-gray-300">—</span>
                        </div>
                    @endif
                    <button wire:click="$set('scheduleSheet', true)"
                        class="ml-auto flex items-center gap-1 text-[10px] font-semibold text-teal-600 bg-teal-50 px-2 py-1 rounded-full hover:bg-teal-100 active:scale-95">
                        <x-icon name="o-pencil" class="h-3 w-3" />Edit
                    </button>
                </div>

                {{-- NIM --}}
                <p class="text-xs font-semibold text-purple-600">
                    {{ $participant->research?->student?->nim ?? '—' }}
                </p>

                {{-- Name --}}
                <p class="text-sm font-bold text-gray-800 leading-tight">
                    {{ trim(($participant->research?->student?->first_name ?? '') . ' ' . ($participant->research?->student?->last_name ?? '')) ?: '—' }}
                </p>

                {{-- Research title --}}
                @if($participant->research?->title)
                    <p class="text-[11px] text-gray-500 uppercase leading-snug">{{ $participant->research->title }}</p>
                @endif

                {{-- Milestone --}}
                @if($participant->research?->milestone)
                    <x-milestone-chip :code="$participant->research->milestone->code" :phase="$participant->research->milestone->phase" />
                @endif

                {{-- Publish badge --}}
                <span class="inline-flex text-[9px] font-bold px-2 py-0.5 rounded-full {{ $participant->publish ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ $participant->publish ? 'Published' : 'Draft' }}
                </span>
            </div>
        </div>

        {{-- ─── Supervisors Section ─── --}}
        <div>
            <div class="flex items-center gap-2 mb-2 px-1">
                <x-icon name="o-user-group" class="h-4 w-4 text-purple-500" />
                <h3 class="text-sm font-bold text-gray-700">Supervisors</h3>
            </div>

            <div class="rounded-xl bg-white shadow-sm overflow-hidden">
                @forelse($participant->research->supervisor as $spv)
                    @php
                        $spvPresence    = $spv->defenseSupervisorPresence;
                        $spvScore       = $spvPresence?->score;
                        $spvHasPresence = $spvPresence !== null;
                        $isMySpv        = $isSupervisor && $mySupervisor && $mySupervisor->id === $spv->id;
                        $spvName        = trim(($spv->staff?->first_name ?? '') . ' ' . ($spv->staff?->last_name ?? ''));
                    @endphp
                    <div wire:key="spv-{{ $spv->id }}" class="flex items-center gap-3 p-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">

                        {{-- Presence icon (tappable only if own supervisor record) --}}
                        @if($isMySpv)
                            <button wire:click="toggleSupervisorPresence({{ $spv->id }})"
                                class="shrink-0 hover:opacity-70 transition-opacity">
                                <x-icon name="{{ $spvHasPresence ? 's-check-circle' : 'o-check-circle' }}"
                                        class="h-7 w-7 {{ $spvHasPresence ? 'text-green-500' : 'text-gray-300' }}" />
                            </button>
                        @else
                            <x-icon name="{{ $spvHasPresence ? 's-check-circle' : 'o-check-circle' }}"
                                    class="h-7 w-7 {{ $spvHasPresence ? 'text-green-500' : 'text-gray-300' }} shrink-0" />
                        @endif

                        {{-- Name + role badge --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <p class="text-sm font-semibold text-gray-800 truncate">{{ $spvName ?: '—' }}</p>
                                <span class="shrink-0 text-[8px] font-bold px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">
                                    {{ $spv->order <= 1 ? 'SPV' : 'Co-SPV' }}
                                </span>
                            </div>
                            @if($spv->staff?->code)
                                <p class="text-[11px] text-gray-400">{{ $spv->staff->code }}</p>
                            @endif
                        </div>

                        {{-- Score badge --}}
                        @if($isMySpv)
                            @if($spvScore !== null && $spvScore !== -1)
                                <button wire:click="openScoreModal('supervisor', {{ $spv->id }}, {{ $mySupervisor?->defenseSupervisorPresence?->score ?? 'null' }}, '{{ addslashes($mySupervisor?->defenseSupervisorPresence?->remark ?? '') }}')"
                                    class="shrink-0 rounded-full bg-green-500 px-2.5 py-0.5 text-[11px] font-bold text-white hover:opacity-80">
                                    {{ $spvScore }}
                                </button>
                            @else
                                <button wire:click="openScoreModal('supervisor', {{ $spv->id }}, null, '')"
                                    class="shrink-0 rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-semibold text-red-500 hover:bg-red-200">
                                    Unscored
                                </button>
                            @endif
                        @else
                            @if($spvScore !== null && $spvScore !== -1)
                                <span class="shrink-0 rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-semibold text-green-700">Scored</span>
                            @else
                                <span class="shrink-0 rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-semibold text-red-500">Unscored</span>
                            @endif
                        @endif
                    </div>
                @empty
                    <div class="p-4 text-center text-sm text-gray-400">No supervisors assigned.</div>
                @endforelse
            </div>
        </div>

        {{-- ─── Examiners Section ─── --}}
        <div>
            <div class="flex items-center gap-2 mb-2 px-1">
                <x-icon name="o-clipboard-document-check" class="h-4 w-4 text-purple-500" />
                <h3 class="text-sm font-bold text-gray-700 flex-1">Examiners</h3>
                <button wire:click="$set('addExaminerSheet', true); $set('examinerSearch', '')"
                    class="flex items-center gap-1 rounded-full bg-purple-100 px-2.5 py-1 text-[11px] font-semibold text-purple-600 hover:bg-purple-200">
                    <x-icon name="o-plus" class="h-3 w-3" />Add
                </button>
            </div>

            <div class="rounded-xl bg-white shadow-sm overflow-hidden">
                @forelse($participant->defenseExaminer as $examiner)
                    @php
                        $exPresence   = $examiner->defenseExaminerPresence;
                        $hasPresence  = $exPresence !== null;
                        $examScore    = $exPresence?->score;
                        $isMyExaminer = $isExaminer && $myExaminer && $myExaminer->id === $examiner->id;
                        $exName       = trim(($examiner->staff?->first_name ?? '') . ' ' . ($examiner->staff?->last_name ?? ''));
                    @endphp
                    <div wire:key="ex-{{ $examiner->id }}" class="flex items-center gap-3 p-3 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">

                        {{-- Name + code --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $exName ?: '—' }}</p>
                            @if($examiner->staff?->code)
                                <p class="text-[11px] text-gray-400">{{ $examiner->staff->code }}</p>
                            @endif
                        </div>

                        {{-- Score badge --}}
                        @if($isMyExaminer && $isExaminerPresent)
                            @if($examScore !== null && $examScore !== -1)
                                <button wire:click="openScoreModal('examiner', {{ $examiner->id }}, {{ $myExaminer?->defenseExaminerPresence?->score ?? 'null' }}, '{{ addslashes($myExaminer?->defenseExaminerPresence?->remark ?? '') }}')"
                                    class="shrink-0 rounded-full bg-green-500 px-2.5 py-0.5 text-[11px] font-bold text-white hover:opacity-80">
                                    {{ $examScore }}
                                </button>
                            @else
                                <button wire:click="openScoreModal('examiner', {{ $examiner->id }}, null, '')"
                                    class="shrink-0 rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-semibold text-red-500 hover:bg-red-200">
                                    Unscored
                                </button>
                            @endif
                        @elseif(!$isMyExaminer)
                            @if($examScore !== null && $examScore !== -1)
                                <span class="shrink-0 rounded-full bg-green-100 px-2.5 py-0.5 text-[11px] font-semibold text-green-700">Scored</span>
                            @else
                                <span class="shrink-0 rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-semibold text-red-500">Unscored</span>
                            @endif
                        @endif

                        {{-- Presence toggle (supervisor only) --}}
                        @if($isSupervisor)
                            <button wire:click="toggleExaminerPresence({{ $examiner->id }})"
                                class="shrink-0 hover:opacity-70 transition-opacity">
                                <x-icon name="{{ $hasPresence ? 's-check-circle' : 'o-check-circle' }}"
                                        class="h-7 w-7 {{ $hasPresence ? 'text-green-500' : 'text-gray-300' }}" />
                            </button>
                        @else
                            <x-icon name="{{ $hasPresence ? 's-check-circle' : 'o-check-circle' }}"
                                    class="h-7 w-7 {{ $hasPresence ? 'text-green-500' : 'text-gray-300' }} shrink-0" />
                        @endif

                        {{-- Remove --}}
                        <button wire:click="removeExaminer({{ $examiner->id }})"
                            class="shrink-0 flex h-8 w-8 items-center justify-center rounded-full bg-red-50 hover:bg-red-100 transition-colors">
                            <x-icon name="o-x-mark" class="h-3.5 w-3.5 text-red-400" />
                        </button>
                    </div>
                @empty
                    <div class="p-6 text-center">
                        <x-icon name="o-user-plus" class="mx-auto mb-2 h-8 w-8 text-gray-200" />
                        <p class="text-xs text-gray-400">No examiners assigned.</p>
                    </div>
                @endforelse
            </div>
        </div>

        @endif
    </div>

    {{-- ─── Edit Schedule Bottom Sheet ─── --}}
    <div x-data x-show="$wire.scheduleSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('scheduleSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.scheduleSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl">
        <div class="flex justify-center pt-3 pb-1"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-800">Edit Schedule</h3>
            <button wire:click="$set('scheduleSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="px-4 py-4 space-y-4">
            <div>
                <label class="text-xs font-bold text-gray-500 mb-1.5 block uppercase tracking-wide">Room</label>
                <select wire:model="spaceId"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-teal-400">
                    <option value="0">— None —</option>
                    @foreach($spaces as $space)
                        <option value="{{ $space->id }}">{{ $space->code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs font-bold text-gray-500 mb-1.5 block uppercase tracking-wide">Session</label>
                <select wire:model="sessionId"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-teal-400">
                    <option value="0">— None —</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}">{{ $session->time }}{{ $session->day ? ' (' . $session->day . ')' : '' }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex gap-3 px-4 pb-24 pt-2 border-t border-gray-100">
            <button wire:click="$set('scheduleSheet', false)"
                class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
            <button wire:click="saveSchedule" wire:loading.attr="disabled"
                class="flex-1 py-3 rounded-xl bg-teal-600 text-white text-sm font-bold hover:bg-teal-700 disabled:opacity-60 transition-colors">
                <span wire:loading.remove wire:target="saveSchedule">Save</span>
                <span wire:loading wire:target="saveSchedule" class="loading loading-spinner loading-sm"></span>
            </button>
        </div>
    </div>

    {{-- ─── Add Examiner Bottom Sheet ─── --}}
    <div x-data x-show="$wire.addExaminerSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('addExaminerSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.addExaminerSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl max-h-[80vh] flex flex-col">
        <div class="flex justify-center pt-3 pb-1 shrink-0"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <h3 class="text-base font-bold text-gray-800">Add Examiner</h3>
            <button wire:click="$set('addExaminerSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="px-4 pt-3 pb-2 shrink-0">
            <div class="relative">
                <x-icon name="o-magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                <input wire:model.live.debounce.300ms="examinerSearch" type="text" placeholder="Search name or code..."
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 py-2.5 pl-9 pr-3 text-sm focus:border-purple-400 focus:outline-none" />
            </div>
        </div>
        <div class="overflow-y-auto flex-1 px-2 pb-20">
            @if(!$examinerSearch)
                <div class="py-10 text-center">
                    <x-icon name="o-magnifying-glass" class="mx-auto mb-2 h-8 w-8 text-gray-200" />
                    <p class="text-xs text-gray-400">Type a name or code to search.</p>
                </div>
            @elseif($availableStaff->isEmpty())
                <div class="py-10 text-center">
                    <x-icon name="o-user-slash" class="mx-auto mb-2 h-8 w-8 text-gray-200" />
                    <p class="text-xs text-gray-400">No staff found for "{{ $examinerSearch }}".</p>
                </div>
            @else
                @foreach($availableStaff as $s)
                    <button wire:click="addExaminer({{ $s->id }})"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-purple-50 active:bg-purple-100 transition-colors text-left">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-purple-100 text-sm font-bold text-purple-600">
                            {{ strtoupper(substr($s->first_name ?? 'S', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ trim($s->first_name . ' ' . $s->last_name) }}</p>
                            <p class="text-[11px] text-gray-400">{{ $s->code ?? '—' }}</p>
                        </div>
                        <x-icon name="o-plus-circle" class="h-5 w-5 text-purple-400 shrink-0" />
                    </button>
                @endforeach
            @endif
        </div>
    </div>

    {{-- ─── Score Bottom Sheet ─── --}}
    <div x-data x-show="$wire.scoreModal"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('scoreModal', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.scoreModal"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl max-h-[85vh] overflow-y-auto">
        <div class="flex justify-center pt-3 pb-1"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-gray-800">Submit Score</h3>
            <button wire:click="$set('scoreModal', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="px-4 py-4 space-y-4">
            @if($participant)
                <div class="rounded-xl bg-purple-50 px-3 py-2.5">
                    <p class="text-xs font-semibold text-purple-600">{{ $participant->research?->student?->nim ?? '—' }}</p>
                    <p class="text-sm font-bold text-gray-800">{{ trim(($participant->research?->student?->first_name ?? '') . ' ' . ($participant->research?->student?->last_name ?? '')) ?: '—' }}</p>
                </div>
            @endif
            <x-input wire:model="scoreInput" label="Score" type="number" min="1" max="400"
                hint="Enter a value between 1 and 400" placeholder="e.g. 350" />
            <x-textarea wire:model="remarkInput" label="Remark" rows="3" placeholder="Optional remark..." />
            <button wire:click="$set('guideModal', true)" class="text-xs font-medium text-purple-600 hover:underline">
                View Scoring Guide
            </button>
        </div>
        <div class="flex gap-3 px-4 pb-24 pt-2 border-t border-gray-100">
            <button wire:click="$set('scoreModal', false)"
                class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
            <button wire:click="submitScore" wire:loading.attr="disabled"
                class="flex-1 py-3 rounded-xl bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="submitScore">Submit</span>
                <span wire:loading wire:target="submitScore" class="loading loading-spinner loading-sm"></span>
            </button>
        </div>
    </div>

    {{-- ─── Score Guide Bottom Sheet ─── --}}
    <div x-data x-show="$wire.guideModal"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('guideModal', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.guideModal"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl max-h-[75vh] flex flex-col">
        <div class="flex justify-center pt-3 pb-1 shrink-0"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <h3 class="text-base font-bold text-gray-800">Scoring Guide</h3>
            <button wire:click="$set('guideModal', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 hover:bg-gray-200">
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
                        <tr><td colspan="3" class="py-6 text-center text-gray-400 text-xs">No scoring guide available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 pb-8 pt-2 border-t border-gray-100 shrink-0">
            <button wire:click="$set('guideModal', false)"
                class="w-full py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Close</button>
        </div>
    </div>
</div>
