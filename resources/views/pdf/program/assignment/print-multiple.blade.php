
<!DOCTYPE html>
<html lang="id">
<head>
    {{-- Pengaturan metadata dasar untuk dokumen HTML --}}
    <meta charset="UTF-8">
    <title>Jadwal Dosen</title>
    {{-- Gunakan Tailwind via CDN agar terbaca oleh PDF Generator (Browsershot) --}}
    {{-- Browsershot (yang menggunakan Puppeteer) akan memuat resource eksternal seperti ini saat merender HTML menjadi PDF. --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Pengaturan dasar untuk body dokumen */
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        /* Styling dasar untuk tabel */
        table {
            width: 100%;
            border-collapse: collapse; /* Gabungkan border sel */
        }

        /* Memastikan header tabel muncul di setiap halaman baru saat dicetak */
        thead {
            display: table-header-group;
        }

        /* Perbaikan untuk memastikan border tabel tidak terpotong saat pindah halaman (page break) */
        tr {
            border-bottom: 1px solid #e2e8f0; /* a light gray, sesuaikan dengan warna border Anda */
        }

        /* Aturan khusus untuk media cetak */
        @media print {
            .overflow-x-auto {
                overflow-x: visible;
            }
        }
    </style>
</head>
<body>

{{-- Judul utama dokumen --}}
<h2 class="text-xl font-bold mb-4 text-center">Jadwal Penugasan Dosen</h2>

<div class="overflow-x-auto">
    {{-- Tabel utama untuk menampilkan jadwal --}}
    <table class="min-w-full border-collapse border border-gray-400 text-sm">
        <thead class="bg-gray-100">
        <tr>
            <th class="border border-gray-400 px-2 py-2">No</th>
            <th class="border border-gray-400 px-2 py-2">Data Dosen</th>
            <th class="border border-gray-400 px-2 py-2">Jabatan dan Golongan</th>
            <th class="border border-gray-400 px-2 py-2">Kode</th>
            <th class="border border-gray-400 px-2 py-2">Mata Kuliah</th>
            <th class="border border-gray-400 px-2 py-2">SKS</th>
            <th class="border border-gray-400  w-1/12 px-2 py-2">Kelas</th>
            <th class="border border-gray-400 px-2 py-2">Sem</th>
            <th class="border border-gray-400 px-2 py-2">Hari</th>
            <th class="border border-gray-400 px-2 py-2">Waktu</th>
            <th class="border border-gray-400 w-1/12 px-2 py-2">Ruang</th>
        </tr>
        </thead>
        <tbody>
        {{-- Loop utama untuk setiap dosen dalam koleksi $teachers --}}
        {{-- Semua baris berada dalam satu tbody untuk membuat tabel yang menyambung --}}
        @foreach($teachers as $teacher)
            {{-- Gunakan @forelse untuk loop jadwal setiap dosen. Ini memungkinkan penanganan kasus jika dosen tidak punya jadwal. --}}
            @forelse($teacher->schedules as $schedule)
                <tr class="hover:bg-gray-50">
                    {{-- @if($loop->first) digunakan untuk menampilkan data dosen hanya sekali (di baris pertama jadwalnya) --}}
                    @if($loop->first)
                        <td class="border border-gray-400 px-2 py-2 text-center align-top"
                            rowspan="{{ $teacher->schedules->count() > 0 ? $teacher->schedules->count() : 1 }}">
                            {{ $loop->parent->iteration }}
                        </td>
                        <td class="border border-gray-400 px-2 py-2 align-top"
                            rowspan="{{ $teacher->schedules->count() > 0 ? $teacher->schedules->count() : 1 }}">
                            <div class="font-bold">
                                {{ $teacher->front_title }} {{ $teacher->name }}, {{ $teacher->rear_title }}
                                <br/>
                                NIP: {{ $teacher->employee_id }}
                            </div>
                        </td>
                        <td class="border border-gray-400 px-2 py-2 align-top"
                            rowspan="{{ $teacher->schedules->count() > 0 ? $teacher->schedules->count() : 1 }}">
                            {{ $teacher->position ?? '-' }} <br/>
                            {{ $teacher->role ?? '-' }}
                        </td>
                    @endif

                    {{-- Kolom-kolom ini berisi detail untuk setiap jadwal dan akan di-render untuk setiap jadwal dalam loop --}}
                    <td class="border border-gray-400 px-2 py-2 text-center">{{ $schedule->subject->code ?? '-'}}</td>
                    <td class="border border-gray-400 px-2 py-2">{{ $schedule->subject->name ?? '-' }}</td>
                    <td class="border border-gray-400 px-2 py-2 text-center">{{ $schedule->subject->credit ?? '-' }}</td>
                    <td class="border border-gray-400 px-2 py-2 text-center">{{ $schedule->student }}</td>
                    <td class="border border-gray-400 px-2 py-2 text-center">{{ $schedule->subject->semester ?? '-' }}</td>
                    <td class="border border-gray-400 px-2 py-2 text-center">{{ $schedule->day }}</td>
                    <td class="border border-gray-400 px-2 py-2 text-center text-xs whitespace-nowrap">
                        {{ \Carbon\Carbon::parse($schedule->start)->format('H:i') }} -
                        {{ \Carbon\Carbon::parse($schedule->end)->format('H:i') }}
                    </td>
                    <td class="border border-gray-400 px-2 py-2 text-xs">
                        {{ $schedule->room }}
                    </td>
                </tr>
            @empty
                {{-- Blok @empty dieksekusi jika $teacher->schedules kosong. Menampilkan baris yang menandakan tidak ada jadwal. --}}
                {{-- Jika dosen tidak punya jadwal, tampilkan baris khusus --}}
                <tr>
                    <td class="border border-gray-400 px-2 py-2 text-center align-top">{{ $loop->iteration }}</td>
                    <td class="border border-gray-400 px-2 py-2 align-top">
                        <div class="font-bold">
                            {{ $teacher->front_title }} {{ $teacher->name }}, {{ $teacher->rear_title }}
                            <br/>
                            NIP: {{ $teacher->employee_id }}
                        </div>
                    </td>
                    <td class="border border-gray-400 px-2 py-2 align-top">
                        {{ $teacher->position ?? '-' }} <br/>
                        {{ $teacher->role ?? '-' }}
                    </td>
                    {{-- Gunakan colspan="8" agar sel ini mencakup sisa kolom tabel --}}
                    <td colspan="8" class="border border-gray-400 px-2 py-2 text-center bg-gray-50">Tidak ada jadwal.</td>
                </tr>
            @endforelse
        @endforeach
        </tbody>
    </table>
</div>

</body>
</html>
