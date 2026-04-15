<?php

use App\Models\ArSys\InstitutionRole;
use App\Models\ArSys\ResearchConfig;
use App\Models\ArSys\InstitutionConfig;
use App\Models\ArSys\Specialization;
use App\Models\ArSys\Staff;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Config')] class extends Component
{
    use Toast;

    // Specialization
    public bool   $addSpecSheet  = false;
    public bool   $editSpecSheet = false;
    public ?int   $editSpecId    = null;
    public string $specCode      = '';
    public string $specDesc      = '';
    public ?int   $specHeadId    = null;

    private function program()
    {
        return InstitutionRole::where('user_id', auth()->id())->first()?->program;
    }

    // ─── Specialization ───────────────────────────────────────────────────────

    public function openAddSpec(): void
    {
        $this->reset(['specCode', 'specDesc', 'specHeadId']);
        $this->addSpecSheet = true;
    }

    public function saveSpec(): void
    {
        $this->validate([
            'specCode' => 'required|string|max:5',
            'specDesc' => 'required|string|max:50',
        ]);

        $program = $this->program();
        Specialization::create([
            'code'        => strtoupper($this->specCode),
            'description' => $this->specDesc,
            'program_id'  => $program->id,
            'staff_id'    => $this->specHeadId,
        ]);

        $this->reset(['specCode', 'specDesc', 'specHeadId']);
        $this->addSpecSheet = false;
        $this->success('Specialization added.');
    }

    public function openEditSpec(int $id): void
    {
        $spec = Specialization::findOrFail($id);
        $this->editSpecId  = $id;
        $this->specCode    = $spec->code;
        $this->specDesc    = $spec->description;
        $this->specHeadId  = $spec->staff_id;
        $this->editSpecSheet = true;
    }

    public function updateSpec(): void
    {
        $this->validate([
            'specCode' => 'required|string|max:5',
            'specDesc' => 'required|string|max:50',
        ]);

        Specialization::findOrFail($this->editSpecId)->update([
            'code'        => strtoupper($this->specCode),
            'description' => $this->specDesc,
            'staff_id'    => $this->specHeadId,
        ]);

        $this->reset(['editSpecId', 'specCode', 'specDesc', 'specHeadId']);
        $this->editSpecSheet = false;
        $this->success('Specialization updated.');
    }

    public function deleteSpec(int $id): void
    {
        $program = $this->program();
        $spec = Specialization::where('program_id', $program?->id)->findOrFail($id);
        $spec->delete();
        $this->success('Specialization deleted.');
    }

    // ─── Research Config toggle ────────────────────────────────────────────────

    public function toggleResearchConfig(int $id): void
    {
        $cfg = ResearchConfig::findOrFail($id);
        $cfg->update(['status' => !$cfg->status]);
    }

    // ─── Institution Config toggle ─────────────────────────────────────────────

    public function toggleInstitutionConfig(int $id): void
    {
        $cfg = InstitutionConfig::findOrFail($id);
        $cfg->update(['status' => !$cfg->status]);
    }

    public function with(): array
    {
        $program = $this->program();
        return [
            'program'       => $program,
            'specializations' => Specialization::with('head')
                ->where('program_id', $program?->id)
                ->orderBy('code')->get(),
            'researchConfigs' => ResearchConfig::with('data')
                ->where('program_id', $program?->id)->get(),
            'institutionConfigs' => InstitutionConfig::with('data')
                ->where('program_id', $program?->id)->get(),
            'staffs' => Staff::where('program_id', $program?->id)->orderBy('code')->get(),
        ];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">{{ $program?->code }} — {{ $program?->name }}</p>

    <div class="px-3 py-3 space-y-4 pb-24">

        {{-- ─── Specialization ─────────────────────────────────────────── --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Specializations</span>
                <button wire:click="openAddSpec"
                    class="flex items-center gap-1 text-xs text-purple-600 font-medium hover:text-purple-800">
                    <x-icon name="o-plus-circle" class="w-4 h-4" /> Add
                </button>
            </div>
            <div class="space-y-2">
                @forelse($specializations as $spec)
                    <div class="bg-white rounded-xl shadow-sm border-l-4 border-purple-300 p-3 flex items-center justify-between">
                        <div>
                            <span class="font-mono font-bold text-sm text-gray-800">{{ $spec->code }}</span>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $spec->description }}</p>
                            @if($spec->head)
                                <div class="flex items-center gap-1 mt-1">
                                    <x-icon name="o-user-circle" class="w-3 h-3 text-purple-400" />
                                    <span class="text-[10px] text-gray-400">{{ $spec->head->first_name }} {{ $spec->head->last_name }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center gap-1">
                            <x-button icon="o-pencil-square" class="btn-xs btn-ghost text-gray-400"
                                wire:click="openEditSpec({{ $spec->id }})" spinner />
                            <x-button icon="o-trash" class="btn-xs btn-ghost text-red-400"
                                wire:click="deleteSpec({{ $spec->id }})"
                                wire:confirm="Delete specialization {{ $spec->code }}?" spinner />
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-gray-400 text-center py-3">No specializations yet.</p>
                @endforelse
            </div>
        </div>

        {{-- ─── Research Config ─────────────────────────────────────────── --}}
        @if($researchConfigs->count())
        <div>
            <div class="mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Research Config</span>
            </div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden divide-y">
                @foreach($researchConfigs as $cfg)
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-gray-700">{{ $cfg->data?->description ?? $cfg->data?->code }}</span>
                        <input type="checkbox"
                            class="toggle toggle-sm toggle-primary"
                            @checked($cfg->status)
                            wire:click="toggleResearchConfig({{ $cfg->id }})" />
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ─── Institution Config ──────────────────────────────────────── --}}
        @if($institutionConfigs->count())
        <div>
            <div class="mb-2">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Institution Config</span>
            </div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden divide-y">
                @foreach($institutionConfigs as $cfg)
                    <div class="flex items-center justify-between px-4 py-3">
                        <span class="text-sm text-gray-700">{{ $cfg->data?->description ?? $cfg->data?->code }}</span>
                        <input type="checkbox"
                            class="toggle toggle-sm toggle-primary"
                            @checked($cfg->status)
                            wire:click="toggleInstitutionConfig({{ $cfg->id }})" />
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- Add Specialization Sheet --}}
    <div x-data x-show="$wire.addSpecSheet" class="fixed inset-0 z-40 flex items-end justify-center">
        <div class="absolute inset-0 bg-black/40" @click="$wire.set('addSpecSheet', false)"></div>
        <div class="relative z-50 w-full max-w-sm bg-white rounded-t-2xl shadow-xl">
            <div class="flex justify-center pt-3 pb-1"><div class="w-10 h-1 bg-gray-300 rounded-full"></div></div>
            <div class="px-5 py-3 border-b">
                <h3 class="font-semibold text-base text-gray-800">Add Specialization</h3>
            </div>
            <div class="px-5 py-4 space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <x-input label="Code *" wire:model="specCode" placeholder="e.g. KBK1" />
                    <x-input label="Description *" wire:model="specDesc" placeholder="e.g. Network" />
                </div>
                <x-select label="Head (optional)" wire:model="specHeadId" placeholder="-- None --"
                    :options="$staffs->map(fn($s) => ['id'=>$s->id,'label'=>$s->code.' — '.$s->first_name])"
                    option-value="id" option-label="label" />
            </div>
            <div class="px-5 py-4 border-t flex gap-2 pb-20">
                <x-button label="Cancel" class="flex-1" @click="$wire.set('addSpecSheet', false)" />
                <x-button label="Save" class="btn-primary flex-1" wire:click="saveSpec" spinner />
            </div>
        </div>
    </div>

    {{-- Edit Specialization Sheet --}}
    <div x-data x-show="$wire.editSpecSheet" class="fixed inset-0 z-40 flex items-end justify-center">
        <div class="absolute inset-0 bg-black/40" @click="$wire.set('editSpecSheet', false)"></div>
        <div class="relative z-50 w-full max-w-sm bg-white rounded-t-2xl shadow-xl">
            <div class="flex justify-center pt-3 pb-1"><div class="w-10 h-1 bg-gray-300 rounded-full"></div></div>
            <div class="px-5 py-3 border-b">
                <h3 class="font-semibold text-base text-gray-800">Edit Specialization</h3>
            </div>
            <div class="px-5 py-4 space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <x-input label="Code *" wire:model="specCode" />
                    <x-input label="Description *" wire:model="specDesc" />
                </div>
                <x-select label="Head (optional)" wire:model="specHeadId" placeholder="-- None --"
                    :options="$staffs->map(fn($s) => ['id'=>$s->id,'label'=>$s->code.' — '.$s->first_name])"
                    option-value="id" option-label="label" />
            </div>
            <div class="px-5 py-4 border-t flex gap-2 pb-20">
                <x-button label="Cancel" class="flex-1" @click="$wire.set('editSpecSheet', false)" />
                <x-button label="Update" class="btn-primary flex-1" wire:click="updateSpec" spinner />
            </div>
        </div>
    </div>
</div>
