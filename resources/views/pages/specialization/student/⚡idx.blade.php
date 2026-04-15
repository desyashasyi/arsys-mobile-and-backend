<?php

use App\Models\ArSys\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Login As Student')] class extends Component
{
    use WithPagination, Toast;

    public string $search      = '';
    public bool   $loginModal  = false;
    public ?int   $loginTarget = null;
    public string $loginName   = '';

    public function updatedSearch(): void { $this->resetPage(); }

    public function confirmLogin(int $studentId, string $name): void
    {
        $this->loginTarget = $studentId;
        $this->loginName   = $name;
        $this->loginModal  = true;
    }

    public function loginAs(): void
    {
        $student = Student::findOrFail($this->loginTarget);

        $user = User::where('sso', $student->number)->first()
            ?? ($student->email ? User::where('email', $student->email)->first() : null);

        if (!$user) {
            $user = User::create([
                'name'     => trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')),
                'email'    => $student->email ?? $student->number . '@arsys.example.com',
                'sso'      => $student->number,
                'password' => Hash::make(Str::random(16)),
            ]);
        }

        if ($user->sso !== $student->number) {
            $user->update(['sso' => $student->number]);
        }

        if ($student->user_id !== $user->id) {
            $student->update(['user_id' => $user->id]);
        }

        if (!$user->hasRole('student')) {
            $user->assignRole('student');
        }

        Auth::login($user);
        $this->redirect('/', navigate: true);
    }

    public function with(): array
    {
        $programId = Auth::user()->staff?->program_id;

        return [
            'students' => Student::with(['user', 'program', 'specialization'])
                ->when($programId, fn($q) => $q->where('program_id', $programId))
                ->when($this->search, fn($q) => $q->where(fn($q2) =>
                    $q2->where('number', 'like', "%{$this->search}%")
                       ->orWhere('first_name', 'like', "%{$this->search}%")
                       ->orWhere('last_name', 'like', "%{$this->search}%")
                ))
                ->orderByDesc('number')
                ->paginate(20),
        ];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">LOGIN AS STUDENT IN YOUR PROGRAM</p>
    <div class="px-3 pb-2">
        <x-input
            placeholder="Search NIM or name..."
            wire:model.live.debounce="search"
            icon="o-magnifying-glass"
            clearable
        />
    </div>

    <div class="px-3 py-3 space-y-2 pb-24">
        @forelse($students as $student)
            @php $name = trim($student->first_name . ' ' . $student->last_name); @endphp
            <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $student->user ? 'border-purple-400' : 'border-gray-200' }}">
                <div class="p-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-mono font-bold text-sm text-gray-800">{{ $student->nim }}</span>
                        @if($student->program)
                            <x-badge value="{{ $student->program->code }}" class="badge-ghost badge-xs" />
                        @endif
                    </div>

                    <p class="text-sm font-semibold text-gray-700">{{ $name ?: '—' }}</p>

                    @if($student->specialization)
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $student->specialization->name ?? $student->specialization->code }}</p>
                    @endif

                    <div class="mt-2 flex items-center justify-between">
                        @if($student->user)
                            <x-badge value="Active Account" class="badge-success badge-xs" />
                        @else
                            <x-badge value="No Account" class="badge-ghost badge-xs" />
                        @endif
                        <x-button
                            icon="o-arrow-right-end-on-rectangle"
                            class="btn-xs btn-ghost text-purple-500"
                            wire:click="confirmLogin({{ $student->id }}, '{{ addslashes($name) }}')"
                            spinner
                            tooltip="Login As"
                        />
                    </div>
                </div>
            </div>
        @empty
            <div class="py-16 text-center">
                <x-icon name="o-academic-cap" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No students found.</p>
            </div>
        @endforelse
    </div>

    @if($students->hasPages())
        <div class="px-3 pb-4">{{ $students->links() }}</div>
    @endif

    {{-- Login Confirmation Modal --}}
    <x-modal wire:model="loginModal" title="Login As Student" box-class="w-[calc(100vw-2rem)] max-w-sm" separator>
        <div class="flex items-center gap-3 py-1">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-100">
                <x-icon name="o-arrow-right-end-on-rectangle" class="h-5 w-5 text-purple-500" />
            </div>
            <p class="text-sm text-gray-600">Login sebagai <strong>{{ $loginName }}</strong>?</p>
        </div>
        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('loginModal', false)" />
            <x-button label="Login" class="btn-primary" wire:click="loginAs" spinner="loginAs" />
        </x-slot:actions>
    </x-modal>
</div>
