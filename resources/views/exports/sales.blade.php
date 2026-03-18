@extends('exports._layout')
@section('content')
<div class="header">
  <div class="company-name">{{ $company?->name ?? 'Empresa' }}</div>
  <div class="report-title">Reporte de Ventas — {{ ucfirst($groupBy) }}</div>
  <div class="report-period">Período: {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
</div>

<!-- Resumen -->
<table style="width:auto; margin-bottom:10px;">
  <tr>
    <td style="padding:4px 10px; background:#f0f4ff; border-left:3px solid #4e73df;">
      <div style="font-size:7px;color:#64748b;">Documentos</div>
      <div style="font-size:12px;font-weight:bold;">{{ number_format($totals->total_docs) }}</div>
    </td>
    <td style="padding:4px 10px; background:#f0f4ff; border-left:3px solid #1cc88a; margin-left:4px;">
      <div style="font-size:7px;color:#64748b;">Total Ventas</div>
      <div style="font-size:12px;font-weight:bold;">$ {{ number_format($totals->total_amount, 0, ',', '.') }}</div>
    </td>
    <td style="padding:4px 10px; background:#f0f4ff; border-left:3px solid #f6c23e;">
      <div style="font-size:7px;color:#64748b;">IVA</div>
      <div style="font-size:12px;font-weight:bold;">$ {{ number_format($totals->total_tax, 0, ',', '.') }}</div>
    </td>
  </tr>
</table>

<table>
  <thead>
    <tr>
      <th>Período / Agrupación</th>
      <th class="text-right">Documentos</th>
      <th class="text-right">Total Venta</th>
      <th class="text-right">IVA</th>
    </tr>
  </thead>
  <tbody>
    @foreach($rows as $r)
    <tr>
      <td>{{ $r->period ?? $r->product_name ?? '—' }}</td>
      <td class="text-right">{{ number_format($r->total_docs) }}</td>
      <td class="text-right">$ {{ number_format($r->total_amount ?? 0, 0, ',', '.') }}</td>
      <td class="text-right">$ {{ number_format($r->total_tax ?? 0, 0, ',', '.') }}</td>
    </tr>
    @endforeach
    <tr class="total-row">
      <td>TOTALES</td>
      <td class="text-right">{{ number_format($totals->total_docs) }}</td>
      <td class="text-right">$ {{ number_format($totals->total_amount, 0, ',', '.') }}</td>
      <td class="text-right">$ {{ number_format($totals->total_tax, 0, ',', '.') }}</td>
    </tr>
  </tbody>
</table>
@endsection
