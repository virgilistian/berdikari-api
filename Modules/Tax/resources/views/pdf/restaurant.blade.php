@extends('tax::pdf.layout')

@section('table')
<table class="report">
    <thead>
        <tr>
            <th rowspan="2">No.</th>
            <th rowspan="2">Hari</th>
            <th colspan="2">Penerimaan Hasil</th>
        </tr>
        <tr>
            <th>Hasil Penjualan</th>
            <th>Pajak 10%</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report->entries as $entry)
        <tr>
            <td class="center">{{ $entry->day_number }}</td>
            <td>{{ $entry->weekday_name }}</td>
            <td class="num">{{ number_format((float) $entry->sales, 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $entry->tax, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2" class="center">Jumlah</td>
            <td class="num">{{ number_format((float) $report->total_sales, 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $report->total_tax, 0, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>
@endsection
