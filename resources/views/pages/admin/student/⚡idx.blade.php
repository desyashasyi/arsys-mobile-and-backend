<?php

use App\Models\ArSys\InstitutionRole;
use App\Models\ArSys\Specialization;
use App\Models\ArSys\Student;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Students')] class extends Component
{
    use WithPagination, Toast;

    public string $search = '';

    // Add sheet
    public bool   $addSheet          = false;
    public string $fNumber           = '';
    public string $fFirstName        = '';
    public string $fLastName         = '';
    public string $fEmail            = '';
    public string $fPhone            = '';
    public ?int   $fSpecializationId = null;

    // Edit sheet
    public bool   $editSheet         = false;
    public ?int   $editStudentId     = null;
    public string $eFirstName        = '';
    public string $eLastName         = '';
    public string $eEmail            = '';
    public string $ePhone            = '';
    public ?int   $eSpecializationId = null;

    // Login as
    public bool   $loginModal  = false;
    public ?int   $loginTarget = null;
    public string $loginName   = '';

    private function program()
    {
        return InstitutionRole::where('user_id', auth()->id())->first()?->program;
    }

    public function updatedSearch(): void { $this->resetPage(); }

    // ─── Add ─────────────────────────────────────────────────────────────────

    public function saveStudent(): void
    {
        $program = $this->program();
        $this->validate([
            'fNumber'    => 'required|string|max:10',
            'fFirstName' => 'required|string',
        ]);

        $student = Student::create([
            'number'            => $this->fNumber,
            'code'              => $program->code . '.' . $this->fNumber,
            'first_name'        => $this->fFirstName,
            'last_name'         => $this->fLastName,
            'email'             => $this->fEmail,
            'phone'             => $this->fPhone,
            'program_id'        => $program->id,
            'specialization_id' => $this->fSpecializationId,
        ]);

        $user = User::firstOrCreate(
            ['sso' => $student->code],
            [
                'name'     => $student->code,
                'sso'      => $student->code,
                'email'    => $this->fEmail ?: strtolower($student->code) . '@arsys.id',
                'password' => Hash::make($this->fNumber),
            ]
        );
        $user->assignRole('student');
        $student->update(['user_id' => $user->id]);

        $this->reset(['fNumber','fFirstName','fLastName','fEmail','fPhone','fSpecializationId']);
        $this->addSheet = false;
        $this->success("Student '{$student->code}' added.");
    }

    // ─── Edit ────────────────────────────────────────────────────────────────

    public function openEditSheet(int $id): void
    {
        $student = Student::where('program_id', $this->program()?->id)->findOrFail($id);
        $this->editStudentId     = $id;
        $this->eFirstName        = $student->first_name;
        $this->eLastName         = $student->last_name ?? '';
        $this->eEmail            = $student->email ?? '';
        $this->ePhone            = $student->phone ?? '';
        $this->eSpecializationId = $student->specialization_id;
        $this->editSheet         = true;
    }

    public function updateStudent(): void
    {
        $this->validate([
            'eFirstName' => 'required|string|max:100',
            'eLastName'  => 'nullable|string|max:100',
            'eEmail'     => 'nullable|email|max:100',
            'ePhone'     => 'nullable|string|max:20',
        ]);

        Student::where('program_id', $this->program()?->id)
            ->findOrFail($this->editStudentId)
            ->update([
                'first_name'        => $this->eFirstName,
                'last_name'         => $this->eLastName ?: null,
                'email'             => $this->eEmail ?: null,
                'phone'             => $this->ePhone ?: null,
                'specialization_id' => $this->eSpecializationId,
            ]);

        $this->editSheet = false;
        $this->success('Student updated.');
    }

    // ─── Login As ────────────────────────────────────────────────────────────

    public function confirmLogin(int $id, string $name): void
    {
        $this->loginTarget = $id;
        $this->loginName   = $name;
        $this->loginModal  = true;
    }

    public function loginAs(): void
    {
        $student = Student::with('user')->where('program_id', $this->program()?->id)->findOrFail($this->loginTarget);
        if (!$student->user) {
            $this->error('No account found.');
            return;
        }
        Auth::login($student->user);
        $this->redirect('/');
    }

    public function with(): array
    {
        $program = $this->program();
        return [
            'program'         => $program,
            'students'        => Student::with(['specialization'])
                ->where('program_id', $program?->id)
                ->when($this->search, fn($q) =>
                    $q->where('first_name', 'like', "%{$this->search}%")
                      ->orWhere('last_name',  'like', "%{$this->search}%")
                      ->orWhere('number',     'like', "%{$this->search}%")
                      ->orWhere('code',       'like', "%{$this->search}%")
                )
                ->orderByDesc('number')
                ->paginate(20),
            'specializations' => Specialization::where('program_id', $program?->id)->orderBy('code')->get(),
        ];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">{{ $program?->code }} — {{ $program?->name }}</p>
    <div class="px-3 pb-2 flex gap-2">
        <div class="flex-1">
            <x-input placeholder="Search name or student ID..."
                wire:model.live.debounce="search"
                icon="o-magnifying-glass" clearable />
        </div>
        <button wire:click="$set('addSheet', true)"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-600 text-white shadow hover:bg-purple-700 active:scale-95 transition-all">
            <x-icon name="o-plus" class="h-5 w-5" />
        </button>
    </div>

    {{-- Student List --}}
    <div class="px-3 py-3 space-y-2 pb-24">
        @forelse($students as $student)
            <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 border-indigo-300 p-3">
                <div class="flex items-center justify-between mb-0.5">
                    <span class="font-mono font-bold text-sm text-gray-800">{{ $student->code }}</span>
                    @if($student->specialization)
                        <x-badge value="{{ $student->specialization->code }}" class="badge-ghost badge-xs" />
                    @endif
                </div>
                <p class="text-sm text-gray-700">{{ trim($student->first_name . ' ' . $student->last_name) }}</p>
                <div class="mt-1.5 flex justify-between items-center">
                    <span class="text-[10px] text-gray-400">{{ $student->number }}</span>
                    <div class="flex items-center gap-1">
                        <x-button icon="o-pencil-square" class="btn-xs btn-ghost text-gray-400"
                            wire:click="openEditSheet({{ $student->id }})" spinner />
                        <x-button icon="o-arrow-right-end-on-rectangle" class="btn-xs btn-ghost text-purple-500"
                            wire:click="confirmLogin({{ $student->id }}, '{{ addslashes(trim($student->first_name . ' ' . $student->last_name)) }}')"
                            spinner />
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

    {{-- ─── Add Student Sheet ─── --}}
    <div x-data x-show="$wire.addSheet"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/40 z-30"
         @click="$wire.set('addSheet', false)"></div>
    <div x-data x-show="$wire.addSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0"
         class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-sm rounded-t-2xl bg-white shadow-2xl z-40 flex flex-col max-h-[90%]">
        <div class="flex justify-center pt-3 pb-1 shrink-0"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <h3 class="font-semibold text-gray-800 text-sm">Add Student</h3>
            <button @click="$wire.set('addSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="overflow-y-auto flex-1 px-4 py-3 space-y-3">
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Student Number <span class="text-red-400">*</span></label>
                <input wire:model="fNumber" type="text" maxlength="10" placeholder="e.g. 2021001"
                    class="input input-bordered input-sm w-full text-sm" />
                @error('fNumber') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">First Name <span class="text-red-400">*</span></label>
                    <input wire:model="fFirstName" type="text" class="input input-bordered input-sm w-full text-sm" />
                    @error('fFirstName') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Last Name</label>
                    <input wire:model="fLastName" type="text" class="input input-bordered input-sm w-full text-sm" />
                </div>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Email</label>
                <input wire:model="fEmail" type="email" class="input input-bordered input-sm w-full text-sm" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Phone</label>
                <input wire:model="fPhone" type="text" maxlength="15" class="input input-bordered input-sm w-full text-sm" />
            </div>
            @if($specializations->count())
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Specialization</label>
                <select wire:model="fSpecializationId" class="select select-bordered select-sm w-full text-sm">
                    <option value="">— Optional —</option>
                    @foreach($specializations as $spec)
                        <option value="{{ $spec->id }}">{{ $spec->code }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>
        <div class="px-4 pt-3 pb-24 border-t border-gray-100 flex gap-2 shrink-0">
            <button type="button" class="btn btn-ghost flex-1" @click="$wire.set('addSheet', false)">Cancel</button>
            <button type="button" class="btn btn-primary flex-1"
                wire:click="saveStudent" wire:loading.attr="disabled" wire:target="saveStudent">
                <span wire:loading.remove wire:target="saveStudent">Save</span>
                <span wire:loading wire:target="saveStudent">Saving...</span>
            </button>
        </div>
    </div>

    {{-- ─── Edit Student Sheet ─── --}}
    <div x-data x-show="$wire.editSheet"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/40 z-30"
         @click="$wire.set('editSheet', false)"></div>
    <div x-data x-show="$wire.editSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0"
         class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-sm rounded-t-2xl bg-white shadow-2xl z-40 flex flex-col max-h-[90%]">
        <div class="flex justify-center pt-3 pb-1 shrink-0"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 shrink-0">
            <h3 class="font-semibold text-gray-800 text-sm">Edit Student</h3>
            <button @click="$wire.set('editSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="overflow-y-auto flex-1 px-4 py-3 space-y-3">
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">First Name <span class="text-red-400">*</span></label>
                    <input wire:model="eFirstName" type="text" class="input input-bordered input-sm w-full text-sm" />
                    @error('eFirstName') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Last Name</label>
                    <input wire:model="eLastName" type="text" class="input input-bordered input-sm w-full text-sm" />
                </div>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Email</label>
                <input wire:model="eEmail" type="email" class="input input-bordered input-sm w-full text-sm" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Phone</label>
                <input wire:model="ePhone" type="text" maxlength="15" class="input input-bordered input-sm w-full text-sm" />
            </div>
            @if($specializations->count())
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Specialization</label>
                <select wire:model="eSpecializationId" class="select select-bordered select-sm w-full text-sm">
                    <option value="">— None —</option>
                    @foreach($specializations as $spec)
                        <option value="{{ $spec->id }}">{{ $spec->code }}</option>
                    @endforeach
                </select>
            </div>
            @endif
        </div>
        <div class="px-4 pt-3 pb-24 border-t border-gray-100 flex gap-2 shrink-0">
            <button type="button" class="btn btn-ghost flex-1" @click="$wire.set('editSheet', false)">Cancel</button>
            <button type="button" class="btn btn-primary flex-1"
                wire:click="updateStudent" wire:loading.attr="disabled" wire:target="updateStudent">
                <span wire:loading.remove wire:target="updateStudent">Update</span>
                <span wire:loading wire:target="updateStudent">Saving...</span>
            </button>
        </div>
    </div>

    {{-- ─── Login As Modal ─── --}}
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
