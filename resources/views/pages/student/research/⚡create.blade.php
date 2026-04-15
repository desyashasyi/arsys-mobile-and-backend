<?php

use App\Models\ArSys\AcademicYear;
use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchConfig;
use App\Models\ArSys\ResearchConfigBase;
use App\Models\ArSys\ResearchFile;
use App\Models\ArSys\ResearchFiletype;
use App\Models\ArSys\ResearchLog;
use App\Models\ArSys\ResearchLogType;
use App\Models\ArSys\ResearchMilestone;
use App\Models\ArSys\ResearchMilestoneLog;
use App\Models\ArSys\ResearchType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('New Research')] class extends Component
{
    use Toast, WithFileUploads;

    public ?int    $researchTypeCreate = null;
    public string  $title              = '';
    public string  $abstract           = '';
    public         $file               = null;
    public string  $proposalUrl        = '';

    private function student()
    {
        return Auth::user()->student;
    }

    private function requireFile(): bool
    {
        $student = $this->student();
        if (!$student) return false;

        $cfg = ResearchConfig::where('program_id', $student->program_id)
            ->where('config_base_id', ResearchConfigBase::where('code', 'RESEARCH_FILE')->value('id'))
            ->first();

        return $cfg?->status == 1;
    }

    public function save(): void
    {
        $student = $this->student();
        if (!$student) {
            $this->error('Student profile not found.');
            return;
        }

        $rules = [
            'researchTypeCreate' => 'required|integer',
            'title'              => 'required|string|max:500',
            'abstract'           => 'required|string',
        ];

        if ($this->requireFile()) {
            $rules['file'] = 'required|mimetypes:application/pdf|max:10000';
        } else {
            $rules['proposalUrl'] = 'required|url';
        }

        $this->validate($rules);

        $typeRow = ResearchType::where('program_id', $student->program_id)
            ->where('id', $this->researchTypeCreate)
            ->with('data.model')
            ->first();

        if (!$typeRow) {
            $this->error('Research type not found.');
            return;
        }

        $researchCounter = Research::where('type_id', $this->researchTypeCreate)
            ->where('student_id', $student->id)
            ->count();

        $code = $typeRow->data->code . '-' . $student->number . '-' . ($researchCounter + 1);

        $milestone = ResearchMilestone::where('research_model_id', $typeRow->data->research_model_id)
            ->where('sequence', 1)
            ->first();

        $research = Research::create([
            'student_id'       => $student->id,
            'title'            => $this->title,
            'abstract'         => $this->abstract,
            'type_id'          => $this->researchTypeCreate,
            'milestone_id'     => $milestone?->id,
            'code'             => $code,
            'academic_year_id' => AcademicYear::latest()->value('id'),
        ]);

        ResearchMilestoneLog::create([
            'research_id'       => $research->id,
            'research_model_id' => $typeRow->data->research_model_id,
            'milestone_id'      => $milestone?->id,
        ]);

        if ($this->requireFile() && $this->file) {
            $filename = $this->file->storeAs(
                'proposal',
                $student->first_name . '-' . $research->id . '-proposal.pdf',
                'public'
            );
            ResearchFile::create([
                'research_id' => $research->id,
                'file_type'   => ResearchFiletype::where('code', 'PRO')->value('id'),
                'filename'    => $filename,
            ]);
        } else {
            $research->update(['file' => $this->proposalUrl]);
        }

        ResearchLog::create([
            'research_id' => $research->id,
            'loger_id'    => Auth::id(),
            'type_id'     => ResearchLogType::where('code', 'CRE')->value('id'),
            'message'     => ResearchLogType::where('code', 'CRE')->value('description'),
            'status'      => 1,
        ]);

        $this->success('Research proposal created.');
        $this->redirect(route('student.research'), navigate: true);
    }

    public function with(): array
    {
        $student = $this->student();

        $types = $student
            ? ResearchType::where('program_id', $student->program_id)
                ->whereHas('data', fn($q) => $q->where('level_id', $student->program?->level_id))
                ->where('status', 1)
                ->with('data')
                ->get()
            : collect();

        return [
            'types'       => $types,
            'requireFile' => $this->requireFile(),
        ];
    }
};
?>

<div class="px-4 py-4 pb-24 space-y-4">

    <div class="rounded-2xl bg-white shadow-sm p-4 space-y-4">

        <div>
            <label class="text-xs font-semibold text-gray-500 mb-1.5 block">
                Research Type <span class="text-red-400">*</span>
            </label>
            <select wire:model="researchTypeCreate"
                    class="select select-bordered select-sm w-full text-sm">
                <option value="">— Please select research type —</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}">{{ $type->data?->code }} — {{ $type->data?->description }}</option>
                @endforeach
            </select>
            @error('researchTypeCreate') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            @if($types->isEmpty())
                <p class="text-xs text-amber-500 mt-1">No research types available for your program.</p>
            @endif
        </div>

        <div>
            <label class="text-xs font-semibold text-gray-500 mb-1.5 block">
                Title <span class="text-red-400">*</span>
            </label>
            <textarea wire:model="title" rows="3"
                      placeholder="Insert research title..."
                      class="textarea textarea-bordered textarea-sm w-full text-sm resize-none"></textarea>
            @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="text-xs font-semibold text-gray-500 mb-1.5 block">
                Abstract <span class="text-red-400">*</span>
            </label>
            <textarea wire:model="abstract" rows="5"
                      placeholder="Insert abstract..."
                      class="textarea textarea-bordered textarea-sm w-full text-sm resize-none"></textarea>
            @error('abstract') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
        </div>

        @if($requireFile)
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">
                    Proposal File (PDF) <span class="text-red-400">*</span>
                </label>
                <input type="file" wire:model="file" accept="application/pdf"
                       class="file-input file-input-bordered file-input-sm w-full text-sm" />
                <div wire:loading wire:target="file" class="text-xs text-purple-500 mt-1">Uploading...</div>
                @error('file') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
        @else
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">
                    Proposal URL <span class="text-red-400">*</span>
                </label>
                <input wire:model="proposalUrl" type="url"
                       placeholder="https://drive.google.com/..."
                       class="input input-bordered input-sm w-full text-sm" />
                @error('proposalUrl') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                <p class="text-[11px] text-gray-400 mt-1 leading-snug">
                    Upload ke Google Drive dan pastikan file dapat diakses publik, lalu tempel URL-nya.
                </p>
            </div>
        @endif

    </div>

    <div class="flex gap-3">
        <a href="{{ route('student.research') }}"
           class="flex-1 btn btn-ghost">Cancel</a>
        <button wire:click="save"
                wire:loading.attr="disabled" wire:target="save"
                class="flex-1 btn btn-primary">
            <span wire:loading.remove wire:target="save">Submit Proposal</span>
            <span wire:loading wire:target="save">Saving...</span>
        </button>
    </div>

</div>
