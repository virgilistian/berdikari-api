@extends('tax::pdf.layout')

@section('price-box')
    Harga Tiket<br>
    Weekday : {{ number_format((float) $config->get('swimming_pool.weekday_price', 0), 0, ',', '.') }}<br>
    Weekend : {{ number_format((float) $config->get('swimming_pool.weekend_price', 0), 0, ',', '.') }}
@endsection

@section('table')
<table class="report">
    <thead>
        <tr>
            <th rowspan="2">No.</th>
            <th rowspan="2">Hari</th>
            <th rowspan="2">Libur<br>Nasional</th>
            <th colspan="3">Penerimaan Hasil</th>
        </tr>
        <tr>
            <th>Jumlah Tiket</th>
            <th>Hasil Penjualan</th>
            <th>Pajak 10%</th>
        </tr>
    </thead>
    <tbody>
        @foreach($report->entries as $entry)
        <tr>
            <td class="center">{{ $entry->day_number }}</td>
            <td>{{ $entry->weekday_name }}</td>
            <td class="center">{{ $entry->is_holiday ? 'ya' : '' }}</td>
            <td class="num">{{ number_format((float) ($entry->ticket_qty ?? 0), 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $entry->sales, 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $entry->tax, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3" class="center">Jumlah</td>
            <td class="num"></td>
            <td class="num">{{ number_format((float) $report->total_sales, 0, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $report->total_tax, 0, ',', '.') }}</td>
        </tr>
    </tfoot>
</table>
@endsection
