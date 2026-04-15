<?php

use App\Models\ArSys\DefenseApproval;
use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchMilestone;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Approval Detail')] class extends Component
{
    use Toast;

    public int    $approvalId  = 0;
    public bool   $revokeSheet = false;
    public bool   $editingGPA  = false;
    public string $ipkInput    = '';
    public string $sksInput    = '';

    public function mount(int $id): void
    {
        $this->approvalId = $id;

        $approval = DefenseApproval::with('research.student')->find($id);
        $student  = $approval?->research?->student;
        $this->ipkInput = $student?->GPA !== null ? (string) $student->GPA : '';
        $this->sksInput = $student?->sks  !== null ? (string) $student->sks  : '';
    }

    public function approve(): void
    {
        $staff = auth()->user()->staff;
        if (!$staff) return;

        $approval = DefenseApproval::where('id', $this->approvalId)
            ->where('approver_id', $staff->id)
            ->whereNull('decision')
            ->first();

        if (!$approval) {
            $this->error('Approval not found or already processed.', position: 'toast-bottom');
            return;
        }

        $approval->decision      = 1;
        $approval->approval_date = Carbon::now();
        $approval->save();

        $this->success('Approval granted successfully.', position: 'toast-bottom');

        $research = Research::find($approval->research_id);
        if ($research) {
            $this->updateMilestone($research);
        }
    }

    public function saveGPA(): void
    {
        $ipk = trim($this->ipkInput);
        if ($ipk === '' || !is_numeric($ipk) || (float)$ipk < 0 || (float)$ipk > 4) {
            $this->error('GPA must be a number between 0.00 and 4.00.', position: 'toast-bottom');
            return;
        }

        $sks = trim($this->sksInput);
        if ($sks === '' || !ctype_digit($sks) || (int)$sks < 0) {
            $this->error('Credits earned must be a positive integer.', position: 'toast-bottom');
            return;
        }

        $approval = DefenseApproval::with('research.student')->find($this->approvalId);
        $student  = $approval?->research?->student;

        if (!$student) {
            $this->error('Student not found.', position: 'toast-bottom');
            return;
        }

        $student->GPA = number_format((float)$ipk, 2);
        $student->sks = (int)$sks;
        $student->save();

        $this->editingGPA = false;
        $this->success('Academic data saved.', position: 'toast-bottom');
    }

    public function revoke(): void
    {
        $staff = auth()->user()->staff;
        if (!$staff) return;

        $approval = DefenseApproval::where('id', $this->approvalId)
            ->where('approver_id', $staff->id)
            ->whereNotNull('decision')
            ->first();

        if (!$approval) {
            $this->error('Approval not found or not yet approved.', position: 'toast-bottom');
            return;
        }

        $approval->decision      = null;
        $approval->approval_date = null;
        $approval->save();

        $this->revokeSheet = false;
        $this->warning('Approval has been revoked.', position: 'toast-bottom');

        $research = Research::find($approval->research_id);
        if ($research) {
            $this->updateMilestone($research);
        }
    }

    private function updateMilestone(Research $research): void
    {
        $research->loadCount(['finaldefenseApproval', 'finaldefenseApproved']);

        $allApproved = $research->finaldefense_approval_count > 0
            && $research->finaldefense_approval_count == $research->finaldefense_approved_count;

        $milestone = $allApproved
            ? ResearchMilestone::where('code', 'Final-defense')->where('phase', 'Approved')->first()
            : ResearchMilestone::where('code', 'Final-defense')->where('phase', 'Submitted')->first();

        if ($milestone && $research->milestone_id != $milestone->id) {
            $research->update(['milestone_id' => $milestone->id]);
        }
    }

    public function with(): array
    {
        $staff = auth()->user()->staff;

        $myApproval = DefenseApproval::where('id', $this->approvalId)
            ->where('approver_id', $staff?->id)
            ->with([
                'research.student.program',
                'research.supervisor.staff',
                'defenseModel',
            ])
            ->first();

        if (!$myApproval) {
            return ['myApproval' => null, 'student' => [], 'allApprovals' => collect(), 'canApprove' => false, 'canRevoke' => false];
        }

        $research = $myApproval->research;
        $student  = $research?->student;

        $allApprovals = DefenseApproval::where('research_id', $myApproval->research_id)
            ->whereHas('defenseModel', fn($q) => $q->where('code', 'PUB'))
            ->with(['staff', 'defenseRole'])
            ->get()
            ->map(function ($a) use ($research) {
                $roleName = $a->defenseRole?->description ?? 'Approver';
                if ($a->defenseRole?->code === 'SPV' && $research) {
                    $sup = $research->supervisor()->where('supervisor_id', $a->approver_id)->first();
                    if ($sup && $sup->order > 1) {
                        $roleName = 'Co-Supervisor';
                    }
                }
                return [
                    'id'          => $a->id,
                    'role_name'   => $roleName,
                    'staff_name'  => $a->staff ? trim($a->staff->first_name . ' ' . $a->staff->last_name) : 'N/A',
                    'is_approved' => $a->decision !== null,
                    'is_me'       => $a->id === $this->approvalId,
                    'date'        => $a->approval_date
                        ? Carbon::parse($a->approval_date)->format('d M Y')
                        : null,
                ];
            });

        $canApprove = $myApproval->decision === null;
        $canRevoke  = $myApproval->decision !== null;

        return [
            'myApproval'   => $myApproval,
            'student'      => [
                'nim'   => $student?->nim ?? 'N/A',
                'name'  => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                'title' => $research?->title ?? 'No Title',
                'model' => $myApproval->defenseModel?->name ?? 'Final Defense',
                'gpa'   => $student?->GPA,
                'sks'   => $student?->sks,
            ],
            'allApprovals' => $allApprovals,
            'canApprove'   => $canApprove,
            'canRevoke'    => $canRevoke,
        ];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">FINAL DEFENSE APPROVAL</p>

    @if(!$myApproval)
        <div class="py-16 text-center">
            <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
            <p class="text-sm text-gray-500">Approval not found.</p>
        </div>
    @else
        <div class="px-3 py-3 space-y-4 pb-28">

            {{-- Student Info Card --}}
            <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 border-amber-500 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-purple-50 text-lg font-bold text-purple-700">
                        {{ strtoupper(substr($student['name'] ?: '?', 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-purple-600">{{ $student['nim'] }}</p>
                        <p class="font-bold text-sm text-gray-800 leading-tight truncate">{{ $student['name'] ?: '—' }}</p>
                        <span class="inline-block mt-0.5 text-[10px] px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 font-medium">{{ $student['model'] }}</span>
                    </div>
                </div>
                <div class="mt-3">
                    <p class="text-[10px] text-gray-400 uppercase font-semibold">Research Title</p>
                    <p class="text-sm text-gray-700 mt-0.5 leading-snug">{{ $student['title'] }}</p>
                </div>
            </div>

            {{-- GPA & Credits --}}
            <div class="rounded-xl bg-white shadow-sm p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-bold text-gray-600 uppercase tracking-wide">Student Academic Data</p>
                    @if(!$editingGPA)
                        <button wire:click="$set('editingGPA', true)"
                            class="flex items-center gap-1 text-xs font-semibold text-purple-600 hover:text-purple-800">
                            <x-icon name="o-pencil-square" class="h-3.5 w-3.5" />
                            Edit
                        </button>
                    @endif
                </div>

                @if($editingGPA)
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">GPA (0.00 – 4.00)</label>
                            <input wire:model="ipkInput" type="number" step="0.01" min="0" max="4"
                                placeholder="e.g. 3.75"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-0 focus:border-purple-400" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Credits Earned</label>
                            <input wire:model="sksInput" type="number" min="0"
                                placeholder="e.g. 144"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm focus:outline-none focus:ring-0 focus:border-purple-400" />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="$set('editingGPA', false)"
                            class="flex-1 py-2 rounded-xl border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button wire:click="saveGPA" wire:loading.attr="disabled"
                            class="flex-1 py-2 rounded-xl bg-purple-600 text-white text-xs font-bold hover:bg-purple-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="saveGPA">Save</span>
                            <span wire:loading wire:target="saveGPA" class="loading loading-spinner loading-xs"></span>
                        </button>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-2.5">
                            <p class="text-[10px] font-semibold text-gray-400 mb-0.5">GPA</p>
                            <p class="text-sm font-bold text-gray-700">{{ $student['gpa'] !== null ? number_format((float)$student['gpa'], 2) : '—' }}</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-2.5">
                            <p class="text-[10px] font-semibold text-gray-400 mb-0.5">Credits Earned</p>
                            <p class="text-sm font-bold text-gray-700">{{ $student['sks'] ?? '—' }}</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Approval Status List --}}
            <div>
                <p class="text-sm font-semibold text-gray-600 mb-2">Approval Status</p>
                <div class="space-y-2">
                    @foreach($allApprovals as $ap)
                        <div class="flex items-center gap-3 rounded-lg px-3 py-2.5
                            {{ $ap['is_approved'] ? 'bg-green-50 border border-green-100' : 'bg-orange-50 border border-orange-100' }}">
                            <x-icon name="{{ $ap['is_approved'] ? 'o-check-circle' : 'o-clock' }}"
                                    class="h-5 w-5 shrink-0 {{ $ap['is_approved'] ? 'text-green-600' : 'text-orange-500' }}" />
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-semibold text-gray-500">{{ $ap['role_name'] }}</p>
                                <p class="text-sm font-medium text-gray-800 truncate">{{ $ap['staff_name'] }}</p>
                            </div>
                            @if($ap['is_approved'] && $ap['date'])
                                <p class="text-[10px] text-gray-400 shrink-0">{{ $ap['date'] }}</p>
                            @endif
                            @if($ap['is_me'])
                                <span class="shrink-0 text-[9px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-600">You</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

        </div>

        {{-- ─── Sticky Action Buttons ─── --}}
        @if($canApprove || $canRevoke)
            <div class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-sm z-30 px-4 pb-20 pt-3 bg-gradient-to-t from-gray-100 via-gray-100/95 to-transparent">
                @if($canApprove)
                    <button
                        wire:click="approve"
                        wire:loading.attr="disabled"
                        class="w-full flex items-center justify-center gap-2 py-3.5 rounded-2xl bg-green-600 text-white font-bold text-sm shadow-lg hover:bg-green-700 active:scale-95 disabled:opacity-60 transition-all">
                        <span wire:loading.remove wire:target="approve">
                            <x-icon name="o-check-badge" class="h-5 w-5 inline -mt-0.5 mr-1" />
                            Approval Granted
                        </span>
                        <span wire:loading wire:target="approve" class="loading loading-spinner loading-sm"></span>
                    </button>
                @endif
                @if($canRevoke)
                    <button
                        wire:click="$set('revokeSheet', true)"
                        class="w-full flex items-center justify-center gap-2 py-3.5 rounded-2xl bg-red-500 text-white font-bold text-sm shadow-lg hover:bg-red-600 active:scale-95 transition-all">
                        <x-icon name="o-x-circle" class="h-5 w-5" />
                        Revoke Approval
                    </button>
                @endif
            </div>
        @endif
    @endif

    {{-- ─── Revoke Confirmation Bottom Sheet ─── --}}
    <div x-data x-show="$wire.revokeSheet"
         x-transition:enter="transition-opacity ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         wire:click="$set('revokeSheet', false)"
         class="fixed inset-0 z-30 bg-black/50">
    </div>

    <div x-data x-show="$wire.revokeSheet"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-full"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 transform rounded-t-2xl bg-white shadow-2xl">

        {{-- Handle --}}
        <div class="flex justify-center pt-3 pb-1">
            <div class="h-1 w-10 rounded-full bg-gray-300"></div>
        </div>

        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <h3 class="text-base font-bold text-red-600">Revoke Approval</h3>
            <button wire:click="$set('revokeSheet', false)"
                class="flex items-center justify-center h-7 w-7 rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>

        {{-- Content --}}
        <div class="px-4 py-4 space-y-3">
            @if($myApproval)
                <div class="rounded-xl bg-red-50 px-3 py-2.5">
                    <p class="text-xs font-semibold text-purple-600">{{ $student['nim'] }}</p>
                    <p class="text-sm font-bold text-gray-800">{{ $student['name'] ?: '—' }}</p>
                </div>
            @endif
            <p class="text-sm text-gray-600">
                Your approval will be revoked. The status will return to <strong>Pending</strong>.
            </p>
            <p class="text-[11px] text-gray-400">
                The student's GPA and credits data will not be deleted.
            </p>
        </div>

        {{-- Actions --}}
        <div class="flex gap-3 px-4 pb-20 pt-2 border-t border-gray-100">
            <button wire:click="$set('revokeSheet', false)"
                class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                Cancel
            </button>
            <button wire:click="revoke" wire:loading.attr="disabled"
                class="flex-1 py-3 rounded-xl bg-red-500 text-white text-sm font-bold hover:bg-red-600 active:opacity-90 disabled:opacity-60">
                <span wire:loading.remove wire:target="revoke">Yes, Revoke</span>
                <span wire:loading wire:target="revoke" class="loading loading-spinner loading-sm"></span>
            </button>
        </div>

    </div>
</div>
