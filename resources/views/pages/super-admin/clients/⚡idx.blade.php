<?php

use App\Models\ArSys\Client;
use App\Models\ArSys\Faculty;
use App\Models\ArSys\University;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Clients')] class extends Component
{
    use WithPagination, Toast;

    public string $search = '';

    // ── Client sheet ──────────────────────────────────────────────────────────
    public bool   $clientSheet  = false;
    public bool   $deleteModal  = false;
    public ?int   $editId       = null;
    public ?int   $deleteId     = null;
    public string $description  = '';
    public ?int   $universityId = null;
    public ?int   $facultyId    = null;

    // ── University management sheet ───────────────────────────────────────────
    public bool   $uniSheet   = false;
    public ?int   $editUniId  = null;
    public string $uniCode    = '';
    public string $uniName    = '';

    // ── Faculty management sheet ──────────────────────────────────────────────
    public bool   $facSheet      = false;
    public ?int   $editFacId     = null;
    public string $facCode       = '';
    public string $facName       = '';
    public ?int   $facUniFilter  = null;

    public function updatedSearch(): void        { $this->resetPage(); }
    public function updatedUniversityId(): void  { $this->facultyId = null; }
    public function updatedFacUniFilter(): void  { $this->editFacId = null; $this->facCode = ''; $this->facName = ''; }

    // ─── Client CRUD ──────────────────────────────────────────────────────────

    public function openCreate(): void
    {
        $this->reset(['editId', 'description', 'universityId', 'facultyId']);
        $this->resetValidation();
        $this->clientSheet = true;
    }

    public function openEdit(int $id): void
    {
        $client             = Client::findOrFail($id);
        $this->editId       = $id;
        $this->description  = $client->description;
        $this->universityId = $client->university_id;
        $this->facultyId    = $client->faculty_id;
        $this->resetValidation();
        $this->clientSheet  = true;
    }

    public function save(): void
    {
        $this->validate([
            'description'  => 'required|string|max:100',
            'universityId' => 'nullable|exists:arsys_institution_university,id',
            'facultyId'    => 'nullable|exists:arsys_institution_faculty,id',
        ], [], [
            'description'  => 'Client Name',
            'universityId' => 'University',
            'facultyId'    => 'Faculty',
        ]);

        $data = [
            'description'   => $this->description,
            'university_id' => $this->universityId,
            'faculty_id'    => $this->facultyId,
        ];

        $this->editId
            ? Client::findOrFail($this->editId)->update($data)
            : Client::create($data);

        $this->success($this->editId ? 'Client updated.' : 'Client added.');
        $this->clientSheet = false;
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId    = $id;
        $this->deleteModal = true;
    }

    public function delete(): void
    {
        Client::destroy($this->deleteId);
        $this->deleteModal = false;
        $this->warning('Client deleted.');
    }

    // ─── University management ────────────────────────────────────────────────

    public function openUniSheet(): void
    {
        $this->reset(['editUniId', 'uniCode', 'uniName']);
        $this->resetValidation(['uniCode', 'uniName']);
        $this->uniSheet = true;
    }

    public function editUni(int $id): void
    {
        $uni = University::findOrFail($id);
        $this->editUniId = $id;
        $this->uniCode   = $uni->code;
        $this->uniName   = $uni->description;
    }

    public function cancelEditUni(): void
    {
        $this->reset(['editUniId', 'uniCode', 'uniName']);
    }

    public function saveUniversity(): void
    {
        $uniqueRule = $this->editUniId
            ? 'unique:arsys_institution_university,code,' . $this->editUniId
            : 'unique:arsys_institution_university,code';

        $this->validate([
            'uniCode' => ['required', $uniqueRule],
            'uniName' => 'required',
        ], [], ['uniCode' => 'Code', 'uniName' => 'Name']);

        $data = ['code' => strtoupper($this->uniCode), 'description' => $this->uniName];

        if ($this->editUniId) {
            University::findOrFail($this->editUniId)->update($data);
            $this->success('University updated.');
        } else {
            $uni = University::create($data);
            $this->universityId = $uni->id; // auto-select in client sheet
            $this->success('University added.');
        }

        $this->reset(['editUniId', 'uniCode', 'uniName']);
    }

    public function deleteUniversity(int $id): void
    {
        University::destroy($id);
        if ($this->universityId === $id) {
            $this->universityId = null;
            $this->facultyId    = null;
        }
        $this->warning('University deleted.');
    }

    // ─── Faculty management ───────────────────────────────────────────────────

    public function openFacSheet(): void
    {
        $this->reset(['editFacId', 'facCode', 'facName']);
        $this->resetValidation(['facUniFilter', 'facCode', 'facName']);
        $this->facUniFilter = $this->universityId;
        $this->facSheet = true;
    }

    public function editFac(int $id): void
    {
        $fac = Faculty::findOrFail($id);
        $this->editFacId    = $id;
        $this->facCode      = $fac->code;
        $this->facName      = $fac->name;
        $this->facUniFilter = $fac->university_id;
    }

    public function cancelEditFac(): void
    {
        $this->reset(['editFacId', 'facCode', 'facName']);
    }

    public function saveFaculty(): void
    {
        $this->validate([
            'facUniFilter' => 'required|exists:arsys_institution_university,id',
            'facCode'      => 'required',
            'facName'      => 'required',
        ], [], ['facUniFilter' => 'University', 'facCode' => 'Code', 'facName' => 'Name']);

        $data = [
            'code'          => strtoupper($this->facCode),
            'name'          => $this->facName,
            'university_id' => $this->facUniFilter,
        ];

        if ($this->editFacId) {
            Faculty::findOrFail($this->editFacId)->update($data);
            $this->success('Faculty updated.');
        } else {
            $fac = Faculty::create($data);
            if ($this->facUniFilter === $this->universityId) {
                $this->facultyId = $fac->id; // auto-select in client sheet
            }
            $this->success('Faculty added.');
        }

        $this->reset(['editFacId', 'facCode', 'facName']);
    }

    public function deleteFaculty(int $id): void
    {
        Faculty::destroy($id);
        if ($this->facultyId === $id) {
            $this->facultyId = null;
        }
        $this->warning('Faculty deleted.');
    }

    // ─── Data ─────────────────────────────────────────────────────────────────

    public function with(): array
    {
        $search = trim($this->search);

        $clients = Client::with(['university', 'faculty'])
            ->when($search, fn($q) => $q->where('description', 'like', "%{$search}%"))
            ->orderBy('description')
            ->paginate(10);

        $universities = University::orderBy('code')->get();

        $faculties = $this->universityId
            ? Faculty::where('university_id', $this->universityId)->orderBy('code')->get()
            : collect();

        $sheetFaculties = $this->facUniFilter
            ? Faculty::where('university_id', $this->facUniFilter)->orderBy('code')->get()
            : collect();

        return compact('clients', 'universities', 'faculties', 'sheetFaculties');
    }
};
?>

<div>
    {{-- Search + Add --}}
    <div class="flex items-center gap-2 px-3 pt-3 pb-2">
        <input wire:model.live.debounce.300ms="search" type="search"
               placeholder="Search client..."
               class="flex-1 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:outline-none focus:border-purple-400" />
        <button wire:click="openCreate"
                class="shrink-0 flex items-center gap-1 px-3 py-2 rounded-xl bg-purple-600 text-white text-xs font-semibold shadow-sm hover:bg-purple-700 active:scale-95 transition-all">
            <x-icon name="o-plus" class="h-4 w-4" /> Add
        </button>
    </div>

    {{-- Client List --}}
    <div class="px-3 pb-3 space-y-2">
        @forelse($clients as $client)
            <div class="rounded-xl bg-white shadow-sm border-l-4 border-purple-400 p-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm text-gray-800">{{ $client->description }}</p>
                        <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                            @if($client->university)
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700">
                                    {{ $client->university->code }}
                                </span>
                            @endif
                            @if($client->faculty)
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700">
                                    {{ $client->faculty->code }}
                                </span>
                            @endif
                        </div>
                        @if($client->university || $client->faculty)
                            <p class="text-[11px] text-gray-400 mt-0.5 truncate">
                                {{ $client->university?->description }}@if($client->university && $client->faculty) · @endif{{ $client->faculty?->name }}
                            </p>
                        @endif
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button wire:click="openEdit({{ $client->id }})"
                                class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 transition-colors">
                            <x-icon name="o-pencil" class="h-4 w-4" />
                        </button>
                        <button wire:click="confirmDelete({{ $client->id }})"
                                class="p-1.5 rounded-lg hover:bg-red-50 text-red-400 transition-colors">
                            <x-icon name="o-trash" class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="py-16 text-center">
                <x-icon name="o-building-office-2" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No clients yet.</p>
            </div>
        @endforelse
    </div>

    @if($clients->hasPages())
        <div class="px-3 pb-4">{{ $clients->links() }}</div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════════
         CLIENT SHEET
    ════════════════════════════════════════════════════════════════════════ --}}
    <div x-show="$wire.clientSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('clientSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-show="$wire.clientSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl">
        <div class="flex justify-center pt-3 pb-1"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-purple-100">
                    <x-icon name="o-building-office-2" class="h-4 w-4 text-purple-600" />
                </div>
                <h3 class="text-base font-bold text-purple-700">{{ $editId ? 'Edit Client' : 'Add Client' }}</h3>
            </div>
            <button wire:click="$set('clientSheet', false)" class="flex items-center justify-center h-7 w-7 rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>

        <div class="px-4 py-4 space-y-4">

            {{-- Client Name --}}
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Client Name <span class="text-red-500">*</span></label>
                <input wire:model="description" type="text" placeholder="e.g. Informatics Engineering"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-purple-400" />
                @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- University --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-gray-500">University</label>
                    <button wire:click="openUniSheet"
                            class="text-[10px] font-semibold text-purple-600 flex items-center gap-0.5 hover:text-purple-800">
                        <x-icon name="o-cog-6-tooth" class="h-3 w-3" /> Manage
                    </button>
                </div>
                <select wire:model.live="universityId"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-purple-400">
                    <option value="">— Select university —</option>
                    @foreach($universities as $uni)
                        <option value="{{ $uni->id }}">{{ $uni->code }} — {{ $uni->description }}</option>
                    @endforeach
                </select>
                @error('universityId') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Faculty --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-xs font-semibold text-gray-500">Faculty</label>
                    <button wire:click="openFacSheet"
                            class="text-[10px] font-semibold text-indigo-600 flex items-center gap-0.5 hover:text-indigo-800">
                        <x-icon name="o-cog-6-tooth" class="h-3 w-3" /> Manage
                    </button>
                </div>
                <select wire:model="facultyId"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-purple-400 {{ !$universityId ? 'opacity-50' : '' }}"
                    {{ !$universityId ? 'disabled' : '' }}>
                    <option value="">{{ $universityId ? '— Select faculty —' : '— Select university first —' }}</option>
                    @foreach($faculties as $fac)
                        <option value="{{ $fac->id }}">{{ $fac->code }} — {{ $fac->name }}</option>
                    @endforeach
                </select>
            </div>

        </div>

        <div class="flex gap-3 px-4 pb-24 pt-2 border-t border-gray-100">
            <button wire:click="$set('clientSheet', false)"
                class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
            <button wire:click="save" wire:loading.attr="disabled"
                class="flex-1 py-3 rounded-xl bg-purple-600 text-white text-sm font-bold hover:bg-purple-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save" class="loading loading-spinner loading-sm"></span>
            </button>
        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <x-modal wire:model="deleteModal" title="Delete Client?" box-class="w-[calc(100vw-2rem)] max-w-sm" separator>
        <div class="flex items-center gap-3 py-1">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                <x-icon name="o-trash" class="h-5 w-5 text-red-500" />
            </div>
            <p class="text-sm text-gray-600">This client will be permanently deleted. This action cannot be undone.</p>
        </div>
        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('deleteModal', false)" />
            <x-button label="Delete" class="btn-error" wire:click="delete" wire:loading.attr="disabled" spinner="delete" />
        </x-slot:actions>
    </x-modal>

    {{-- ════════════════════════════════════════════════════════════════════════
         UNIVERSITY MANAGEMENT SHEET  (z-50, above client sheet)
    ════════════════════════════════════════════════════════════════════════ --}}
    <div x-show="$wire.uniSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('uniSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-show="$wire.uniSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl">

        <div class="flex justify-center pt-3 pb-1"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>

        {{-- Sheet header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-purple-100">
                    <x-icon name="o-building-library" class="h-4 w-4 text-purple-600" />
                </div>
                <h3 class="text-base font-bold text-purple-700">Manage Universities</h3>
            </div>
            <button wire:click="$set('uniSheet', false)" class="flex items-center justify-center h-7 w-7 rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>

        {{-- University list --}}
        <div class="overflow-y-auto max-h-[40vh] divide-y divide-gray-100">
            @forelse($universities as $uni)
                <div class="flex items-center gap-2 px-4 py-2.5">
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-semibold text-gray-800">{{ $uni->code }}</span>
                        <span class="text-xs text-gray-400 ml-1.5 truncate">{{ $uni->description }}</span>
                    </div>
                    <button wire:click="editUni({{ $uni->id }})"
                            class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 transition-colors">
                        <x-icon name="o-pencil" class="h-3.5 w-3.5" />
                    </button>
                    <button wire:click="deleteUniversity({{ $uni->id }})"
                            wire:confirm="Delete university {{ $uni->code }}?"
                            class="p-1.5 rounded-lg hover:bg-red-50 text-red-400 transition-colors">
                        <x-icon name="o-trash" class="h-3.5 w-3.5" />
                    </button>
                </div>
            @empty
                <div class="py-8 text-center text-xs text-gray-400">No universities yet.</div>
            @endforelse
        </div>

        {{-- Add / Edit form --}}
        <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
            <p class="text-[10px] font-bold uppercase tracking-wider text-purple-600 mb-2">
                {{ $editUniId ? 'Edit University' : 'Add University' }}
            </p>
            <div class="flex gap-2">
                <input wire:model="uniCode" type="text" placeholder="Code"
                    class="w-20 rounded-lg border border-gray-200 bg-white px-2 py-2 text-sm focus:outline-none focus:border-purple-400" />
                <input wire:model="uniName" type="text" placeholder="University name"
                    class="flex-1 rounded-lg border border-gray-200 bg-white px-2 py-2 text-sm focus:outline-none focus:border-purple-400" />
                <button wire:click="saveUniversity" wire:loading.attr="disabled"
                    class="shrink-0 px-3 py-2 rounded-lg bg-purple-600 text-white text-xs font-bold hover:bg-purple-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="saveUniversity">Save</span>
                    <span wire:loading wire:target="saveUniversity" class="loading loading-spinner loading-xs"></span>
                </button>
            </div>
            <div class="flex gap-3 mt-1">
                @error('uniCode') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                @error('uniName') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                @if($editUniId)
                    <button wire:click="cancelEditUni" class="text-[11px] text-gray-400 hover:text-gray-600 ml-auto">Cancel edit</button>
                @endif
            </div>
        </div>

        <div class="pb-24"></div>
    </div>

    {{-- ════════════════════════════════════════════════════════════════════════
         FACULTY MANAGEMENT SHEET  (z-50, above client sheet)
    ════════════════════════════════════════════════════════════════════════ --}}
    <div x-show="$wire.facSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('facSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-show="$wire.facSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl">

        <div class="flex justify-center pt-3 pb-1"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>

        {{-- Sheet header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100">
                    <x-icon name="o-building-office" class="h-4 w-4 text-indigo-600" />
                </div>
                <h3 class="text-base font-bold text-indigo-700">Manage Faculties</h3>
            </div>
            <button wire:click="$set('facSheet', false)" class="flex items-center justify-center h-7 w-7 rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>

        {{-- University filter --}}
        <div class="px-4 pt-3 pb-2">
            <select wire:model.live="facUniFilter"
                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm focus:outline-none focus:border-indigo-400">
                <option value="">— All universities —</option>
                @foreach($universities as $uni)
                    <option value="{{ $uni->id }}">{{ $uni->code }} — {{ $uni->description }}</option>
                @endforeach
            </select>
            @error('facUniFilter') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        {{-- Faculty list --}}
        <div class="overflow-y-auto max-h-[32vh] divide-y divide-gray-100">
            @forelse($sheetFaculties as $fac)
                <div class="flex items-center gap-2 px-4 py-2.5">
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-semibold text-gray-800">{{ $fac->code }}</span>
                        <span class="text-xs text-gray-400 ml-1.5 truncate">{{ $fac->name }}</span>
                    </div>
                    <button wire:click="editFac({{ $fac->id }})"
                            class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 transition-colors">
                        <x-icon name="o-pencil" class="h-3.5 w-3.5" />
                    </button>
                    <button wire:click="deleteFaculty({{ $fac->id }})"
                            wire:confirm="Delete faculty {{ $fac->code }}?"
                            class="p-1.5 rounded-lg hover:bg-red-50 text-red-400 transition-colors">
                        <x-icon name="o-trash" class="h-3.5 w-3.5" />
                    </button>
                </div>
            @empty
                <div class="py-6 text-center text-xs text-gray-400">
                    {{ $facUniFilter ? 'No faculties for this university.' : 'Select a university to view faculties.' }}
                </div>
            @endforelse
        </div>

        {{-- Add / Edit form --}}
        <div class="px-4 py-3 border-t border-gray-100 bg-gray-50">
            <p class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 mb-2">
                {{ $editFacId ? 'Edit Faculty' : 'Add Faculty' }}
            </p>
            <div class="flex gap-2">
                <input wire:model="facCode" type="text" placeholder="Code"
                    class="w-20 rounded-lg border border-gray-200 bg-white px-2 py-2 text-sm focus:outline-none focus:border-indigo-400" />
                <input wire:model="facName" type="text" placeholder="Faculty name"
                    class="flex-1 rounded-lg border border-gray-200 bg-white px-2 py-2 text-sm focus:outline-none focus:border-indigo-400" />
                <button wire:click="saveFaculty" wire:loading.attr="disabled"
                    class="shrink-0 px-3 py-2 rounded-lg bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="saveFaculty">Save</span>
                    <span wire:loading wire:target="saveFaculty" class="loading loading-spinner loading-xs"></span>
                </button>
            </div>
            <div class="flex gap-3 mt-1">
                @error('facCode') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                @error('facName') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                @if($editFacId)
                    <button wire:click="cancelEditFac" class="text-[11px] text-gray-400 hover:text-gray-600 ml-auto">Cancel edit</button>
                @endif
            </div>
        </div>

        <div class="pb-24"></div>
    </div>

    <x-toast />
</div>
