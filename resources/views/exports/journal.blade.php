@extends('exports._layout')
@section('content')
<div class="header">
  <div class="company-name">{{ $company?->business_name ?? 'Empresa' }}</div>
  <div class="report-title">Libro Diario</div>
  <div class="report-period">Período: {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
</div>

@php $totalDebit = 0; $totalCredit = 0; @endphp
@foreach($vouchers as $v)
@php
  $typeLabels = [
    1 => 'Factura Venta',
    5 => 'Documento soporte',
    14 => 'Compra',
    91 => 'Nota Crédito',
    92 => 'Nota Débito',
    98 => 'Ajuste manual',
    99 => 'Reverso de ajuste',
  ];
  $state = $v->reversed_at ? 'Reversado' : ($v->annulled ? 'Anulado' : 'Activo');
@endphp
<div style="font-size:8px;font-weight:bold;margin:8px 0 2px;background:#f0f4ff;padding:3px 6px;border-left:2px solid #4e73df;">
  {{ $v->internal_code }} — {{ \Carbon\Carbon::parse($v->issue_date)->format('d/m/Y') }}
  — {{ $typeLabels[(int) $v->type_document_operation_id] ?? 'Operación '.$v->type_document_operation_id }}
  — {{ $state }}
  @if($v->document) — Ref: {{ $v->document->internal_code }} @endif
  @if($v->reversal) — Reverso: {{ $v->reversal->internal_code }} @endif
</div>
@if($v->notes)
<div style="font-size:7px;color:#64748b;margin:0 0 3px 6px;">{{ $v->notes }}</div>
@endif
<table>
  <thead>
    <tr>
      <th style="width:15%;">Cuenta</th>
      <th>Documento</th>
      <th class="text-right" style="width:15%;">Débito</th>
      <th class="text-right" style="width:15%;">Crédito</th>
    </tr>
  </thead>
  <tbody>
    @foreach($v->lines as $line)
    @php $totalDebit += $line->debit ?? 0; $totalCredit += $line->credit ?? 0; @endphp
    <tr>
      <td>{{ $line->accountable_id }}</td>
      <td>{{ $line->document_number ?? '—' }}</td>
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
