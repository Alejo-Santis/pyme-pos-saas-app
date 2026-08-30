@extends('exports._layout')
@section('content')
<div class="header">
  <div class="company-name">{{ $company?->business_name ?? 'Empresa' }}</div>
  <div class="report-title">Balance de Prueba (Comprobación de Saldos)</div>
  <div class="report-period">Período: {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
</div>

@if(!$balanced)
<div style="background:#fee2e2;border-left:3px solid #dc2626;padding:5px 8px;margin-bottom:8px;font-size:8px;color:#991b1b;">
  ⚠ El balance NO cuadra — diferencia: $ {{ number_format(abs($totalDebit - $totalCredit), 2, ',', '.') }}
</div>
@endif

<table>
  <thead>
    <tr>
      <th>Código</th>
      <th>Cuenta</th>
      <th class="text-right">Débito Total</th>
      <th class="text-right">Crédito Total</th>
      <th class="text-right">Saldo Débito</th>
      <th class="text-right">Saldo Crédito</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $row)
    <tr>
      <td>{{ $row->code }}</td>
      <td>{{ $row->name }}</td>
      <td class="text-right">{{ number_format($row->total_debit, 2, ',', '.') }}</td>
      <td class="text-right">{{ number_format($row->total_credit, 2, ',', '.') }}</td>
      <td class="text-right">{{ $row->balance_debit > 0 ? number_format($row->balance_debit, 2, ',', '.') : '' }}</td>
      <td class="text-right">{{ $row->balance_credit > 0 ? number_format($row->balance_credit, 2, ',', '.') : '' }}</td>
    </tr>
    @endforeach
    <tr class="total-row">
      <td colspan="2">TOTALES</td>
      <td class="text-right">{{ number_format($totalDebit, 2, ',', '.') }}</td>
      <td class="text-right">{{ number_format($totalCredit, 2, ',', '.') }}</td>
      <td colspan="2" class="text-center">
        @if($balanced)
          <span class="badge-ok">CUADRA ✓</span>
        @else
          <span class="badge-error">NO CUADRA ✗</span>
        @endif
      </td>
    </tr>
  </tbody>
</table>
@endsection
