<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 12mm 14mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #000;
        }

        .title {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        table.info {
            width: 100%;
            margin-bottom: 8px;
        }

        table.info td {
            vertical-align: top;
            padding: 1px 4px;
            font-size: 10px;
        }

        table.report {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        table.report th,
        table.report td {
            border: 1px solid #000;
            padding: 2px 5px;
            font-size: 9px;
        }

        table.report th {
            background: #eee;
            text-align: center;
        }

        table.report td.num {
            text-align: right;
        }

        table.report td.center {
            text-align: center;
        }

        table.report tfoot td {
            font-weight: bold;
        }

        table.signature {
            width: 100%;
            margin-top: 16px;
        }

        table.signature td {
            vertical-align: top;
            padding: 0 8px;
            font-size: 10px;
        }

        .sign-space {
            height: 60px;
            position: relative;
            margin-top: 4px;
        }

        .stamp-img {
            position: absolute;
            left: 55px;
            top: 0;
            max-height: 60px;
            opacity: 0.9;
        }

        .signature-img {
            position: absolute;
            left: 0;
            top: 0;
            max-height: 52px;
        }
    </style>
</head>

<body>
    <div class="title">Laporan Hasil Penjualan</div>

    <table class="info">
        <tr>
            <td width="12%">Bulan</td>
            <td width="2%">:</td>
            <td width="33%">{{ $monthName }}</td>
            <td width="16%">Nama Perusahaan</td>
            <td width="2%">:</td>
            <td>{{ $profile->company_name }}</td>
        </tr>
        <tr>
            <td>Tahun</td>
            <td>:</td>
            <td>{{ $report->period_year }}</td>
            <td>Alamat</td>
            <td>:</td>
            <td>{{ $profile->company_address }}</td>
        </tr>
        <tr>
            <td>Keterangan</td>
            <td>:</td>
            <td>{{ $keterangan }}</td>
            <td colspan="3">@yield('price-box')</td>
        </tr>
        <tr>
            <td>NPWPD</td>
            <td>:</td>
            <td>{{ $profile->npwpd }}</td>
            <td colspan="3"></td>
        </tr>
    </table>

    @yield('table')

    <table class="signature">
        <tr>
            <td width="50%">
                Diterima tgl,……………………………
                <div class="sign-space"></div>
                &nbsp;<br>
                Petugas Bapenda Karawang
            </td>
            <td width="50%">
                Karawang,……………………………………..
                <div class="sign-space"></div>
                &nbsp;<br>
                Pimpinan /Manager
                <div class="sign-space">
                    @if($stampDataUri)
                        <img src="{{ $stampDataUri }}" class="stamp-img">
                    @endif
                    @if($signatureDataUri)
                        <img src="{{ $signatureDataUri }}" class="signature-img">
                    @endif
                </div>
                ({{ $profile->owner_name ?: '.................................' }})
            </td>
        </tr>
    </table>
</body>

</html>