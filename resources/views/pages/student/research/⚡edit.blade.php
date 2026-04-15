<?php

use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchLog;
use App\Models\ArSys\ResearchLogType;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Edit Research')] class extends Component
{
    use Toast;

    public int    $researchId = 0;
    public string $title      = '';
    public string $abstract   = '';
    public string $fileUrl    = '';

    public function mount(int $id): void
    {
        $this->researchId = $id;

        $research = Research::where('id', $id)
            ->where('student_id', auth()->user()->student?->id ?? 0)
            ->first();

        if (!$research) {
            $this->redirect(route('student.research'), navigate: true);
            return;
        }

        $this->title    = $research->title ?? '';
        $this->abstract = $research->abstract ?? '';
        $this->fileUrl  = $research->file ?? '';
    }

    public function save(): void
    {
        $this->validate([
            'title'    => 'required|string|max:500',
            'abstract' => 'required|string',
            'fileUrl'  => 'nullable|url',
        ]);

        $research = Research::where('id', $this->researchId)
            ->where('student_id', auth()->user()->student?->id ?? 0)
            ->first();

        if (!$research) {
            $this->error('Research not found.', position: 'toast-bottom');
            return;
        }

        DB::transaction(function () use ($research) {
            $research->update([
                'title'    => $this->title,
                'abstract' => $this->abstract,
                'file'     => $this->fileUrl ?: $research->file,
            ]);

            $updType = ResearchLogType::where('code', 'UPD')->first();
            if ($updType) {
                ResearchLog::create([
                    'research_id' => $research->id,
                    'type_id'     => $updType->id,
                    'loger_id'    => auth()->id(),
                    'message'     => $updType->description,
                    'status'      => 1,
                ]);
            }
        });

        $this->success('Research updated.', position: 'toast-bottom');
        $this->redirect(route('student.research.show', $this->researchId), navigate: true);
    }
};
?>

<div class="pb-6">

    <div class="mx-4 mt-4 space-y-4">

        {{-- Title --}}
        <div>
            <label class="mb-1 block text-xs font-semibold text-gray-500">Title <span class="text-red-400">*</span></label>
            <textarea wire:model="title" rows="3"
                      placeholder="Enter research title..."
                      class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-400 resize-none"></textarea>
            @error('title')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- Abstract --}}
        <div>
            <label class="mb-1 block text-xs font-semibold text-gray-500">Abstract <span class="text-red-400">*</span></label>
            <textarea wire:model="abstract" rows="6"
                      placeholder="Enter abstract..."
                      class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-400 resize-none"></textarea>
            @error('abstract')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- File URL --}}
        <div>
            <label class="mb-1 block text-xs font-semibold text-gray-500">Document URL <span class="text-gray-300">(optional)</span></label>
            <input wire:model="fileUrl" type="url"
                   placeholder="https://drive.google.com/..."
                   class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-800 shadow-sm focus:border-purple-400 focus:outline-none focus:ring-1 focus:ring-purple-400" />
            <p class="mt-1 text-[11px] text-gray-400">Ensure the file is publicly accessible (Google Drive, etc.)</p>
            @error('fileUrl')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        {{-- Buttons --}}
        <div class="flex gap-3 pt-2">
            <a href="{{ route('student.research.show', $researchId) }}"
               class="flex-1 rounded-full border border-gray-200 bg-white py-3 text-center text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                Cancel
            </a>
            <button wire:click="save" wire:loading.attr="disabled"
                    class="flex-1 rounded-full bg-purple-600 py-3 text-sm font-semibold text-white shadow-sm hover:bg-purple-700 transition-colors disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save Changes</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>

    </div>

</div>
