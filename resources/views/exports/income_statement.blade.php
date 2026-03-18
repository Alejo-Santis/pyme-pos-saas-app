@extends('exports._layout')
@section('content')
<div class="header">
  <div class="company-name">{{ $company?->name ?? 'Empresa' }}</div>
  <div class="report-title">Estado de Resultados (P&G)</div>
  <div class="report-period">Período: {{ \Carbon\Carbon::parse($from)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
</div>

<table style="width:60%; margin-bottom:12px;">
  <tbody>
    <tr>
      <td style="padding:4px 8px; font-weight:bold;">Ingresos Operacionales</td>
      <td class="text-right" style="padding:4px 8px;">$ {{ number_format($summary['ingresos_operacionales'], 0, ',', '.') }}</td>
    </tr>
    <tr style="background:#fee2e2;">
      <td style="padding:4px 8px;">(–) Costo de Ventas</td>
      <td class="text-right" style="padding:4px 8px;">$ {{ number_format($summary['costo_ventas'], 0, ',', '.') }}</td>
    </tr>
    <tr style="background:#f0f4ff;font-weight:bold;">
      <td style="padding:5px 8px; border-top:1px solid #4e73df;">= Utilidad Bruta</td>
      <td class="text-right" style="padding:5px 8px; border-top:1px solid #4e73df;">$ {{ number_format($summary['utilidad_bruta'], 0, ',', '.') }}</td>
    </tr>
    <tr style="background:#fee2e2;">
      <td style="padding:4px 8px;">(–) Gastos Operacionales</td>
      <td class="text-right" style="padding:4px 8px;">$ {{ number_format($summary['gastos_operacionales'], 0, ',', '.') }}</td>
    </tr>
    <tr style="background:#f0f4ff;font-weight:bold;">
      <td style="padding:5px 8px; border-top:1px solid #4e73df;">= Utilidad Operativa</td>
      <td class="text-right" style="padding:5px 8px; border-top:1px solid #4e73df;">$ {{ number_format($summary['utilidad_operativa'], 0, ',', '.') }}</td>
    </tr>
    @if($summary['ingresos_no_operacionales'] != 0 || $summary['gastos_no_operacionales'] != 0)
    <tr>
      <td style="padding:4px 8px;">(+) Ingresos No Operacionales</td>
      <td class="text-right" style="padding:4px 8px;">$ {{ number_format($summary['ingresos_no_operacionales'], 0, ',', '.') }}</td>
    </tr>
    <tr style="background:#fee2e2;">
      <td style="padding:4px 8px;">(–) Gastos No Operacionales</td>
      <td class="text-right" style="padding:4px 8px;">$ {{ number_format($summary['gastos_no_operacionales'], 0, ',', '.') }}</td>
    </tr>
    @endif
    <tr style="background:{{ $summary['utilidad_neta'] >= 0 ? '#dcfce7' : '#fee2e2' }};font-weight:bold;font-size:10px;">
      <td style="padding:6px 8px; border-top:2px solid #1e293b;">= UTILIDAD NETA DEL PERÍODO</td>
      <td class="text-right" style="padding:6px 8px; border-top:2px solid #1e293b;">$ {{ number_format($summary['utilidad_neta'], 0, ',', '.') }}</td>
    </tr>
  </tbody>
</table>

<!-- Detalle por cuentas -->
@foreach($detail as $class)
<div style="font-weight:bold;font-size:9px;margin:8px 0 4px;color:#4e73df;">
  CLASE {{ $class['class'] }} — {{ strtoupper($class['class_name']) }}
</div>
<table>
  <thead>
    <tr>
      <th>Grupo / Cuenta</th>
      <th>Nombre</th>
      <th class="text-right">Saldo</th>
    </tr>
  </thead>
  <tbody>
    @foreach($class['groups'] as $group)
    <tr class="section-header">
      <td>{{ $group['code'] }}</td>
      <td>{{ $group['name'] }}</td>
      <td class="text-right">$ {{ number_format($group['total'], 0, ',', '.') }}</td>
    </tr>
    @foreach($group['accounts'] as $acc)
    <tr>
      <td style="padding-left:16px;">{{ $acc['code'] }}</td>
      <td>{{ $acc['name'] }}</td>
      <td class="text-right">$ {{ number_format($acc['balance'], 0, ',', '.') }}</td>
    </tr>
    @endforeach
    @endforeach
    <tr class="total-row">
      <td colspan="2">TOTAL {{ strtoupper($class['class_name']) }}</td>
      <td class="text-right">$ {{ number_format($class['total'], 0, ',', '.') }}</td>
    </tr>
  </tbody>
</table>
@endforeach
@endsection
