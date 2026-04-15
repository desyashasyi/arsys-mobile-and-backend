<?php

use App\Models\ArSys\Research;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mobile-app')] #[Title('Supervise')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void { $this->resetPage(); }

    public function with(): array
    {
        $staff = auth()->user()->staff;

        if (!$staff) {
            return ['researches' => collect(), 'noStaff' => true];
        }

        $researches = Research::whereHas('supervisor', fn($q) => $q->where('supervisor_id', $staff->id))
            ->whereHas('active')
            ->when($this->search, fn($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhereHas('student', fn($q) =>
                      $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                  );
            }))
            ->withCount('approvalRequest')
            ->with(['student.program', 'milestone', 'supervisor.staff', 'approvalRequest'])
            ->orderByDesc('approval_request_count')
            ->orderBy('id')
            ->paginate(20);

        return ['researches' => $researches, 'noStaff' => false];
    }


};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">YOUR ACTIVE SUPERVISION LIST</p>
    <div class="px-3 pb-2">
        <x-input
            placeholder="Search title or student..."
            wire:model.live.debounce="search"
            icon="o-magnifying-glass"
            clearable
        />
    </div>

    <div class="px-3 py-3 space-y-2">

        @if($noStaff)
            <div class="py-16 text-center">
                <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
                <p class="text-sm font-semibold text-gray-500">No staff record linked to your account.</p>
            </div>

        @else
        @forelse($researches as $research)
            @php
                $needsApproval = $research->approvalRequest->isNotEmpty();
                $supervisorCodes = $research->supervisor->map(fn($s) => $s->staff?->code)->filter()->implode(', ');
            @endphp
            <a href="{{ route('staff.supervise.detail', $research->id) }}" class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $needsApproval ? 'border-orange-400' : 'border-purple-300' }}">
                <div class="p-3">

                    {{-- Student NIM + Name --}}
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <div>
                            <p class="text-xs font-semibold text-purple-600">{{ $research->student?->nim ?? '—' }}</p>
                            <p class="font-bold text-sm text-gray-800">{{ $research->student?->first_name }} {{ $research->student?->last_name }}</p>
                        </div>
                        @if($needsApproval)
                            <span class="shrink-0 flex items-center gap-1 bg-orange-100 text-orange-600 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                <x-icon name="o-clock" class="h-3 w-3" />
                                Approval
                            </span>
                        @endif
                    </div>

                    {{-- Research Title --}}
                    <p class="text-xs text-gray-600 leading-snug line-clamp-2 uppercase">
                        {{ $research->title ?? 'No Title' }}
                    </p>

                    {{-- Milestone + Supervisors --}}
                    @if($research->milestone)
                        <div class="mt-2">
                            <x-milestone-chip :code="$research->milestone->code" :phase="$research->milestone->phase" />
                        </div>
                    @endif

                    @if($supervisorCodes)
                        <p class="mt-1 text-[11px] text-gray-400">
                            <span class="font-medium">SPV:</span> {{ $supervisorCodes }}
                        </p>
                    @endif

                </div>
            </a>

        @empty
            <div class="py-16 text-center">
                <x-icon name="o-academic-cap" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No active research found.</p>
            </div>
        @endforelse
        @endif

    </div>

    @if(isset($researches) && $researches instanceof \Illuminate\Pagination\LengthAwarePaginator && $researches->hasPages())
        <div class="px-3 pb-4">{{ $researches->links() }}</div>
    @endif
</div>
