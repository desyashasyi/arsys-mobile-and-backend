<?php

use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchLog;
use App\Models\ArSys\ResearchLogType;
use App\Models\ArSys\ResearchMilestone;
use App\Models\ArSys\ResearchMilestoneLog;
use App\Models\ArSys\ResearchRemark;
use App\Models\ArSys\ResearchReview;
use App\Models\ArSys\ResearchSupervisor;
use App\Models\ArSys\Staff;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Research Detail')] class extends Component
{
    use Toast;

    public int    $researchId   = 0;
    public string $staffSearch  = '';
    public bool   $addSpvSheet  = false;
    public bool   $addRevSheet  = false;
    public bool   $remarkSheet  = false;
    public string $newRemark    = '';
    public int    $spvOrder     = 1;

    public function mount(int $id): void
    {
        $this->researchId = $id;
    }

    private function getProgramId(): ?int
    {
        return auth()->user()->staff?->program_id;
    }

    private function getResearch(): ?Research
    {
        return Research::whereHas('student', fn($q) => $q->where('program_id', $this->getProgramId()))
            ->find($this->researchId);
    }

    public function addSupervisor(int $staffId): void
    {
        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        if ($research->supervisor()->where('supervisor_id', $staffId)->exists()) {
            $this->warning('Staff is already a supervisor.', position: 'toast-bottom'); return;
        }

        ResearchSupervisor::create([
            'research_id'   => $research->id,
            'supervisor_id' => $staffId,
            'order'         => $this->spvOrder,
        ]);

        $this->addSpvSheet = false;
        $this->staffSearch = '';
        $this->success('Supervisor added.', position: 'toast-bottom');
    }

    public function removeSupervisor(int $supervisorRecordId): void
    {
        $research = $this->getResearch();
        if (!$research) return;

        ResearchSupervisor::where('research_id', $research->id)->where('id', $supervisorRecordId)->delete();
        $this->success('Supervisor removed.', position: 'toast-bottom');
    }

    public function addReviewer(int $staffId): void
    {
        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        if ($research->reviewers()->where('reviewer_id', $staffId)->exists()) {
            $this->warning('Staff is already a reviewer.', position: 'toast-bottom'); return;
        }

        ResearchReview::create([
            'research_id' => $research->id,
            'reviewer_id' => $staffId,
        ]);

        $this->addRevSheet = false;
        $this->staffSearch = '';
        $this->success('Reviewer added.', position: 'toast-bottom');
    }

    public function removeReviewer(int $reviewerRecordId): void
    {
        $research = $this->getResearch();
        if (!$research) return;

        ResearchReview::where('research_id', $research->id)->where('id', $reviewerRecordId)->delete();
        $this->success('Reviewer removed.', position: 'toast-bottom');
    }

    public function startReview(): void
    {
        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        if ($research->reviewers()->count() === 0) {
            $this->warning('Assign at least one reviewer first.', position: 'toast-bottom'); return;
        }

        $milestone = ResearchMilestone::where('code', 'Proposal')->where('phase', 'Review')->first();
        if (!$milestone) { $this->error('Milestone not found.', position: 'toast-bottom'); return; }

        $research->update(['milestone_id' => $milestone->id]);

        ResearchMilestoneLog::create([
            'research_id' => $research->id,
            'milestone_id' => $milestone->id,
        ]);

        $this->success('Research moved to Proposal | Review.', position: 'toast-bottom');
    }

    public function assignToPreDefense(): void
    {
        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        if ($research->supervisor()->count() === 0) {
            $this->warning('Assign at least one supervisor first.', position: 'toast-bottom'); return;
        }

        $milestone = ResearchMilestone::where('code', 'Pre-defense')->where('phase', 'In Progress')->first();
        if (!$milestone) { $this->error('Milestone not found.', position: 'toast-bottom'); return; }

        DB::transaction(function () use ($research, $milestone) {
            // Deactivate SUB/REV log
            ResearchLog::where('research_id', $research->id)
                ->where('status', 1)
                ->update(['status' => null]);

            $research->update(['milestone_id' => $milestone->id]);

            ResearchMilestoneLog::create([
                'research_id' => $research->id,
                'milestone_id' => $milestone->id,
            ]);

            // Create ACT log
            $actType = ResearchLogType::where('code', 'ACT')->first();
            if ($actType) {
                ResearchLog::create([
                    'research_id' => $research->id,
                    'type_id'     => $actType->id,
                    'loger_id'    => auth()->id(),
                    'message'     => $actType->description ?? 'Research activated',
                    'status'      => 1,
                ]);
            }
        });

        $this->success('Research moved to Pre-defense | In Progress.', position: 'toast-bottom');
    }

    public function rejectResearch(): void
    {
        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        $rejectedMilestone = ResearchMilestone::where('code', 'Rejected')
            ->where('phase', 'Rejected')
            ->whereHas('researches', fn($q) => $q->where('id', $research->id))
            ->first();

        // Fallback: get by research model
        if (!$rejectedMilestone) {
            $modelId = $research->type?->base?->model?->id;
            $rejectedMilestone = ResearchMilestone::where('code', 'Rejected')
                ->where('phase', 'Rejected')
                ->where('research_model_id', $modelId)
                ->first();
        }

        if (!$rejectedMilestone) {
            // Absolute fallback
            $rejectedMilestone = ResearchMilestone::where('code', 'Rejected')->first();
        }

        if (!$rejectedMilestone) { $this->error('Rejected milestone not found.', position: 'toast-bottom'); return; }

        DB::transaction(function () use ($research, $rejectedMilestone) {
            // Deactivate all active logs
            ResearchLog::where('research_id', $research->id)
                ->where('status', 1)
                ->update(['status' => null]);

            $research->update(['milestone_id' => $rejectedMilestone->id]);

            ResearchMilestoneLog::create([
                'research_id' => $research->id,
                'milestone_id' => $rejectedMilestone->id,
            ]);

            $rjcType = ResearchLogType::where('code', 'RJC')->first();
            if ($rjcType) {
                ResearchLog::create([
                    'research_id' => $research->id,
                    'type_id'     => $rjcType->id,
                    'loger_id'    => auth()->id(),
                    'message'     => $rjcType->description ?? 'Proposal rejected',
                    'status'      => 1,
                ]);
            }
        });

        $this->success('Research rejected.', position: 'toast-bottom');
    }

    public function addRemark(): void
    {
        $this->validate(['newRemark' => 'required|string|max:2000']);

        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        ResearchRemark::create([
            'research_id'   => $research->id,
            'discussant_id' => auth()->id(),
            'message'       => $this->newRemark,
        ]);

        $this->newRemark = '';
        $this->success('Remark added.', position: 'toast-bottom');
    }

    public function deleteRemark(int $remarkId): void
    {
        ResearchRemark::where('id', $remarkId)
            ->where('discussant_id', auth()->id())
            ->delete();

        $this->success('Remark deleted.', position: 'toast-bottom');
    }

    public function getStaffResults(): \Illuminate\Support\Collection
    {
        $programId = $this->getProgramId();
        if (!$programId || strlen(trim($this->staffSearch)) < 2) return collect();

        return Staff::where('program_id', $programId)
            ->where(function ($q) {
                $q->where('first_name', 'like', "%{$this->staffSearch}%")
                  ->orWhere('last_name', 'like', "%{$this->staffSearch}%")
                  ->orWhere('code', 'like', "%{$this->staffSearch}%");
            })
            ->limit(8)
            ->get()
            ->map(fn($s) => [
                'id'   => $s->id,
                'name' => trim($s->first_name . ' ' . $s->last_name),
                'code' => $s->code ?? '',
            ]);
    }

    public function with(): array
    {
        $research = Research::with([
            'type.base.model',
            'student.program',
            'milestone',
            'supervisor.staff',
            'reviewers.staff',
            'reviewers.decision',
            'remark' => fn($q) => $q->orderBy('id', 'asc')->with('user.staff', 'user.student'),
            'history' => fn($q) => $q->orderBy('id', 'desc')->with('type'),
        ])->whereHas('student', fn($q) => $q->where('program_id', $this->getProgramId()))
          ->find($this->researchId);

        if (!$research) {
            return ['research' => null, 'staffResults' => collect()];
        }

        $milestoneCode  = $research->milestone?->code ?? '';
        $milestonePhase = $research->milestone?->phase ?? '';

        $supervisors = $research->supervisor->sortBy('order')->map(fn($s) => [
            'id'    => $s->id,
            'name'  => trim(($s->staff?->first_name ?? '') . ' ' . ($s->staff?->last_name ?? '')),
            'code'  => $s->staff?->code ?? '',
            'order' => $s->order,
            'role'  => $s->order <= 1 ? 'Supervisor' : 'Co-Supervisor',
        ]);

        $reviewers = $research->reviewers->map(fn($r) => [
            'id'         => $r->id,
            'name'       => trim(($r->staff?->first_name ?? '') . ' ' . ($r->staff?->last_name ?? '')),
            'code'       => $r->staff?->code ?? '',
            'decision'   => $r->decision?->description ?? null,
        ]);

        $allReviewersDecided = $reviewers->count() > 0 && $reviewers->every(fn($r) => $r['decision'] !== null);

        $canManageSupervisors  = in_array($milestoneCode, ['Proposal', 'Pre-defense', 'Final-defense']);
        $canManageReviewers    = $milestoneCode === 'Proposal';
        $canStartReview        = $milestoneCode === 'Proposal' && $milestonePhase === 'Submitted' && $reviewers->count() > 0;
        $canAssignPreDefense   = $milestoneCode === 'Proposal' && $allReviewersDecided && $supervisors->count() > 0;
        $canReject             = in_array($milestoneCode, ['Proposal']) && in_array($milestonePhase, ['Submitted', 'Review']);

        $history = $research->history->map(fn($h) => [
            'type_code'  => $h->type?->code,
            'type_desc'  => $h->type?->description ?? $h->type?->code,
            'message'    => $h->message,
            'status'     => $h->status,
            'created_at' => $h->created_at?->format('d M Y H:i'),
        ]);

        return [
            'research' => [
                'id'             => $research->id,
                'title'          => $research->title ?? 'No Title',
                'abstract'       => $research->abstract ?? '',
                'file'           => $research->file,
                'student_name'   => trim(($research->student?->first_name ?? '') . ' ' . ($research->student?->last_name ?? '')),
                'student_number' => $research->student?->nim ?? '-',
                'program'        => $research->student?->program?->name ?? '',
                'milestone_code' => $milestoneCode,
                'milestone_phase'=> $milestonePhase,
                'milestone'      => $research->milestone,
                'supervisors'    => $supervisors,
                'reviewers'      => $reviewers,
                'remarks'        => $research->remark->map(function ($r) {
                    $author = 'Unknown';
                    if ($r->user?->staff) {
                        $author = trim($r->user->staff->first_name . ' ' . $r->user->staff->last_name);
                    } elseif ($r->user?->student) {
                        $author = trim($r->user->student->first_name . ' ' . $r->user->student->last_name);
                    } elseif ($r->user) {
                        $author = $r->user->name;
                    }
                    return [
                        'id'         => $r->id,
                        'message'    => $r->message,
                        'author'     => $author,
                        'is_mine'    => $r->discussant_id === auth()->id(),
                        'created_at' => $r->created_at?->format('d M Y H:i'),
                    ];
                }),
                'history'        => $history,
                'can_manage_supervisors'  => $canManageSupervisors,
                'can_manage_reviewers'    => $canManageReviewers,
                'can_start_review'        => $canStartReview,
                'can_assign_pre_defense'  => $canAssignPreDefense,
                'can_reject'              => $canReject,
            ],
            'staffResults' => $this->getStaffResults(),
        ];
    }
};
?>

<div x-data="{ confirmReject: false }">

    @if(!$research)
        <div class="py-16 text-center">
            <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
            <p class="text-sm text-gray-500">Research not found.</p>
        </div>
    @else
    <div class="px-3 py-3 space-y-3 pb-36">

        {{-- ─── Student & Research Info ─── --}}
        <div class="rounded-xl bg-white shadow-sm overflow-hidden">
            {{-- Student identity --}}
            <div class="bg-gradient-to-r from-purple-600 to-purple-400 px-4 py-3">
                <p class="text-[11px] font-semibold text-white/70">{{ $research['student_number'] }}</p>
                <p class="font-bold text-sm text-white leading-tight">{{ $research['student_name'] ?: '—' }}</p>
            </div>
            {{-- Research info --}}
            <div class="p-4 space-y-2">
                <p class="text-sm font-semibold text-gray-800 leading-snug">{{ $research['title'] }}</p>
                @if($research['milestone'])
                    <x-milestone-chip :code="$research['milestone']->code" :phase="$research['milestone']->phase" />
                @endif
                @if($research['abstract'])
                    <p class="text-xs text-gray-500 leading-relaxed line-clamp-3 pt-1 border-t border-gray-50">{{ $research['abstract'] }}</p>
                @endif
                @if($research['file'])
                    <a href="{{ $research['file'] }}" target="_blank"
                       class="flex items-center gap-1.5 text-xs text-blue-500 hover:underline">
                        <x-icon name="o-link" class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate">{{ $research['file'] }}</span>
                        <x-icon name="o-arrow-top-right-on-square" class="h-3 w-3 shrink-0" />
                    </a>
                @endif
            </div>
        </div>

        {{-- ─── Supervisors ─── --}}
        <div class="rounded-xl bg-white shadow-sm p-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-bold text-indigo-600 uppercase tracking-wide">Supervisors</p>
                @if($research['can_manage_supervisors'])
                    <button wire:click="$set('addSpvSheet', true); $set('addRevSheet', false); $set('staffSearch', '')"
                        class="flex items-center gap-1 text-xs font-semibold text-purple-600 hover:text-purple-800">
                        <x-icon name="o-plus" class="h-3.5 w-3.5" /> Add
                    </button>
                @endif
            </div>
            @forelse($research['supervisors'] as $spv)
                <div class="flex items-center justify-between rounded-lg bg-indigo-50 px-3 py-2.5 mb-2">
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $spv['name'] ?: '—' }}</p>
                        <p class="text-[10px] text-indigo-600 font-semibold">{{ $spv['role'] }}
                            @if($spv['code']) · {{ $spv['code'] }} @endif
                        </p>
                    </div>
                    @if($research['can_manage_supervisors'])
                        <button wire:click="removeSupervisor({{ $spv['id'] }})"
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-red-50 hover:bg-red-100">
                            <x-icon name="o-x-mark" class="h-4 w-4 text-red-500" />
                        </button>
                    @endif
                </div>
            @empty
                <p class="text-xs text-gray-400 text-center py-2">No supervisors assigned.</p>
            @endforelse
        </div>

        {{-- ─── Reviewers (only in Proposal phase) ─── --}}
        @if($research['can_manage_reviewers'] || count($research['reviewers']) > 0)
        <div class="rounded-xl bg-white shadow-sm p-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-bold text-orange-600 uppercase tracking-wide">Reviewers</p>
                @if($research['can_manage_reviewers'])
                    <button wire:click="$set('addRevSheet', true); $set('addSpvSheet', false); $set('staffSearch', '')"
                        class="flex items-center gap-1 text-xs font-semibold text-orange-600 hover:text-orange-800">
                        <x-icon name="o-plus" class="h-3.5 w-3.5" /> Add
                    </button>
                @endif
            </div>
            @forelse($research['reviewers'] as $rev)
                <div class="flex items-center justify-between rounded-lg bg-orange-50 px-3 py-2.5 mb-2">
                    <div>
                        <p class="text-sm font-medium text-gray-800">{{ $rev['name'] ?: '—' }}</p>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            @if($rev['code'])
                                <span class="text-[10px] text-gray-400">{{ $rev['code'] }}</span>
                            @endif
                            @if($rev['decision'])
                                <span class="text-[10px] font-semibold text-green-600">· {{ $rev['decision'] }}</span>
                            @else
                                <span class="text-[10px] text-gray-400 italic">· Pending</span>
                            @endif
                        </div>
                    </div>
                    @if($research['can_manage_reviewers'])
                        <button wire:click="removeReviewer({{ $rev['id'] }})"
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-red-50 hover:bg-red-100">
                            <x-icon name="o-x-mark" class="h-4 w-4 text-red-500" />
                        </button>
                    @endif
                </div>
            @empty
                <p class="text-xs text-gray-400 text-center py-2">No reviewers assigned.</p>
            @endforelse
        </div>
        @endif

        {{-- ─── Remarks ─── --}}
        <button wire:click="$set('remarkSheet', true)"
                class="w-full rounded-xl bg-white shadow-sm p-4 flex items-center gap-3 hover:bg-purple-50 transition-colors text-left">
            <x-icon name="o-chat-bubble-left-right" class="h-5 w-5 text-purple-400 shrink-0" />
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-700">Remarks</p>
                <p class="text-[11px] text-gray-400">
                    {{ count($research['remarks']) > 0 ? count($research['remarks']) . ' message(s)' : 'No remarks yet' }}
                </p>
            </div>
            <x-icon name="o-chevron-right" class="h-4 w-4 text-gray-300 shrink-0" />
        </button>

        {{-- ─── History ─── --}}
        @if(count($research['history']) > 0)
        <div class="rounded-xl bg-white shadow-sm p-4">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3">History</p>
            @foreach($research['history']->take(10) as $h)
                @php $isActive = $h['status'] == 1; @endphp
                <div class="mb-1.5 flex items-start gap-2.5 rounded-lg {{ $isActive ? 'bg-purple-50 border border-purple-100' : 'bg-gray-50' }} px-3 py-2">
                    <x-icon name="{{ $isActive ? 'o-circle-stack' : 'o-clock' }}"
                            class="mt-0.5 h-3.5 w-3.5 shrink-0 {{ $isActive ? 'text-purple-500' : 'text-gray-300' }}" />
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold {{ $isActive ? 'text-purple-700' : 'text-gray-600' }}">{{ $h['type_desc'] }}</p>
                        @if($h['message'])
                            <p class="text-[10px] text-gray-400 truncate">{{ $h['message'] }}</p>
                        @endif
                        <p class="text-[10px] text-gray-400">{{ $h['created_at'] }}</p>
                    </div>
                    @if($isActive)
                        <span class="shrink-0 rounded-full bg-purple-100 px-1.5 py-0.5 text-[9px] font-bold text-purple-600">NOW</span>
                    @endif
                </div>
            @endforeach
        </div>
        @endif

    </div>

    {{-- ─── Sticky Action Buttons ─── --}}
    @if($research['can_start_review'] || $research['can_assign_pre_defense'] || $research['can_reject'])
        <div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-sm z-30 px-4 pb-20 pt-3 bg-gradient-to-t from-gray-100 via-gray-100/95 to-transparent space-y-2">

            @if($research['can_assign_pre_defense'])
                <button wire:click="assignToPreDefense" wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl bg-green-600 text-white font-bold text-sm shadow-lg hover:bg-green-700 active:scale-95 disabled:opacity-60 transition-all">
                    <span wire:loading.remove wire:target="assignToPreDefense">
                        <x-icon name="o-arrow-right-circle" class="h-5 w-5 inline -mt-0.5 mr-1" />
                        Assign to Pre-Defense
                    </span>
                    <span wire:loading wire:target="assignToPreDefense" class="loading loading-spinner loading-sm"></span>
                </button>
            @endif

            @if($research['can_start_review'])
                <button wire:click="startReview" wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl bg-orange-500 text-white font-bold text-sm shadow-lg hover:bg-orange-600 active:scale-95 disabled:opacity-60 transition-all">
                    <span wire:loading.remove wire:target="startReview">
                        <x-icon name="o-eye" class="h-5 w-5 inline -mt-0.5 mr-1" />
                        Start Review
                    </span>
                    <span wire:loading wire:target="startReview" class="loading loading-spinner loading-sm"></span>
                </button>
            @endif

            @if($research['can_reject'])
                <button @click="confirmReject = true"
                    class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl bg-red-500 text-white font-bold text-sm shadow-lg hover:bg-red-600 active:scale-95 transition-all">
                    <x-icon name="o-x-circle" class="h-5 w-5 inline -mt-0.5 mr-1" />
                    Reject Proposal
                </button>
            @endif
        </div>
    @endif

    {{-- ─── Reject Confirm Modal ─── --}}
    <div x-show="confirmReject" x-cloak
         class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 px-4 pb-6"
         @click.self="confirmReject = false">
        <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
            <p class="text-base font-bold text-gray-800 mb-1">Reject Proposal?</p>
            <p class="text-sm text-gray-500 mb-5">Research will be moved to Rejected state. The student can request renewal.</p>
            <div class="flex gap-3">
                <button @click="confirmReject = false"
                        class="flex-1 rounded-full border border-gray-200 py-2.5 text-sm font-semibold text-gray-600">
                    Cancel
                </button>
                <button wire:click="rejectResearch" @click="confirmReject = false"
                        class="flex-1 rounded-full bg-red-500 py-2.5 text-sm font-semibold text-white">
                    Reject
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ─── Add Supervisor Bottom Sheet ─── --}}
    <div x-data x-show="$wire.addSpvSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('addSpvSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.addSpvSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl">
        <div class="flex justify-center pt-3 pb-1"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-indigo-700">Add Supervisor</h3>
            <button wire:click="$set('addSpvSheet', false)" class="flex items-center justify-center h-7 w-7 rounded-full bg-gray-100">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="px-4 py-3 space-y-3">
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Order</label>
                <select wire:model="spvOrder" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-purple-400">
                    <option value="1">1 – Main Supervisor</option>
                    <option value="2">2 – Co-Supervisor</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Search Staff (min. 2 chars)</label>
                <input wire:model.live.debounce.300ms="staffSearch" type="text" placeholder="Name or code..."
                    class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-400" />
            </div>
            <div class="space-y-1 max-h-48 overflow-y-auto pb-2">
                @foreach($staffResults as $staff)
                    <button wire:click="addSupervisor({{ $staff['id'] }})"
                        class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 bg-gray-50 hover:bg-indigo-50 text-left">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $staff['name'] }}</p>
                            @if($staff['code'])
                                <p class="text-[10px] text-gray-400">{{ $staff['code'] }}</p>
                            @endif
                        </div>
                        <x-icon name="o-plus" class="h-4 w-4 text-indigo-500 shrink-0" />
                    </button>
                @endforeach
                @if(strlen(trim($staffSearch)) >= 2 && $staffResults->isEmpty())
                    <p class="text-xs text-center text-gray-400 py-3">No staff found.</p>
                @endif
            </div>
        </div>
        <div class="pb-24 px-4"></div>
    </div>

    {{-- ─── Add Reviewer Bottom Sheet ─── --}}
    <div x-data x-show="$wire.addRevSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('addRevSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.addRevSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl">
        <div class="flex justify-center pt-3 pb-1"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-orange-600">Add Reviewer</h3>
            <button wire:click="$set('addRevSheet', false)" class="flex items-center justify-center h-7 w-7 rounded-full bg-gray-100">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="px-4 py-3 space-y-3">
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Search Staff (min. 2 chars)</label>
                <input wire:model.live.debounce.300ms="staffSearch" type="text" placeholder="Name or code..."
                    class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-400" />
            </div>
            <div class="space-y-1 max-h-48 overflow-y-auto pb-2">
                @foreach($staffResults as $staff)
                    <button wire:click="addReviewer({{ $staff['id'] }})"
                        class="w-full flex items-center justify-between rounded-lg px-3 py-2.5 bg-gray-50 hover:bg-orange-50 text-left">
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $staff['name'] }}</p>
                            @if($staff['code'])
                                <p class="text-[10px] text-gray-400">{{ $staff['code'] }}</p>
                            @endif
                        </div>
                        <x-icon name="o-plus" class="h-4 w-4 text-orange-500 shrink-0" />
                    </button>
                @endforeach
                @if(strlen(trim($staffSearch)) >= 2 && $staffResults->isEmpty())
                    <p class="text-xs text-center text-gray-400 py-3">No staff found.</p>
                @endif
            </div>
        </div>
        <div class="pb-24 px-4"></div>
    </div>

    {{-- ─── Remarks Bottom Sheet ─── --}}
    <div x-data x-show="$wire.remarkSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('remarkSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.remarkSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl flex flex-col"
         style="max-height: 80vh">

        <div class="flex justify-center pt-3 pb-1 shrink-0">
            <div class="h-1 w-10 rounded-full bg-gray-300"></div>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-2">
                <x-icon name="o-chat-bubble-left-right" class="h-5 w-5 text-purple-500" />
                <h3 class="text-base font-bold text-purple-700">Remarks</h3>
            </div>
            <button wire:click="$set('remarkSheet', false)" class="flex items-center justify-center h-7 w-7 rounded-full bg-gray-100">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>

        {{-- Messages list --}}
        <div class="flex-1 overflow-y-auto px-4 py-3 space-y-3">
            @if($research && count($research['remarks']) > 0)
                @foreach($research['remarks'] as $remark)
                    @php $isMine = $remark['is_mine']; @endphp
                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[80%] {{ $isMine ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-800' }} rounded-2xl {{ $isMine ? 'rounded-tr-sm' : 'rounded-tl-sm' }} px-3 py-2">
                            @if(!$isMine)
                                <p class="text-[10px] font-bold text-purple-600 mb-0.5">{{ $remark['author'] }}</p>
                            @endif
                            <p class="text-sm leading-snug">{{ $remark['message'] }}</p>
                            <div class="flex items-center {{ $isMine ? 'justify-end' : 'justify-between' }} gap-2 mt-1">
                                <p class="text-[9px] {{ $isMine ? 'text-white/60' : 'text-gray-400' }}">{{ $remark['created_at'] }}</p>
                                @if($isMine)
                                    <button wire:click="deleteRemark({{ $remark['id'] }})" wire:confirm="Delete this remark?"
                                            class="text-[9px] text-white/60 hover:text-white/90">✕</button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="py-8 text-center">
                    <x-icon name="o-chat-bubble-left-right" class="mx-auto h-10 w-10 text-gray-200" />
                    <p class="mt-2 text-sm text-gray-400">No remarks yet.</p>
                </div>
            @endif
        </div>

        {{-- Input area --}}
        <div class="shrink-0 border-t border-gray-100 px-4 pt-3 pb-8">
            <div class="flex items-end gap-2">
                <textarea wire:model="newRemark" rows="2"
                          placeholder="Write a remark..."
                          class="flex-1 rounded-2xl border border-gray-200 px-3 py-2.5 text-sm resize-none focus:outline-none focus:border-purple-400"></textarea>
                <button wire:click="addRemark" wire:loading.attr="disabled"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-600 text-white hover:bg-purple-700 disabled:opacity-60 transition-colors">
                    <span wire:loading.remove wire:target="addRemark">
                        <x-icon name="o-paper-airplane" class="h-4 w-4" />
                    </span>
                    <span wire:loading wire:target="addRemark" class="loading loading-spinner loading-xs"></span>
                </button>
            </div>
            @error('newRemark') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
