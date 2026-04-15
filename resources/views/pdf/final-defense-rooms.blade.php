<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $eventLabel }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { border-bottom: 1px solid #e2e8f0; }
        .page-break { page-break-after: always; }
        @media print { .overflow-x-auto { overflow-x: visible; } }
    </style>
</head>
<body class="p-6">

    {{-- Header --}}
    <div class="mb-5 text-center">
        <h2 class="text-lg font-bold uppercase">Final Defense Schedule</h2>
        <p class="text-sm font-semibold text-gray-600">{{ strtoupper($eventLabel) }}</p>
        @if($event->program)
            <p class="text-xs text-gray-500">{{ $event->program->name }}</p>
        @endif
        <p class="text-xs text-gray-500 mt-1">
            Date: {{ \Carbon\Carbon::parse($event->event_date)->isoFormat('D MMMM YYYY') }}
        </p>
    </div>

    {{-- Rooms --}}
    @forelse($rooms as $room)
        <div class="mb-6 {{ !$loop->last ? '' : '' }}">
            {{-- Room header --}}
            <div class="flex items-start gap-6 mb-2 px-1">
                <div>
                    <span class="font-bold text-purple-700 text-sm">Room {{ $room['index'] }}</span>
                </div>
                <div class="flex gap-6 text-xs text-gray-600">
                    <span><span class="font-semibold">Space:</span> {{ $room['space'] }}</span>
                    <span><span class="font-semibold">Session:</span> {{ $room['session'] }}</span>
                    <span><span class="font-semibold">Moderator:</span> {{ $room['moderator'] }}</span>
                    <span><span class="font-semibold">Examiners:</span> {{ $room['examiners'] }}</span>
                </div>
            </div>

            {{-- Participants table --}}
            <table class="min-w-full border border-gray-400 text-xs">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="border border-gray-400 px-2 py-1.5 text-center w-6">No</th>
                        <th class="border border-gray-400 px-2 py-1.5 text-left w-24">Student ID</th>
                        <th class="border border-gray-400 px-2 py-1.5 text-left">Name</th>
                        <th class="border border-gray-400 px-2 py-1.5 text-left">Research Title</th>
                        <th class="border border-gray-400 px-2 py-1.5 text-center w-16">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($room['participants'] as $p)
                        <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                            <td class="border border-gray-300 px-2 py-1.5 text-center">{{ $p['no'] }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 font-mono">{{ $p['number'] }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 font-semibold">{{ $p['name'] ?: '—' }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-gray-700">{{ $p['title'] }}</td>
                            <td class="border border-gray-300 px-2 py-1.5 text-center">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $p['publish'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ $p['publish'] ? 'Published' : 'Draft' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="border border-gray-300 px-4 py-4 text-center text-gray-400 italic">
                                No participants in this room.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <div class="py-10 text-center text-gray-400">No rooms found.</div>
    @endforelse

    <div class="mt-4 text-xs text-gray-400 text-right">
        Printed: {{ now()->isoFormat('D MMMM YYYY, HH:mm') }}
    </div>

</body>
</html>
