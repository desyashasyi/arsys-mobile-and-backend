<?php

use App\Models\ArSys\Research;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile-app')] #[Title('My Research')] class extends Component
{
    public function with(): array
    {
        $student = auth()->user()->student;

        if (!$student) {
            return ['researches' => collect()];
        }

        $researches = Research::where('student_id', $student->id)
            ->with([
                'type.base',
                'milestone',
                'history' => fn($q) => $q->where('status', 1)->with('type'),
                'supervisor.staff',
                'reviewers.staff',
            ])
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($r) {
                $activeLog  = $r->history->first();
                $statusCode = $activeLog?->type?->code;

                // Show reviewers if in review state, supervisors otherwise
                $people = [];
                if ($statusCode === 'REV' && $r->reviewers->isNotEmpty()) {
                    foreach ($r->reviewers as $rev) {
                        $people[] = ['code' => $rev->staff?->code, 'role' => 'REV'];
                    }
                } elseif ($r->supervisor->isNotEmpty()) {
                    foreach ($r->supervisor as $spv) {
                        $people[] = ['code' => $spv->staff?->code, 'role' => 'SPV'];
                    }
                }

                return [
                    'id'              => $r->id,
                    'code'            => $r->code,
                    'title'           => $r->title,
                    'type_name'       => ($r->type?->base?->code ?? '') . ' — ' . ($r->type?->base?->description ?? ''),
                    'milestone_code'  => $r->milestone?->code,
                    'milestone_phase' => $r->milestone?->phase,
                    'status_code'     => $statusCode,
                    'status_desc'     => $activeLog?->type?->description,
                    'people'          => $people,
                ];
            });

        return compact('researches');
    }
};
?>

<div class="pb-2">

    {{-- Header with create button --}}
    <div class="flex items-center justify-between px-4 py-3">
        <span class="text-sm font-semibold text-gray-600">{{ count($researches) }} research(es)</span>
        <a href="{{ route('student.research.create') }}"
           class="flex items-center gap-1.5 rounded-full bg-purple-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-purple-700 transition-colors">
            <x-icon name="o-plus" class="h-3.5 w-3.5" />
            New
        </a>
    </div>

    @forelse ($researches as $r)
    @php
        $statusColor = match($r['status_code'] ?? '') {
            'CRE'  => 'bg-yellow-100 text-yellow-700',
            'SUB'  => 'bg-blue-100 text-blue-700',
            'REV'  => 'bg-indigo-100 text-indigo-700',
            'ACT'  => 'bg-green-100 text-green-700',
            'FRE'  => 'bg-cyan-100 text-cyan-700',
            'REN'  => 'bg-orange-100 text-orange-700',
            'RJC'  => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-600',
        };
        $borderColor = match($r['status_code'] ?? '') {
            'ACT'  => 'border-l-green-400',
            'REV'  => 'border-l-indigo-400',
            'SUB'  => 'border-l-blue-400',
            'RJC'  => 'border-l-red-400',
            'FRE'  => 'border-l-cyan-400',
            'REN'  => 'border-l-orange-400',
            default => 'border-l-purple-200',
        };
    @endphp
    <a href="{{ url('/student/research/' . $r['id']) }}"
       class="mx-4 mb-3 block rounded-2xl bg-white shadow-sm hover:shadow-md transition-shadow overflow-hidden border-l-4 {{ $borderColor }}">
        <div class="p-4">
            {{-- Code & Status --}}
            <div class="flex items-start justify-between gap-2 mb-1.5">
                <span class="text-[10px] font-semibold text-gray-400 tracking-wide">{{ $r['code'] }}</span>
                @if($r['status_code'])
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $statusColor }}">
                        {{ $r['status_code'] }}
                    </span>
                @endif
            </div>

            {{-- Title --}}
            <p class="text-sm font-semibold text-gray-800 leading-snug line-clamp-2 mb-1">
                {{ $r['title'] }}
            </p>

            {{-- Type --}}
            <p class="text-[11px] text-gray-400 mb-2">{{ $r['type_name'] }}</p>

            {{-- Milestone chip --}}
            @if($r['milestone_code'])
                <x-milestone-chip :code="$r['milestone_code']" :phase="$r['milestone_phase']" />
            @endif

            {{-- Supervisors / Reviewers --}}
            @if(!empty($r['people']))
                <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                    @foreach($r['people'] as $person)
                        @if($person['code'])
                            <span class="inline-flex items-center gap-1 rounded-full
                                {{ $person['role'] === 'REV' ? 'bg-indigo-50 text-indigo-600' : 'bg-purple-50 text-purple-600' }}
                                px-2 py-0.5 text-[10px] font-semibold">
                                <x-icon name="{{ $person['role'] === 'REV' ? 'o-clipboard-document-check' : 'o-user' }}"
                                        class="h-2.5 w-2.5" />
                                {{ $person['code'] }}
                            </span>
                        @endif
                    @endforeach
                    <span class="text-[9px] text-gray-300">
                        {{ $r['people'][0]['role'] === 'REV' ? 'reviewer(s)' : 'supervisor(s)' }}
                    </span>
                </div>
            @endif
        </div>
        <div class="border-t border-gray-50 px-4 py-2 flex justify-end">
            <span class="text-[11px] text-purple-500 font-medium flex items-center gap-1">
                View detail <x-icon name="o-chevron-right" class="h-3 w-3" />
            </span>
        </div>
    </a>
    @empty
    <div class="mx-4 mt-8 flex flex-col items-center gap-3 text-center">
        <x-icon name="o-document-text" class="h-14 w-14 text-purple-200" />
        <p class="text-sm font-semibold text-gray-400">No research yet</p>
        <a href="{{ route('student.research.create') }}"
           class="rounded-full bg-purple-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-700 transition-colors">
            Start a new proposal
        </a>
    </div>
    @endforelse

</div>
