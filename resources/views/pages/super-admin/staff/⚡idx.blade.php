<?php

use App\Models\ArSys\Staff;
use App\Models\ArSys\StaffType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.mobile-app')] #[Title('Staff')] class extends Component
{
    use WithPagination, Toast;

    public string $search   = '';

    // Add staff sheet
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

    // Login modal
    public bool   $loginModal  = false;
    public ?int   $loginTarget = null;
    public string $loginName   = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    private function generateCode(string $firstName, string $lastName = ''): string
    {
        $words = array_values(array_filter(explode(' ', trim($firstName . ' ' . $lastName))));
        if (!$words) return '';

        // Build a 3-letter pool:
        // - 3+ words  : one initial per word (ESP from Eko Suryo Pratomo)
        // - 2 words   : 2 letters from first word + 1 from second (DIS from Didi Sukyadi)
        // - 1 word    : first 3 unique letters of the word
        $wordLetters = array_map(
            fn($w) => str_split(strtoupper(preg_replace('/[^a-zA-Z]/', '', $w))),
            $words
        );

        $n         = count($words);
        $wordsUsed = min($n, 3);
        $slots     = array_fill(0, $wordsUsed, 1);   // 1 letter per word
        $remaining = 3 - $wordsUsed;                 // extra letters to assign to earlier words
        for ($i = 0; $i < $remaining; $i++) {
            $slots[$i % $wordsUsed]++;
        }

        $pool = [];
        foreach ($wordLetters as $wi => $letters) {
            if (!isset($slots[$wi])) break;
            $taken = 0;
            foreach ($letters as $ch) {
                if ($taken >= $slots[$wi]) break;
                if (!in_array($ch, $pool)) { $pool[] = $ch; $taken++; }
            }
        }

        // Fallback: repeat first letter if name is extremely short
        while (count($pool) < 3) $pool[] = $pool[0];

        // Try all 6 permutations of the 3-letter pool, priority = natural order
        $perms = [
            [$pool[0], $pool[1], $pool[2]],
            [$pool[0], $pool[2], $pool[1]],
            [$pool[1], $pool[0], $pool[2]],
            [$pool[1], $pool[2], $pool[0]],
            [$pool[2], $pool[0], $pool[1]],
            [$pool[2], $pool[1], $pool[0]],
        ];

        foreach ($perms as $p) {
            $code = implode('', $p);
            if (!Staff::where('code', $code)->exists()) return $code;
        }

        return implode('', $pool); // absolute fallback
    }

    public function updatedFFirstName(): void
    {
        $this->fCode = $this->generateCode($this->fFirstName, $this->fLastName);
    }

    public function updatedFLastName(): void
    {
        $this->fCode = $this->generateCode($this->fFirstName, $this->fLastName);
    }

    public function openAddSheet(): void
    {
        $this->reset(['fCode','fUnivCode','fSso','fEmployeeId','fFrontTitle','fFirstName','fLastName','fRearTitle','fEmail','fPhone','fTypeId']);
        $this->resetValidation();
        $this->addSheet = true;
    }

    public function saveStaff(): void
    {
        $this->validate([
            'fCode'      => 'required|string|max:3|unique:arsys_staff,code',
            'fUnivCode'  => 'required|string|max:4',
            'fFirstName' => 'required|string|max:50',
            'fTypeId'    => 'required|exists:arsys_staff_type,id',
            'fSso'       => 'nullable|string|max:20',
            'fEmployeeId'=> 'nullable|string|max:20',
        ]);

        Staff::create([
            'code'          => strtoupper(trim($this->fCode)),
            'univ_code'     => strtoupper(trim($this->fUnivCode)),
            'sso'           => $this->fSso ?: null,
            'employee_id'   => $this->fEmployeeId ?: null,
            'front_title'   => $this->fFrontTitle ?: null,
            'first_name'    => $this->fFirstName,
            'last_name'     => $this->fLastName ?: null,
            'rear_title'    => $this->fRearTitle ?: null,
            'email'         => $this->fEmail ?: null,
            'phone'         => $this->fPhone ?: null,
            'staff_type_id' => $this->fTypeId,
        ]);

        $this->addSheet = false;
        $this->success('Staff added successfully.');
        $this->resetPage();
    }

    public function openRoleSheet(int $staffId): void
    {
        $this->roleStaffId = $staffId;
        $this->roleSheet   = true;
    }

    private function resolveUser(Staff $staff): User
    {
        // Use existing linked user if available
        if ($staff->user_id && $user = User::find($staff->user_id)) {
            return $user;
        }

        // Otherwise find/create by SSO and link
        $user = User::firstOrCreate(
            ['sso' => $staff->sso],
            ['name' => $staff->code, 'sso' => $staff->sso]
        );

        $staff->update(['user_id' => $user->id]);

        return $user;
    }

    public function addRole(string $role = ''): void
    {
        if (!$role) {
            $this->error('Select a role first.');
            return;
        }

        try {
            $staff = Staff::findOrFail($this->roleStaffId);
            $user  = $this->resolveUser($staff);
            $user->assignRole($role);
            $this->success("Role '{$role}' added.");
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
    }

    public function removeRole(string $role): void
    {
        try {
            $staff = Staff::findOrFail($this->roleStaffId);
            $user  = User::where('sso', $staff->sso)->firstOrFail();
            $user->removeRole($role);
            $this->resetPage();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
    }

    public function confirmLogin(int $staffId, string $name): void
    {
        $this->loginTarget = $staffId;
        $this->loginName   = $name;
        $this->loginModal  = true;
    }

    public function loginAs(): void
    {
        $staff = Staff::findOrFail($this->loginTarget);
        $user  = $staff->user ?? User::where('sso', $staff->sso)->first();

        if (!$user) {
            $this->error("No account found for this staff. Assign a role first.");
            return;
        }

        Auth::login($user);
        $this->redirect('/');
    }

    public function with(): array
    {
        $roleStaff = $this->roleStaffId ? Staff::with('user')->find($this->roleStaffId) : null;

        $currentRoles = $roleStaff?->user?->getRoleNames()->toArray() ?? [];

        $availableRoles = Role::orderBy('name')->pluck('name')
            ->reject(fn($r) => in_array($r, $currentRoles))
            ->values();

        return [
            'staffTypes' => StaffType::orderBy('id')->get(),
            'staffs' => Staff::with(['user', 'role.base', 'type'])
                ->when($this->search, fn($q) =>
                    $q->where(fn($q) =>
                        $q->where('first_name', 'like', "%{$this->search}%")
                          ->orWhere('last_name',  'like', "%{$this->search}%")
                          ->orWhere('code',       'like', "%{$this->search}%")
                          ->orWhere('employee_id','like', "%{$this->search}%")
                          ->orWhere('sso',        'like', "%{$this->search}%")
                    )
                )
                ->whereHas('type', fn($q) => $q->whereIn('code', ['PNS', 'PTU', 'DLB']))
                ->orderBy('code')
                ->paginate(20),
            'roleStaff'      => $roleStaff,
            'currentRoles'   => $currentRoles,
            'availableRoles' => $availableRoles,
        ];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">MANAGE STAFF ACCOUNTS AND ROLES</p>
    <div class="px-3 pb-2 flex gap-2">
        <div class="flex-1">
            <x-input
                placeholder="Search name, code, employee ID..."
                wire:model.live.debounce="search"
                icon="o-magnifying-glass"
                clearable
            />
        </div>
        <button wire:click="openAddSheet"
            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-600 text-white shadow hover:bg-purple-700 active:scale-95 transition-all">
            <x-icon name="o-plus" class="h-5 w-5" />
        </button>
    </div>

    {{-- Staff List --}}
    <div class="px-3 py-3 space-y-2 pb-24">
        @forelse($staffs as $staff)
            <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 {{ $staff->user ? 'border-purple-400' : 'border-gray-200' }}">
                <div class="p-3">
                    <div class="flex items-center justify-between mb-1">
                        <span class="font-mono font-bold text-sm text-gray-800">{{ $staff->code }}</span>
                        <x-badge value="{{ $staff->type?->code ?? '-' }}" class="badge-ghost badge-xs" />
                    </div>

                    <p class="text-sm font-semibold text-gray-700 leading-snug">
                        {{ trim(($staff->front_title ? $staff->front_title.' ' : '') . $staff->first_name . ' ' . $staff->last_name) }}
                    </p>

                    <p class="font-mono text-[11px] text-gray-400 mt-0.5">{{ $staff->sso ?? $staff->employee_id }}</p>

                    <div class="mt-2 flex items-center justify-between">
                        <div class="flex flex-wrap gap-1">
                            @if($staff->user)
                                @forelse($staff->user->getRoleNames() as $role)
                                    <x-badge value="{{ $role }}" class="badge-primary badge-xs" />
                                @empty
                                    <span class="text-[11px] text-gray-400">No role</span>
                                @endforelse
                            @else
                                <span class="text-[11px] text-gray-400">No account</span>
                            @endif
                        </div>

                        <div class="flex items-center gap-1 shrink-0">
                            <x-button
                                icon="o-key"
                                class="btn-xs btn-ghost text-gray-500"
                                wire:click="openRoleSheet({{ $staff->id }})"
                                spinner
                                tooltip="Manage Roles"
                            />
                            @if($staff->user)
                            <x-button
                                icon="o-arrow-right-end-on-rectangle"
                                class="btn-xs btn-ghost text-purple-500"
                                wire:click="confirmLogin({{ $staff->id }}, '{{ addslashes($staff->code) }}')"
                                spinner
                                tooltip="Login As"
                            />
                            @endif
                        </div>
                    </div>
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

    {{-- Add Staff Backdrop --}}
    <div
        x-show="$wire.addSheet"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/40 z-30"
        @click="$wire.set('addSheet', false)"
    ></div>

    {{-- Add Staff Sheet --}}
    <div
        x-show="$wire.addSheet"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-full opacity-0"
        class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-sm rounded-t-2xl bg-white shadow-2xl z-40"
    >
        <div class="flex justify-center pt-3 pb-1">
            <div class="w-10 h-1 rounded-full bg-gray-300"></div>
        </div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800 text-sm">Add Staff</h3>
            <button @click="$wire.set('addSheet', false)" class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>

        <div class="px-4 py-3 space-y-3 max-h-[60vh] overflow-y-auto pb-20">

            {{-- Type --}}
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Type <span class="text-red-400">*</span></label>
                <select wire:model="fTypeId" class="select select-bordered select-sm w-full text-sm">
                    <option value="">— select type —</option>
                    @foreach($staffTypes as $type)
                        <option value="{{ $type->id }}">{{ $type->code }} — {{ $type->description }}</option>
                    @endforeach
                </select>
                @error('fTypeId') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
            </div>

            {{-- Front Title + First Name --}}
            <div class="flex gap-2">
                <div class="w-28">
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Front Title</label>
                    <input wire:model="fFrontTitle" type="text" placeholder="Dr."
                        class="input input-bordered input-sm w-full text-sm" />
                </div>
                <div class="flex-1">
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">First Name <span class="text-red-400">*</span></label>
                    <input wire:model.live.debounce.600ms="fFirstName" type="text" placeholder="First name"
                        class="input input-bordered input-sm w-full text-sm" />
                    @error('fFirstName') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Last Name + Rear Title --}}
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Last Name</label>
                    <input wire:model.live.debounce.600ms="fLastName" type="text" placeholder="Last name"
                        class="input input-bordered input-sm w-full text-sm" />
                </div>
                <div class="w-28">
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Rear Title</label>
                    <input wire:model="fRearTitle" type="text" placeholder="M.T."
                        class="input input-bordered input-sm w-full text-sm" />
                </div>
            </div>

            {{-- Code + Univ Code --}}
            <div class="flex gap-2">
                <div class="flex-1">
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">
                        Code <span class="text-red-400">*</span>
                        <span class="text-gray-400 font-normal">(auto)</span>
                    </label>
                    <input wire:model="fCode" type="text" maxlength="3" placeholder="e.g. DIS"
                        class="input input-bordered input-sm w-full text-sm uppercase" />
                    @error('fCode') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
                <div class="w-24">
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Univ Code <span class="text-red-400">*</span></label>
                    <input wire:model="fUnivCode" type="text" maxlength="4" placeholder="e.g. A123"
                        class="input input-bordered input-sm w-full text-sm uppercase" />
                    @error('fUnivCode') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- SSO --}}
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">SSO / NIP</label>
                <input wire:model="fSso" type="text" maxlength="20" placeholder="SSO or NIP"
                    class="input input-bordered input-sm w-full text-sm" />
            </div>

            {{-- Employee ID --}}
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Employee ID</label>
                <input wire:model="fEmployeeId" type="text" maxlength="20" placeholder="Employee ID"
                    class="input input-bordered input-sm w-full text-sm" />
            </div>

            {{-- Email --}}
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Email</label>
                <input wire:model="fEmail" type="email" placeholder="email@example.com"
                    class="input input-bordered input-sm w-full text-sm" />
            </div>

            {{-- Phone --}}
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1 block">Phone</label>
                <input wire:model="fPhone" type="text" maxlength="12" placeholder="08xxxxxxxxxx"
                    class="input input-bordered input-sm w-full text-sm" />
            </div>

        </div>

        <div class="px-4 pt-3 pb-24 border-t border-gray-100 flex gap-2">
            <button type="button" class="btn btn-ghost flex-1"
                @click="$wire.set('addSheet', false)">Cancel</button>
            <button type="button" class="btn btn-primary flex-1"
                wire:click="saveStaff"
                wire:loading.attr="disabled" wire:target="saveStaff">
                <span wire:loading.remove wire:target="saveStaff">Save</span>
                <span wire:loading wire:target="saveStaff">Saving...</span>
            </button>
        </div>
    </div>

    {{-- Backdrop --}}
    <div
        x-show="$wire.roleSheet"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/40 z-30"
        @click="$wire.set('roleSheet', false)"
    ></div>

    {{-- Role Sheet --}}
    <div
        x-show="$wire.roleSheet"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-y-full opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-y-0 opacity-100"
        x-transition:leave-end="translate-y-full opacity-0"
        class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-sm rounded-t-2xl bg-white shadow-2xl z-40"
    >
        {{-- Handle --}}
        <div class="flex justify-center pt-3 pb-1">
            <div class="w-10 h-1 rounded-full bg-gray-300"></div>
        </div>

        {{-- Header --}}
        <div class="px-4 pt-2 pb-3 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800 text-sm">Manage Roles</h3>
            @if($roleStaff)
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $roleStaff->code }} —
                    {{ trim(($roleStaff->front_title ? $roleStaff->front_title.' ' : '') . $roleStaff->first_name . ' ' . $roleStaff->last_name) }}
                </p>
            @endif
        </div>

        {{-- Current Roles --}}
        <div class="px-4 pt-3 pb-2">
            <p class="text-xs text-gray-400 mb-2">Current roles</p>
            @if(count($currentRoles))
                <div class="flex flex-wrap gap-2">
                    @foreach($currentRoles as $role)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                            {{ $role }}
                            <button
                                wire:click="removeRole('{{ $role }}')"
                                wire:loading.attr="disabled"
                                type="button"
                                class="ml-0.5 hover:text-red-500 transition-colors"
                            >&times;</button>
                        </span>
                    @endforeach
                </div>
            @else
                <p class="text-xs text-gray-400 italic">No roles assigned</p>
            @endif
        </div>

        {{-- Add Role --}}
        <div class="px-4 pt-3 pb-24 border-t border-gray-100" x-data="{ newRole: '' }">
            <p class="text-xs text-gray-400 mb-2">Add role</p>
            <div class="flex items-center gap-2">
                <select
                    x-model="newRole"
                    class="select select-bordered select-sm flex-1 text-sm"
                >
                    <option value="">— select role —</option>
                    @foreach($availableRoles as $role)
                        <option value="{{ $role }}">{{ $role }}</option>
                    @endforeach
                </select>
                <button
                    type="button"
                    @click="$wire.addRole(newRole); newRole = ''"
                    class="btn btn-primary btn-sm btn-circle"
                    title="Add role"
                >
                    <x-icon name="o-plus" class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>

    {{-- Login Confirmation Modal --}}
    <x-modal wire:model="loginModal" title="Login As Staff" box-class="w-[calc(100vw-2rem)] max-w-sm" separator>
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
