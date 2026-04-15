<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

    @livewireStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-300 font-sans antialiased">

    @php
        $routeName  = request()->route()?->getName();
        $path       = request()->path();
        $pageTitle  = match($routeName) {
            'home'                        => 'Home',
            'staff.supervise'             => 'Supervised Research',
            'staff.supervise.detail'      => 'Research Detail',
            'staff.review'                => 'Review',
            'staff.review.detail'         => 'Review Detail',
            'staff.pre-defense'           => 'Pre-Defense',
            'staff.pre-defense.event'     => 'Pre-Defense Participants',
            'staff.pre-defense.applicant' => 'Participant Detail',
            'staff.final-defense'         => 'Final Defense',
            'staff.final-defense.event'   => 'Final Defense Rooms',
            'super-admin.staff'            => 'Staff Management',
            'super-admin.student'          => 'Student Management',
            'super-admin.config.program'   => 'Study Programs',
            'super-admin.clients'          => 'Clients',
            'admin.staff'                  => 'Staff',
            'admin.student'                => 'Students',
            'admin.config'                 => 'Configuration',
            'program.pre-defense'              => 'Pre-Defense Scores',
            'program.pre-defense.event'        => 'Pre-Defense Detail',
            'program.pre-defense.participant'  => 'Participant Detail',
            'program.final-defense'            => 'Final Defense Scores',
            'program.final-defense.event'      => 'Final Defense Rooms',
            'program.final-defense.room'       => 'Room Detail',
            'program.final-defense.participant'=> 'Participant Detail',
            'program.approval'             => 'Defense Approval',
            'program.approval.detail'      => 'Approval Detail',
            'student.home'             => 'Home',
            'student.research'         => 'My Research',
            'student.research.create'  => 'New Research',
            'student.research.show'    => 'Research Detail',
            'student.research.edit'    => 'Edit Research',
            'student.research.remark'  => 'Remarks',
            'student.profile'          => 'Profile',
            'student.events'           => 'Events',
            'specialization.login-as'                     => 'Login As Student',
            'specialization.research.new'                 => 'New & Renew Proposals',
            'specialization.research.review'              => 'Being Reviewed',
            'specialization.research.progress'            => 'In Progress',
            'specialization.research.rejected'            => 'Rejected',
            'specialization.research.show'                => 'Research Detail',
            'specialization.defense.events'               => 'List of Events',
            'specialization.defense.pre-defense'          => 'Pre-Defense Events',
            'specialization.defense.pre-defense.event'    => 'Pre-Defense Participants',
            'specialization.defense.pre-defense.participant' => 'Participant Detail',
            'specialization.defense.seminar'              => 'Seminar Events',
            'specialization.defense.final-defense'        => 'Final Defense Events',
            'specialization.defense.final-defense.rooms'  => 'Final Defense Rooms',
            'specialization.defense.final-defense.room'   => 'Room Detail',
            default                        => config('app.name'),
        };
    @endphp

    <div class="relative mx-auto min-h-screen w-full max-w-sm bg-gray-100 shadow-2xl"
         x-data="{ path: window.location.pathname }">

        {{-- Top App Bar --}}
        @php
        $hasBack = in_array($routeName, [
                // Student
                'student.research.show', 'student.research.create', 'student.research.edit', 'student.research.remark', 'student.profile',
                // Staff
                'staff.supervise.detail', 'staff.review.detail',
                'staff.pre-defense.event', 'staff.pre-defense.applicant',
                'staff.final-defense.event',
                // Program (kaprodi) — list pages + detail pages
                'program.pre-defense', 'program.pre-defense.event', 'program.pre-defense.participant',
                'program.final-defense', 'program.final-defense.event', 'program.final-defense.room', 'program.final-defense.participant',
                'program.approval', 'program.approval.detail',
                // Specialization research
                'specialization.research.show',
                'specialization.research.new', 'specialization.research.review',
                'specialization.research.progress', 'specialization.research.rejected',
                // Specialization defense
                'specialization.defense.events',
                'specialization.defense.pre-defense', 'specialization.defense.pre-defense.event', 'specialization.defense.pre-defense.participant',
                'specialization.defense.final-defense', 'specialization.defense.final-defense.rooms', 'specialization.defense.final-defense.room',
                'specialization.defense.seminar',
                // Super admin
                'super-admin.staff', 'super-admin.student', 'super-admin.config.program', 'super-admin.clients',
                // Admin
                'admin.staff', 'admin.student', 'admin.config',
                // Specialization student
                'specialization.login-as',
            ]);
        @endphp
        <header class="sticky top-0 z-40 bg-purple-600 shadow-md">
            <div class="flex items-center gap-2 px-4 py-3">
                @if($hasBack)
                    <button onclick="history.back()"
                            class="mr-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/20 text-white hover:bg-white/30 transition-colors">
                        <x-icon name="o-chevron-left" class="h-4 w-4" />
                    </button>
                @endif
                <h1 class="flex-1 text-white font-semibold text-base truncate">{{ $pageTitle }}</h1>

                @auth
                <div class="dropdown dropdown-end">
                    <label tabindex="0"
                           class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-white/20 text-sm font-bold text-white">
                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                    </label>
                    <ul tabindex="0"
                        class="dropdown-content menu z-50 w-40 rounded-xl bg-white p-1 shadow-lg text-sm mt-1">
                        <li class="menu-title px-2 py-1">
                            <span class="text-xs text-gray-500 truncate block">{{ auth()->user()->name }}</span>
                        </li>
                        <li>
                            <a href="{{ route('logout') }}" class="text-red-500 font-medium">
                                <x-icon name="o-power" class="h-4 w-4" />
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
                @endauth
            </div>
        </header>

        {{-- Page Content --}}
        <main class="pb-20">
            {{ $slot }}
        </main>

        {{-- ─── Bottom Navigation ─── --}}

        @auth
        @php
            $navUser = auth()->user();
            $isAdmin = $navUser->hasRole('admin') && !$navUser->hasRole('super_admin');
        @endphp

        @if($isAdmin)
        {{-- ADMIN: 4 tabs --}}
        <nav class="fixed bottom-0 left-1/2 z-50 w-full max-w-sm -translate-x-1/2 bg-purple-600 shadow-[0_-2px_10px_rgba(0,0,0,0.2)]">
            <div class="flex h-16 items-center">
                <a href="{{ route('home') }}"
                   class="flex flex-1 flex-col items-center justify-center gap-0.5 h-full transition-all"
                   :class="path === '/home' ? 'text-white' : 'text-white/50 hover:text-white/80'">
                    <x-icon name="o-home" class="h-5 w-5" />
                    <span class="text-[9px] font-medium">Home</span>
                </a>
                <a href="{{ route('admin.staff') }}"
                   class="flex flex-1 flex-col items-center justify-center gap-0.5 h-full transition-all"
                   :class="path.startsWith('/admin/staff') ? 'text-white' : 'text-white/50 hover:text-white/80'">
                    <x-icon name="o-user-group" class="h-5 w-5" />
                    <span class="text-[9px] font-medium">Staff</span>
                </a>
                <a href="{{ route('admin.student') }}"
                   class="flex flex-1 flex-col items-center justify-center gap-0.5 h-full transition-all"
                   :class="path.startsWith('/admin/student') ? 'text-white' : 'text-white/50 hover:text-white/80'">
                    <x-icon name="o-academic-cap" class="h-5 w-5" />
                    <span class="text-[9px] font-medium">Student</span>
                </a>
                <a href="{{ route('admin.config') }}"
                   class="flex flex-1 flex-col items-center justify-center gap-0.5 h-full transition-all"
                   :class="path.startsWith('/admin/config') ? 'text-white' : 'text-white/50 hover:text-white/80'">
                    <x-icon name="o-cog-6-tooth" class="h-5 w-5" />
                    <span class="text-[9px] font-medium">Config</span>
                </a>
            </div>
        </nav>

        @elseif($navUser->hasRole('student'))
        {{-- STUDENT: 3 tabs (Research | Home | Events) matching Flutter layout --}}
        <nav class="fixed bottom-0 left-1/2 z-50 w-full max-w-sm -translate-x-1/2 bg-white shadow-[0_-4px_16px_rgba(0,0,0,0.10)]">
            <div class="grid grid-cols-3 h-16 relative">

                {{-- Research --}}
                <a href="{{ route('student.research') }}"
                   class="flex flex-col items-center justify-center gap-0.5 transition-colors"
                   :class="path.startsWith('/student/research') ? 'text-purple-600' : 'text-gray-400 hover:text-gray-600'">
                    <x-icon name="o-academic-cap" class="h-5 w-5" />
                    <span class="text-[10px] font-medium">Research</span>
                </a>

                {{-- Home (center, elevated) --}}
                <a href="{{ route('student.home') }}"
                   class="flex flex-col items-center justify-center relative"
                   :class="path === '/student/home' ? 'text-white' : 'text-white hover:opacity-90'">
                    <span class="absolute -top-5 flex h-14 w-14 flex-col items-center justify-center rounded-full shadow-lg transition-all"
                          :class="path === '/student/home' ? 'bg-purple-700' : 'bg-purple-500'">
                        <x-icon name="o-home" class="h-6 w-6 text-white" />
                        <span class="text-[9px] font-bold text-white">Home</span>
                    </span>
                </a>

                {{-- Events --}}
                <a href="{{ route('student.events') }}"
                   class="flex flex-col items-center justify-center gap-0.5 transition-colors"
                   :class="path.startsWith('/student/events') ? 'text-purple-600' : 'text-gray-400 hover:text-gray-600'">
                    <x-icon name="o-calendar-days" class="h-5 w-5" />
                    <span class="text-[10px] font-medium">Events</span>
                </a>

            </div>
        </nav>

        @else
        {{-- STAFF / SUPER ADMIN / ALL OTHER ROLES (incl. specialization): 5 tabs, purple bg --}}
        <nav class="fixed bottom-0 left-1/2 z-50 w-full max-w-sm -translate-x-1/2 bg-purple-600 shadow-[0_-2px_10px_rgba(0,0,0,0.2)]">
            <div class="grid grid-cols-5 h-16 items-center">

                {{-- Supervise --}}
                <a href="{{ route('staff.supervise') }}"
                   class="flex flex-col items-center justify-center gap-0.5 h-full transition-all"
                   :class="path.startsWith('/staff/supervise') ? 'text-white' : 'text-white/50 hover:text-white/80'">
                    <x-icon name="o-user-group" class="h-5 w-5" />
                    <span class="text-[9px] font-medium">Supervise</span>
                </a>

                {{-- Review --}}
                <a href="{{ route('staff.review') }}"
                   class="flex flex-col items-center justify-center gap-0.5 h-full transition-all"
                   :class="path.startsWith('/staff/review') ? 'text-white' : 'text-white/50 hover:text-white/80'">
                    <x-icon name="o-clipboard-document-list" class="h-5 w-5" />
                    <span class="text-[9px] font-medium">Review</span>
                </a>

                {{-- Home (CENTER — convex raised) --}}
                <a href="{{ route('home') }}"
                   class="flex flex-col items-center justify-center h-full relative"
                   :class="path === '/home' ? 'text-purple-600' : 'text-white/50 hover:text-white/80'">
                    <span :class="path === '/home'
                              ? 'flex flex-col items-center justify-center gap-0.5 -mt-5 h-14 w-14 rounded-full bg-white shadow-lg text-purple-600'
                              : 'flex flex-col items-center justify-center gap-0.5 text-white/50'"
                          class="transition-all duration-200">
                        <x-icon name="o-home" class="h-6 w-6" />
                        <span class="text-[9px] font-bold">Home</span>
                    </span>
                </a>

                {{-- Pre-Defense --}}
                <a href="{{ route('staff.pre-defense') }}"
                   class="flex flex-col items-center justify-center gap-0.5 h-full transition-all"
                   :class="path.startsWith('/staff/pre-defense') ? 'text-white' : 'text-white/50 hover:text-white/80'">
                    <x-icon name="o-scale" class="h-5 w-5" />
                    <span class="text-[9px] font-medium">Pre</span>
                </a>

                {{-- Final Defense --}}
                <a href="{{ route('staff.final-defense') }}"
                   class="flex flex-col items-center justify-center gap-0.5 h-full transition-all"
                   :class="path.startsWith('/staff/final-defense') ? 'text-white' : 'text-white/50 hover:text-white/80'">
                    <x-icon name="o-trophy" class="h-5 w-5" />
                    <span class="text-[9px] font-medium">Final</span>
                </a>

            </div>
        </nav>
        @endif
        @endauth

    </div>

    <x-toast />
    @livewireScripts
</body>
</html>
