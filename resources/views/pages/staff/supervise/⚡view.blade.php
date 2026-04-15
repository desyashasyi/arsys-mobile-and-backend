<?php

use App\Models\ArSys\Research;
use App\Models\ArSys\DefenseApproval;
use App\Models\ArSys\ResearchMilestone;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Research Detail')] class extends Component
{
    use Toast;
    public int $researchId = 0;

    public function mount(int $id): void
    {
        $this->researchId = $id;
    }

    public function toggleApproval(int $approvalId): void
    {
        $staff = auth()->user()->staff;
        if (!$staff) return;

        $approval = DefenseApproval::where('id', $approvalId)
            ->where('approver_id', $staff->id)
            ->first();

        if (!$approval) return;

        if (is_null($approval->decision)) {
            $approval->decision = 1;
            $approval->approval_date = Carbon::now();
            $approval->save();
            $this->success('Approval granted.', position: 'toast-bottom');
        } else {
            $approval->decision = null;
            $approval->approval_date = null;
            $approval->save();
            $this->warning('Approval revoked.', position: 'toast-bottom');
        }

        $research = Research::find($approval->research_id);
        if ($research) {
            $this->updateMilestone($research);
        }
    }

    private function updateMilestone(Research $research): void
    {
        $research->loadCount(['predefenseApproval', 'predefenseApproved', 'finaldefenseApproval', 'finaldefenseApproved']);

        $allPreApproved  = $research->predefense_approval_count > 0 && $research->predefense_approval_count == $research->predefense_approved_count;
        $allFinalApproved = $research->finaldefense_approval_count > 0 && $research->finaldefense_approval_count == $research->finaldefense_approved_count;

        $milestone = null;
        if ($allFinalApproved) {
            $milestone = ResearchMilestone::where('code', 'Final-defense')->where('phase', 'Approved')->first();
        } elseif ($allPreApproved) {
            $milestone = ResearchMilestone::where('code', 'Pre-defense')->where('phase', 'Approved')->first();
        } else {
            $current = $research->milestone;
            if ($current) {
                if ($current->code === 'Final-defense' && $current->phase === 'Approved') {
                    $milestone = ResearchMilestone::where('code', 'Final-defense')->where('phase', 'Submitted')->first();
                } elseif ($current->code === 'Pre-defense' && $current->phase === 'Approved') {
                    $milestone = ResearchMilestone::where('code', 'Pre-defense')->where('phase', 'Submitted')->first();
                }
            }
        }

        if ($milestone && $research->milestone_id != $milestone->id) {
            $research->update(['milestone_id' => $milestone->id]);
        }
    }

    public function with(): array
    {
        $research = Research::with([
            'student.program',
            'supervisor.staff',
            'milestone',
            'defenseApproval.staff',
            'defenseApproval.defenseModel',
        ])->find($this->researchId);

        if (!$research) {
            return ['research' => null, 'approvals' => collect()];
        }

        $currentUser    = auth()->user();
        $currentStaffId = $currentUser->staff?->id;
        $currentMilestone = $research->milestone;

        $isSupervisorOfThis = $research->supervisor->contains('supervisor_id', $currentStaffId);
        $canSeeApprovals    = $isSupervisorOfThis || $currentUser->hasAnyRole(['kaprodi', 'super_admin']);

        $supervisorMap = $research->supervisor->keyBy('supervisor_id');

        $approvals = $research->defenseApproval->sortBy('id')->map(function ($approval) use ($currentStaffId, $currentMilestone, $supervisorMap) {
            $approvalType = $approval->defenseModel?->description ?? 'unknown';
            $isLocked = false;
            if ($currentMilestone) {
                $approvalMilestoneCode = str_replace(' ', '-', $approvalType);
                if ($currentMilestone->code != $approvalMilestoneCode || $currentMilestone->phase == 'Approved') {
                    $isLocked = true;
                }
            }
            $name = trim(($approval->staff?->first_name ?? '') . ' ' . ($approval->staff?->last_name ?? ''));
            $spvRecord = $supervisorMap->get($approval->approver_id);
            $approverRole = $spvRecord
                ? ($spvRecord->order == 1 ? 'Supervisor' : 'Co-Supervisor')
                : 'Program';
            return [
                'id'              => $approval->id,
                'type'            => $approvalType,
                'approver_name'   => $name,
                'approver_code'   => $approval->staff?->code ?? 'N/A',
                'approver_role'   => $approverRole,
                'is_approved'     => !is_null($approval->decision),
                'is_current_user' => $approval->approver_id == $currentStaffId,
                'is_locked'       => $isLocked,
            ];
        })->values();

        return ['research' => $research, 'approvals' => $approvals, 'canSeeApprovals' => $canSeeApprovals];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">SUPERVISION DETAIL</p>

    @if(!$research)
        <div class="py-16 text-center">
            <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
            <p class="text-sm text-gray-500">Research not found.</p>
        </div>
    @else
        <div class="px-3 py-3 space-y-4">

            {{-- Student Info Card --}}
            <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 border-purple-400">
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <x-icon name="o-user" class="h-4 w-4 text-purple-400 shrink-0" />
                        <div>
                            <p class="text-xs font-semibold text-purple-600">{{ $research->student?->nim ?? '—' }}</p>
                            <p class="font-bold text-sm text-gray-800">{{ $research->student?->first_name }} {{ $research->student?->last_name }}</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-600 leading-snug uppercase">
                        {{ $research->title ?? 'No Title' }}
                    </p>
                    @if($research->milestone)
                        <div class="mt-2">
                            <x-milestone-chip :code="$research->milestone->code" :phase="$research->milestone->phase" />
                        </div>
                    @endif
                </div>
            </div>

            {{-- Supervisors --}}
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <x-icon name="o-user-group" class="h-4 w-4 text-purple-400" />
                    <h3 class="font-bold text-sm text-gray-700">Supervisors</h3>
                </div>
                <div class="rounded-xl bg-white shadow-sm overflow-hidden divide-y divide-gray-100">
                    @forelse($research->supervisor->sortBy('order') as $spv)
                        @php
                            $role = $spv->order == 1 ? 'Supervisor' : 'Co-supervisor';
                            $name = trim(($spv->staff?->first_name ?? '') . ' ' . ($spv->staff?->last_name ?? ''));
                        @endphp
                        <div class="flex items-center gap-3 p-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $role === 'Supervisor' ? 'bg-purple-100' : 'bg-gray-100' }}">
                                <x-icon name="o-user" class="h-4 w-4 {{ $role === 'Supervisor' ? 'text-purple-600' : 'text-gray-500' }}" />
                            </div>
                            <p class="flex-1 text-sm font-medium text-gray-800 truncate">{{ $name ?: ($spv->staff?->code ?? '—') }}</p>
                            <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $role === 'Supervisor' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $role }}
                            </span>
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-gray-400">No supervisors assigned.</div>
                    @endforelse
                </div>
            </div>

            {{-- Approval Requests (only for supervisors of this research or kaprodi/admin) --}}
            @if($canSeeApprovals)
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <x-icon name="o-shield-check" class="h-4 w-4 text-purple-400" />
                    <h3 class="font-bold text-sm text-gray-700">Approval Requests</h3>
                </div>

                @if($approvals->isEmpty())
                    <div class="rounded-xl bg-white shadow-sm p-8 text-center">
                        <x-icon name="o-inbox" class="mx-auto mb-2 h-8 w-8 text-gray-200" />
                        <p class="text-xs text-gray-400">No approval requests.</p>
                    </div>
                @else
                    <div class="rounded-xl bg-white shadow-sm overflow-hidden divide-y divide-gray-100">
                        @foreach($approvals as $approval)
                            @php $canToggle = $approval['is_current_user'] && !$approval['is_locked']; @endphp
                            <div class="flex items-center gap-3 p-3">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $approval['is_approved'] ? 'bg-green-50' : 'bg-gray-100' }}">
                                    <x-icon name="{{ $approval['is_approved'] ? 'o-check' : 'o-clock' }}" class="h-4 w-4 {{ $approval['is_approved'] ? 'text-green-500' : 'text-gray-400' }}" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-800 truncate">
                                        {{ $approval['approver_name'] ?: $approval['approver_code'] }}
                                    </p>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-[10px] px-1.5 py-0.5 rounded font-semibold
                                            {{ $approval['approver_role'] === 'Supervisor' ? 'bg-purple-50 text-purple-600' : ($approval['approver_role'] === 'Co-Supervisor' ? 'bg-indigo-50 text-indigo-500' : 'bg-amber-50 text-amber-600') }}">
                                            {{ $approval['approver_role'] }}
                                        </span>
                                        <span class="text-[10px] bg-gray-50 text-gray-400 px-1.5 py-0.5 rounded">{{ $approval['type'] }}</span>
                                        @if($approval['is_current_user'])
                                            <span class="text-[10px] bg-blue-50 text-blue-500 px-1.5 py-0.5 rounded">You</span>
                                        @endif
                                    </div>
                                </div>
                                <button
                                    wire:click="toggleApproval({{ $approval['id'] }})"
                                    @if(!$canToggle) disabled @endif
                                    class="shrink-0 p-1 {{ $canToggle ? 'cursor-pointer' : 'opacity-40 cursor-not-allowed' }}"
                                >
                                    <x-icon
                                        name="{{ $approval['is_approved'] ? 's-check-circle' : 'o-check-circle' }}"
                                        class="h-7 w-7 {{ $approval['is_approved'] ? 'text-green-500' : 'text-gray-300' }}"
                                    />
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            @endif

        </div>
    @endif
</div>
