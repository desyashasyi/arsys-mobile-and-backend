# Super-Admin Staff Web Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Halaman web khusus super-admin untuk manajemen data staff dengan tabel MaryUI dan fitur import Excel.

**Architecture:** Satu Livewire 4 single-file component (`⚡web.blade.php`) pakai `layouts.app`, didukung `StaffImport` class untuk upsert via `maatwebsite/excel`. Route terpisah dari halaman mobile existing, tidak muncul di menu.

**Tech Stack:** Laravel 12, Livewire 4, MaryUI, maatwebsite/excel ^3.1, `layouts.app`

---

### Task 1: Tambah Route

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Tambah route baru di dalam grup `super_admin`**

Cari blok:
```php
Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
```

Tambah satu baris di dalam grup tersebut:
```php
Route::livewire('/staff-web', 'pages::super-admin.staff.⚡web')->name('staff.web');
```

- [ ] **Step 2: Verify route terdaftar**

```bash
cd /home/deewahyu/WebDev/arsys/backend
make artisan "route:list --name=super-admin.staff.web"
```

Expected: baris route `super-admin.staff.web` muncul.

---

### Task 2: Buat StaffImport Class

**Files:**
- Create: `app/Imports/StaffImport.php`

- [ ] **Step 1: Buat file import**

```php
<?php

namespace App\Imports;

use App\Models\ArSys\Staff;
use App\Models\ArSys\StaffType;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class StaffImport implements ToCollection, WithHeadingRow
{
    public array $errors  = [];
    public int   $updated = 0;
    public int   $created = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // baris Excel (header = 1)

            $sso = trim($row['sso'] ?? '');
            if (!$sso) {
                $this->errors[] = "Row {$rowNum}: kolom 'sso' kosong, dilewati.";
                continue;
            }

            $firstName = trim($row['first_name'] ?? '');
            if (!$firstName) {
                $this->errors[] = "Row {$rowNum}: kolom 'first_name' kosong, dilewati.";
                continue;
            }

            // Resolve staff_type_id dari kode (PNS/PTU/DLB/dll)
            $typeCode = strtoupper(trim($row['staff_type'] ?? ''));
            $type     = $typeCode ? StaffType::where('code', $typeCode)->first() : null;

            $data = array_filter([
                'first_name'    => $firstName,
                'last_name'     => trim($row['last_name']    ?? '') ?: null,
                'front_title'   => trim($row['front_title']  ?? '') ?: null,
                'rear_title'    => trim($row['rear_title']   ?? '') ?: null,
                'employee_id'   => trim($row['employee_id']  ?? '') ?: null,
                'code'          => strtoupper(trim($row['code'] ?? '')) ?: null,
                'univ_code'     => strtoupper(trim($row['univ_code'] ?? '')) ?: null,
                'email'         => trim($row['email'] ?? '') ?: null,
                'phone'         => trim($row['phone'] ?? '') ?: null,
                'staff_type_id' => $type?->id,
            ], fn($v) => $v !== null);

            $existing = Staff::where('sso', $sso)->first();

            if ($existing) {
                $existing->update($data);
                $this->updated++;
            } else {
                Staff::create(array_merge($data, ['sso' => $sso]));
                $this->created++;
            }
        }
    }
}
```

---

### Task 3: Buat Livewire Web Page

**Files:**
- Create: `resources/views/pages/super-admin/staff/⚡web.blade.php`

- [ ] **Step 1: Buat file komponen**

```php
<?php

use App\Imports\StaffImport;
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

new #[Layout('layouts.app')] #[Title('Staff — Web')] class extends Component
{
    use WithPagination, Toast, WithFileUploads;

    // ── Search & pagination ───────────────────────────────────────────────────
    public string $search = '';
    public int    $perPage = 20;

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
    public bool  $importDrawer = false;
    public mixed $importFile   = null;
    public array $importErrors = [];
    public ?int  $importCreated = null;
    public ?int  $importUpdated = null;

    // ── Role drawer ───────────────────────────────────────────────────────────
    public bool $roleDrawer   = false;
    public ?int $roleStaffId  = null;

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
        $this->success('Staff added.');
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
            $this->error('No account found. Assign a role first.');
            return;
        }
        Auth::login($user);
        $this->redirect('/');
    }

    // ── Data ──────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $roleStaff     = $this->roleStaffId ? Staff::with('user')->find($this->roleStaffId) : null;
        $currentRoles  = $roleStaff?->user?->getRoleNames()->toArray() ?? [];
        $availableRoles = Role::orderBy('name')->pluck('name')
            ->reject(fn($r) => in_array($r, $currentRoles))
            ->values();

        $staffs = Staff::with(['user', 'type', 'program'])
            ->when($this->search, fn($q) =>
                $q->where(fn($q) =>
                    $q->where('first_name',  'ilike', "%{$this->search}%")
                      ->orWhere('last_name',  'ilike', "%{$this->search}%")
                      ->orWhere('code',       'ilike', "%{$this->search}%")
                      ->orWhere('employee_id','ilike', "%{$this->search}%")
                      ->orWhere('sso',        'ilike', "%{$this->search}%")
                )
            )
            ->orderBy('code')
            ->paginate($this->perPage);

        $headers = [
            ['key' => 'code',        'label' => 'Code',        'sortable' => true],
            ['key' => 'name',        'label' => 'Name'],
            ['key' => 'employee_id', 'label' => 'Employee ID'],
            ['key' => 'sso',         'label' => 'SSO'],
            ['key' => 'type',        'label' => 'Type'],
            ['key' => 'program',     'label' => 'Program'],
            ['key' => 'roles',       'label' => 'Roles'],
            ['key' => 'actions',     'label' => ''],
        ];

        return [
            'staffs'         => $staffs,
            'headers'        => $headers,
            'staffTypes'     => StaffType::orderBy('id')->get(),
            'roleStaff'      => $roleStaff,
            'currentRoles'   => $currentRoles,
            'availableRoles' => $availableRoles,
        ];
    }
};
?>

<div>
    {{-- Header ----------------------------------------------------------------}}
    <x-header title="Staff Management" subtitle="Super Admin — Web View" separator>
        <x-slot:actions>
            <x-input
                placeholder="Search code, name, SSO, employee ID..."
                wire:model.live.debounce="search"
                icon="o-magnifying-glass"
                clearable
                class="w-72"
            />
            <x-button
                label="Import Excel"
                icon="o-arrow-up-tray"
                wire:click="openImportDrawer"
                class="btn-outline"
            />
            <x-button
                label="Add Staff"
                icon="o-plus"
                wire:click="openAddDrawer"
                class="btn-primary"
            />
        </x-slot:actions>
    </x-header>

    {{-- Table -----------------------------------------------------------------}}
    <x-card>
        <x-table :headers="$headers" :rows="$staffs" with-pagination>
            @scope('cell_name', $staff)
                <div>
                    <p class="font-semibold text-sm leading-tight">
                        {{ trim(($staff->front_title ? $staff->front_title.' ' : '') . $staff->first_name . ' ' . $staff->last_name . ($staff->rear_title ? ', '.$staff->rear_title : '')) }}
                    </p>
                </div>
            @endscope

            @scope('cell_type', $staff)
                <x-badge value="{{ $staff->type?->code ?? '—' }}" class="badge-ghost badge-sm" />
            @endscope

            @scope('cell_program', $staff)
                <span class="text-xs text-gray-500">{{ $staff->program?->code ?? '—' }}</span>
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
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- Add Staff Drawer ------------------------------------------------------}}
    <x-drawer wire:model="addDrawer" title="Add Staff" right class="w-96">
        <div class="space-y-4 p-1">
            {{-- Type --}}
            <x-select
                label="Type"
                wire:model="fTypeId"
                :options="$staffTypes"
                option-value="id"
                option-label="code"
                placeholder="— select type —"
                required
            />
            @error('fTypeId') <p class="text-xs text-red-500 -mt-3">{{ $message }}</p> @enderror

            {{-- Name row --}}
            <div class="grid grid-cols-3 gap-2">
                <x-input label="Front Title" wire:model="fFrontTitle" placeholder="Dr." />
                <x-input label="First Name" wire:model.live.debounce.600ms="fFirstName" placeholder="First name" class="col-span-2" required />
            </div>
            @error('fFirstName') <p class="text-xs text-red-500 -mt-3">{{ $message }}</p> @enderror

            <div class="grid grid-cols-3 gap-2">
                <x-input label="Last Name" wire:model.live.debounce.600ms="fLastName" placeholder="Last name" class="col-span-2" />
                <x-input label="Rear Title" wire:model="fRearTitle" placeholder="M.T." />
            </div>

            {{-- Code row --}}
            <div class="grid grid-cols-2 gap-2">
                <x-input label="Code (auto)" wire:model="fCode" maxlength="3" placeholder="e.g. BDS" class="uppercase" required />
                <x-input label="Univ Code" wire:model="fUnivCode" maxlength="4" placeholder="A001" class="uppercase" required />
            </div>
            @error('fCode') <p class="text-xs text-red-500 -mt-3">{{ $message }}</p> @enderror
            @error('fUnivCode') <p class="text-xs text-red-500 -mt-3">{{ $message }}</p> @enderror

            <x-input label="SSO / NIP" wire:model="fSso" maxlength="20" placeholder="196XXXXXXXX" />
            <x-input label="Employee ID" wire:model="fEmployeeId" maxlength="20" placeholder="197XXXXXX" />
            <x-input label="Email" wire:model="fEmail" type="email" placeholder="email@upi.edu" />
            <x-input label="Phone" wire:model="fPhone" maxlength="12" placeholder="08xxxxxxxxxx" />
        </div>

        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('addDrawer', false)" />
            <x-button label="Save" class="btn-primary" wire:click="saveStaff" spinner="saveStaff" />
        </x-slot:actions>
    </x-drawer>

    {{-- Import Drawer ---------------------------------------------------------}}
    <x-drawer wire:model="importDrawer" title="Import Staff from Excel" right class="w-96">
        <div class="space-y-4 p-1">
            <x-alert title="Format kolom" class="alert-info text-sm">
                <p class="text-xs mt-1">
                    <code class="bg-blue-50 px-1 rounded">sso · first_name · last_name · front_title · rear_title · employee_id · code · univ_code · email · phone · staff_type</code>
                </p>
                <p class="text-xs mt-1 text-gray-500">Upsert berdasarkan kolom <strong>sso</strong>. Baris tanpa sso dilewati.</p>
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
                hint="Max 5MB"
            />
            @error('importFile') <p class="text-xs text-red-500">{{ $message }}</p> @enderror

            {{-- Hasil import --}}
            @if(!is_null($importCreated))
                <x-alert title="Hasil Import" class="alert-success text-sm">
                    <p class="text-xs">Ditambah: <strong>{{ $importCreated }}</strong> · Diupdate: <strong>{{ $importUpdated }}</strong></p>
                </x-alert>
            @endif

            @if(count($importErrors) > 0)
                <x-alert title="{{ count($importErrors) }} baris dilewati" class="alert-warning text-sm">
                    <ul class="text-xs mt-1 space-y-0.5 max-h-40 overflow-y-auto">
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
                <p class="text-sm font-semibold text-gray-700">
                    {{ $roleStaff->code }} —
                    {{ trim(($roleStaff->front_title ? $roleStaff->front_title.' ' : '') . $roleStaff->first_name . ' ' . $roleStaff->last_name) }}
                </p>
            @endif

            {{-- Current roles --}}
            <div>
                <p class="text-xs text-gray-400 mb-2">Current roles</p>
                @if(count($currentRoles))
                    <div class="flex flex-wrap gap-2">
                        @foreach($currentRoles as $role)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                {{ $role }}
                                <button
                                    wire:click="removeRole('{{ $role }}')"
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

            {{-- Add role --}}
            <div x-data="{ newRole: '' }">
                <p class="text-xs text-gray-400 mb-2">Add role</p>
                <div class="flex gap-2">
                    <select x-model="newRole" class="select select-bordered select-sm flex-1 text-sm">
                        <option value="">— select role —</option>
                        @foreach($availableRoles as $role)
                            <option value="{{ $role }}">{{ $role }}</option>
                        @endforeach
                    </select>
                    <button
                        type="button"
                        @click="$wire.addRole(newRole); newRole = ''"
                        class="btn btn-primary btn-sm btn-circle"
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

    {{-- Login Confirmation Modal ----------------------------------------------}}
    <x-modal wire:model="loginModal" title="Login As Staff" separator>
        <div class="flex items-center gap-3 py-1">
            <x-icon name="o-arrow-right-end-on-rectangle" class="h-8 w-8 text-purple-500 shrink-0" />
            <p class="text-sm text-gray-600">Login sebagai <strong>{{ $loginName }}</strong>?</p>
        </div>
        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('loginModal', false)" />
            <x-button label="Login" class="btn-primary" wire:click="loginAs" spinner="loginAs" />
        </x-slot:actions>
    </x-modal>
</div>
```

---

### Task 4: Verifikasi Manual

- [ ] Akses `http://localhost:8081/super-admin/staff-web` sebagai `super_admin`
- [ ] Tabel muncul, search bekerja
- [ ] Add Staff drawer → isi form → save → staff muncul di tabel
- [ ] Import drawer → download template → isi data → upload → cek created/updated count
- [ ] Manage Roles → assign role → role muncul di badge
- [ ] Login As → confirm modal → redirect ke `/`
- [ ] Halaman **tidak muncul** di sidebar/menu
