@extends('exports._layout')
@section('content')
<div class="header">
  <div class="company-name">{{ $company?->name ?? 'Empresa' }}</div>
  <div class="report-title">Balance General</div>
  <div class="report-period">Al {{ \Carbon\Carbon::parse($to)->format('d/m/Y') }}</div>
</div>

<!-- Resumen de cuadre -->
<table style="width:100%; margin-bottom:10px;">
  <tbody>
    <tr>
      <td style="background:#dbeafe;padding:6px 10px;font-weight:bold;font-size:10px; width:33%;">
        ACTIVOS<br>
        <span style="font-size:13px;color:#1e40af;">$ {{ number_format($summary['total_activos'], 0, ',', '.') }}</span>
      </td>
      <td style="background:#fce7f3;padding:6px 10px;font-weight:bold;font-size:10px; width:33%;">
        PASIVOS<br>
        <span style="font-size:13px;color:#be185d;">$ {{ number_format($summary['total_pasivos'], 0, ',', '.') }}</span>
      </td>
      <td style="background:#dcfce7;padding:6px 10px;font-weight:bold;font-size:10px; width:34%;">
        PATRIMONIO (incl. utilidad)<br>
        <span style="font-size:13px;color:#15803d;">$ {{ number_format($summary['patrimonio_total'], 0, ',', '.') }}</span>
        <span style="display:block;font-size:7px;color:#64748b;">Utilidad del período: $ {{ number_format($summary['utilidad_periodo'], 0, ',', '.') }}</span>
      </td>
    </tr>
    <tr>
      <td colspan="3" style="text-align:center;padding:5px;font-size:9px;font-weight:bold;
        background:{{ $summary['cuadre'] ? '#dcfce7' : '#fee2e2' }};
        color:{{ $summary['cuadre'] ? '#166534' : '#991b1b' }};">
        {{ $summary['cuadre'] ? '✓ Balance cuadrado — Activos = Pasivos + Patrimonio' : '✗ Balance NO cuadra' }}
      </td>
    </tr>
  </tbody>
</table>

<!-- Detalle por clase -->
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
