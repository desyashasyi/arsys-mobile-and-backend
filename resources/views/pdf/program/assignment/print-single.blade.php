
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Dosen</title>
    {{-- Gunakan Tailwind via CDN agar terbaca oleh PDF Generator (Browsershot) --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: sans-serif; font-size: 12px; }

        /* Tambahkan ini */
        @page {
            size: A4 landscape;
            margin: 20px;
        }
    </style>
</head>
<body>

<h2 class="text-xl font-bold mb-4 text-center">Jadwal Penugasan Dosen</h2>

<div class="overflow-x-auto">
    <table class="min-w-full border-collapse border border-gray-400 text-sm">
        <thead class="bg-gray-100">
        <tr>
            <th class="border border-gray-400 px-2 py-2">No</th>
            <th class="border border-gray-400 px-2 py-2">Data Dosen</th>
            <th class="border border-gray-400 px-2 py-2">Jabatan dan Golongan</th>
            <th class="border border-gray-400 px-2 py-2">Kode</th>
            <th class="border border-gray-400 px-2 py-2">Mata Kuliah</th>
            <th class="border border-gray-400 px-2 py-2">SKS</th>
            <th class="border border-gray-400 px-2 py-2">Kelas</th>
            <th class="border border-gray-400 px-2 py-2">Sem</th>
            <th class="border border-gray-400 px-2 py-2">Hari</th>
            <th class="border border-gray-400 px-2 py-2">Waktu</th>
            <th class="border border-gray-400 px-2 py-2">Ruang</th>
        </tr>
        </thead>
        <tbody>
        @foreach($teachers as $index => $teacher)

            @foreach($teacher->schedules as $schedule)
                <tr class="hover:bg-gray-50">
                    @if($loop->first)
                        <td class="border border-gray-400 px-2 py-2 text-center align-top bg-white"
                            rowspan="{{ $teacher->schedules->count() }}">
                            {{ $loop->parent->iteration }}
                        </td>
                        <td class="border border-gray-400 px-2 py-2 align-top bg-white"
                            rowspan="{{ $teacher->schedules->count() }}">
                            <div class="font-bold">
                                {{ $teacher->front_title }} {{ $teacher->name }}, {{ $teacher->rear_title }}
                                <br/>
                                NIP: {{ $teacher->employee_id }}
                            </div>
                        </td>
                        <td class="border border-gray-400 px-2 py-2 align-top bg-white"
                            rowspan="{{ $teacher->schedules->count() }}">
                            {{ $teacher->position ?? '-' }} <br/>
                            {{ $teacher->role ?? '-' }}
                        </td>
                    @endif

                    <td class="border border-gray-400 px-2 py-2 text-center">{{ $schedule->subject->code }}</td>
                    <td class="border border-gray-400 px-2 py-2">{{ $schedule->subject->name }}</td>
                    <td class="border border-gray-400 px-2 py-2 text-center">{{ $schedule->credit }}</td>
                    <td class="border border-gray-400 px-2 py-2 text-center">{{ $schedule->student }}</td>
                    <td class="border border-gray-400 px-2 py-2 text-center">{{ $schedule->semester }}</td>
                    <td class="border border-gray-400 px-2 py-2 text-center">{{ $schedule->day }}</td>
                    <td class="border border-gray-400 px-2 py-2 text-center text-xs whitespace-nowrap">
                        {{-- Menggunakan Carbon --}}
                        {{ \Carbon\Carbon::parse($schedule->start)->format('H:i') }} -
                        {{ \Carbon\Carbon::parse($schedule->end)->format('H:i') }}
                    </td>
                    <td class="border border-gray-400 px-2 py-2 text-xs">
                        {{ $schedule->room }}
                    </td>
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table>
</div>

</body>
</html>
