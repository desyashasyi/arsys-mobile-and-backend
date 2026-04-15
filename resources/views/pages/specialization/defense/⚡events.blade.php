<?php

use App\Models\ArSys\Event;
use App\Models\ArSys\EventType;
use App\Models\ArSys\Program;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts.mobile-app')] #[Title('List of Events')] class extends Component
{
    use Toast;

    public bool   $addSheet      = false;
    public bool   $editSheet     = false;
    public bool   $deleteConfirm = false;
    public int    $editEventId   = 0;
    public string $filterType    = '';

    public string $typeCode      = '';
    public string $eventDate     = '';
    public string $appDeadline   = '';
    public string $draftDeadline = '';
    public string $quota         = '';

    private function getProgramId(): ?int
    {
        return auth()->user()->staff?->program_id;
    }

    public function openAdd(): void
    {
        $this->reset(['typeCode', 'eventDate', 'appDeadline', 'draftDeadline', 'quota']);
        $this->addSheet = true;
    }

    public function openEdit(int $eventId): void
    {
        $event = Event::where('program_id', $this->getProgramId())->find($eventId);
        if (!$event) return;

        $this->editEventId   = $eventId;
        $this->typeCode      = $event->type?->code ?? '';
        $this->eventDate     = $event->event_date ?? '';
        $this->appDeadline   = $event->application_deadline ?? '';
        $this->draftDeadline = $event->draft_deadline ?? '';
        $this->quota         = $event->quota !== null ? (string) $event->quota : '';
        $this->editSheet     = true;
    }

    public function store(): void
    {
        $this->validate(['typeCode' => 'required', 'eventDate' => 'required|date']);

        $programId = $this->getProgramId();
        $type = EventType::where('code', $this->typeCode)->first();
        if (!$type) { $this->error('Event type not found.', position: 'toast-bottom'); return; }

        Event::create([
            'program_id'           => $programId,
            'event_type_id'        => $type->id,
            'event_date'           => $this->eventDate,
            'application_deadline' => $this->appDeadline ?: null,
            'draft_deadline'       => $this->draftDeadline ?: null,
            'quota'                => $this->quota !== '' ? (int) $this->quota : null,
            'status'               => 1,
        ]);

        $this->addSheet = false;
        $this->success('Event created.', position: 'toast-bottom');
    }

    public function update(): void
    {
        $this->validate(['eventDate' => 'required|date']);

        $event = Event::where('program_id', $this->getProgramId())->find($this->editEventId);
        if (!$event) { $this->error('Event not found.', position: 'toast-bottom'); return; }

        $event->update([
            'event_date'           => $this->eventDate,
            'application_deadline' => $this->appDeadline ?: null,
            'draft_deadline'       => $this->draftDeadline ?: null,
            'quota'                => $this->quota !== '' ? (int) $this->quota : null,
        ]);

        $this->editSheet = false;
        $this->success('Event updated.', position: 'toast-bottom');
    }

    public function confirmDelete(int $eventId): void
    {
        $this->editEventId   = $eventId;
        $this->deleteConfirm = true;
    }

    public function destroy(): void
    {
        $event = Event::where('program_id', $this->getProgramId())->find($this->editEventId);
        if (!$event) { $this->error('Event not found.', position: 'toast-bottom'); return; }

        $hasApplicants = $event->defenseApplicant()->exists()
            || $event->finaldefenseApplicant()->exists()
            || $event->seminarApplicant()->exists();

        if ($hasApplicants) {
            $this->deleteConfirm = false;
            $this->warning('Cannot delete: event has existing applicants.', position: 'toast-bottom');
            return;
        }

        $event->delete();
        $this->deleteConfirm = false;
        $this->success('Event deleted.', position: 'toast-bottom');
    }

    public function with(): array
    {
        $programId  = $this->getProgramId();
        $program    = Program::find($programId);
        $clusterIds = Program::where('faculty_id', $program?->faculty_id)->pluck('id');

        $query = Event::with(['type', 'program'])
            ->whereIn('program_id', $clusterIds)
            ->orderBy('event_date', 'desc');

        if ($this->filterType) {
            $query->whereHas('type', fn($q) => $q->where('code', $this->filterType));
        }

        $events = $query->get()->map(fn($e) => [
            'id'           => $e->id,
            'date_key'     => Carbon::parse($e->event_date)->format('Y-m-d'),
            'date_label'   => Carbon::parse($e->event_date)->isoFormat('dddd, D MMMM YYYY'),
            'type_code'    => $e->type?->code ?? '-',
            'event_label'  => ($e->type?->code ?? '?') . '-' . Carbon::parse($e->event_date)->format('dmy') . '-' . $e->id,
            'app_deadline' => $e->application_deadline ? Carbon::parse($e->application_deadline)->isoFormat('D MMM YYYY') : null,
            'quota'        => $e->quota,
            'program_code' => $e->program ? ($e->program->code . '.' . $e->program->abbrev) : '-',
            'is_own'       => $e->program_id === $programId,
        ]);

        // Group by date, own program first within each group, newest date first
        $grouped = $events
            ->groupBy('date_key')
            ->map(fn($group) => $group->sortByDesc('is_own')->values())
            ->sortKeysDesc();

        $types = EventType::whereIn('code', ['PRE', 'PUB', 'SSP'])->get(['code', 'description']);

        return ['grouped' => $grouped, 'types' => $types];
    }
};
?>

<div>
    {{-- Filter dropdown --}}
    <div class="px-3 pt-3 pb-1">
        <select wire:model.live="filterType"
            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm font-semibold text-gray-700 focus:outline-none focus:border-purple-400 shadow-sm">
            <option value="">All Types</option>
            @foreach($types as $type)
                <option value="{{ $type->code }}">{{ $type->description }} ({{ $type->code }})</option>
            @endforeach
        </select>
    </div>

    {{-- Grouped event list --}}
    <div class="px-3 py-3 space-y-4 pb-6">
        @forelse($grouped as $dateKey => $dayEvents)
            @php
                $hdr = match($loop->index % 3) {
                    0 => ['bg' => 'bg-purple-50 border-purple-100',  'text' => 'text-purple-800',  'icon' => 'text-purple-400'],
                    1 => ['bg' => 'bg-violet-50 border-violet-100',  'text' => 'text-violet-800',  'icon' => 'text-violet-400'],
                    2 => ['bg' => 'bg-indigo-50 border-indigo-100',  'text' => 'text-indigo-800',  'icon' => 'text-indigo-400'],
                };
            @endphp
            {{-- Date block --}}
            <div class="rounded-2xl bg-white shadow-sm overflow-hidden">
                {{-- Date header --}}
                <div class="flex items-center gap-2 px-4 py-2.5 {{ $hdr['bg'] }} border-b">
                    <x-icon name="o-calendar-days" class="h-3.5 w-3.5 {{ $hdr['icon'] }} shrink-0" />
                    <p class="text-xs font-bold {{ $hdr['text'] }}">{{ $dayEvents->first()['date_label'] }}</p>
                </div>

                {{-- Events in this date block --}}
                <div class="divide-y divide-gray-100">
                    @foreach($dayEvents as $event)
                        @php
                            $typeColor = match($event['type_code']) {
                                'PRE' => 'bg-orange-100 text-orange-700',
                                'PUB' => 'bg-purple-100 text-purple-700',
                                'SSP' => 'bg-blue-100 text-blue-700',
                                default => 'bg-gray-100 text-gray-500',
                            };
                        @endphp

                        @if($event['is_own'])
                            {{-- Own program: prominent colored row --}}
                            <div class="flex items-center gap-3 px-4 py-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full {{ $typeColor }}">
                                            {{ $event['type_code'] }}
                                        </span>
                                        <span class="text-[10px] font-bold text-purple-700">{{ $event['program_code'] }}</span>
                                    </div>
                                    <p class="text-sm font-bold text-gray-800 uppercase">{{ $event['event_label'] }}</p>
                                    @if($event['app_deadline'])
                                        <p class="text-[10px] text-gray-400 mt-0.5">Deadline: {{ $event['app_deadline'] }}</p>
                                    @endif
                                    @if($event['quota'])
                                        <p class="text-[10px] text-gray-400">Quota: {{ $event['quota'] }}</p>
                                    @endif
                                </div>
                                <div class="shrink-0 flex items-center gap-1.5">
                                    <button wire:click="openEdit({{ $event['id'] }})"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-50 hover:bg-blue-100">
                                        <x-icon name="o-pencil-square" class="h-4 w-4 text-blue-500" />
                                    </button>
                                    <button wire:click="confirmDelete({{ $event['id'] }})"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-red-50 hover:bg-red-100">
                                        <x-icon name="o-trash" class="h-4 w-4 text-red-400" />
                                    </button>
                                </div>
                            </div>
                        @else
                            {{-- Other program: compact gray row --}}
                            <div class="flex items-center gap-3 px-4 py-2.5 bg-gray-50/50">
                                <span class="shrink-0 text-[9px] font-bold px-1.5 py-0.5 rounded bg-gray-200 text-gray-500">
                                    {{ $event['type_code'] }}
                                </span>
                                <p class="text-xs font-semibold text-gray-400 flex-1 truncate uppercase">{{ $event['event_label'] }}</p>
                                <span class="shrink-0 text-[10px] font-semibold text-gray-400">{{ $event['program_code'] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @empty
            <div class="py-16 text-center">
                <x-icon name="o-inbox" class="mx-auto mb-3 h-12 w-12 text-gray-200" />
                <p class="text-sm text-gray-400">No events found.</p>
                <button wire:click="openAdd" class="mt-3 text-xs font-semibold text-purple-600 hover:text-purple-800">
                    + Add first event
                </button>
            </div>
        @endforelse
    </div>

    {{-- ─── FAB: Add Event ─── --}}
    <button wire:click="openAdd"
        class="fixed bottom-24 right-1/2 translate-x-[10.5rem] z-20
               flex items-center justify-center h-14 w-14 rounded-full
               bg-purple-600 text-white shadow-lg hover:bg-purple-700 active:scale-95 transition-all">
        <x-icon name="o-plus" class="h-6 w-6" />
    </button>

    {{-- ─── Delete Confirmation Sheet ─── --}}
    <div x-data x-show="$wire.deleteConfirm"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('deleteConfirm', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.deleteConfirm"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl">
        <div class="flex justify-center pt-3 pb-1"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="px-4 py-4 space-y-2">
            <div class="flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-red-100">
                    <x-icon name="o-trash" class="h-4 w-4 text-red-500" />
                </div>
                <h3 class="text-base font-bold text-red-600">Delete Event?</h3>
            </div>
            <p class="text-sm text-gray-500">This event will be permanently deleted and cannot be undone.</p>
        </div>
        <div class="flex gap-3 px-4 pb-24 pt-2 border-t border-gray-100">
            <button wire:click="$set('deleteConfirm', false)"
                class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                Cancel
            </button>
            <button wire:click="destroy" wire:loading.attr="disabled"
                class="flex-1 py-3 rounded-xl bg-red-500 text-white text-sm font-bold hover:bg-red-600 disabled:opacity-60">
                <span wire:loading.remove wire:target="destroy">Delete</span>
                <span wire:loading wire:target="destroy" class="loading loading-spinner loading-sm"></span>
            </button>
        </div>
    </div>

    {{-- ─── Add Event Sheet ─── --}}
    <div x-data x-show="$wire.addSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('addSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.addSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl">
        <div class="flex justify-center pt-3 pb-1"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-purple-100">
                    <x-icon name="o-plus" class="h-4 w-4 text-purple-600" />
                </div>
                <h3 class="text-base font-bold text-purple-700">Add Event</h3>
            </div>
            <button wire:click="$set('addSheet', false)"
                class="flex items-center justify-center h-7 w-7 rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="px-4 py-4 space-y-3 max-h-[55vh] overflow-y-auto">
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Event Type <span class="text-red-500">*</span></label>
                <select wire:model="typeCode"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-purple-400">
                    <option value="">— Select type —</option>
                    @foreach($types as $type)
                        <option value="{{ $type->code }}">{{ $type->description }} ({{ $type->code }})</option>
                    @endforeach
                </select>
                @error('typeCode') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Event Date <span class="text-red-500">*</span></label>
                <input wire:model="eventDate" type="date"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-purple-400" />
                @error('eventDate') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Application Deadline</label>
                <input wire:model="appDeadline" type="date"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-purple-400" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Draft Deadline</label>
                <input wire:model="draftDeadline" type="date"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-purple-400" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Quota</label>
                <input wire:model="quota" type="number" min="1" placeholder="Leave blank for unlimited"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-purple-400" />
            </div>
        </div>
        <div class="flex gap-3 px-4 pb-24 pt-3 border-t border-gray-100">
            <button wire:click="$set('addSheet', false)"
                class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
            <button wire:click="store" wire:loading.attr="disabled"
                class="flex-1 py-3 rounded-xl bg-purple-600 text-white text-sm font-bold hover:bg-purple-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="store">Create</span>
                <span wire:loading wire:target="store" class="loading loading-spinner loading-sm"></span>
            </button>
        </div>
    </div>

    {{-- ─── Edit Event Sheet ─── --}}
    <div x-data x-show="$wire.editSheet"
         x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         wire:click="$set('editSheet', false)"
         class="fixed inset-0 z-30 bg-black/50"></div>

    <div x-data x-show="$wire.editSheet"
         x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-full" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-full"
         class="fixed bottom-0 left-1/2 z-40 w-full max-w-sm -translate-x-1/2 rounded-t-2xl bg-white shadow-2xl">
        <div class="flex justify-center pt-3 pb-1"><div class="h-1 w-10 rounded-full bg-gray-300"></div></div>
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
            <div class="flex items-center gap-2">
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-blue-100">
                    <x-icon name="o-pencil-square" class="h-4 w-4 text-blue-600" />
                </div>
                <h3 class="text-base font-bold text-blue-700">Edit Event</h3>
            </div>
            <button wire:click="$set('editSheet', false)"
                class="flex items-center justify-center h-7 w-7 rounded-full bg-gray-100 hover:bg-gray-200">
                <x-icon name="o-x-mark" class="h-4 w-4 text-gray-500" />
            </button>
        </div>
        <div class="px-4 py-4 space-y-3 max-h-[55vh] overflow-y-auto">
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Event Date <span class="text-red-500">*</span></label>
                <input wire:model="eventDate" type="date"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-blue-400" />
                @error('eventDate') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Application Deadline</label>
                <input wire:model="appDeadline" type="date"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-blue-400" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Draft Deadline</label>
                <input wire:model="draftDeadline" type="date"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-blue-400" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500 mb-1.5 block">Quota</label>
                <input wire:model="quota" type="number" min="1" placeholder="Leave blank for unlimited"
                    class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-3 text-sm focus:outline-none focus:border-blue-400" />
            </div>
        </div>
        <div class="flex gap-3 px-4 pb-24 pt-3 border-t border-gray-100">
            <button wire:click="$set('editSheet', false)"
                class="flex-1 py-3 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50">Cancel</button>
            <button wire:click="update" wire:loading.attr="disabled"
                class="flex-1 py-3 rounded-xl bg-blue-600 text-white text-sm font-bold hover:bg-blue-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="update">Save</span>
                <span wire:loading wire:target="update" class="loading loading-spinner loading-sm"></span>
            </button>
        </div>
    </div>
</div>
