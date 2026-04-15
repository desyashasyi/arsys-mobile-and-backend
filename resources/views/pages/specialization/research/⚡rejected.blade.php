<?php

use App\Models\ArSys\Research;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mobile-app')] #[Title('Rejected')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $programId = auth()->user()->staff?->program_id;

        if (!$programId) {
            return ['items' => collect()];
        }

        $search = trim($this->search);

        $items = Research::with(['student.program', 'milestone'])
            ->whereHas('student', fn($q) => $q->where('program_id', $programId))
            ->whereHas('history', fn($q) => $q->whereHas('type', fn($q2) => $q2->where('code', 'RJC')))
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('title', 'like', "%{$search}%")
                       ->orWhereHas('student', function ($q3) use ($search) {
                           $q3->where('first_name', 'like', "%{$search}%")
                              ->orWhere('last_name',  'like', "%{$search}%")
                              ->orWhere('number',     'like', "%{$search}%");
                       });
                });
            })
            ->orderBy('updated_at', 'desc')
            ->paginate(10)
            ->through(fn($r) => [
                'id'             => $r->id,
                'title'          => $r->title ?? 'No Title',
                'student_name'   => trim(($r->student?->first_name ?? '') . ' ' . ($r->student?->last_name ?? '')),
                'student_number' => $r->student?->nim ?? '-',
                'milestone_code' => $r->milestone?->code ?? '-',
                'milestone_phase'=> $r->milestone?->phase ?? '-',
                'updated_at'     => $r->updated_at ? Carbon::parse($r->updated_at)->isoFormat('D MMM YYYY') : '-',
            ]);

        return ['items' => $items];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">Proposals that were rejected</p>

    <div class="px-3 pb-3 space-y-2">
        @forelse($items as $item)
            <a href="{{ route('specialization.research.show', $item['id']) }}"
               class="block rounded-xl bg-white shadow-sm overflow-hidden border-l-4 border-red-400 p-4 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-semibold text-purple-600 mb-0.5">{{ $item['student_number'] }}</p>
                        <p class="font-semibold text-sm text-gray-800 leading-tight truncate">{{ $item['student_name'] ?: '—' }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 leading-snug line-clamp-2">{{ $item['title'] }}</p>
                        <div class="mt-1.5">
                            <x-milestone-chip :code="$item['milestone_code']" :phase="$item['milestone_phase']" />
                        </div>
                    </div>
                    <div class="shrink-0 flex flex-col items-end gap-1">
                        <x-icon name="o-chevron-right" class="h-4 w-4 text-gray-300" />
                    </div>
                </div>
            </a>
        @empty
            <div class="py-16 text-center">
                <x-icon name="o-inbox" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No rejected research found.</p>
            </div>
        @endforelse
    </div>

    @if($items->hasPages())
        <div class="px-3 pb-4">{{ $items->links() }}</div>
    @endif
</div>
