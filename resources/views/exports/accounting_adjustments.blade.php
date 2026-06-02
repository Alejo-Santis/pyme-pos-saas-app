@extends('exports._layout')

@section('content')
<div class="header">
  <div class="company-name">{{ $company?->name ?? 'Empresa' }}</div>
  <div class="report-title">Ajustes Contables</div>
  <div class="report-period">
    Período: {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}
  </div>
</div>

@php
  $statusLabels = [
    'active' => 'Activos',
    'reversed' => 'Reversados',
    'reversal' => 'Sólo reversos',
    'all' => 'Todos',
  ];
  $totalDebit = 0;
  $totalCredit = 0;
@endphp

<div style="font-size:8px;margin-bottom:6px;color:#475569;">
  Estado: {{ $statusLabels[$status] ?? 'Todos' }}
</div>

@foreach($vouchers as $voucher)
  @php
    $isReversal = (int) $voucher->type_document_operation_id === 99;
    $state = $voucher->reversed_at ? 'Reversado' : ($voucher->annulled ? 'Anulado' : 'Activo');
  @endphp
  <div style="font-size:8px;font-weight:bold;margin:8px 0 2px;background:#f8fafc;padding:4px 6px;border-left:2px solid {{ $isReversal ? '#2563eb' : '#d97706' }};">
    {{ $voucher->internal_code }} — {{ \Carbon\Carbon::parse($voucher->issue_date)->format('d/m/Y') }}
    — {{ $isReversal ? 'Reverso de ajuste' : 'Ajuste manual' }}
    — {{ $state }}
    @if($voucher->document)
      — Ref: {{ $voucher->document->internal_code }}
    @endif
    @if($voucher->reversal)
      — Reverso: {{ $voucher->reversal->internal_code }}
    @endif
  </div>
  @if($voucher->notes)
    <div style="font-size:7px;color:#64748b;margin:0 0 3px 6px;">
      {{ $voucher->notes }}
    </div>
  @endif

  <table>
    <thead>
      <tr>
        <th style="width:18%;">Cuenta</th>
        <th>Documento</th>
        <th class="text-right" style="width:15%;">Débito</th>
        <th class="text-right" style="width:15%;">Crédito</th>
      </tr>
    </thead>
    <tbody>
      @foreach($voucher->lines as $line)
        @php
          $totalDebit += $line->debit ?? 0;
          $totalCredit += $line->credit ?? 0;
        @endphp
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
