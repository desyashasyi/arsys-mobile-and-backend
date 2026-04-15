<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 8.5pt;
            color: #2E86C1;
            margin: 0;
            padding: 0 15mm 0 20mm;
            width: 100%;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .text-cell {
            width: 80%;
            text-align: left;
            vertical-align: middle;
            padding-right: 15px;
        }
        .image-cell {
            width: 20%;
            text-align: right;
            vertical-align: middle;
        }
        .bse-logo {
            width: 75px;
            height: auto;
            display: block;
            margin-left: auto;
        }
        p {
            margin: 0;
            line-height: 1.4;
        }
    </style>
</head>
<body>
<table class="footer-table">
    <tr>
        <td class="text-cell">
            <p>
                Dokumen ini telah ditandatangani secara elektronik menggunakan sertifikat elektronik yang diterbitkan oleh Balai Sertifikasi Elektronik, Badan Siber dan Sandi Negara sesuai dengan Undang-Undang Nomor 11 Tahun 2008 Tentang Informasi dan Transaksi Elektronik, maka tanda tangan secara elektronik memiliki kekuatan hukum yang sah.
            </p>
        </td>
        <td class="image-cell">
            @php
                // FINAL ATTEMPT: Reverting to the robust Base64 method.
                // We have confirmed that 'bse.png' exists in the public directory.
                $path = public_path('bse.png');
                if (file_exists($path)) {
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    $base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                } else {
                    // This should not happen now, but it's a good fallback.
                    $base64 = null;
                }
            @endphp

            @if($base64)
                <img src="{{ $base64 }}" alt="Logo" class="bse-logo">
            @else
                <span style="font-size: 7pt; color: red;">Logo Missing (File not found by server)</span>
            @endif
        </td>
    </tr>
</table>
</body>
</html>
