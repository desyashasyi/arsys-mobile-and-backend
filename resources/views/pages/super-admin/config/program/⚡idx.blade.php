<?php

use App\Models\ArSys\ClusterBase;
use App\Models\ArSys\Faculty;
use App\Models\ArSys\InstitutionConfig;
use App\Models\ArSys\InstitutionConfigBase;
use App\Models\ArSys\InstitutionRole;
use App\Models\ArSys\Level;
use App\Models\ArSys\Program;
use App\Models\ArSys\ResearchConfig;
use App\Models\ArSys\ResearchConfigBase;
use App\Models\ArSys\ResearchType;
use App\Models\ArSys\ResearchTypeBase;
use App\Models\ArSys\Staff;
use App\Models\ArSys\StudyCompletion;
use App\Models\ArSys\StudyCompletionBase;
use App\Models\ArSys\Cluster;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Programs')] class extends Component
{
    use WithPagination, Toast;

    // State modals
    public bool $createProgramModal = false;
    public bool $createFacultyModal = false;
    public bool $createClusterModal = false;
    public bool $editProgramModal   = false;
    public bool $confirmLoginModal  = false;

    // Edit program
    public ?int    $editId        = null;
    public string  $editCode      = '';
    public string  $editAbbrev    = '';
    public string  $editName      = '';
    public ?int    $editFacultyId = null;
    public ?int    $editLevelId   = null;
    public ?int    $editHeadId    = null;
    public ?int    $editClusterId = null;

    // Confirm login as
    public ?int    $loginProgramId   = null;
    public ?string $loginProgramCode = null;

    // Search
    public string $search = '';

    // Form: Program baru
    public string $code       = '';
    public string $abbrev     = '';
    public string $name       = '';
    public ?int   $facultyId  = null;
    public ?int   $levelId    = null;
    public ?int   $headId     = null;
    public ?int   $clusterId  = null;

    // Form: Fakultas baru
    public string $facultyCode = '';
    public string $facultyName = '';

    // Form: Cluster baru
    public string $clusterCode = '';
    public string $clusterName = '';

    public function updatedSearch(): void { $this->resetPage(); }

    // ─── Program ──────────────────────────────────────────────────────────────

    public function saveProgram(): void
    {
        $this->validate([
            'code'     => 'required|string|max:20',
            'abbrev'   => 'required|string|max:20',
            'name'     => 'required|string',
            'facultyId'=> 'required|exists:arsys_institution_faculty,id',
            'levelId'  => 'required|exists:arsys_level,id',
            'headId'   => 'required|exists:arsys_staff,id',
            'clusterId'=> 'required|exists:arsys_cluster_base,id',
        ]);

        $code = Str::upper($this->code);

        if (Program::where('code', $code)->exists()) {
            $this->error("A study program with code '{$code}' already exists.");
            return;
        }

        // 1. Buat program studi
        $program = Program::create([
            'code'       => $code,
            'abbrev'     => Str::upper($this->abbrev),
            'name'       => Str::title($this->name),
            'faculty_id' => $this->facultyId,
            'level_id'   => $this->levelId,
            'staff_id'   => $this->headId,
        ]);

        // 2. Link kepala program → user & role
        $head = Staff::find($this->headId);
        $headUser = User::firstOrCreate(
            ['sso' => $head->sso],
            ['name' => $head->code, 'sso' => $head->sso]
        );
        $head->update(['user_id' => $headUser->id]);
        $headUser->assignRole('program');

        // 3. Akun admin program (CODE-Admin)
        $adminUser = User::firstOrCreate(
            ['name' => "{$code}-Admin"],
            [
                'name'     => "{$code}-Admin",
                'sso'      => "{$code}-Admin",
                'email'    => Str::lower($this->code) . '.admin@arsys.id',
                'password' => Hash::make(Str::lower($this->code) . 'Admin##'),
            ]
        );
        $adminUser->assignRole('admin');
        InstitutionRole::updateOrCreate(
            ['code' => "{$code}-Admin"],
            ['program_id' => $program->id, 'user_id' => $adminUser->id]
        );

        // 4. Akun koordinator program (CODE)
        $proUser = User::firstOrCreate(
            ['name' => $code],
            [
                'name'     => $code,
                'sso'      => $code,
                'email'    => Str::lower($this->code) . '.pro@arsys.id',
                'password' => Hash::make(Str::lower($this->code) . 'Pro##'),
            ]
        );
        $proUser->assignRole('program');
        InstitutionRole::updateOrCreate(
            ['code' => $code],
            ['program_id' => $program->id, 'user_id' => $proUser->id]
        );

        // 5. Cluster
        Cluster::updateOrCreate(
            ['program_id' => $program->id, 'cluster_base_id' => $this->clusterId]
        );

        // 6. Research configs
        foreach (ResearchConfigBase::all() as $cfg) {
            ResearchConfig::updateOrCreate(
                ['program_id' => $program->id, 'config_base_id' => $cfg->id],
                ['status' => $cfg->status]
            );
        }
        foreach (ResearchTypeBase::where('level_id', $this->levelId)->get() as $cfg) {
            ResearchType::updateOrCreate(
                ['program_id' => $program->id, 'research_type_base_id' => $cfg->id],
                [
                    'supervisor_number'        => $cfg->supervisor_number,
                    'status'                   => 1,
                    'week_of_supervise'        => $cfg->week_of_supervise,
                    'enable_week_of_supervise' => $cfg->enable_week_of_supervise,
                ]
            );
        }

        // 7. Institution configs
        foreach (InstitutionConfigBase::all() as $cfg) {
            InstitutionConfig::updateOrCreate(
                ['program_id' => $program->id, 'config_base_id' => $cfg->id]
            );
            if ($cfg->code === 'STUDY_COMPLETION') {
                foreach (StudyCompletionBase::all() as $sc) {
                    StudyCompletion::create([
                        'study_completion_base_id' => $sc->id,
                        'program_id'               => $program->id,
                    ]);
                }
            }
        }

        $this->reset(['code', 'abbrev', 'name', 'facultyId', 'levelId', 'headId', 'clusterId']);
        $this->createProgramModal = false;
        $this->success("Study program '{$code}' created successfully with accounts and configuration.");
    }

    // ─── Edit Program ─────────────────────────────────────────────────────────

    public function openEditProgram(int $programId): void
    {
        $program = Program::with('cluster')->findOrFail($programId);
        $this->editId        = $programId;
        $this->editCode      = $program->code;
        $this->editAbbrev    = $program->abbrev;
        $this->editName      = $program->name;
        $this->editFacultyId = $program->faculty_id;
        $this->editLevelId   = $program->level_id;
        $this->editHeadId    = $program->staff_id;
        $this->editClusterId = $program->cluster?->cluster_base_id;
        $this->editProgramModal = true;
    }

    public function updateProgram(): void
    {
        $this->validate([
            'editAbbrev'    => 'required|string|max:20',
            'editName'      => 'required|string',
            'editFacultyId' => 'required|exists:arsys_institution_faculty,id',
            'editLevelId'   => 'required|exists:arsys_level,id',
            'editHeadId'    => 'required|exists:arsys_staff,id',
            'editClusterId' => 'required|exists:arsys_cluster_base,id',
        ]);

        $program = Program::findOrFail($this->editId);
        $program->update([
            'abbrev'     => Str::upper($this->editAbbrev),
            'name'       => Str::title($this->editName),
            'faculty_id' => $this->editFacultyId,
            'level_id'   => $this->editLevelId,
            'staff_id'   => $this->editHeadId,
        ]);

        // Update cluster
        Cluster::where('program_id', $program->id)
            ->update(['cluster_base_id' => $this->editClusterId]);

        // Assign role ke head baru jika ada user
        $head = Staff::find($this->editHeadId);
        if ($head?->user_id) {
            User::find($head->user_id)?->assignRole('program');
        }

        $this->reset(['editId','editCode','editAbbrev','editName','editFacultyId','editLevelId','editHeadId','editClusterId']);
        $this->editProgramModal = false;
        $this->success("Program '{$program->code}' updated successfully.");
    }

    // ─── Fakultas ─────────────────────────────────────────────────────────────

    public function saveFaculty(): void
    {
        $this->validate([
            'facultyCode' => 'required|string|max:20',
            'facultyName' => 'required|string',
        ]);

        if (Faculty::where('code', $this->facultyCode)->exists()) {
            $this->error("Faculty '{$this->facultyCode}' already exists.");
            return;
        }

        Faculty::create(['code' => $this->facultyCode, 'name' => $this->facultyName]);
        $this->reset(['facultyCode', 'facultyName']);
        $this->createFacultyModal = false;
        $this->success('Faculty added successfully.');
    }

    // ─── Cluster ──────────────────────────────────────────────────────────────

    public function saveCluster(): void
    {
        $this->validate([
            'clusterCode' => 'required|string|max:20',
            'clusterName' => 'required|string',
        ]);

        if (ClusterBase::where('code', $this->clusterCode)->exists()) {
            $this->error("Cluster '{$this->clusterCode}' already exists.");
            return;
        }

        ClusterBase::create(['code' => $this->clusterCode, 'name' => $this->clusterName]);
        $this->reset(['clusterCode', 'clusterName']);
        $this->createClusterModal = false;
        $this->success('Cluster added successfully.');
    }

    // ─── Login As ─────────────────────────────────────────────────────────────

    public function confirmLoginAs(int $programId): void
    {
        $program = Program::findOrFail($programId);
        $this->loginProgramId   = $programId;
        $this->loginProgramCode = $program->code;
        $this->confirmLoginModal = true;
    }

    public function loginAsProgram(): void
    {
        $role = InstitutionRole::where('code', $this->loginProgramCode . '-Admin')->first();
        if (!$role?->user_id) {
            $this->error('Program admin account is not available yet.');
            $this->confirmLoginModal = false;
            return;
        }
        Auth::loginUsingId($role->user_id);
        $this->redirect('/');
    }

    public function with(): array
    {
        return [
            'programs'  => Program::with(['faculty', 'level', 'staff'])
                ->when($this->search, fn($q) => $q->where('code', 'like', "%{$this->search}%")
                    ->orWhere('name', 'like', "%{$this->search}%"))
                ->orderBy('code')
                ->paginate(10),
            'faculties' => Faculty::orderBy('code')->get(),
            'levels'    => Level::orderBy('id')->get(),
            'clusters'  => ClusterBase::orderBy('code')->get(),
            'staffs'    => Staff::orderBy('code')->get(),
        ];
    }


};
?>

<div>
    {{-- Page AppBar --}}
    <div class="bg-purple-600 px-4 pb-4 pt-2">
        <h2 class="text-white font-bold text-base">Study Programs</h2>
        <p class="text-purple-200 text-xs mt-0.5">Manage programs, faculties, and clusters</p>
        <div class="mt-3 flex items-center gap-2">
            <div class="flex-1">
                <x-input
                    placeholder="Search code or name..."
                    wire:model.live.debounce="search"
                    icon="o-magnifying-glass"
                    clearable
                    class="bg-white/10 placeholder-purple-300 text-white border-0"
                />
            </div>
            <button
                wire:click="$set('createProgramModal', true)"
                class="flex items-center justify-center w-10 h-10 rounded-full bg-white/20 text-white hover:bg-white/30 active:scale-95 transition-all shrink-0"
            >
                <x-icon name="o-plus" class="w-5 h-5" />
            </button>
        </div>
    </div>

    {{-- Program List --}}
    <div class="px-3 pb-3 space-y-2">
        @forelse($programs as $program)
            <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 border-purple-400">
                <div class="p-3">

                    {{-- Code --}}
                    <span class="font-mono font-bold text-sm text-gray-800">{{ $program->code }}</span>

                    {{-- Level · Abbrev · Faculty --}}
                    <p class="text-sm font-semibold text-gray-700 leading-snug mt-0.5">
                        @if($program->level)<span class="text-purple-500">{{ $program->level->code }}</span> @endif{{ $program->abbrev }}@if($program->faculty) <span class="text-gray-300 font-normal">|</span> <span class="text-gray-400 font-normal text-xs">{{ $program->faculty->code }}</span>@endif
                    </p>

                    {{-- Head --}}
                    @if($program->staff)
                        <div class="flex items-center gap-1 mt-1.5">
                            <x-icon name="o-user-circle" class="w-3.5 h-3.5 text-purple-400 shrink-0" />
                            <span class="text-[11px] text-gray-600 truncate">{{ $program->staff->name }}</span>
                            <span class="text-[10px] text-gray-300 shrink-0">· {{ $program->staff->code }}</span>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="mt-2 flex justify-between items-center">
                        <x-button
                            label="Edit"
                            icon="o-pencil-square"
                            class="btn-xs btn-ghost text-gray-400"
                            wire:click="openEditProgram({{ $program->id }})"
                            spinner
                        />
                        <x-button
                            label="Login As"
                            icon="o-arrow-right-end-on-rectangle"
                            class="btn-xs btn-ghost text-purple-500"
                            wire:click="confirmLoginAs({{ $program->id }})"
                            spinner
                        />
                    </div>

                </div>
            </div>
        @empty
            <div class="py-16 text-center">
                <x-icon name="o-building-library" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No study programs found.</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($programs->hasPages())
        <div class="px-3 pb-4">
            {{ $programs->links() }}
        </div>
    @endif

    {{-- Confirm Login As (absolute overlay, terkurung dalam container) --}}
    <div x-data="{ open: @entangle('confirmLoginModal') }" x-cloak>
        <div
            x-show="open"
            x-transition.opacity
            class="absolute inset-0 z-50 flex items-center justify-center bg-black/40 px-8"
            @click.self="open = false"
        >
            <div class="w-full bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="px-5 pt-5 pb-4 text-center space-y-2">
                    <x-icon name="o-arrow-right-end-on-rectangle" class="mx-auto w-10 h-10 text-purple-500" />
                    <p class="text-sm text-gray-500">Login as admin for</p>
                    <p class="font-bold text-base text-gray-800">{{ $loginProgramCode }}</p>
                    <p class="text-xs text-gray-400">Your current session will be replaced.</p>
                </div>
                <div class="flex border-t">
                    <button wire:click="$set('confirmLoginModal', false)"
                        class="flex-1 py-3 text-sm text-gray-500 hover:bg-gray-50 transition-colors border-r">
                        Cancel
                    </button>
                    <button wire:click="loginAsProgram" wire:loading.attr="disabled"
                        class="flex-1 py-3 text-sm font-semibold text-purple-600 hover:bg-purple-50 transition-colors">
                        <span wire:loading.remove wire:target="loginAsProgram">Confirm</span>
                        <span wire:loading wire:target="loginAsProgram">...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom Sheet: Add Study Program --}}
    <div x-data="{ open: @entangle('createProgramModal') }" x-cloak>
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40 z-40" @click="open = false"></div>
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute bottom-0 left-0 right-0 z-50 bg-white rounded-t-2xl shadow-xl flex flex-col"
            style="max-height: 90%;"
        >
            {{-- Handle --}}
            <div class="flex justify-center pt-3 pb-1 shrink-0">
                <div class="w-10 h-1 bg-gray-300 rounded-full"></div>
            </div>
            {{-- Title --}}
            <div class="px-5 py-3 border-b shrink-0">
                <h3 class="font-semibold text-base text-gray-800">Add Study Program</h3>
            </div>
            {{-- Scrollable Content --}}
            <div class="overflow-y-auto flex-1 px-5 py-4 space-y-4">
                <x-input label="Code *" wire:model="code" placeholder="e.g. IF" />
                <div class="grid grid-cols-2 gap-3">
                    <x-input label="Abbreviation *" wire:model="abbrev" placeholder="e.g. TIF" />
                    <x-select label="Level *" wire:model="levelId" placeholder="--"
                        :options="$levels" option-value="id" option-label="code" />
                </div>
                <x-input label="Program Name *" wire:model="name" placeholder="e.g. Informatics Engineering" />
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <x-choices-offline label="Faculty *" wire:model="facultyId"
                            :options="$faculties" option-value="id" option-label="name"
                            placeholder="Search faculty..." single searchable clearable />
                    </div>
                    <x-button label="+ New" class="btn-ghost btn-sm mb-0.5 text-purple-600" wire:click="$set('createFacultyModal', true)" />
                </div>
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <x-choices-offline label="Cluster *" wire:model="clusterId"
                            :options="$clusters" option-value="id" option-label="name"
                            placeholder="Search cluster..." single searchable clearable />
                    </div>
                    <x-button label="+ New" class="btn-ghost btn-sm mb-0.5 text-purple-600" wire:click="$set('createClusterModal', true)" />
                </div>
                <x-choices-offline
                    label="Program Head *"
                    wire:model="headId"
                    :options="$staffs->map(fn($s) => ['id' => $s->id, 'name' => $s->code . ' — ' . $s->first_name . ' ' . $s->last_name])"
                    option-value="id"
                    option-label="name"
                    placeholder="Search staff..."
                    single
                    searchable
                />
            </div>
            {{-- Actions --}}
            <div class="px-5 py-4 border-t flex gap-2 shrink-0 pb-20">
                <x-button label="Cancel" class="flex-1" wire:click="$set('createProgramModal', false)" />
                <x-button label="Save" class="btn-primary flex-1" wire:click="saveProgram" spinner />
            </div>
        </div>
    </div>

    {{-- Bottom Sheet: Add Faculty --}}
    <div x-data="{ open: @entangle('createFacultyModal') }" x-cloak>
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40 z-40" @click="open = false"></div>
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute bottom-0 left-0 right-0 z-50 bg-white rounded-t-2xl shadow-xl"
        >
            <div class="flex justify-center pt-3 pb-1">
                <div class="w-10 h-1 bg-gray-300 rounded-full"></div>
            </div>
            <div class="px-5 py-3 border-b">
                <h3 class="font-semibold text-base text-gray-800">Add Faculty</h3>
            </div>
            <div class="px-5 py-4 space-y-3">
                <x-input label="Faculty Code *" wire:model="facultyCode" placeholder="e.g. FPTK" />
                <x-input label="Faculty Name *" wire:model="facultyName" placeholder="e.g. Faculty of Technology and Vocational Education" />
            </div>
            <div class="px-5 py-4 border-t flex gap-2 pb-20">
                <x-button label="Cancel" class="flex-1" wire:click="$set('createFacultyModal', false)" />
                <x-button label="Save" class="btn-primary flex-1" wire:click="saveFaculty" spinner />
            </div>
        </div>
    </div>

    {{-- Bottom Sheet: Add Cluster --}}
    <div x-data="{ open: @entangle('createClusterModal') }" x-cloak>
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40 z-40" @click="open = false"></div>
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
            class="absolute bottom-0 left-0 right-0 z-50 bg-white rounded-t-2xl shadow-xl"
        >
            <div class="flex justify-center pt-3 pb-1">
                <div class="w-10 h-1 bg-gray-300 rounded-full"></div>
            </div>
            <div class="px-5 py-3 border-b">
                <h3 class="font-semibold text-base text-gray-800">Add Cluster</h3>
            </div>
            <div class="px-5 py-4 space-y-3">
                <x-input label="Cluster Code *" wire:model="clusterCode" placeholder="e.g. KBK-TI" />
                <x-input label="Cluster Name *" wire:model="clusterName" placeholder="e.g. Informatics Engineering" />
            </div>
            <div class="px-5 py-4 border-t flex gap-2 pb-20">
                <x-button label="Cancel" class="flex-1" wire:click="$set('createClusterModal', false)" />
                <x-button label="Save" class="btn-primary flex-1" wire:click="saveCluster" spinner />
            </div>
        </div>
    </div>

    {{-- Bottom Sheet: Edit Program --}}
    @if($editProgramModal)
        <div class="absolute inset-0 bg-black/40 z-40" wire:click="$set('editProgramModal', false)"></div>
        <div class="absolute bottom-0 left-0 right-0 z-50 bg-white rounded-t-2xl shadow-xl flex flex-col" style="max-height:90%;">
            <div class="flex justify-center pt-3 pb-1 shrink-0">
                <div class="w-10 h-1 bg-gray-300 rounded-full"></div>
            </div>
            <div class="px-5 py-3 border-b shrink-0">
                <h3 class="font-semibold text-base text-gray-800">Edit Program <span class="text-purple-500">{{ $editCode }}</span></h3>
            </div>
            <div class="overflow-y-auto flex-1 px-5 py-4 space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <x-input label="Abbreviation *" wire:model="editAbbrev" placeholder="e.g. TIF" />
                    <div>
                        <fieldset class="fieldset py-0">
                            <legend class="fieldset-legend mb-0.5">Level *</legend>
                            <label class="select w-full">
                                <select wire:model="editLevelId">
                                    <option value="">--</option>
                                    @foreach($levels as $l)
                                        <option value="{{ $l->id }}" @selected($l->id === $editLevelId)>{{ $l->code }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </fieldset>
                    </div>
                </div>
                <x-input label="Program Name *" wire:model="editName" />
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <x-choices-offline label="Faculty *" wire:model="editFacultyId"
                            wire:key="edit-faculty-{{ $editFacultyId }}"
                            :options="$faculties" option-value="id" option-label="name"
                            placeholder="Search faculty..." single searchable clearable />
                    </div>
                    <x-button label="+ New" class="btn-ghost btn-sm mb-0.5 text-purple-600" wire:click="$set('createFacultyModal', true)" />
                </div>
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <x-choices-offline label="Cluster *" wire:model="editClusterId"
                            wire:key="edit-cluster-{{ $editClusterId }}"
                            :options="$clusters" option-value="id" option-label="name"
                            placeholder="Search cluster..." single searchable clearable />
                    </div>
                    <x-button label="+ New" class="btn-ghost btn-sm mb-0.5 text-purple-600" wire:click="$set('createClusterModal', true)" />
                </div>
                <x-choices-offline
                    label="Program Head *"
                    wire:model="editHeadId"
                    wire:key="edit-head-{{ $editHeadId }}"
                    :options="$staffs->map(fn($s) => ['id' => $s->id, 'name' => $s->code . ' — ' . $s->first_name . ' ' . $s->last_name])"
                    option-value="id"
                    option-label="name"
                    placeholder="Search staff..."
                    single
                    searchable
                    clearable
                />
            </div>
            <div class="px-5 py-4 border-t flex gap-2 shrink-0 pb-20">
                <x-button label="Cancel" class="flex-1" wire:click="$set('editProgramModal', false)" />
                <x-button label="Update" class="btn-primary flex-1" wire:click="updateProgram" spinner />
            </div>
        </div>
    @endif
</div>
