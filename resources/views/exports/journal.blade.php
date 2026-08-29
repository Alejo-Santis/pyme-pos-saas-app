@extends('exports._layout')
@section('content')
<div class="header">
  <div class="company-name">{{ $company?->business_name ?? 'Empresa' }}</div>
  <div class="report-title">Libro Diario</div>
  <div class="report-period">Período: {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
</div>

@php $totalDebit = 0; $totalCredit = 0; @endphp
@foreach($vouchers as $v)
<div style="font-size:8px;font-weight:bold;margin:8px 0 2px;background:#f0f4ff;padding:3px 6px;border-left:2px solid #4e73df;">
  {{ $v->internal_code }} — {{ \Carbon\Carbon::parse($v->issue_date)->format('d/m/Y') }}
  @if($v->description) — {{ $v->description }} @endif
</div>
<table>
  <thead>
    <tr>
      <th style="width:15%;">Cuenta</th>
      <th>Descripción</th>
      <th class="text-right" style="width:15%;">Débito</th>
      <th class="text-right" style="width:15%;">Crédito</th>
    </tr>
  </thead>
  <tbody>
    @foreach($v->lines as $line)
    @php $totalDebit += $line->debit ?? 0; $totalCredit += $line->credit ?? 0; @endphp
    <tr>
      <td>{{ $line->accountable_id }}</td>
      <td>{{ $line->description ?? '—' }}</td>
      <td class="text-right">{{ $line->debit > 0 ? number_format($line->debit, 2, ',', '.') : '' }}</td>
      <td class="text-right">{{ $line->credit > 0 ? number_format($line->credit, 2, ',', '.') : '' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endforeach

<table style="margin-top:10px;">
  <tr class="total-row">
    <td>TOTALES GENERALES</td>
    <td class="text-right">$ {{ number_format($totalDebit, 2, ',', '.') }}</td>
    <td class="text-right">$ {{ number_format($totalCredit, 2, ',', '.') }}</td>
  </tr>
</table>
@endsection
