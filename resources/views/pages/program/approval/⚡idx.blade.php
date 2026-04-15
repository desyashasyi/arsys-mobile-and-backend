<?php

use App\Models\ArSys\DefenseApproval;
use App\Models\ArSys\DefenseRole;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mobile-app')] #[Title('Defense Approval')] class extends Component
{
    use WithPagination;

    public function with(): array
    {
        $staff = auth()->user()->staff;
        if (!$staff) {
            return ['approvals' => collect()];
        }

        $prgRoleId = DefenseRole::where('code', 'PRG')->value('id');
        if (!$prgRoleId) {
            return ['approvals' => collect()];
        }

        $approvals = DefenseApproval::where('approver_id', $staff->id)
            ->where('approver_role', $prgRoleId)
            ->whereHas('defenseModel', fn($q) => $q->where('code', 'PUB'))
            ->with([
                'research.student.program',
                'defenseModel',
            ])
            ->orderByRaw('decision IS NOT NULL, created_at DESC')
            ->paginate(10)
            ->through(function ($approval) {
                $research = $approval->research;
                $student  = $research?->student;
                return [
                    'id'           => $approval->id,
                    'nim'          => $student?->nim ?? 'N/A',
                    'name'         => trim(($student?->first_name ?? '') . ' ' . ($student?->last_name ?? '')),
                    'title'        => $research?->title ?? 'No Title',
                    'is_approved'  => $approval->decision !== null,
                    'approval_date'=> $approval->approval_date
                        ? \Carbon\Carbon::parse($approval->approval_date)->isoFormat('D MMM YYYY')
                        : null,
                ];
            });

        return ['approvals' => $approvals];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">FINAL DEFENSE APPROVALS</p>

    <div class="px-3 py-3 space-y-2">
        @forelse($approvals as $ap)
            <a href="{{ route('program.approval.detail', $ap['id']) }}"
               class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $ap['is_approved'] ? 'border-green-400' : 'border-amber-400' }} p-4 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-purple-600">{{ $ap['nim'] }}</p>
                        <p class="font-semibold text-sm text-gray-800 mt-0.5">{{ $ap['name'] ?: '—' }}</p>
                        <p class="text-xs text-gray-500 mt-1 leading-snug uppercase truncate">{{ $ap['title'] }}</p>
                        @if($ap['is_approved'] && $ap['approval_date'])
                            <p class="text-[10px] text-green-600 mt-1">Approved · {{ $ap['approval_date'] }}</p>
                        @endif
                    </div>
                    <div class="shrink-0 flex flex-col items-end gap-1">
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $ap['is_approved'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $ap['is_approved'] ? 'Approved' : 'Pending' }}
                        </span>
                        <x-icon name="o-chevron-right" class="h-4 w-4 text-gray-300" />
                    </div>
                </div>
            </a>
        @empty
            <div class="py-16 text-center">
                <x-icon name="o-inbox" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No approval requests.</p>
            </div>
        @endforelse
    </div>

    @if($approvals->hasPages())
        <div class="px-3 pb-4">{{ $approvals->links() }}</div>
    @endif
</div>
