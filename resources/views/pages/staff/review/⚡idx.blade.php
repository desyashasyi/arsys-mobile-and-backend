<?php

use App\Models\ArSys\ResearchReview;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mobile-app')] #[Title('Review')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void { $this->resetPage(); }

    public function with(): array
    {
        $staff = auth()->user()->staff;

        if (!$staff) {
            return ['reviews' => collect(), 'noStaff' => true];
        }

        $reviews = ResearchReview::where('reviewer_id', $staff->id)
            ->whereNull('approval_date')
            ->whereHas('research.milestone', fn($q) => $q->where('code', 'Proposal')->where('phase', 'Review'))
            ->when($this->search, fn($q) => $q->whereHas('research', fn($q) =>
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhereHas('student', fn($q) =>
                      $q->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                  )
            ))
            ->with(['research.student.program', 'research.milestone', 'decision'])
            ->latest()
            ->paginate(20);

        return ['reviews' => $reviews, 'noStaff' => false];
    }


};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">PROPOSALS ASSIGNED FOR YOUR REVIEW</p>
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
        @forelse($reviews as $review)
            @php
                $decision = $review->decision;
                $borderColor = match($decision?->code) {
                    'APP' => 'border-green-400',
                    'RJC' => 'border-red-400',
                    default => 'border-blue-300',
                };
                $badgeClass = match($decision?->code) {
                    'APP' => 'bg-green-100 text-green-700',
                    'RJC' => 'bg-red-100 text-red-600',
                    default => 'bg-blue-100 text-blue-600',
                };
                $badgeLabel = $decision?->description ?? 'Pending';
            @endphp

            <a href="{{ route('staff.review.detail', $review->research_id) }}" class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $borderColor }}">
                <div class="p-3">

                    {{-- Student NIM + Name --}}
                    <div class="flex items-start justify-between gap-2 mb-1">
                        <div>
                            <p class="text-xs font-semibold text-purple-600">{{ $review->research?->student?->nim ?? '—' }}</p>
                            <p class="font-bold text-sm text-gray-800">{{ $review->research?->student?->first_name }} {{ $review->research?->student?->last_name }}</p>
                        </div>
                        <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $badgeClass }}">
                            {{ $badgeLabel }}
                        </span>
                    </div>

                    {{-- Research Title --}}
                    <p class="text-xs text-gray-600 leading-snug line-clamp-2 uppercase">
                        {{ $review->research?->title ?? 'No Title' }}
                    </p>

                    {{-- Milestone --}}
                    @if($review->research?->milestone)
                        <div class="mt-2">
                            <x-milestone-chip :code="$review->research->milestone->code" :phase="$review->research->milestone->phase" />
                        </div>
                    @endif

                </div>
            </a>

        @empty
            <div class="py-16 text-center">
                <x-icon name="o-clipboard-document-list" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No proposals assigned for review.</p>
            </div>
        @endforelse
        @endif

    </div>

    @if(isset($reviews) && $reviews instanceof \Illuminate\Pagination\LengthAwarePaginator && $reviews->hasPages())
        <div class="px-3 pb-4">{{ $reviews->links() }}</div>
    @endif
</div>
