<?php

use App\Imports\StaffImport;
use App\Models\ArSys\Program;
use App\Models\ArSys\Staff;
use App\Models\ArSys\StaffType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Mary\Traits\Toast;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] #[Title('Staff Management')] class extends Component
{
    use WithPagination, Toast, WithFileUploads;

    // ── Search & pagination ───────────────────────────────────────────────────
    public string $search       = '';
    public int    $perPage      = 20;
    public bool   $showInactive = false;
    public ?int   $filterProgram = null;

    // ── Add Staff drawer ──────────────────────────────────────────────────────
    public bool   $addDrawer   = false;
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

    // ── Import drawer ─────────────────────────────────────────────────────────
    public bool  $importDrawer  = false;
    public mixed $importFile    = null;
    public array $importErrors  = [];
    public ?int  $importCreated = null;
    public ?int  $importUpdated = null;

    // ── Role drawer ───────────────────────────────────────────────────────────
    public bool $roleDrawer  = false;
    public ?int $roleStaffId = null;

    // ── Login As modal ────────────────────────────────────────────────────────
    public bool   $loginModal  = false;
    public ?int   $loginTarget = null;
    public string $loginName   = '';

    // ─────────────────────────────────────────────────────────────────────────

    public function updatedSearch(): void { $this->resetPage(); }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function generateCode(string $firstName, string $lastName = ''): string
    {
        $words = array_values(array_filter(explode(' ', trim($firstName . ' ' . $lastName))));
        if (!$words) return '';
        $wordLetters = array_map(
            fn($w) => str_split(strtoupper(preg_replace('/[^a-zA-Z]/', '', $w))),
            $words
        );
        $n         = count($words);
        $wordsUsed = min($n, 3);
        $slots     = array_fill(0, $wordsUsed, 1);
        $remaining = 3 - $wordsUsed;
        for ($i = 0; $i < $remaining; $i++) $slots[$i % $wordsUsed]++;
        $pool = [];
        foreach ($wordLetters as $wi => $letters) {
            if (!isset($slots[$wi])) break;
            $taken = 0;
            foreach ($letters as $ch) {
                if ($taken >= $slots[$wi]) break;
                if (!in_array($ch, $pool)) { $pool[] = $ch; $taken++; }
            }
        }
        while (count($pool) < 3) $pool[] = $pool[0];
        $perms = [
            [$pool[0],$pool[1],$pool[2]], [$pool[0],$pool[2],$pool[1]],
            [$pool[1],$pool[0],$pool[2]], [$pool[1],$pool[2],$pool[0]],
            [$pool[2],$pool[0],$pool[1]], [$pool[2],$pool[1],$pool[0]],
        ];
        foreach ($perms as $p) {
            $code = implode('', $p);
            if (!Staff::where('code', $code)->exists()) return $code;
        }
        return implode('', $pool);
    }

    public function updatedFFirstName(): void
    {
        $this->fCode = $this->generateCode($this->fFirstName, $this->fLastName);
    }

    public function updatedFLastName(): void
    {
        $this->fCode = $this->generateCode($this->fFirstName, $this->fLastName);
    }

    // ── Add Staff ─────────────────────────────────────────────────────────────

    public function openAddDrawer(): void
    {
        $this->reset(['fCode','fUnivCode','fSso','fEmployeeId','fFrontTitle','fFirstName','fLastName','fRearTitle','fEmail','fPhone','fTypeId']);
        $this->resetValidation();
        $this->addDrawer = true;
    }

    public function saveStaff(): void
    {
        $this->validate([
            'fCode'       => 'required|string|max:3|unique:arsys_staff,code',
            'fUnivCode'   => 'required|string|max:4',
            'fFirstName'  => 'required|string|max:50',
            'fTypeId'     => 'required|exists:arsys_staff_type,id',
            'fSso'        => 'nullable|string|max:20',
            'fEmployeeId' => 'nullable|string|max:20',
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

        $this->addDrawer = false;
        $this->success('Staff berhasil ditambahkan.');
        $this->resetPage();
    }

    // ── Import Excel ──────────────────────────────────────────────────────────

    public function openImportDrawer(): void
    {
        $this->reset(['importFile','importErrors','importCreated','importUpdated']);
        $this->importDrawer = true;
    }

    public function runImport(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $import = new StaffImport();
        Excel::import($import, $this->importFile->getRealPath());

        $this->importErrors  = $import->errors;
        $this->importCreated = $import->created;
        $this->importUpdated = $import->updated;
        $this->importFile    = null;

        if (!$import->errors) {
            $this->importDrawer = false;
            $this->success("Import selesai: {$import->created} ditambah, {$import->updated} diupdate.");
        } else {
            $this->warning("Import selesai dengan " . count($import->errors) . " baris dilewati.");
        }

        $this->resetPage();
    }

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = ['sso','first_name','last_name','front_title','rear_title','employee_id','code','univ_code','email','phone','staff_type'];
        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, ['196XXXXXXXX','Budi','Santoso','Dr.','M.T.','197XXXXXX','BDS','A001','budi@upi.edu','08xxxxxxxx','PNS']);
            fclose($out);
        }, 'staff-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    // ── Role Management ───────────────────────────────────────────────────────

    public function openRoleDrawer(int $staffId): void
    {
        $this->roleStaffId = $staffId;
        $this->roleDrawer  = true;
    }

    private function resolveUser(Staff $staff): User
    {
        if ($staff->user_id && $user = User::find($staff->user_id)) return $user;
        $user = User::firstOrCreate(
            ['sso' => $staff->sso],
            ['name' => $staff->code, 'sso' => $staff->sso]
        );
        $staff->update(['user_id' => $user->id]);
        return $user;
    }

    public function addRole(string $role = ''): void
    {
        if (!$role) { $this->error('Pilih role dulu.'); return; }
        try {
            $staff = Staff::findOrFail($this->roleStaffId);
            $user  = $this->resolveUser($staff);
            $user->assignRole($role);
            $this->success("Role '{$role}' ditambahkan.");
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

    // ── Soft Delete / Restore ─────────────────────────────────────────────────

    public function softDelete(int $staffId): void
    {
        Staff::findOrFail($staffId)->delete();
        $this->success('Staff dinonaktifkan.');
        $this->resetPage();
    }

    public function restoreStaff(int $staffId): void
    {
        Staff::withTrashed()->findOrFail($staffId)->restore();
        $this->success('Staff diaktifkan kembali.');
        $this->resetPage();
    }

    // ── Login As ──────────────────────────────────────────────────────────────

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
            $this->error('Tidak ada akun untuk staff ini. Assign role terlebih dahulu.');
            return;
        }
        Auth::login($user);
        $this->redirect('/');
    }

    // ── Data ──────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $roleStaff      = $this->roleStaffId ? Staff::with('user')->find($this->roleStaffId) : null;
        $currentRoles   = $roleStaff?->user?->getRoleNames()->toArray() ?? [];
        $availableRoles = Role::orderBy('name')->pluck('name')
            ->reject(fn($r) => in_array($r, $currentRoles))
            ->values();

        $query = $this->showInactive
            ? Staff::onlyTrashed()->with(['user'])
            : Staff::with(['user']);

        $staffs = $query
            ->when($this->search, fn($q) =>
                $q->where(fn($inner) =>
                    $inner->where('first_name',  'ilike', "%{$this->search}%")
                          ->orWhere('last_name',  'ilike', "%{$this->search}%")
                          ->orWhere('code',       'ilike', "%{$this->search}%")
                          ->orWhere('employee_id','ilike', "%{$this->search}%")
                          ->orWhere('sso',        'ilike', "%{$this->search}%")
                )
            )
            ->when($this->filterProgram, fn($q) => $q->where('program_id', $this->filterProgram))
            ->orderBy('code')
            ->paginate($this->perPage);

        $headers = [
            ['key' => 'code',        'label' => 'Code'],
            ['key' => 'name',        'label' => 'Name'],
            ['key' => 'employee_id', 'label' => 'Employee ID'],
            ['key' => 'sso',         'label' => 'SSO'],
            ['key' => 'roles',       'label' => 'Roles'],
            ['key' => 'actions',     'label' => ''],
        ];

        return [
            'staffs'         => $staffs,
            'headers'        => $headers,
            'staffTypes'     => StaffType::orderBy('id')->get(),
            'programs'       => Program::orderBy('code')->get(),
            'roleStaff'      => $roleStaff,
            'currentRoles'   => $currentRoles,
            'availableRoles' => $availableRoles,
        ];
    }
};
?>

<div>
    {{-- Header ----------------------------------------------------------------}}
    <x-header title="Staff Management" subtitle="Super Admin — Data Lengkap" separator>
        <x-slot:actions>
            <x-input
                placeholder="Cari code, nama, SSO, employee ID..."
                wire:model.live.debounce="search"
                icon="o-magnifying-glass"
                clearable
                class="w-56"
            />
            <x-select
                wire:model.live="filterProgram"
                :options="$programs"
                option-value="id"
                option-label="code"
                placeholder="Semua Prodi"
                class="select-sm w-40"
            />
            <x-toggle
                label="Nonaktif"
                wire:model.live="showInactive"
                class="toggle-warning"
            />
            <x-button
                label="Import"
                icon="o-arrow-up-tray"
                wire:click="openImportDrawer"
                class="btn-outline btn-sm"
            />
            <x-button
                label="Tambah"
                icon="o-plus"
                wire:click="openAddDrawer"
                class="btn-primary btn-sm"
            />
        </x-slot:actions>
    </x-header>

    {{-- Table -----------------------------------------------------------------}}
    <x-card>
        <x-table :headers="$headers" :rows="$staffs" with-pagination container-class="w-full">

            @scope('cell_name', $staff)
                <div>
                    <p class="font-semibold text-sm leading-tight">
                        {{ trim(($staff->front_title ? $staff->front_title.' ' : '') . $staff->first_name . ' ' . $staff->last_name . ($staff->rear_title ? ', '.$staff->rear_title : '')) }}
                    </p>
                </div>
            @endscope

            @scope('cell_roles', $staff)
                <div class="flex flex-wrap gap-1">
                    @if($staff->user)
                        @forelse($staff->user->getRoleNames() as $role)
                            <x-badge value="{{ $role }}" class="badge-primary badge-sm" />
                        @empty
                            <span class="text-xs text-gray-400">No role</span>
                        @endforelse
                    @else
                        <span class="text-xs text-gray-400">No account</span>
                    @endif
                </div>
            @endscope

            @scope('cell_actions', $staff)
                <div class="flex items-center gap-1">
                    @if(!$staff->trashed())
                        <x-button
                            icon="o-key"
                            class="btn-xs btn-ghost"
                            wire:click="openRoleDrawer({{ $staff->id }})"
                            tooltip="Manage Roles"
                            spinner
                        />
                        @if($staff->user)
                        <x-button
                            icon="o-arrow-right-end-on-rectangle"
                            class="btn-xs btn-ghost text-purple-500"
                            wire:click="confirmLogin({{ $staff->id }}, '{{ addslashes($staff->code) }}')"
                            tooltip="Login As"
                            spinner
                        />
                        @endif
                        <x-button
                            icon="o-eye-slash"
                            class="btn-xs btn-ghost text-red-400"
                            wire:click="softDelete({{ $staff->id }})"
                            wire:confirm="Nonaktifkan {{ $staff->code }}?"
                            tooltip="Nonaktifkan"
                            spinner
                        />
                    @else
                        <x-button
                            icon="o-arrow-path"
                            class="btn-xs btn-ghost text-green-500"
                            wire:click="restoreStaff({{ $staff->id }})"
                            tooltip="Aktifkan kembali"
                            spinner
                        />
                    @endif
                </div>
            @endscope

        </x-table>
    </x-card>

    {{-- Add Staff Drawer ------------------------------------------------------}}
    <x-drawer wire:model="addDrawer" title="Tambah Staff" right class="w-96">
        <div class="space-y-4 p-1">

            <x-select
                label="Type"
                wire:model="fTypeId"
                :options="$staffTypes"
                option-value="id"
                option-label="code"
                placeholder="— pilih type —"
                required
            />
            @error('fTypeId') <p class="text-xs text-red-500 -mt-3">{{ $message }}</p> @enderror

            <div class="grid grid-cols-3 gap-2">
                <x-input label="Front Title" wire:model="fFrontTitle" placeholder="Dr." />
                <div class="col-span-2">
                    <x-input label="First Name" wire:model.live.debounce.600ms="fFirstName" placeholder="Nama depan" required />
                    @error('fFirstName') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div class="col-span-2">
                    <x-input label="Last Name" wire:model.live.debounce.600ms="fLastName" placeholder="Nama belakang" />
                </div>
                <x-input label="Rear Title" wire:model="fRearTitle" placeholder="M.T." />
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <x-input label="Code (auto)" wire:model="fCode" maxlength="3" placeholder="BDS" class="uppercase" required />
                    @error('fCode') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <x-input label="Univ Code" wire:model="fUnivCode" maxlength="4" placeholder="A001" class="uppercase" required />
                    @error('fUnivCode') <p class="text-xs text-red-500 mt-0.5">{{ $message }}</p> @enderror
                </div>
            </div>

            <x-input label="SSO / NIP" wire:model="fSso" maxlength="20" placeholder="196XXXXXXXX" />
            <x-input label="Employee ID" wire:model="fEmployeeId" maxlength="20" placeholder="197XXXXXX" />
            <x-input label="Email" wire:model="fEmail" type="email" placeholder="nama@upi.edu" />
            <x-input label="Phone" wire:model="fPhone" maxlength="12" placeholder="08xxxxxxxxxx" />

        </div>
        <x-slot:actions>
            <x-button label="Batal" wire:click="$set('addDrawer', false)" />
            <x-button label="Simpan" class="btn-primary" wire:click="saveStaff" spinner="saveStaff" />
        </x-slot:actions>
    </x-drawer>

    {{-- Import Drawer ---------------------------------------------------------}}
    <x-drawer wire:model="importDrawer" title="Import Staff dari Excel" right class="w-96">
        <div class="space-y-4 p-1">

            <x-alert title="Format kolom Excel" class="alert-info text-sm">
                <p class="text-xs mt-1 font-mono break-all">
                    sso · first_name · last_name · front_title · rear_title · employee_id · code · univ_code · email · phone · staff_type
                </p>
                <p class="text-xs mt-2 text-gray-600">
                    Upsert berdasarkan kolom <strong>sso</strong>. Baris tanpa sso dilewati.
                    Nilai <strong>staff_type</strong>: PNS / PTU / DLB.
                </p>
            </x-alert>

            <x-button
                label="Download Template"
                icon="o-arrow-down-tray"
                wire:click="downloadTemplate"
                class="btn-outline btn-sm w-full"
            />

            <x-file
                label="File Excel / CSV"
                wire:model="importFile"
                accept=".xlsx,.xls,.csv"
                hint="Format: .xlsx, .xls, .csv · Maks 5MB"
            />
            @error('importFile') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

            @if(!is_null($importCreated))
                <x-alert title="Hasil Import" class="alert-success text-sm">
                    <p class="text-xs">Ditambah: <strong>{{ $importCreated }}</strong> &nbsp;·&nbsp; Diupdate: <strong>{{ $importUpdated }}</strong></p>
                </x-alert>
            @endif

            @if(count($importErrors) > 0)
                <x-alert title="{{ count($importErrors) }} baris dilewati" class="alert-warning text-sm">
                    <ul class="text-xs mt-1 space-y-0.5 max-h-40 overflow-y-auto list-disc list-inside">
                        @foreach($importErrors as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            @endif

        </div>
        <x-slot:actions>
            <x-button label="Tutup" wire:click="$set('importDrawer', false)" />
            <x-button label="Import" icon="o-arrow-up-tray" class="btn-primary" wire:click="runImport" spinner="runImport" />
        </x-slot:actions>
    </x-drawer>

    {{-- Role Drawer -----------------------------------------------------------}}
    <x-drawer wire:model="roleDrawer" title="Manage Roles" right class="w-80">
        <div class="space-y-4 p-1">

            @if($roleStaff)
                <div class="p-3 rounded-lg bg-gray-50 border border-gray-200">
                    <p class="text-sm font-semibold text-gray-800">{{ $roleStaff->code }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ trim(($roleStaff->front_title ? $roleStaff->front_title.' ' : '') . $roleStaff->first_name . ' ' . $roleStaff->last_name) }}
                    </p>
                </div>
            @endif

            <div>
                <p class="text-xs font-medium text-gray-400 mb-2 uppercase tracking-wide">Role Aktif</p>
                @if(count($currentRoles))
                    <div class="flex flex-wrap gap-2">
                        @foreach($currentRoles as $role)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                {{ $role }}
                                <button
                                    wire:click="removeRole('{{ $role }}')"
                                    type="button"
                                    class="ml-0.5 hover:text-red-500 transition-colors leading-none"
                                    title="Hapus role"
                                >&times;</button>
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic">Belum ada role</p>
                @endif
            </div>

            <div x-data="{ newRole: '' }">
                <p class="text-xs font-medium text-gray-400 mb-2 uppercase tracking-wide">Tambah Role</p>
                <div class="flex gap-2">
                    <select x-model="newRole" class="select select-bordered select-sm flex-1 text-sm">
                        <option value="">— pilih role —</option>
                        @foreach($availableRoles as $role)
                            <option value="{{ $role }}">{{ $role }}</option>
                        @endforeach
                    </select>
                    <button
                        type="button"
                        @click="$wire.addRole(newRole); newRole = ''"
                        class="btn btn-primary btn-sm btn-circle"
                        title="Tambah"
                    >
                        <x-icon name="o-plus" class="w-4 h-4" />
                    </button>
                </div>
            </div>

        </div>
        <x-slot:actions>
            <x-button label="Tutup" wire:click="$set('roleDrawer', false)" />
        </x-slot:actions>
    </x-drawer>

    {{-- Login As Modal --------------------------------------------------------}}
    <x-modal wire:model="loginModal" title="Login As Staff" separator>
        <div class="flex items-center gap-3 py-2">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-100">
                <x-icon name="o-arrow-right-end-on-rectangle" class="h-5 w-5 text-purple-600" />
            </div>
            <p class="text-sm text-gray-600">
                Login sebagai <strong class="text-gray-800">{{ $loginName }}</strong>?
            </p>
        </div>
        <x-slot:actions>
            <x-button label="Batal" wire:click="$set('loginModal', false)" />
            <x-button label="Login" class="btn-primary" wire:click="loginAs" spinner="loginAs" />
        </x-slot:actions>
    </x-modal>

</div>
