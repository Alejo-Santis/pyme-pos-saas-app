<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #1e293b; }
  .header { border-bottom: 2px solid #4e73df; padding-bottom: 8px; margin-bottom: 12px; }
  .company-name { font-size: 14px; font-weight: bold; color: #1e293b; }
  .report-title { font-size: 11px; color: #4e73df; font-weight: bold; margin-top: 2px; }
  .report-period { font-size: 8px; color: #64748b; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  th { background-color: #4e73df; color: #fff; padding: 5px 6px; text-align: left; font-size: 8px; }
  td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; font-size: 8px; }
  tr:nth-child(even) td { background-color: #f8f9ff; }
  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .total-row td { font-weight: bold; background-color: #eef2ff !important; border-top: 2px solid #4e73df; }
  .section-header td { background-color: #e2e8f0 !important; font-weight: bold; color: #1e293b; }
  .summary-box { display: inline-block; padding: 6px 12px; background: #f0f4ff; border-left: 3px solid #4e73df; margin: 4px 4px 4px 0; }
  .summary-label { font-size: 7px; color: #64748b; }
  .summary-value { font-size: 11px; font-weight: bold; color: #1e293b; }
  .badge-ok    { background:#dcfce7; color:#166534; padding:1px 4px; border-radius:3px; font-size:7px; }
  .badge-warn  { background:#fef9c3; color:#713f12; padding:1px 4px; border-radius:3px; font-size:7px; }
  .badge-error { background:#fee2e2; color:#991b1b; padding:1px 4px; border-radius:3px; font-size:7px; }
  .footer { margin-top: 16px; border-top: 1px solid #e2e8f0; padding-top: 6px; font-size: 7px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
@yield('content')
<div class="footer">
  Generado el {{ now()->format('d/m/Y H:i') }} — {{ $company?->name ?? '' }}
  @if(isset($company) && $company?->nit) — NIT {{ $company->nit }} @endif
</div>
</body>
</html>
