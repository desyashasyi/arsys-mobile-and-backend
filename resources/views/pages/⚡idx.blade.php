<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts.mobile-app')] #[Title('Home')] class extends Component
{
    public function with(): array
    {
        $user = auth()->user();
        return [
            'user'        => $user,
            'roles'       => $user->getRoleNames(),
            'isKaprodi'   => $user->hasRole('program'),
            'isResearch'       => $user->hasAnyRole(['research', 'specialization']),
            'isDefense'        => $user->hasAnyRole(['defense', 'specialization']),
            'isSpecialization' => $user->hasRole('specialization'),
            'isSuperAdmin'     => $user->hasRole('super_admin'),
        ];
    }
};
?>

<div class="pb-2">

    {{-- ─── Welcome Card (Flutter gradient card) ─── --}}
    <div class="m-4 rounded-2xl overflow-hidden shadow-md bg-gradient-to-br from-purple-700 to-purple-400">
        <div class="flex items-center gap-4 p-5">
            {{-- Avatar --}}
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/20 text-2xl font-bold text-white">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            {{-- Name --}}
            <div class="flex-1 min-w-0">
                <p class="text-white/80 text-xs">Welcome,</p>
                <p class="text-white font-bold text-lg leading-tight truncate">{{ $user->name }}</p>
                <div class="flex flex-wrap gap-1 mt-1.5">
                    @foreach($roles as $role)
                        <span class="bg-white/20 text-white text-[10px] font-semibold px-2 py-0.5 rounded-full">{{ $role }}</span>
                    @endforeach
                </div>
            </div>
            {{-- Profile button --}}
            <a href="{{ auth()->user()->hasRole('student') ? route('student.profile') : '#' }}"
               class="shrink-0 rounded-full border border-white/50 px-3 py-1.5 text-xs text-white font-medium flex items-center gap-1">
                <x-icon name="o-user" class="h-3.5 w-3.5" />
                Profile
            </a>
        </div>
    </div>

    {{-- ─── Super Admin Block ─── --}}
    @if($isSuperAdmin)
    <div class="mx-4 mb-4">
        <div class="mb-2 flex items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-purple-700">Super Admin</span>
            <div class="h-px flex-1 bg-purple-100"></div>
        </div>
        <div class="grid grid-cols-3 gap-2">

            <a href="{{ route('super-admin.staff') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-purple-50 transition-colors">
                <x-icon name="o-users" class="h-7 w-7 text-purple-600" />
                <span class="text-[11px] font-semibold text-purple-700 text-center leading-tight">Staff</span>
            </a>

            <a href="{{ route('super-admin.config.program') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-indigo-50 transition-colors">
                <x-icon name="o-building-library" class="h-7 w-7 text-indigo-600" />
                <span class="text-[11px] font-semibold text-indigo-700 text-center leading-tight">Programs</span>
            </a>

            <a href="{{ route('super-admin.student') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-violet-50 transition-colors">
                <x-icon name="o-academic-cap" class="h-7 w-7 text-violet-600" />
                <span class="text-[11px] font-semibold text-violet-700 text-center leading-tight">Students</span>
            </a>

            <a href="{{ route('super-admin.clients') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-blue-50 transition-colors">
                <x-icon name="o-building-office-2" class="h-7 w-7 text-blue-600" />
                <span class="text-[11px] font-semibold text-blue-700 text-center leading-tight">Clients</span>
            </a>

        </div>
    </div>
    @endif

    {{-- ─── Program Menu (kaprodi) ─── --}}
    @if($isKaprodi)
    <div class="mx-4 mb-4">
        <div class="mb-2 flex items-center gap-2">
            <span class="text-xs font-bold uppercase tracking-wider text-gray-600">Program Menu</span>
            <div class="h-px flex-1 bg-gray-200"></div>
        </div>
        <div class="grid grid-cols-3 gap-2">

            <a href="{{ route('program.pre-defense') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-orange-50 transition-colors">
                <x-icon name="o-scale" class="h-7 w-7 text-orange-500" />
                <span class="text-[11px] font-semibold text-orange-600 text-center leading-tight">Pre-Defense Scores</span>
            </a>

            <a href="{{ route('program.final-defense') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-purple-50 transition-colors">
                <x-icon name="o-trophy" class="h-7 w-7 text-purple-700" />
                <span class="text-[11px] font-semibold text-purple-700 text-center leading-tight">Final Defense Scores</span>
            </a>

            <a href="{{ route('program.approval') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-amber-50 transition-colors">
                <x-icon name="o-check-badge" class="h-7 w-7 text-amber-600" />
                <span class="text-[11px] font-semibold text-amber-700 text-center leading-tight">Defense Approval</span>
            </a>

        </div>
    </div>
    @endif

    {{-- ─── Specialization Block ─── --}}
    @if($isDefense || $isResearch)
    <div class="mx-4 mb-4">
        {{-- Section header --}}
        <div class="mb-3 rounded-lg bg-purple-50 border border-purple-100 px-3 py-1.5">
            <span class="text-xs font-bold text-purple-700 tracking-wide">Specialization</span>
        </div>

        @if($isDefense)
        {{-- Event sub-header --}}
        <div class="mb-2 flex items-center gap-2">
            <x-icon name="o-calendar-days" class="h-4 w-4 text-teal-500" />
            <span class="text-xs font-semibold text-teal-600">Event</span>
            <div class="h-px flex-1 bg-teal-100"></div>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-3">

            <a href="{{ route('specialization.defense.events') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-teal-50 transition-colors">
                <x-icon name="o-list-bullet" class="h-7 w-7 text-teal-500" />
                <span class="text-[11px] font-semibold text-teal-600 text-center leading-tight">List of Event</span>
            </a>

            <a href="{{ route('specialization.defense.pre-defense') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-orange-50 transition-colors">
                <x-icon name="o-scale" class="h-7 w-7 text-orange-500" />
                <span class="text-[11px] font-semibold text-orange-600 text-center leading-tight">Pre-Defense</span>
            </a>

            <a href="{{ route('specialization.defense.final-defense') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-purple-50 transition-colors">
                <x-icon name="o-trophy" class="h-7 w-7 text-purple-600" />
                <span class="text-[11px] font-semibold text-purple-700 text-center leading-tight">Final Defense</span>
            </a>

            <a href="{{ route('specialization.defense.seminar') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-blue-50 transition-colors">
                <x-icon name="o-megaphone" class="h-7 w-7 text-blue-500" />
                <span class="text-[11px] font-semibold text-blue-600 text-center leading-tight">Seminar</span>
            </a>

        </div>
        @endif

        @if($isResearch)
        {{-- Research sub-header --}}
        <div class="mb-2 flex items-center gap-2">
            <x-icon name="o-academic-cap" class="h-4 w-4 text-purple-700" />
            <span class="text-xs font-semibold text-purple-700">Research</span>
            <div class="h-px flex-1 bg-purple-100"></div>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-3">

            <a href="{{ route('specialization.research.new') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-green-50 transition-colors">
                <x-icon name="o-sparkles" class="h-7 w-7 text-green-500" />
                <span class="text-[11px] font-semibold text-green-600 text-center leading-tight">New &amp; Renew</span>
            </a>

            <a href="{{ route('specialization.research.review') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-blue-50 transition-colors">
                <x-icon name="o-eye" class="h-7 w-7 text-blue-500" />
                <span class="text-[11px] font-semibold text-blue-600 text-center leading-tight">Review</span>
            </a>

            <a href="{{ route('specialization.research.progress') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-indigo-50 transition-colors">
                <x-icon name="o-arrow-path" class="h-7 w-7 text-indigo-500" />
                <span class="text-[11px] font-semibold text-indigo-600 text-center leading-tight">In-Progress</span>
            </a>

            <a href="{{ route('specialization.research.rejected') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-red-50 transition-colors">
                <x-icon name="o-x-circle" class="h-7 w-7 text-red-400" />
                <span class="text-[11px] font-semibold text-red-500 text-center leading-tight">Rejected</span>
            </a>

        </div>
        @endif

        @if($isDefense || $isResearch)
        {{-- Login As sub-header --}}
        <div class="mb-2 flex items-center gap-2">
            <x-icon name="o-arrow-right-on-rectangle" class="h-4 w-4 text-gray-500" />
            <span class="text-xs font-semibold text-gray-600">Student</span>
            <div class="h-px flex-1 bg-gray-200"></div>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-3">
            <a href="{{ route('specialization.login-as') }}"
               class="flex flex-col items-center gap-2 rounded-xl bg-white p-4 shadow-sm hover:bg-gray-50 transition-colors">
                <x-icon name="o-user-circle" class="h-7 w-7 text-gray-500" />
                <span class="text-[11px] font-semibold text-gray-600 text-center leading-tight">Login As</span>
            </a>
        </div>
        @endif

    </div>
    @endif

    {{-- ─── ArSys Branding Footer ─── --}}
    <div class="py-8 text-center">
        <x-icon name="o-academic-cap" class="mx-auto h-14 w-14 text-purple-200" />
        <p class="mt-2 text-lg font-bold text-purple-600">ArSys</p>
        <p class="text-xs text-gray-400">Advanced Research Support System</p>
    </div>

</div>
