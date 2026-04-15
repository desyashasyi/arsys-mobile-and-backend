<?php

use App\Models\ArSys\InstitutionRole;
use App\Models\ArSys\Staff;
use App\Models\ArSys\StaffType;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Staff')] class extends Component
{
    use WithPagination, Toast;

    public string $search = '';

    // Add sheet
    public bool   $addSheet    = false;
    public string $fCode       = '';
    public string $fUnivCode   = '';
    public string $fSso        = '';
    public string $fEmployeeId = '';
    public string $fFrontTitle = '';
    public string $fFirstName  = '';
    public string $fLastName   = '';
    public string $fRearTitle  = '';
    public string $fEmail      = '';
    public string $fPhone      = '';
    public string $fTypeId     = '';

    // Role sheet
    public bool   $roleSheet   = false;
    public ?int   $roleStaffId = null;

    private function program()
    {
        return InstitutionRole::where('user_id', auth()->id())->first()?->program;
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function openAddSheet(): void
    {
        $this->reset(['fCode','fUnivCode','fSso','fEmployeeId','fFrontTitle','fFirstName','fLastName','fRearTitle','fEmail','fPhone','fTypeId']);
        $this->resetValidation();
        $this->addSheet = true;
    }

    public function saveStaff(): void
    {
        $this->validate([
            'fFirstName'  => 'required|string',
            'fLastName'   => 'nullable|string',
            'fSso'        => 'required|string|max:50',
            'fEmployeeId' => 'required|string|max:20',
            'fTypeId'     => 'required|exists:arsys_staff_type,id',
        ]);

        $program = $this->program();
        if (!$program) {
            $this->error('Program not found.');
            return;
        }

        Staff::create([
            'code'          => strtoupper($this->fCode ?: $this->fSso),
            'univ_code'     => $this->fUnivCode,
            'sso'           => $this->fSso,
            'employee_id'   => $this->fEmployeeId,
            'front_title'   => $this->fFrontTitle ?: null,
            'first_name'    => $this->fFirstName,
            'last_name'     => $this->fLastName ?: null,
            'rear_title'    => $this->fRearTitle ?: null,
            'email'         => $this->fEmail ?: null,
            'phone'         => $this->fPhone ?: null,
            'program_id'    => $program->id,
            'staff_type_id' => $this->fTypeId,
        ]);

        $this->addSheet = false;
        $this->success('Staff added successfully.');
        $this->resetPage();
    }

    // ─── Role Management (staff role only) ───────────────────────────────────

    public function openRoleSheet(int $staffId): void
    {
        $this->roleStaffId = $staffId;
        $this->roleSheet   = true;
    }

    private function resolveUser(Staff $staff): User
    {
        if ($staff->user_id && $user = User::find($staff->user_id)) {
            return $user;
        }

        $user = User::firstOrCreate(
            ['sso' => $staff->sso],
            ['name' => $staff->code, 'sso' => $staff->sso]
        );

        $staff->update(['user_id' => $user->id]);

        return $user;
    }

    public function toggleStaffRole(): void
    {
        try {
            $staff = Staff::where('program_id', $this->program()?->id)->findOrFail($this->roleStaffId);
            $user  = $this->resolveUser($staff);

            if ($user->hasRole('staff')) {
                $user->removeRole('staff');
                $this->success('Role staff removed.');
            } else {
                $user->assignRole('staff');
                $this->success('Role staff assigned.');
            }
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
    }

    public function with(): array
    {
        $program   = $this->program();
        $roleStaff = $this->roleStaffId
            ? Staff::with('user')->where('program_id', $program?->id)->find($this->roleStaffId)
            : null;

        return [
            'program'      => $program,
            'staffs'       => Staff::with(['user', 'type'])
                ->where('program_id', $program?->id)
                ->when($this->search, fn($q) =>
                    $q->where('first_name', 'like', "%{$this->search}%")
                      ->orWhere('last_name',  'like', "%{$this->search}%")
                      ->orWhere('code',       'like', "%{$this->search}%")
                      ->orWhere('employee_id','like', "%{$this->search}%")
                )
                ->orderBy('code')
                ->paginate(20),
            'types'        => StaffType::orderBy('id')->get(),
            'roleStaff'    => $roleStaff,
            'hasStaffRole' => $roleStaff?->user?->hasRole('staff') ?? false,
        ];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">{{ $program?->code }} — {{ $program?->name }}</p>

    <div class="px-3 pb-2 flex gap-2">
        <div class="flex-1">
            <x-input placeholder="Search name, code, ID..."
                wire:model.live.debounce="search"
                icon="o-magnifying-glass" clearable />
        </div>
        <button wire:click="openAddSheet"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-600 text-white shadow hover:bg-purple-700 active:scale-95 transition-all">
            <x-icon name="o-plus" class="h-5 w-5" />
        </button>
    </div>

    {{-- Staff List --}}
    <div class="px-3 py-3 space-y-2 pb-24">
        @forelse($staffs as $staff)
            <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $staff->user?->hasRole('staff') ? 'border-purple-400' : 'border-gray-200' }} p-3">
                <div class="flex items-center justify-between mb-0.5">
                    <span class="font-mono font-bold text-sm text-gray-800">{{ $staff->code }}</span>
                    @if($staff->type)
                        <x-badge value="{{ $staff->type->code }}" class="badge-ghost badge-xs" />
                    @endif
                </div>
                <p class="text-sm text-gray-700">
                    {{ trim(($staff->front_title ? $staff->front_title.' ' : '') . $staff->first_name . ' ' . $staff->last_name . ($staff->rear_title ? ', '.$staff->rear_title : '')) }}
                </p>
                @if($staff->employee_id)
                    <p class="text-[11px] text-gray-400 mt-0.5">{{ $staff->employee_id }}</p>
                @endif

                <div class="mt-2 flex items-center justify-between">
                    <span class="text-[11px] {{ $staff->user?->hasRole('staff') ? 'text-purple-600 font-semibold' : 'text-gray-400' }}">
                        {{ $staff->user?->hasRole('staff') ? 'staff' : 'no role' }}
                    </span>
                    <x-button icon="o-key" class="btn-xs btn-ghost text-gray-500"
                        wire:click="openRoleSheet({{ $staff->id }})" spinner tooltip="Manage Role" />
                </div>
            </div>
        @empty
            <div class="py-16 text-center">
                <x-icon name="o-users" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No staff found.</p>
            </div>
        @endforelse
    </div>

    @if($staffs->hasPages())
        <div class="px-3 pb-4">{{ $staffs->links() }}</div>
    @endif

    {{-- ─── Add Staff Sheet ─── --}}
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
            <h3 class="font-semibold text-gray-800 text-sm">Add Staff</h3>
            <button @click="$wire.set('addSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="overflow-y-auto flex-1 px-4 py-3 space-y-3">
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Type <span class="text-red-400">*</span></label>
                <select wire:model="fTypeId" class="select select-bordered select-sm w-full text-sm">
                    <option value="">— select type —</option>
                    @foreach($types as $type)
                        <option value="{{ $type->id }}">{{ $type->code }} — {{ $type->description }}</option>
                    @endforeach
                </select>
                @error('fTypeId') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
            </div>
            <div class="flex gap-2">
                <div class="w-24">
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Front Title</label>
                    <input wire:model="fFrontTitle" type="text" placeholder="Dr."
                        class="input input-bordered input-sm w-full text-sm" />
                </div>
                <div class="flex-1">
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">First Name <span class="text-red-400">*</span></label>
                    <input wire:model="fFirstName" type="text" placeholder="First name"
                        class="input input-bordered input-sm w-full text-sm" />
                    @error('fFirstName') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Last Name</label>
                    <input wire:model="fLastName" type="text" placeholder="Last name"
                        class="input input-bordered input-sm w-full text-sm" />
                </div>
                <div class="w-24">
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Rear Title</label>
                    <input wire:model="fRearTitle" type="text" placeholder="M.T."
                        class="input input-bordered input-sm w-full text-sm" />
                </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Employee ID <span class="text-red-400">*</span></label>
                    <input wire:model="fEmployeeId" type="text" maxlength="20" placeholder="NIP / ID"
                        class="input input-bordered input-sm w-full text-sm" />
                    @error('fEmployeeId') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">SSO <span class="text-red-400">*</span></label>
                    <input wire:model="fSso" type="text" maxlength="50" placeholder="SSO"
                        class="input input-bordered input-sm w-full text-sm" />
                    @error('fSso') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Email</label>
                <input wire:model="fEmail" type="email" placeholder="email@example.com"
                    class="input input-bordered input-sm w-full text-sm" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Phone</label>
                <input wire:model="fPhone" type="text" maxlength="15" placeholder="08xxxxxxxxxx"
                    class="input input-bordered input-sm w-full text-sm" />
            </div>
        </div>
        <div class="px-4 pt-3 pb-24 border-t border-gray-100 flex gap-2 shrink-0">
            <button type="button" class="btn btn-ghost flex-1" @click="$wire.set('addSheet', false)">Cancel</button>
            <button type="button" class="btn btn-primary flex-1"
                wire:click="saveStaff" wire:loading.attr="disabled" wire:target="saveStaff">
                <span wire:loading.remove wire:target="saveStaff">Save</span>
                <span wire:loading wire:target="saveStaff">Saving...</span>
            </button>
        </div>
    </div>

    {{-- ─── Role Sheet ─── --}}
    <div x-data x-show="$wire.roleSheet"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/40 z-30"
         @click="$wire.set('roleSheet', false)"></div>
    <div x-data x-show="$wire.roleSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="translate-y-full opacity-0"
         class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-sm rounded-t-2xl bg-white shadow-2xl z-40">
        <div class="flex justify-center pt-3 pb-1"><div class="w-10 h-1 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <div>
                <h3 class="font-semibold text-gray-800 text-sm">Role: Staff</h3>
                @if($roleStaff)
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $roleStaff->code }} —
                        {{ trim(($roleStaff->front_title ? $roleStaff->front_title.' ' : '') . $roleStaff->first_name . ' ' . $roleStaff->last_name) }}
                    </p>
                @endif
            </div>
            <button @click="$wire.set('roleSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>

        <div class="px-4 py-5 pb-24 flex items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-800">staff</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $hasStaffRole ? 'Role aktif' : 'Role belum diberikan' }}</p>
            </div>
            <button wire:click="toggleStaffRole" wire:loading.attr="disabled"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 {{ $hasStaffRole ? 'bg-purple-600' : 'bg-gray-200' }} focus:outline-none">
                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 {{ $hasStaffRole ? 'translate-x-5' : 'translate-x-0' }}"></span>
            </button>
        </div>
    </div>
</div>
