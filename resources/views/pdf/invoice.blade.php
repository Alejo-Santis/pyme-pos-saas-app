<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $document->prefix }}{{ $document->number }} — {{ $company->business_name ?? $company->trade_name }}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'DejaVu Sans', Arial, sans-serif;
      font-size: 10px;
      color: #1e293b;
      background: #fff;
      padding: 20px 24px;
    }

    /* ── Cabecera ──────────────────────────────────────────── */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2px solid #2563eb;
      padding-bottom: 12px;
      margin-bottom: 14px;
    }

    .company-block { flex: 1; }
    .company-name { font-size: 15px; font-weight: 700; color: #1e3a8a; }
    .company-sub  { font-size: 9px; color: #64748b; margin-top: 2px; line-height: 1.5; }

    .doc-block {
      text-align: right;
      min-width: 160px;
    }
    .doc-type {
      font-size: 11px;
      font-weight: 700;
      color: #1e3a8a;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .doc-number {
      font-size: 18px;
      font-weight: 800;
      color: #2563eb;
      margin: 2px 0;
    }
    .doc-meta { font-size: 8.5px; color: #64748b; line-height: 1.6; }

    /* ── Badges de estado ──────────────────────────────────── */
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 9999px;
      font-size: 8px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-top: 4px;
    }
    .badge-dian    { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
    .badge-paid    { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
    .badge-annulled{ background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
    .badge-draft   { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }

    /* ── Sección de info (emisor / receptor) ───────────────── */
    .info-grid {
      display: flex;
      gap: 16px;
      margin-bottom: 14px;
    }
    .info-box {
      flex: 1;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      padding: 10px 12px;
    }
    .info-box-title {
      font-size: 8px;
      font-weight: 700;
      color: #94a3b8;
      text-transform: uppercase;
      letter-spacing: 0.7px;
      margin-bottom: 6px;
    }
    .info-row { font-size: 9.5px; line-height: 1.7; }
    .info-label { color: #64748b; }
    .info-val { font-weight: 600; color: #1e293b; }

    /* ── Tabla de líneas ───────────────────────────────────── */
    .lines-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14px;
    }
    .lines-table thead tr {
      background: #1e3a8a;
      color: #fff;
    }
    .lines-table thead th {
      padding: 6px 8px;
      font-size: 8.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    .lines-table thead th.right { text-align: right; }
    .lines-table tbody tr { border-bottom: 1px solid #e2e8f0; }
    .lines-table tbody tr:nth-child(even) { background: #f8fafc; }
    .lines-table tbody td {
      padding: 5px 8px;
      font-size: 9.5px;
      vertical-align: top;
    }
    .lines-table tbody td.right { text-align: right; font-variant-numeric: tabular-nums; }
    .item-name { font-weight: 600; color: #1e293b; }
    .item-code { font-size: 8px; color: #94a3b8; }

    /* ── Totales ───────────────────────────────────────────── */
    .totals-wrap {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 14px;
    }
    .totals-table { width: 240px; border-collapse: collapse; }
    .totals-table td { padding: 3px 8px; font-size: 9.5px; }
    .totals-table td.label { color: #64748b; text-align: left; }
    .totals-table td.val   { text-align: right; font-variant-numeric: tabular-nums; }
    .totals-table tr.separator td { border-top: 1px solid #e2e8f0; padding-top: 5px; }
    .totals-table tr.grand td {
      font-size: 12px;
      font-weight: 800;
      color: #1e3a8a;
      border-top: 2px solid #2563eb;
      padding-top: 5px;
    }

    /* ── CUFE / QR ─────────────────────────────────────────── */
    .dian-block {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 6px;
      padding: 8px 12px;
      margin-bottom: 14px;
    }
    .dian-title { font-size: 8px; font-weight: 700; color: #1d4ed8; text-transform: uppercase; margin-bottom: 4px; }
    .cufe-val { font-size: 7.5px; color: #1e40af; word-break: break-all; font-family: monospace; }

    /* ── Nota / pie ────────────────────────────────────────── */
    .note-block {
      background: #fffbeb;
      border: 1px solid #fde68a;
      border-radius: 6px;
      padding: 6px 10px;
      margin-bottom: 12px;
      font-size: 9px;
      color: #78350f;
    }
    .footer {
      border-top: 1px solid #e2e8f0;
      padding-top: 8px;
      text-align: center;
      font-size: 8px;
      color: #94a3b8;
    }
  </style>
</head>
<body>

  {{-- ── CABECERA ──────────────────────────────────────────── --}}
  <div class="header">
    <div class="company-block">
      <div class="company-name">
        {{ $company->trade_name ?? $company->business_name ?? 'Empresa' }}
      </div>
      <div class="company-sub">
        NIT: {{ $company->identification_number }}{{ $company->dv ? '-' . $company->dv : '' }}<br>
        {{ $company->address ?? '' }}{{ $company->address && $company->municipality_name ? ' · ' : '' }}{{ $company->municipality_name ?? '' }}<br>
        @if($company->phone) Tel: {{ $company->phone }} &nbsp; @endif
        @if($company->email) {{ $company->email }} @endif
      </div>
    </div>

    <div class="doc-block">
      <div class="doc-type">{{ $docTypeName }}</div>
      <div class="doc-number">{{ $document->prefix }}{{ $document->number }}</div>
      <div class="doc-meta">
        Fecha: {{ \Carbon\Carbon::parse($document->issue_date)->format('d/m/Y') }}<br>
        Código interno: {{ $document->internal_code ?? '—' }}
      </div>
      @if($document->annulled)
        <span class="badge badge-annulled">Anulada</span>
      @elseif($document->electronic)
        <span class="badge badge-dian">✓ Enviada DIAN</span>
      @else
        <span class="badge badge-draft">Borrador</span>
      @endif
      @if($document->paid)
        <span class="badge badge-paid">Pagado</span>
      @endif
    </div>
  </div>

  {{-- ── EMISOR / RECEPTOR ─────────────────────────────────── --}}
  <div class="info-grid">

    {{-- Emisor --}}
    <div class="info-box">
      <div class="info-box-title">Emisor</div>
      <div class="info-row">
        <span class="info-label">Empresa: </span>
        <span class="info-val">{{ $company->business_name ?? $company->trade_name }}</span>
      </div>
      <div class="info-row">
        <span class="info-label">NIT: </span>
        <span class="info-val">{{ $company->identification_number }}{{ $company->dv ? '-' . $company->dv : '' }}</span>
      </div>
      @if($resolutionText)
      <div class="info-row">
        <span class="info-label">Resolución: </span>
        <span class="info-val">{{ $resolutionText }}</span>
      </div>
      @endif
    </div>

    {{-- Receptor --}}
    <div class="info-box">
      <div class="info-box-title">Receptor</div>
      @if($third)
      <div class="info-row">
        <span class="info-label">Nombre: </span>
        <span class="info-val">{{ trim(($third->name ?? '') . ' ' . ($third->surname ?? '')) ?: '—' }}</span>
      </div>
      <div class="info-row">
        <span class="info-label">Identificación: </span>
        <span class="info-val">{{ $third->identification_number ?? '—' }}</span>
      </div>
      @if($third->email)
      <div class="info-row">
        <span class="info-label">Email: </span>
        <span class="info-val">{{ $third->email }}</span>
      </div>
      @endif
      @if($third->address)
      <div class="info-row">
        <span class="info-label">Dirección: </span>
        <span class="info-val">{{ $third->address }}</span>
      </div>
      @endif
      @else
      <div class="info-row"><span class="info-val">Consumidor final</span></div>
      @endif
    </div>

  </div>

  {{-- ── LÍNEAS ─────────────────────────────────────────────── --}}
  <table class="lines-table">
    <thead>
      <tr>
        <th style="width:5%; text-align:center;">#</th>
        <th style="width:35%;">Descripción</th>
        <th style="width:8%; text-align:right;">Cant.</th>
        <th style="width:14%; text-align:right;">Precio unit.</th>
        <th style="width:8%; text-align:right;">Dcto %</th>
        <th style="width:10%; text-align:right;">IVA %</th>
        <th style="width:20%; text-align:right;">Total</th>
      </tr>
    </thead>
    <tbody>
      @php
        $subtotal      = 0;
        $totalDiscount = 0;
        $totalTax      = 0;
      @endphp
      @foreach($lines as $i => $line)
        @php
          $base     = floatval($line->amount) * floatval($line->sale_price);
          $disc     = $base * (floatval($line->discount ?? 0) / 100);
          $net      = $base - $disc;
          $taxPct   = floatval($line->taxes[0]['percent'] ?? 0);
          $taxAmt   = $net * ($taxPct / 100);
          $lineTotal= $net + $taxAmt;
          $subtotal      += $net;
          $totalDiscount += $disc;
          $totalTax      += $taxAmt;
        @endphp
        <tr>
          <td style="text-align:center; color:#94a3b8;">{{ $i + 1 }}</td>
          <td>
            <div class="item-name">{{ $line->description }}</div>
            @if($line->item_id)
              <div class="item-code">{{ $line->internal_code ?? '' }}</div>
            @endif
          </td>
          <td class="right">{{ number_format(floatval($line->amount), 2, ',', '.') }}</td>
          <td class="right">{{ number_format(floatval($line->sale_price), 0, ',', '.') }}</td>
          <td class="right">{{ $line->discount ? number_format(floatval($line->discount), 1) . '%' : '—' }}</td>
          <td class="right">{{ $taxPct > 0 ? number_format($taxPct, 0) . '%' : '0%' }}</td>
          <td class="right" style="font-weight:600;">{{ '$' . number_format($lineTotal, 0, ',', '.') }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  {{-- ── TOTALES ─────────────────────────────────────────────── --}}
  @php $grandTotal = $subtotal + $totalTax; @endphp
  <div class="totals-wrap">
    <table class="totals-table">
      <tr>
        <td class="label">Subtotal</td>
        <td class="val">$ {{ number_format($subtotal, 0, ',', '.') }}</td>
      </tr>
      @if($totalDiscount > 0)
      <tr>
        <td class="label">Descuentos</td>
        <td class="val" style="color:#dc2626;">- $ {{ number_format($totalDiscount, 0, ',', '.') }}</td>
      </tr>
      @endif
      @if($totalTax > 0)
      <tr>
        <td class="label">IVA</td>
        <td class="val">$ {{ number_format($totalTax, 0, ',', '.') }}</td>
      </tr>
      @endif
      <tr class="separator grand">
        <td class="label">TOTAL</td>
        <td class="val">$ {{ number_format($grandTotal, 0, ',', '.') }}</td>
      </tr>
    </table>
  </div>

  {{-- ── CUFE (si está enviada a DIAN) ─────────────────────── --}}
  @if($document->cufe)
  <div class="dian-block">
    <div class="dian-title">Documento electrónico DIAN — CUFE / CUDE</div>
    <div class="cufe-val">{{ $document->cufe }}</div>
    @if($document->dian_validation_date_time)
      <div style="font-size:8px; color:#1d4ed8; margin-top:4px;">
        Validado: {{ \Carbon\Carbon::parse($document->dian_validation_date_time['date'] ?? $document->dian_validation_date_time)->format('d/m/Y H:i') }}
      </div>
    @endif
  </div>
  @endif

  {{-- ── NOTA ──────────────────────────────────────────────── --}}
  @if($document->note)
  <div class="note-block">
    <strong>Observaciones:</strong> {{ $document->note }}
  </div>
  @endif

  {{-- ── PIE DE PÁGINA ─────────────────────────────────────── --}}
  <div class="footer">
    Documento generado por PymePOS SaaS · {{ now()->format('d/m/Y H:i') }} ·
    {{ $company->business_name ?? $company->trade_name }} · NIT {{ $company->identification_number }}
  </div>

</body>
</html>
