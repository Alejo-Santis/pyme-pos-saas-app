@extends('exports._layout')
@section('content')
<div class="header">
  <div class="company-name">{{ $company?->name ?? 'Empresa' }}</div>
  <div class="report-title">Reporte de Caja y Movimientos</div>
  <div class="report-period">Período: {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
</div>

<table>
  <thead>
    <tr>
      <th>Fecha</th>
      <th>Caja</th>
      <th>Concepto</th>
      <th>Documento</th>
      <th class="text-right">Ingreso</th>
      <th class="text-right">Egreso</th>
    </tr>
  </thead>
  <tbody>
    @php $totalDebit = 0; $totalCredit = 0; @endphp
    @foreach($movements as $m)
    @php $totalDebit += $m->debit ?? 0; $totalCredit += $m->credit ?? 0; @endphp
    <tr>
      <td>{{ \Carbon\Carbon::parse($m->issue_date)->format('d/m/Y') }}</td>
      <td>{{ $m->cashBox?->name ?? '—' }}</td>
      <td>{{ $m->concept ?? '—' }}</td>
      <td>{{ $m->document?->internal_code ?? '—' }}</td>
      <td class="text-right">{{ $m->debit > 0 ? '$ '.number_format($m->debit, 0, ',', '.') : '' }}</td>
      <td class="text-right">{{ $m->credit > 0 ? '$ '.number_format($m->credit, 0, ',', '.') : '' }}</td>
    </tr>
    @endforeach
    <tr class="total-row">
      <td colspan="4">TOTALES</td>
      <td class="text-right">$ {{ number_format($totalDebit, 0, ',', '.') }}</td>
      <td class="text-right">$ {{ number_format($totalCredit, 0, ',', '.') }}</td>
    </tr>
    <tr class="total-row">
      <td colspan="4">SALDO NETO</td>
      <td class="text-right" colspan="2">$ {{ number_format($totalDebit - $totalCredit, 0, ',', '.') }}</td>
    </tr>
  </tbody>
</table>
@endsection
