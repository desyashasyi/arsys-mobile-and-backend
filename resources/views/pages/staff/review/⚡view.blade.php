<?php

use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchReview;
use App\Models\ArSys\ResearchReviewDecisionType;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('Review Detail')] class extends Component
{
    use Toast;
    public int $researchId = 0;
    public bool $confirmModal = false;
    public string $pendingDecision = '';

    public function mount(int $id): void
    {
        $this->researchId = $id;
    }

    public function openConfirm(string $decision): void
    {
        if (!in_array($decision, ['approve', 'reject'])) return;
        $this->pendingDecision = $decision;
        $this->confirmModal = true;
    }

    public function submitDecision(string $decision): void
    {
        if (!in_array($decision, ['approve', 'reject'])) return;

        $staff = auth()->user()->staff;
        if (!$staff) return;

        $review = ResearchReview::where('research_id', $this->researchId)
            ->where('reviewer_id', $staff->id)
            ->first();

        if (!$review) return;

        $decisionCode = $decision === 'approve' ? 'APP' : 'RJC';
        $decisionType = ResearchReviewDecisionType::where('code', $decisionCode)->first();
        if (!$decisionType) return;

        $review->decision_id = $decisionType->id;
        $review->approval_date = Carbon::now();
        $review->save();

        $this->confirmModal = false;
        $this->pendingDecision = '';

        $label = $decision === 'approve' ? 'Approved' : 'Rejected';
        $this->success("Decision submitted: {$label}.", position: 'toast-bottom');
    }

    public function with(): array
    {
        $research = Research::with([
            'student.program',
            'milestone',
            'reviewers.staff',
            'reviewers.decision',
            'proposalFile',
        ])->find($this->researchId);

        if (!$research) {
            return ['research' => null, 'myReview' => null];
        }

        $staff = auth()->user()->staff;
        $myReview = $staff
            ? $research->reviewers->firstWhere('reviewer_id', $staff->id)
            : null;

        return ['research' => $research, 'myReview' => $myReview];
    }
};
?>

<div>
    <p class="px-4 py-2 text-xs text-purple-600 font-medium">RESEARCH PROPOSAL REVIEW</p>

    @if(!$research)
        <div class="py-16 text-center">
            <x-icon name="o-exclamation-triangle" class="mx-auto mb-3 h-12 w-12 text-amber-300" />
            <p class="text-sm text-gray-500">Research not found.</p>
        </div>
    @else
        <div class="px-3 py-3 space-y-4">

            {{-- Student Info Card --}}
            <div class="rounded-xl bg-white shadow-sm overflow-hidden border-l-4 border-purple-400">
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <x-icon name="o-user" class="h-4 w-4 text-purple-400 shrink-0" />
                        <div>
                            <p class="text-xs font-semibold text-purple-600">{{ $research->student?->nim ?? '—' }}</p>
                            <p class="font-bold text-sm text-gray-800">{{ $research->student?->first_name }} {{ $research->student?->last_name }}</p>
                        </div>
                    </div>
                    <p class="text-xs text-gray-600 leading-snug uppercase">
                        {{ $research->title ?? 'No Title' }}
                    </p>
                    @if($research->milestone)
                        <div class="mt-2">
                            <x-milestone-chip :code="$research->milestone->code" :phase="$research->milestone->phase" />
                        </div>
                    @endif
                </div>
            </div>

            {{-- Reviewers --}}
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <x-icon name="o-users" class="h-4 w-4 text-purple-400" />
                    <h3 class="font-bold text-sm text-gray-700">Reviewers</h3>
                </div>
                <div class="rounded-xl bg-white shadow-sm p-3">
                    <div class="flex flex-wrap gap-2">
                        @foreach($research->reviewers as $reviewer)
                            @php
                                $dec = $reviewer->decision?->description;
                                $decLabel = ($dec === null || $dec === 'Not Defined') ? 'Pending' : $dec;
                                $chipClass = match($decLabel) {
                                    'Approve' => 'bg-green-500 text-white',
                                    'Reject'  => 'bg-red-500 text-white',
                                    default   => 'bg-gray-100 text-gray-600',
                                };
                                $chipIcon = match($decLabel) {
                                    'Approve' => 'o-check-circle',
                                    'Reject'  => 'o-x-circle',
                                    default   => 'o-clock',
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold {{ $chipClass }}">
                                <x-icon name="{{ $chipIcon }}" class="h-3 w-3" />
                                {{ $reviewer->staff?->code }}: {{ $decLabel }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Abstract --}}
            @if($research->abstract)
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <x-icon name="o-document-text" class="h-4 w-4 text-purple-400" />
                        <h3 class="font-bold text-sm text-gray-700">Abstract</h3>
                    </div>
                    <div class="rounded-xl bg-white shadow-sm p-4">
                        <p class="text-xs text-gray-700 leading-relaxed">{{ $research->abstract }}</p>
                    </div>
                </div>
            @endif

            {{-- Proposal File --}}
            @if($research->proposalFile->isNotEmpty())
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <x-icon name="o-paper-clip" class="h-4 w-4 text-purple-400" />
                        <h3 class="font-bold text-sm text-gray-700">File</h3>
                    </div>
                    @foreach($research->proposalFile as $file)
                        <a href="{{ $file->path ?? '#' }}" target="_blank"
                           class="rounded-xl bg-white shadow-sm p-3 flex items-center gap-3 mb-2">
                            <x-icon name="o-document" class="h-8 w-8 text-red-400 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800">Proposal File</p>
                                <p class="text-xs text-gray-400">Tap to open</p>
                            </div>
                            <x-icon name="o-arrow-top-right-on-square" class="h-4 w-4 text-gray-400 shrink-0" />
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Decision --}}
            @if($myReview)
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <x-icon name="o-scale" class="h-4 w-4 text-purple-400" />
                        <h3 class="font-bold text-sm text-gray-700">Decision</h3>
                    </div>

                    @if(is_null($myReview->decision_id))
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                wire:click="openConfirm('approve')"
                                class="flex items-center justify-center gap-2 rounded-xl bg-green-500 py-3 text-sm font-bold text-white shadow-sm">
                                <x-icon name="o-check-circle" class="h-4 w-4" />
                                Approve
                            </button>
                            <button
                                wire:click="openConfirm('reject')"
                                class="flex items-center justify-center gap-2 rounded-xl bg-red-500 py-3 text-sm font-bold text-white shadow-sm">
                                <x-icon name="o-x-circle" class="h-4 w-4" />
                                Reject
                            </button>
                        </div>
                    @else
                        @php
                            $myDecLabel = $myReview->decision?->description ?? 'Submitted';
                            $myDecClass = $myDecLabel === 'Approve' ? 'text-green-600' : 'text-red-600';
                            $myDecIcon  = $myDecLabel === 'Approve' ? 'o-check-badge' : 'o-x-circle';
                        @endphp
                        <div class="rounded-xl bg-white shadow-sm p-4 text-center">
                            <x-icon name="{{ $myDecIcon }}" class="mx-auto mb-1 h-8 w-8 {{ $myDecClass }}" />
                            <p class="text-sm font-semibold {{ $myDecClass }}">Decision submitted: {{ $myDecLabel }}</p>
                        </div>
                    @endif
                </div>
            @endif

        </div>
    @endif

    {{-- Confirmation Modal --}}
    <x-modal wire:model="confirmModal" title="Confirm Decision" class="backdrop-blur">
        <div class="text-center py-2">
            @if($pendingDecision === 'approve')
                <x-icon name="o-check-circle" class="mx-auto mb-3 h-12 w-12 text-green-500" />
                <p class="text-sm font-semibold text-gray-700">Submit <span class="text-green-600">Approve</span> decision for this research?</p>
            @else
                <x-icon name="o-x-circle" class="mx-auto mb-3 h-12 w-12 text-red-500" />
                <p class="text-sm font-semibold text-gray-700">Submit <span class="text-red-600">Reject</span> decision for this research?</p>
            @endif
            <p class="text-xs text-gray-400 mt-1">This action cannot be undone.</p>
        </div>
        <x-slot:actions>
            <x-button label="Cancel" wire:click="$set('confirmModal', false)" class="btn-ghost" />
            <x-button
                label="{{ $pendingDecision === 'approve' ? 'Approve' : 'Reject' }}"
                wire:click="submitDecision('{{ $pendingDecision }}')"
                class="{{ $pendingDecision === 'approve' ? 'btn-success' : 'btn-error' }}"
            />
        </x-slot:actions>
    </x-modal>
</div>
