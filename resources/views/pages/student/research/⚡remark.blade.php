<?php

use App\Models\ArSys\Research;
use App\Models\ArSys\ResearchRemark;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.mobile-app')] #[Title('Remarks')] class extends Component
{
    public int    $researchId = 0;
    public string $message    = '';

    public function mount(int $id): void
    {
        $this->researchId = $id;

        $exists = Research::where('id', $id)
            ->where('student_id', Auth::user()->student?->id ?? 0)
            ->exists();

        if (!$exists) {
            $this->redirect(route('student.research'), navigate: true);
        }
    }

    public function addRemark(): void
    {
        $this->validate(['message' => 'required|string|max:2000']);

        $research = Research::where('id', $this->researchId)
            ->where('student_id', Auth::user()->student?->id ?? 0)
            ->first();

        if (!$research) return;

        ResearchRemark::create([
            'research_id'   => $research->id,
            'discussant_id' => Auth::id(),
            'message'       => nl2br(e($this->message)),
        ]);

        $this->message = '';
    }

    public function with(): array
    {
        $research = Research::where('id', $this->researchId)
            ->where('student_id', Auth::user()->student?->id ?? 0)
            ->with(['remark' => fn($q) => $q->orderBy('id', 'asc')->with('user.staff', 'user.student')])
            ->first();

        $remarks = $research?->remark->map(function ($r) {
            $author = 'Unknown';
            if ($r->user?->staff) {
                $author = trim(($r->user->staff->first_name ?? '') . ' ' . ($r->user->staff->last_name ?? ''));
            } elseif ($r->user?->student) {
                $author = trim(($r->user->student->first_name ?? '') . ' ' . ($r->user->student->last_name ?? ''));
            } elseif ($r->user) {
                $author = $r->user->name;
            }

            $isMe = $r->discussant_id === Auth::id();

            return [
                'id'         => $r->id,
                'author'     => $author,
                'message'    => $r->message,
                'created_at' => $r->created_at?->format('d M Y H:i'),
                'is_me'      => $isMe,
            ];
        }) ?? collect();

        return [
            'researchCode' => $research?->code ?? '—',
            'remarks'      => $remarks,
        ];
    }
};
?>

<div class="flex flex-col" style="min-height: calc(100vh - 8rem)">

    {{-- Research code header --}}
    <div class="px-4 py-2 bg-purple-50 border-b border-purple-100">
        <p class="text-[10px] font-semibold text-purple-500 tracking-wide">{{ $researchCode }}</p>
    </div>

    {{-- Remarks list --}}
    <div class="flex-1 overflow-y-auto px-4 py-3 pb-28 space-y-3"
         id="remarks-list">

        @forelse($remarks as $remark)
        <div class="rounded-2xl p-3 {{ $remark['is_me'] ? 'bg-purple-600' : 'bg-white shadow-sm' }}">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-bold {{ $remark['is_me'] ? 'text-white' : 'text-purple-600' }}">
                    {{ $remark['author'] }}
                </span>
                <span class="text-[9px] {{ $remark['is_me'] ? 'text-white/60' : 'text-gray-400' }}">
                    {{ $remark['created_at'] }}
                </span>
            </div>
            <div class="text-xs leading-relaxed {{ $remark['is_me'] ? 'text-white' : 'text-gray-700' }}">
                {!! $remark['message'] !!}
            </div>
        </div>
        @empty
        <div class="flex flex-col items-center justify-center py-16 gap-2 text-center">
            <x-icon name="o-chat-bubble-left-right" class="h-10 w-10 text-gray-200" />
            <p class="text-sm text-gray-400">No remarks yet</p>
            <p class="text-xs text-gray-300">Start the discussion below</p>
        </div>
        @endforelse

    </div>

    {{-- Input area — fixed above bottom navbar --}}
    <div class="fixed bottom-16 left-1/2 -translate-x-1/2 w-full max-w-sm bg-white border-t border-gray-100 px-3 py-2 z-40 shadow-[0_-2px_8px_rgba(0,0,0,0.06)]">
        <div class="flex items-end gap-2">
            <textarea wire:model="message"
                      rows="2"
                      placeholder="Type a remark..."
                      class="flex-1 resize-none rounded-2xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 focus:border-purple-300 focus:outline-none focus:ring-1 focus:ring-purple-200"></textarea>
            <button wire:click="addRemark"
                    wire:loading.attr="disabled"
                    wire:target="addRemark"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-600 text-white shadow-sm transition-colors hover:bg-purple-700 disabled:opacity-50">
                <span wire:loading.remove wire:target="addRemark">
                    <x-icon name="o-paper-airplane" class="h-4 w-4" />
                </span>
                <span wire:loading wire:target="addRemark">
                    <x-icon name="o-arrow-path" class="h-4 w-4 animate-spin" />
                </span>
            </button>
        </div>
        @error('message')
            <p class="mt-1 text-[10px] text-red-500">{{ $message }}</p>
        @enderror
    </div>

</div>

<script>
    // Auto-scroll to bottom on page load
    document.addEventListener('livewire:navigated', () => {
        const list = document.getElementById('remarks-list');
        if (list) list.scrollTop = list.scrollHeight;
    });
    document.addEventListener('DOMContentLoaded', () => {
        const list = document.getElementById('remarks-list');
        if (list) list.scrollTop = list.scrollHeight;
    });
    // Scroll to bottom after new remark
    document.addEventListener('livewire:updated', () => {
        const list = document.getElementById('remarks-list');
        if (list) list.scrollTop = list.scrollHeight;
    });
</script>
