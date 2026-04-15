<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $eventLabel }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { border-bottom: 1px solid #e2e8f0; }
        @media print { .overflow-x-auto { overflow-x: visible; } }
    </style>
</head>
<body class="p-6">

    <div class="mb-4 text-center">
        <h2 class="text-lg font-bold uppercase">Jadwal Pre-Defense</h2>
        <p class="text-sm font-semibold text-gray-600">{{ strtoupper($eventLabel) }}</p>
        @if($event->program)
            <p class="text-xs text-gray-500">{{ $event->program->name }}</p>
        @endif
        <p class="text-xs text-gray-500 mt-1">
            Tanggal: {{ \Carbon\Carbon::parse($event->event_date)->isoFormat('D MMMM YYYY') }}
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-400 text-xs">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border border-gray-400 px-2 py-1.5 text-center w-6">No</th>
                    <th class="border border-gray-400 px-2 py-1.5 text-left w-24">NIM</th>
                    <th class="border border-gray-400 px-2 py-1.5 text-left">Nama Mahasiswa</th>
                    <th class="border border-gray-400 px-2 py-1.5 text-left">Judul Penelitian</th>
                    <th class="border border-gray-400 px-2 py-1.5 text-center w-14">Ruang</th>
                    <th class="border border-gray-400 px-2 py-1.5 text-center w-20">Sesi</th>
                    <th class="border border-gray-400 px-2 py-1.5 text-left">Pembimbing</th>
                    <th class="border border-gray-400 px-2 py-1.5 text-left">Penguji</th>
                    <th class="border border-gray-400 px-2 py-1.5 text-center w-16">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($participants as $p)
                    <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                        <td class="border border-gray-300 px-2 py-1.5 text-center">{{ $p['no'] }}</td>
                        <td class="border border-gray-300 px-2 py-1.5 font-mono">{{ $p['student_number'] }}</td>
                        <td class="border border-gray-300 px-2 py-1.5 font-semibold">{{ $p['student_name'] ?: '—' }}</td>
                        <td class="border border-gray-300 px-2 py-1.5 text-gray-700">{{ $p['title'] }}</td>
                        <td class="border border-gray-300 px-2 py-1.5 text-center font-bold text-purple-700">{{ $p['space'] }}</td>
                        <td class="border border-gray-300 px-2 py-1.5 text-center font-bold text-blue-700">{{ $p['session'] }}</td>
                        <td class="border border-gray-300 px-2 py-1.5 text-gray-700">{{ $p['supervisors'] }}</td>
                        <td class="border border-gray-300 px-2 py-1.5 text-gray-700">{{ $p['examiners'] }}</td>
                        <td class="border border-gray-300 px-2 py-1.5 text-center">
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold {{ $p['publish'] ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ $p['publish'] ? 'Published' : 'Draft' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="border border-gray-300 px-4 py-6 text-center text-gray-400">
                            No participants found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 text-xs text-gray-400 text-right">
        Dicetak: {{ now()->isoFormat('D MMMM YYYY, HH:mm') }}
    </div>

</body>
</html>
