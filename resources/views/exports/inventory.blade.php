@extends('exports._layout')
@section('content')
<div class="header">
  <div class="company-name">{{ $company?->business_name ?? 'Empresa' }}</div>
  <div class="report-title">Reporte de Inventario</div>
  <div class="report-period">Generado: {{ now()->format('d/m/Y H:i') }}</div>
</div>

<table>
  <thead>
    <tr>
      <th>Código</th>
      <th>Nombre</th>
      <th>Categoría</th>
      <th class="text-right">Stock</th>
      <th class="text-right">Mín.</th>
      <th class="text-right">Costo Prom.</th>
      <th class="text-right">P. Venta</th>
      <th class="text-right">Valor Stock</th>
      <th class="text-center">Estado</th>
    </tr>
  </thead>
  <tbody>
    @php $totalValue = 0; @endphp
    @foreach($items as $i)
    @php $totalValue += $i['stock_value'] ?? 0; @endphp
    <tr>
      <td>{{ $i['internal_code'] }}</td>
      <td>{{ $i['name'] }}</td>
      <td>{{ $i['category'] }}</td>
      <td class="text-right">{{ number_format($i['total_stock'], 2, ',', '.') }}</td>
      <td class="text-right">{{ number_format($i['min_stock'], 2, ',', '.') }}</td>
      <td class="text-right">$ {{ number_format($i['avg_cost'], 0, ',', '.') }}</td>
      <td class="text-right">$ {{ number_format($i['sale_price'], 0, ',', '.') }}</td>
      <td class="text-right">$ {{ number_format($i['stock_value'], 0, ',', '.') }}</td>
      <td class="text-center">
        @if($i['status'] === 'empty')
          <span class="badge-error">Sin stock</span>
        @elseif($i['status'] === 'low')
          <span class="badge-warn">Stock bajo</span>
        @else
          <span class="badge-ok">OK</span>
        @endif
      </td>
    </tr>
    @endforeach
    <tr class="total-row">
      <td colspan="7">VALOR TOTAL INVENTARIO</td>
      <td class="text-right">$ {{ number_format($totalValue, 0, ',', '.') }}</td>
      <td></td>
    </tr>
  </tbody>
</table>
@endsection
