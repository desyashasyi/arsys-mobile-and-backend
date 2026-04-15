<?php

use App\Models\ArSys\Student;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Students')] class extends Component
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
        $student = Student::with('user')->findOrFail($this->loginTarget);

        if (!$student->user) {
            $this->error('No account found for this student.');
            return;
        }

        Auth::login($student->user);
        $this->redirect('/');
    }

    public function with(): array
    {
        return [
            'students' => Student::with(['user', 'program', 'specialization'])
                ->when($this->search, fn($q) => $q->where(function ($q) {
                    $q->where('first_name', 'like', "%{$this->search}%")
                      ->orWhere('last_name', 'like', "%{$this->search}%")
                      ->orWhere('number', 'like', "%{$this->search}%");
                }))
                ->orderByDesc('code')
                ->paginate(20),
        ];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">LIST OF REGISTERED STUDENTS</p>
    <div class="px-3 pb-2">
        <x-input
            placeholder="Search name or student ID..."
            wire:model.live.debounce="search"
            icon="o-magnifying-glass"
            clearable
        />
    </div>

    {{-- Student List --}}
    <div class="px-3 py-3 space-y-2 pb-24">
        @forelse($students as $student)
            <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $student->user ? 'border-purple-400' : 'border-gray-200' }}">
                <div class="p-3">

                    {{-- NIM + Program badge --}}
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-mono font-bold text-sm text-gray-800">{{ $student->nim }}</span>
                        @if($student->program)
                            <x-badge value="{{ $student->program->code }}" class="badge-ghost badge-xs" />
                        @endif
                    </div>

                    {{-- Full Name --}}
                    <p class="text-sm font-semibold text-gray-700">
                        {{ trim($student->first_name . ' ' . $student->last_name) }}
                    </p>

                    {{-- Specialization --}}
                    @if($student->specialization)
                        <p class="text-[11px] text-gray-400 mt-0.5">{{ $student->specialization->name ?? $student->specialization->code }}</p>
                    @endif

                    {{-- Account status + Login As --}}
                    <div class="mt-2 flex items-center justify-between">
                        @if($student->user)
                            <x-badge value="Active Account" class="badge-success badge-xs" />
                            <x-button
                                icon="o-arrow-right-end-on-rectangle"
                                class="btn-xs btn-ghost text-purple-500"
                                wire:click="confirmLogin({{ $student->id }}, '{{ addslashes(trim($student->first_name . ' ' . $student->last_name)) }}')"
                                spinner
                                tooltip="Login As"
                            />
                        @else
                            <x-badge value="No Account" class="badge-ghost badge-xs" />
                        @endif
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

    {{-- Pagination --}}
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
