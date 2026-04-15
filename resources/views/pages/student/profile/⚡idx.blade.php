<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Profile')] class extends Component
{
    use Toast;

    public string $name  = '';
    public string $email = '';
    public bool   $isEditing = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->name  = $user->name ?? '';
        $this->email = $user->email ?? '';
    }

    public function save(): void
    {
        $this->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . auth()->id(),
        ]);

        auth()->user()->update([
            'name'  => $this->name,
            'email' => $this->email,
        ]);

        $this->isEditing = false;
        $this->success('Profile updated.', position: 'toast-bottom');
    }

    public function with(): array
    {
        $user    = auth()->user();
        $student = $user->student;

        return compact('user', 'student');
    }
};
?>

<div class="pb-6">

    {{-- ─── Avatar Card ─── --}}
    <div class="mx-4 mt-4 rounded-2xl bg-gradient-to-br from-purple-600 to-purple-400 p-5 shadow-md">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-white/20 text-2xl font-bold text-white">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-white font-bold text-base leading-tight truncate">{{ $user->name }}</p>
                <p class="text-white/70 text-xs mt-0.5 truncate">{{ $user->email }}</p>
                <span class="mt-1.5 inline-block rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-semibold text-white">
                    student
                </span>
            </div>
        </div>
    </div>

    {{-- ─── Student Info ─── --}}
    @if($student)
    <div class="mx-4 mt-4">
        <p class="mb-2 text-xs font-bold uppercase tracking-wider text-gray-400">Student Information</p>
        <div class="rounded-2xl bg-white shadow-sm overflow-hidden divide-y divide-gray-50">
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-gray-400 w-28 shrink-0">NIM</span>
                <span class="text-sm font-semibold text-gray-700">{{ $student->number ?? '-' }}</span>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-gray-400 w-28 shrink-0">Full Name</span>
                <span class="text-sm font-semibold text-gray-700 text-right">
                    {{ trim(($student->first_name ?? '') . ' ' . ($student->last_name ?? '')) ?: '-' }}
                </span>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-gray-400 w-28 shrink-0">Program</span>
                <span class="text-sm font-semibold text-gray-700 text-right">{{ $student->program?->name ?? '-' }}</span>
            </div>
            @if($student->specialization)
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-gray-400 w-28 shrink-0">Specialization</span>
                <span class="text-sm font-semibold text-gray-700 text-right">{{ $student->specialization->name }}</span>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- ─── Account Settings ─── --}}
    <div class="mx-4 mt-4">
        <div class="flex items-center justify-between mb-2">
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Account</p>
            @if(!$isEditing)
                <button wire:click="$set('isEditing', true)"
                        class="flex items-center gap-1 text-xs font-semibold text-purple-600 hover:text-purple-700">
                    <x-icon name="o-pencil" class="h-3.5 w-3.5" /> Edit
                </button>
            @else
                <button wire:click="$set('isEditing', false)"
                        class="text-xs font-semibold text-gray-400 hover:text-gray-600">
                    Cancel
                </button>
            @endif
        </div>

        @if(!$isEditing)
        <div class="rounded-2xl bg-white shadow-sm overflow-hidden divide-y divide-gray-50">
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-gray-400 w-28 shrink-0">Display Name</span>
                <span class="text-sm font-semibold text-gray-700">{{ $user->name }}</span>
            </div>
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-gray-400 w-28 shrink-0">Email</span>
                <span class="text-sm font-semibold text-gray-700 text-right truncate max-w-[180px]">{{ $user->email }}</span>
            </div>
            @if($user->sso)
            <div class="flex items-center justify-between px-4 py-3">
                <span class="text-xs text-gray-400 w-28 shrink-0">SSO</span>
                <span class="text-sm text-gray-600">{{ $user->sso }}</span>
            </div>
            @endif
        </div>
        @else
        <div class="rounded-2xl bg-white shadow-sm p-4 space-y-4">
            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-500">Display Name</label>
                <input wire:model="name" type="text"
                       class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-400" />
                @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-gray-500">Email</label>
                <input wire:model="email" type="email"
                       class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-400" />
                @error('email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
            </div>
            <button wire:click="save" wire:loading.attr="disabled"
                    class="w-full rounded-full bg-purple-600 py-3 text-sm font-semibold text-white shadow-sm hover:bg-purple-700 transition-colors disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save Changes</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
        @endif
    </div>

    {{-- ─── Logout ─── --}}
    <div class="mx-4 mt-4">
        <a href="{{ route('logout') }}"
           class="flex items-center justify-center gap-2 rounded-full border border-red-200 bg-white py-3 text-sm font-semibold text-red-500 hover:bg-red-50 transition-colors shadow-sm">
            <x-icon name="o-power" class="h-4 w-4" />
            Logout
        </a>
    </div>

</div>
