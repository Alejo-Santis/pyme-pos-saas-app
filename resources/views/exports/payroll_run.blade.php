@extends('exports._layout')
@section('content')
<div class="header">
  <div class="company-name">{{ $company?->business_name ?? 'Empresa' }}</div>
  <div class="report-title">Liquidación de Nómina — {{ $run->name }}</div>
  <div class="report-period">
    Período: {{ \Carbon\Carbon::parse($run->period_start)->format('d/m/Y') }}
    al {{ \Carbon\Carbon::parse($run->period_end)->format('d/m/Y') }}
    | Estado: {{ \App\Modules\Payroll\Models\PayrollRun::statusLabel($run->status) }}
    | Empleados: {{ $run->details->count() }}
  </div>
</div>

<!-- Resumen -->
<table style="width:100%; margin-bottom:10px;">
  <tbody>
    <tr>
      <td style="background:#dbeafe;padding:5px 8px;font-size:9px;width:25%;">
        <div style="font-size:7px;color:#64748b;">Total Devengado</div>
        <div style="font-weight:bold;font-size:11px;">$ {{ number_format($run->total_earned, 0, ',', '.') }}</div>
      </td>
      <td style="background:#fee2e2;padding:5px 8px;font-size:9px;width:25%;">
        <div style="font-size:7px;color:#64748b;">Total Deducciones</div>
        <div style="font-weight:bold;font-size:11px;">$ {{ number_format($run->total_deductions, 0, ',', '.') }}</div>
      </td>
      <td style="background:#dcfce7;padding:5px 8px;font-size:9px;width:25%;">
        <div style="font-size:7px;color:#64748b;">Neto a Pagar</div>
        <div style="font-weight:bold;font-size:11px;color:#166534;">$ {{ number_format($run->total_net, 0, ',', '.') }}</div>
      </td>
      <td style="background:#fef9c3;padding:5px 8px;font-size:9px;width:25%;">
        <div style="font-size:7px;color:#64748b;">Costo Total Empleador</div>
        <div style="font-weight:bold;font-size:11px;color:#713f12;">$ {{ number_format($run->total_employer_cost, 0, ',', '.') }}</div>
      </td>
    </tr>
  </tbody>
</table>

<!-- Detalle por empleado -->
<table>
  <thead>
    <tr>
      <th>Empleado</th>
      <th>Cargo</th>
      <th class="text-center">Días</th>
      <th class="text-right">Sal. Básico</th>
      <th class="text-right">Transp.</th>
      <th class="text-right">Extras/Com.</th>
      <th class="text-right">Devengado</th>
      <th class="text-right">Salud</th>
      <th class="text-right">Pensión</th>
      <th class="text-right">Ret.Fte</th>
      <th class="text-right">Deducciones</th>
      <th class="text-right" style="background:#166534;color:#fff;">NETO</th>
    </tr>
  </thead>
  <tbody>
    @foreach($run->details as $d)
    <tr>
      <td>{{ $d->employee?->first_name }} {{ $d->employee?->last_name }}</td>
      <td>{{ $d->contract?->job_title ?? '—' }}</td>
      <td class="text-center">{{ $d->worked_days }}</td>
      <td class="text-right">{{ number_format($d->basic_salary, 0, ',', '.') }}</td>
      <td class="text-right">{{ $d->transport_allowance > 0 ? number_format($d->transport_allowance, 0, ',', '.') : '—' }}</td>
      <td class="text-right">{{ ($d->overtime_amount + $d->commissions + $d->bonuses) > 0 ? number_format($d->overtime_amount + $d->commissions + $d->bonuses, 0, ',', '.') : '—' }}</td>
      <td class="text-right" style="font-weight:bold;">{{ number_format($d->total_earned, 0, ',', '.') }}</td>
      <td class="text-right" style="color:#991b1b;">({{ number_format($d->health_employee, 0, ',', '.') }})</td>
      <td class="text-right" style="color:#991b1b;">({{ number_format($d->pension_employee, 0, ',', '.') }})</td>
      <td class="text-right" style="color:#991b1b;">{{ $d->income_tax_withholding > 0 ? '('.number_format($d->income_tax_withholding, 0, ',', '.').')' : '—' }}</td>
      <td class="text-right" style="color:#991b1b;">({{ number_format($d->total_deductions, 0, ',', '.') }})</td>
      <td class="text-right" style="font-weight:bold;color:#166534;">{{ number_format($d->net_pay, 0, ',', '.') }}</td>
    </tr>
    @endforeach
    <tr class="total-row">
      <td colspan="6">TOTALES</td>
      <td class="text-right">{{ number_format($run->total_earned, 0, ',', '.') }}</td>
      <td colspan="3"></td>
      <td class="text-right">({{ number_format($run->total_deductions, 0, ',', '.') }})</td>
      <td class="text-right" style="color:#166534;">{{ number_format($run->total_net, 0, ',', '.') }}</td>
    </tr>
  </tbody>
</table>

<!-- Firma -->
<div style="margin-top:30px;">
  <table style="width:60%;">
    <tr>
      <td style="width:50%;text-align:center;padding-top:20px;border-top:1px solid #94a3b8;font-size:8px;color:#64748b;">
        Elaborado por: {{ $run->createdBy?->name ?? '—' }}<br>
        Cargo: Recursos Humanos
      </td>
      <td style="width:50%;text-align:center;padding-top:20px;border-top:1px solid #94a3b8;font-size:8px;color:#64748b;">
        Aprobado por: {{ $run->approvedBy?->name ?? '—' }}<br>
        Cargo: Gerente
      </td>
    </tr>
  </table>
</div>
@endsection
