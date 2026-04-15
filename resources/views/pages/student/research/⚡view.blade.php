<?php

use App\Models\ArSys\DefenseApproval;
use App\Models\ArSys\DefenseModel;
use App\Models\ArSys\DefenseRole;
use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchLog;
use App\Models\ArSys\ResearchLogType;
use App\Models\ArSys\ResearchMilestone;
use App\Models\ArSys\ResearchMilestoneLog;
use App\Models\ArSys\Staff;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Research Detail')] class extends Component
{
    use Toast;

    public int    $researchId = 0;
    public string $editTitle    = '';
    public string $editAbstract = '';
    public string $editFileUrl  = '';

    public function mount(int $id): void
    {
        $this->researchId = $id;

        $research = Research::where('id', $id)
            ->where('student_id', auth()->user()->student?->id ?? 0)
            ->first();

        if ($research) {
            $this->editTitle    = $research->title    ?? '';
            $this->editAbstract = $research->abstract ?? '';
            $this->editFileUrl  = $research->file     ?? '';
        }
    }

    public function saveEdit(): void
    {
        $this->validate([
            'editTitle'    => 'required|string|max:500',
            'editAbstract' => 'nullable|string',
            'editFileUrl'  => 'nullable|url',
        ]);

        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        DB::transaction(function () use ($research) {
            $research->update([
                'title'    => $this->editTitle,
                'abstract' => $this->editAbstract,
                'file'     => $this->editFileUrl ?: null,
            ]);

            $updType = ResearchLogType::where('code', 'UPD')->first();
            if ($updType) {
                ResearchLog::create([
                    'research_id' => $research->id,
                    'type_id'     => $updType->id,
                    'loger_id'    => auth()->id(),
                    'message'     => $updType->description,
                    'status'      => null,
                ]);
            }
        });

        $this->success('Research updated.', position: 'toast-bottom');
        $this->dispatch('close-edit');
    }

    private function getResearch(): ?Research
    {
        return Research::where('id', $this->researchId)
            ->where('student_id', auth()->user()->student?->id ?? 0)
            ->with([
                'type.base.model',
                'milestone',
                'supervisor.staff',
                'reviewers.staff',
                'remark',
                'history' => fn($q) => $q->orderBy('id', 'desc')->with('type'),
                'defenseApproval.staff',
                'defenseApproval.defenseModel',
            ])
            ->first();
    }

    /** Create defense approval requests for supervisors (and optionally kaprodi). */
    private function createDefenseApprovalRequests(Research $research, string $modelCode, bool $includeKaprodi = false): void
    {
        $defenseModel = DefenseModel::where('code', $modelCode)->first();
        $spvRoleId    = DefenseRole::where('code', 'SPV')->value('id');
        $prgRoleId    = DefenseRole::where('code', 'PRG')->value('id');

        if (!$defenseModel || !$spvRoleId) return;

        // Supervisor approvals
        foreach ($research->supervisor as $spv) {
            if (!$spv->supervisor_id) continue;
            DefenseApproval::firstOrCreate([
                'research_id'     => $research->id,
                'defense_model_id'=> $defenseModel->id,
                'approver_id'     => $spv->supervisor_id,
                'approver_role'   => $spvRoleId,
            ]);
        }

        // Kaprodi approval (role=program, same program_id as student)
        if ($includeKaprodi && $prgRoleId) {
            $student    = $research->student ?? auth()->user()->student;
            $programId  = $student?->program_id;
            if ($programId) {
                $kaprodiStaff = Staff::where('program_id', $programId)
                    ->whereHas('user', fn($q) => $q->role('program'))
                    ->first();
                if ($kaprodiStaff) {
                    DefenseApproval::firstOrCreate([
                        'research_id'     => $research->id,
                        'defense_model_id'=> $defenseModel->id,
                        'approver_id'     => $kaprodiStaff->id,
                        'approver_role'   => $prgRoleId,
                    ]);
                }
            }
        }
    }

    private function advanceMilestone(Research $research, string $code, string $phase): void
    {
        $milestone = ResearchMilestone::where('code', $code)->where('phase', $phase)->first();
        if ($milestone && $research->milestone_id !== $milestone->id) {
            $research->update(['milestone_id' => $milestone->id]);
            ResearchMilestoneLog::create(['research_id' => $research->id, 'milestone_id' => $milestone->id]);
        }
    }

    private function addLog(Research $research, string $typeCode): void
    {
        $type = ResearchLogType::where('code', $typeCode)->first();
        if (!$type) return;
        $activeLog = ResearchLog::where('research_id', $research->id)->where('status', 1)->first();
        if ($activeLog) $activeLog->update(['status' => null]);
        ResearchLog::create([
            'research_id' => $research->id,
            'type_id'     => $type->id,
            'loger_id'    => auth()->id(),
            'message'     => $type->description,
            'status'      => 1,
        ]);
    }

    public function proposePreDefense(): void
    {
        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        if ($research->predefenseApproval()->exists()) {
            $this->error('Pre-defense approval request already submitted.', position: 'toast-bottom'); return;
        }

        DB::transaction(function () use ($research) {
            $this->createDefenseApprovalRequests($research, 'PRE', false);
            $this->advanceMilestone($research, 'Pre-defense', 'Submitted');
            $this->addLog($research, 'DEFAPPREQ');
        });

        $this->success('Pre-defense approval request submitted.', position: 'toast-bottom');
    }

    public function proposeFinalDefense(): void
    {
        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        if ($research->finaldefenseApproval()->exists()) {
            $this->error('Final defense approval request already submitted.', position: 'toast-bottom'); return;
        }

        DB::transaction(function () use ($research) {
            $this->createDefenseApprovalRequests($research, 'PUB', true);
            $this->advanceMilestone($research, 'Final-defense', 'Submitted');
            $this->addLog($research, 'PUBAPPREQ');
        });

        $this->success('Final defense approval request submitted.', position: 'toast-bottom');
    }

    public function proposeSeminar(): void
    {
        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        if ($research->seminarApproval()->exists()) {
            $this->error('Seminar approval request already submitted.', position: 'toast-bottom'); return;
        }

        DB::transaction(function () use ($research) {
            $this->createDefenseApprovalRequests($research, 'SEM', false);
            $this->advanceMilestone($research, 'Seminar', 'Submitted');
            $this->addLog($research, 'SEMAPPREQ');
        });

        $this->success('Seminar approval request submitted.', position: 'toast-bottom');
    }

    public function submitProposal(): void
    {
        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        $creLog = ResearchLog::where('research_id', $research->id)
            ->where('status', 1)
            ->whereHas('type', fn($q) => $q->where('code', 'CRE'))
            ->first();

        if (!$creLog) { $this->error('Research is not in proposal state.', position: 'toast-bottom'); return; }

        $hasActiveOrReview = Research::where('student_id', auth()->user()->student?->id ?? 0)
            ->where('id', '!=', $research->id)
            ->whereHas('history', fn($q) => $q->where('status', 1)
                ->whereHas('type', fn($t) => $t->whereIn('code', ['SUB', 'REV', 'ACT'])))
            ->exists();

        if ($hasActiveOrReview) {
            $this->error('Cannot submit while another research is under review or active.', position: 'toast-bottom');
            return;
        }

        DB::transaction(function () use ($research, $creLog) {
            $creLog->update(['status' => null]);

            $nextMilestone = ResearchMilestone::where('research_model_id', $research->type?->base?->model?->id)
                ->where('sequence', ($research->milestone?->sequence ?? 0) + 1)
                ->first();

            if ($nextMilestone) {
                $research->update(['milestone_id' => $nextMilestone->id]);
                ResearchMilestoneLog::create(['research_id' => $research->id, 'milestone_id' => $nextMilestone->id]);
            }

            $subType = ResearchLogType::where('code', 'SUB')->first();
            if ($subType) {
                ResearchLog::create([
                    'research_id' => $research->id,
                    'type_id'     => $subType->id,
                    'loger_id'    => auth()->id(),
                    'message'     => $subType->description,
                    'status'      => 1,
                ]);
            }
        });

        $this->success('Proposal submitted for review.', position: 'toast-bottom');
    }

    public function deleteResearch(): void
    {
        $research = $this->getResearch();
        if (!$research) { $this->error('Research not found.', position: 'toast-bottom'); return; }

        $canDelete = ResearchLog::where('research_id', $research->id)
            ->where('status', 1)
            ->whereHas('type', fn($q) => $q->where('code', 'CRE'))
            ->exists();

        if (!$canDelete) { $this->error('Research can only be deleted in proposal/write state.', position: 'toast-bottom'); return; }

        $research->delete();
        $this->success('Research deleted.', position: 'toast-bottom');
        $this->redirect(route('student.research'), navigate: true);
    }

    public function renewResearch(): void
    {
        $research = $this->getResearch();
        if (!$research) return;

        $freezeLog = ResearchLog::where('research_id', $research->id)
            ->where('status', 1)
            ->whereHas('type', fn($q) => $q->where('code', 'FRE'))
            ->first();

        if (!$freezeLog) { $this->error('Research is not frozen.', position: 'toast-bottom'); return; }

        DB::transaction(function () use ($research, $freezeLog) {
            $freezeLog->update(['status' => null]);
            $renType = ResearchLogType::where('code', 'REN')->first();
            if ($renType) {
                ResearchLog::create([
                    'research_id' => $research->id,
                    'type_id'     => $renType->id,
                    'loger_id'    => auth()->id(),
                    'message'     => $renType->description ?? 'Renewal requested',
                    'status'      => 1,
                ]);
            }
        });

        $this->success('Renewal requested.', position: 'toast-bottom');
    }

    public function with(): array
    {
        $research = $this->getResearch();
        if (!$research) return ['research' => null, 'actions' => [], 'logCode' => null];

        $activeLog = $research->history->where('status', 1)->first();
        $logCode   = $activeLog?->type?->code;
        $actions   = $this->resolveActions($research, $logCode);

        return compact('research', 'actions', 'logCode');
    }

    private function resolveActions(Research $research, ?string $logCode): array
    {
        $phase = $research->milestone?->phase;
        $code  = $research->milestone?->code;
        $inProgress = $phase === 'In Progress' || $phase === 'In progress';

        return match($logCode) {
            'CRE'  => ['edit', 'delete', 'submit'],
            'ACT'  => match(true) {
                $inProgress && $code === 'Pre-defense'  => ['edit', 'propose_predefense'],
                $inProgress && $code === 'Final-defense' => ['edit', 'propose_finaldefense'],
                $inProgress && $code === 'Seminar'       => ['edit', 'propose_seminar'],
                default => ['edit'],
            },
            'FRE'  => ['edit'], // Renew is shown inside the warning banner
            null   => ($phase === 'Created' || $phase === 'Rejected') ? ['edit', 'delete', 'submit'] : ['edit'],
            default => ['edit'],
        };
    }
};
?>

<div class="px-4 py-4 pb-24 space-y-4">

    @if (!$research)

    {{-- ─── Not Found ─── --}}
    <div class="mt-12 flex flex-col items-center gap-3 text-center">
        <x-icon name="o-document-magnifying-glass" class="h-12 w-12 text-gray-200" />
        <p class="text-sm font-semibold text-gray-400">Research not found</p>
        <p class="text-xs text-gray-300">This research may not exist or doesn't belong to your account.</p>
        <a href="{{ route('student.research') }}"
           class="mt-2 rounded-full bg-purple-600 px-6 py-2 text-xs font-semibold text-white">
            Back to Research
        </a>
    </div>

    @else

    @php
        $logCode  = $logCode ?? null;
        $isFrozen = $logCode === 'FRE';
    @endphp

    {{-- ─── Warning Banner ─── --}}
    @if($isFrozen)
        <div class="flex items-center gap-3 rounded-2xl border border-cyan-200 bg-cyan-50 p-4">
            <x-icon name="o-pause-circle" class="h-5 w-5 shrink-0 text-cyan-500" />
            <p class="flex-1 text-xs font-medium text-cyan-800 leading-snug">
                This research has been frozen due to inactivity.
            </p>
            <button wire:click="renewResearch"
                    wire:loading.attr="disabled"
                    class="shrink-0 rounded-full bg-cyan-500 px-3 py-1.5 text-[10px] font-bold text-white hover:bg-cyan-600 transition-colors">
                Renew
            </button>
        </div>
    @elseif($logCode === 'REN')
        <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
            <x-icon name="o-arrow-path" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
            <p class="text-xs font-medium text-amber-800 leading-snug">Research renewal is being processed.</p>
        </div>
    @elseif($logCode === 'RJC')
        <div class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 p-4">
            <x-icon name="o-x-circle" class="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
            <p class="text-xs font-medium text-red-800 leading-snug">This research has been rejected.</p>
        </div>
    @elseif($logCode === 'SIASPRO')
        <div class="flex items-start gap-3 rounded-2xl border border-orange-200 bg-orange-50 p-4">
            <x-icon name="o-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-orange-500" />
            <p class="text-xs font-medium text-orange-800 leading-snug">SIAS proposal warning.</p>
        </div>
    @endif

    {{-- ─── Research Info Card ─── --}}
    <div class="rounded-2xl bg-white shadow-sm overflow-hidden">
        <div class="bg-gradient-to-r from-purple-600 to-purple-400 px-4 py-3">
            <p class="text-[10px] font-semibold text-white/70 tracking-wider">{{ $research->code }}</p>
            <p class="mt-0.5 text-sm font-bold text-white leading-snug">{{ Str::upper($research->title) }}</p>
        </div>
        <div class="p-4 space-y-3">
            <p class="text-xs text-gray-500">{{ $research->type?->base?->code }} — {{ $research->type?->base?->description }}</p>
            @if($research->milestone)
                <x-milestone-chip :code="$research->milestone->code" :phase="$research->milestone->phase" />
            @endif
            @if($research->abstract)
                <div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Abstract</p>
                    <p class="text-xs text-gray-600 leading-relaxed line-clamp-5">{{ $research->abstract }}</p>
                </div>
            @endif
            @if($research->file)
                <a href="{{ $research->file }}" target="_blank"
                   class="flex items-center gap-1.5 text-xs text-blue-500 hover:underline">
                    <x-icon name="o-link" class="h-3.5 w-3.5 shrink-0" />
                    <span class="truncate">{{ $research->file }}</span>
                    <x-icon name="o-arrow-top-right-on-square" class="h-3 w-3 shrink-0" />
                </a>
            @endif
        </div>
    </div>

    {{-- ─── Supervisors ─── --}}
    <div>
        <p class="mb-2 text-sm font-bold text-gray-700">Supervisors</p>
        @forelse($research->supervisor as $s)
            <div class="mb-2 flex items-center gap-3 rounded-2xl bg-white p-3 shadow-sm">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-purple-100 text-sm font-bold text-purple-700">
                    {{ strtoupper(substr($s->staff?->first_name ?? 'S', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-700 truncate">
                        {{ trim(($s->staff?->first_name ?? '') . ' ' . ($s->staff?->last_name ?? '')) ?: 'N/A' }}
                    </p>
                    <p class="text-[11px] text-gray-400">{{ $s->staff?->code ?? '—' }}</p>
                </div>
            </div>
        @empty
            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-400">No supervisors assigned</p>
            </div>
        @endforelse
    </div>

    {{-- ─── Reviewers ─── --}}
    @if($research->reviewers->isNotEmpty())
    <div>
        <p class="mb-2 text-sm font-bold text-gray-700">Reviewers</p>
        @foreach($research->reviewers as $r)
            <div class="mb-2 flex items-center gap-3 rounded-2xl bg-white p-3 shadow-sm">
                <x-icon name="{{ $r->decision !== null ? 'o-check-circle' : 'o-clock' }}"
                        class="h-5 w-5 shrink-0 {{ $r->decision !== null ? 'text-green-500' : 'text-orange-400' }}" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-700 truncate">
                        {{ trim(($r->staff?->first_name ?? '') . ' ' . ($r->staff?->last_name ?? '')) ?: 'N/A' }}
                    </p>
                    <p class="text-[11px] text-gray-400">{{ $r->staff?->code ?? '—' }}</p>
                </div>
            </div>
        @endforeach
    </div>
    @endif

    {{-- ─── Defense Approvals ─── --}}
    @if($research->defenseApproval->isNotEmpty())
    <div>
        <p class="mb-2 text-sm font-bold text-gray-700">Approvals</p>
        @foreach($research->defenseApproval as $a)
            <div class="mb-2 flex items-center gap-3 rounded-2xl bg-white p-3 shadow-sm">
                <x-icon name="{{ $a->decision !== null ? 'o-check-circle' : 'o-hourglass' }}"
                        class="h-5 w-5 shrink-0 {{ $a->decision !== null ? 'text-green-500' : 'text-orange-400' }}" />
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-700 truncate">
                        {{ trim(($a->staff?->first_name ?? '') . ' ' . ($a->staff?->last_name ?? '')) ?: 'N/A' }}
                    </p>
                    <p class="text-[11px] text-gray-400">
                        {{ $a->defenseModel?->description }} — {{ $a->role }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
    @endif

    {{-- ─── Action Buttons ─── --}}
    @if(!empty($actions))
    <div>
        <p class="mb-2 text-sm font-bold text-gray-700">Actions</p>
        <div class="rounded-2xl bg-white p-4 shadow-sm"
             x-data="{ showDelete: false, showSubmit: false, showPropose: false, showEdit: false }"
             @close-edit.window="showEdit = false">
            <div class="flex flex-wrap gap-2">

                @if(in_array('edit', $actions))
                    <button type="button" @click="showEdit = true"
                            class="flex items-center gap-1.5 rounded-full border border-purple-300 bg-white px-4 py-2 text-xs font-semibold text-purple-600 hover:bg-purple-50 transition-colors">
                        <x-icon name="o-pencil" class="h-3.5 w-3.5" /> Edit
                    </button>
                @endif

                @if(in_array('delete', $actions))
                    <button type="button" @click="showDelete = true"
                            class="flex items-center gap-1.5 rounded-full bg-red-500 px-4 py-2 text-xs font-semibold text-white hover:bg-red-600 transition-colors">
                        <x-icon name="o-trash" class="h-3.5 w-3.5" /> Delete
                    </button>
                @endif

                @if(in_array('submit', $actions))
                    <button type="button" @click="showSubmit = true"
                            class="flex items-center gap-1.5 rounded-full bg-green-500 px-4 py-2 text-xs font-semibold text-white hover:bg-green-600 transition-colors">
                        <x-icon name="o-paper-airplane" class="h-3.5 w-3.5" /> Submit Proposal
                    </button>
                @endif

                @if(in_array('propose_predefense', $actions))
                    <button type="button" @click="showPropose = true"
                            class="flex items-center gap-1.5 rounded-full bg-orange-500 px-4 py-2 text-xs font-semibold text-white hover:bg-orange-600 transition-colors">
                        <x-icon name="o-scale" class="h-3.5 w-3.5" /> Propose Pre-Defense
                    </button>
                @endif

                @if(in_array('propose_finaldefense', $actions))
                    <button type="button" @click="showPropose = true"
                            class="flex items-center gap-1.5 rounded-full bg-purple-600 px-4 py-2 text-xs font-semibold text-white hover:bg-purple-700 transition-colors">
                        <x-icon name="o-trophy" class="h-3.5 w-3.5" /> Propose Final Defense
                    </button>
                @endif

                @if(in_array('propose_seminar', $actions))
                    <button type="button" @click="showPropose = true"
                            class="flex items-center gap-1.5 rounded-full bg-teal-500 px-4 py-2 text-xs font-semibold text-white hover:bg-teal-600 transition-colors">
                        <x-icon name="o-megaphone" class="h-3.5 w-3.5" /> Propose Seminar
                    </button>
                @endif

            </div>

            {{-- Delete Confirm Modal --}}
            <div x-show="showDelete" x-cloak
                 class="fixed inset-0 z-[100] flex items-end justify-center bg-black/50 px-4 pb-8">
                <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl" @click.stop>
                    <p class="text-base font-bold text-gray-800 mb-1">Delete Research?</p>
                    <p class="text-sm text-gray-500 mb-5">This action cannot be undone.</p>
                    <div class="flex gap-3">
                        <button type="button" @click="showDelete = false"
                                class="flex-1 rounded-full border border-gray-200 py-2.5 text-sm font-semibold text-gray-600">
                            Cancel
                        </button>
                        <button type="button" wire:click="deleteResearch" @click="showDelete = false"
                                class="flex-1 rounded-full bg-red-500 py-2.5 text-sm font-semibold text-white">
                            Delete
                        </button>
                    </div>
                </div>
            </div>

            {{-- Submit Confirm Modal --}}
            <div x-show="showSubmit" x-cloak
                 class="fixed inset-0 z-[100] flex items-end justify-center bg-black/50 px-4 pb-8">
                <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl" @click.stop>
                    <p class="text-base font-bold text-gray-800 mb-1">Submit Proposal?</p>
                    <p class="text-sm text-gray-500 mb-5">Pastikan semua informasi sudah benar sebelum submit.</p>
                    <div class="flex gap-3">
                        <button type="button" @click="showSubmit = false"
                                class="flex-1 rounded-full border border-gray-200 py-2.5 text-sm font-semibold text-gray-600">
                            Cancel
                        </button>
                        <button type="button" wire:click="submitProposal" @click="showSubmit = false"
                                class="flex-1 rounded-full bg-green-500 py-2.5 text-sm font-semibold text-white">
                            Submit
                        </button>
                    </div>
                </div>
            </div>

            {{-- Edit Bottom Sheet --}}
            <div x-show="showEdit" x-cloak
                 class="absolute inset-0 z-40 flex flex-col justify-end bg-black/50"
                 @click.self="showEdit = false">
                <div class="w-full mb-16 rounded-t-3xl bg-white shadow-2xl max-h-[85vh] flex flex-col" @click.stop
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="transform translate-y-full"
                     x-transition:enter-end="transform translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="transform translate-y-0"
                     x-transition:leave-end="transform translate-y-full">

                    {{-- Handle bar --}}
                    <div class="flex justify-center pt-3 pb-1 shrink-0">
                        <div class="h-1 w-10 rounded-full bg-gray-200"></div>
                    </div>

                    {{-- Header --}}
                    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 shrink-0">
                        <p class="text-base font-bold text-gray-800">Edit Research</p>
                        <button type="button" @click="showEdit = false"
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200">
                            <x-icon name="o-x-mark" class="h-4 w-4" />
                        </button>
                    </div>

                    {{-- Form (scrollable) --}}
                    <div class="overflow-y-auto flex-1 px-5 py-4 space-y-4">

                        {{-- Title --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Title <span class="text-red-400">*</span>
                            </label>
                            <textarea wire:model="editTitle" rows="3"
                                      placeholder="Research title..."
                                      class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-300 resize-none"></textarea>
                            @error('editTitle')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Abstract --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wide">Abstract</label>
                            <textarea wire:model="editAbstract" rows="5"
                                      placeholder="Research abstract..."
                                      class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-300 resize-none"></textarea>
                            @error('editAbstract')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Document URL --}}
                        <div>
                            <label class="mb-1.5 block text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                Document URL <span class="text-gray-300 normal-case font-normal">(optional)</span>
                            </label>
                            <input wire:model="editFileUrl" type="url"
                                   placeholder="https://drive.google.com/..."
                                   class="w-full rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-800 focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-300" />
                            <p class="mt-1 text-[10px] text-gray-400">Google Drive, OneDrive, etc. — pastikan bisa diakses publik</p>
                            @error('editFileUrl')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Save button --}}
                        <div class="pb-6">
                            <button type="button" wire:click="saveEdit" wire:loading.attr="disabled" wire:target="saveEdit"
                                    class="w-full rounded-full bg-purple-600 py-3.5 text-sm font-bold text-white shadow-sm hover:bg-purple-700 transition-colors disabled:opacity-60">
                                <span wire:loading.remove wire:target="saveEdit">Save Changes</span>
                                <span wire:loading wire:target="saveEdit">Saving...</span>
                            </button>
                        </div>

                    </div>
                </div>
            </div>

            {{-- Propose Defense Confirm Modal --}}
            <div x-show="showPropose" x-cloak
                 class="fixed inset-0 z-[100] flex items-end justify-center bg-black/50 px-4 pb-8">
                <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl" @click.stop>
                    <p class="text-base font-bold text-gray-800 mb-1">Submit Defense Request?</p>
                    <p class="text-sm text-gray-500 mb-5">Approval request akan dikirim ke pembimbing. Lanjutkan?</p>
                    <div class="flex gap-3">
                        <button type="button" @click="showPropose = false"
                                class="flex-1 rounded-full border border-gray-200 py-2.5 text-sm font-semibold text-gray-600">
                            Cancel
                        </button>
                        @if(in_array('propose_predefense', $actions))
                            <button type="button" wire:click="proposePreDefense" @click="showPropose = false"
                                    class="flex-1 rounded-full bg-orange-500 py-2.5 text-sm font-semibold text-white">
                                Confirm
                            </button>
                        @elseif(in_array('propose_finaldefense', $actions))
                            <button type="button" wire:click="proposeFinalDefense" @click="showPropose = false"
                                    class="flex-1 rounded-full bg-purple-600 py-2.5 text-sm font-semibold text-white">
                                Confirm
                            </button>
                        @elseif(in_array('propose_seminar', $actions))
                            <button type="button" wire:click="proposeSeminar" @click="showPropose = false"
                                    class="flex-1 rounded-full bg-teal-500 py-2.5 text-sm font-semibold text-white">
                                Confirm
                            </button>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>
    @endif

    {{-- ─── Remarks (nav card — adopting Flutter's _RemarksNavCard) ─── --}}
    <div>
        <p class="mb-2 text-sm font-bold text-gray-700">Remarks</p>
        <a href="{{ url('/student/research/' . $research->id . '/remark') }}"
           class="flex items-center gap-3 rounded-2xl bg-white p-4 shadow-sm hover:bg-gray-50 transition-colors">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-100">
                <x-icon name="o-chat-bubble-left-right" class="h-5 w-5 text-purple-500" />
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-700">Remarks</p>
                <p class="text-[11px] text-gray-400">
                    @php $remarkCount = $research->remark->count(); @endphp
                    {{ $remarkCount > 0 ? $remarkCount . ' message' . ($remarkCount > 1 ? 's' : '') : 'No remarks yet' }}
                </p>
            </div>
            <x-icon name="o-chevron-right" class="h-4 w-4 text-gray-300" />
        </a>
    </div>

    {{-- ─── History ─── --}}
    <div>
        <p class="mb-2 text-sm font-bold text-gray-700">History</p>
        @forelse($research->history->filter(fn($h) => $h->type?->code !== 'UPD')->take(10) as $h)
            @php $isActive = $h->status == 1; @endphp
            <div class="mb-1.5 flex items-start gap-2.5 rounded-2xl p-3 shadow-sm
                        {{ $isActive ? 'bg-purple-50 border border-purple-100' : 'bg-white' }}">
                <x-icon name="{{ $isActive ? 'o-check-circle' : 'o-circle-stack' }}"
                        class="mt-0.5 h-3.5 w-3.5 shrink-0 {{ $isActive ? 'text-purple-500' : 'text-gray-300' }}" />
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold {{ $isActive ? 'text-purple-700' : 'text-gray-600' }}">
                        {{ $h->type?->description ?? $h->type?->code }}
                    </p>
                    <p class="text-[10px] text-gray-400">{{ $h->created_at?->format('d M Y H:i') }}</p>
                </div>
                @if($isActive)
                    <span class="shrink-0 rounded-full bg-purple-100 px-2 py-0.5 text-[9px] font-bold text-purple-600">ACTIVE</span>
                @endif
            </div>
        @empty
            <div class="rounded-2xl bg-white p-4 shadow-sm">
                <p class="text-sm text-gray-400">No history</p>
            </div>
        @endforelse
    </div>

    @endif
</div>
