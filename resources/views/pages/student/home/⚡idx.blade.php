<?php

use App\Models\ArSys\Research;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile-app')] #[Title('Home')] class extends Component
{
    public function with(): array
    {
        $user    = auth()->user();
        $student = $user?->student()->with('program')->first();

        $researchCount  = $student
            ? Research::where('student_id', $student->id)->count()
            : 0;

        $activeResearch = $student
            ? Research::where('student_id', $student->id)
                ->whereHas('history', fn($q) => $q->where('status', 1)
                    ->whereHas('type', fn($t) => $t->whereIn('code', ['ACT', 'SUB', 'REV'])))
                ->with(['milestone', 'history.type'])
                ->latest()
                ->first()
            : null;

        return [
            'userName'      => $user?->name ?? 'Student',
            'studentNumber' => $student?->number ?? '—',
            'programName'   => $student?->program?->name ?? '—',
            'researchCount' => $researchCount,
            'activeResearch'=> $activeResearch,
        ];
    }
};
?>

<div class="px-4 py-4 pb-24 space-y-4">

    {{-- ─── Welcome Card ─── --}}
    <div class="rounded-2xl overflow-hidden shadow-sm"
         style="background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%)">
        <div class="px-5 py-5">
            <div class="flex items-center gap-4">
                {{-- Avatar --}}
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/20 text-2xl font-bold text-white">
                    {{ strtoupper(substr($userName, 0, 1)) }}
                </div>
                {{-- Name & info --}}
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-white/70 mb-0.5">Welcome,</p>
                    <p class="text-base font-bold text-white leading-tight truncate">{{ $userName }}</p>
                    <p class="text-[11px] text-white/60 mt-0.5 truncate">{{ $studentNumber }} · {{ $programName }}</p>
                </div>
                {{-- Profile button --}}
                <a href="{{ route('student.profile') }}"
                   class="shrink-0 flex items-center gap-1 rounded-full border border-white/40 px-3 py-1.5 text-xs font-semibold text-white hover:bg-white/10 transition-colors">
                    <x-icon name="o-user" class="h-3.5 w-3.5" />
                    Profile
                </a>
            </div>
        </div>
    </div>

    {{-- ─── Quick Stats ─── --}}
    <div class="grid grid-cols-2 gap-3">
        <div class="rounded-2xl bg-white shadow-sm p-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100">
                <x-icon name="o-academic-cap" class="h-5 w-5 text-purple-600" />
            </div>
            <div>
                <p class="text-xl font-bold text-purple-700">{{ $researchCount }}</p>
                <p class="text-[10px] text-gray-400 font-medium">Research</p>
            </div>
        </div>
        <a href="{{ route('student.events') }}"
           class="rounded-2xl bg-white shadow-sm p-4 flex items-center gap-3 hover:bg-gray-50 transition-colors">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-orange-100">
                <x-icon name="o-calendar-days" class="h-5 w-5 text-orange-500" />
            </div>
            <div>
                <p class="text-sm font-bold text-orange-600">Events</p>
                <p class="text-[10px] text-gray-400 font-medium">Schedule</p>
            </div>
        </a>
    </div>

    {{-- ─── Active Research ─── --}}
    @if($activeResearch)
    <div>
        <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Active Research</p>
        <a href="{{ url('/student/research/' . $activeResearch->id) }}"
           class="block rounded-2xl bg-white shadow-sm overflow-hidden hover:shadow-md transition-shadow">
            <div class="h-1 bg-gradient-to-r from-purple-500 to-purple-300"></div>
            <div class="p-4">
                <p class="text-[10px] font-semibold text-gray-400 mb-0.5">{{ $activeResearch->code }}</p>
                <p class="text-sm font-bold text-gray-800 leading-snug line-clamp-2">
                    {{ Str::upper($activeResearch->title) }}
                </p>
                @if($activeResearch->milestone)
                    <div class="mt-2">
                        <x-milestone-chip
                            :code="$activeResearch->milestone->code"
                            :phase="$activeResearch->milestone->phase" />
                    </div>
                @endif
            </div>
        </a>
    </div>
    @endif

    {{-- ─── Quick Access ─── --}}
    <div>
        <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2">Quick Access</p>
        <div class="space-y-2">
            <a href="{{ route('student.research') }}"
               class="flex items-center gap-4 rounded-2xl bg-white shadow-sm p-4 hover:bg-gray-50 transition-colors">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100">
                    <x-icon name="o-academic-cap" class="h-5 w-5 text-purple-600" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-gray-700">My Research</p>
                    <p class="text-[11px] text-gray-400">View & manage research proposals</p>
                </div>
                <x-icon name="o-chevron-right" class="h-4 w-4 text-gray-300" />
            </a>
            <a href="{{ route('student.events') }}"
               class="flex items-center gap-4 rounded-2xl bg-white shadow-sm p-4 hover:bg-gray-50 transition-colors">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-orange-100">
                    <x-icon name="o-calendar-days" class="h-5 w-5 text-orange-500" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-gray-700">Events</p>
                    <p class="text-[11px] text-gray-400">Pre-defense & final defense schedule</p>
                </div>
                <x-icon name="o-chevron-right" class="h-4 w-4 text-gray-300" />
            </a>
        </div>
    </div>

    {{-- ─── Branding ─── --}}
    <div class="py-6 flex flex-col items-center gap-2">
        <x-icon name="o-academic-cap" class="h-12 w-12 text-purple-200" />
        <p class="text-lg font-bold text-purple-700">ArSys</p>
        <p class="text-xs text-gray-400">Advanced Research Support System</p>
    </div>

</div>
