<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $docNum }}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,sans-serif;font-size:14px;color:#334155;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 0;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

        {{-- Cabecera --}}
        <tr>
          <td style="background:#1e3a8a;padding:28px 32px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td>
                  <p style="margin:0;color:#bfdbfe;font-size:12px;text-transform:uppercase;letter-spacing:1px;">
                    {{ $company->trade_name ?? $company->business_name ?? 'Empresa' }}
                  </p>
                  <h1 style="margin:6px 0 0;color:#ffffff;font-size:22px;font-weight:700;">
                    Documento {{ $docNum }}
                  </h1>
                </td>
                <td align="right">
                  <div style="background:rgba(255,255,255,0.15);border-radius:8px;padding:10px 16px;display:inline-block;">
                    <p style="margin:0;color:#bfdbfe;font-size:11px;">Fecha</p>
                    <p style="margin:4px 0 0;color:#ffffff;font-weight:700;font-size:15px;">
                      {{ \Carbon\Carbon::parse($document->issue_date)->format('d/m/Y') }}
                    </p>
                  </div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- Estado --}}
        @if($document->electronic)
        <tr>
          <td style="background:#dcfce7;padding:10px 32px;border-bottom:1px solid #bbf7d0;">
            <p style="margin:0;color:#166534;font-size:13px;font-weight:600;">
              ✓ &nbsp;Documento electrónico validado por la DIAN
            </p>
          </td>
        </tr>
        @endif

        {{-- Cuerpo --}}
        <tr>
          <td style="padding:28px 32px;">

            {{-- Saludo --}}
            <p style="margin:0 0 20px;color:#475569;font-size:14px;line-height:1.6;">
              Estimado(a)
              @if($document->thirdParty)
                <strong>{{ trim(($document->thirdParty->name ?? '') . ' ' . ($document->thirdParty->surname ?? '')) ?: $document->thirdParty->identification_number }}</strong>,
              @else
                cliente,
              @endif
              <br>
              Adjunto encontrará el documento <strong>{{ $docNum }}</strong> emitido por
              <strong>{{ $company->trade_name ?? $company->business_name }}</strong>.
            </p>

            {{-- Resumen --}}
            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:24px;">
              <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:12px 16px;color:#64748b;font-size:13px;">Número</td>
                <td style="padding:12px 16px;font-weight:600;font-size:13px;text-align:right;">{{ $docNum }}</td>
              </tr>
              <tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:12px 16px;color:#64748b;font-size:13px;">Fecha</td>
                <td style="padding:12px 16px;font-weight:600;font-size:13px;text-align:right;">
                  {{ \Carbon\Carbon::parse($document->issue_date)->format('d \d\e F \d\e Y') }}
                </td>
              </tr>
              <tr style="background:#eff6ff;">
                <td style="padding:14px 16px;color:#1e40af;font-size:14px;font-weight:700;">Total</td>
                <td style="padding:14px 16px;color:#1e40af;font-size:18px;font-weight:800;text-align:right;">
                  $ {{ number_format(floatval($document->total), 0, ',', '.') }}
                </td>
              </tr>
            </table>

            @if($document->note)
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;margin-bottom:24px;">
              <p style="margin:0;color:#92400e;font-size:13px;">
                <strong>Observaciones:</strong> {{ $document->note }}
              </p>
            </div>
            @endif

            <p style="margin:0 0 8px;color:#64748b;font-size:13px;line-height:1.6;">
              El documento en formato PDF está adjunto a este correo.
              Si tiene alguna inquietud no dude en contactarnos.
            </p>

          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="background:#f8fafc;border-top:1px solid #e2e8f0;padding:20px 32px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td>
                  <p style="margin:0;color:#94a3b8;font-size:12px;line-height:1.6;">
                    <strong style="color:#475569;">{{ $company->trade_name ?? $company->business_name }}</strong><br>
                    NIT: {{ $company->identification_number }}{{ $company->dv ? '-' . $company->dv : '' }}<br>
                    @if($company->phone) Tel: {{ $company->phone }}<br> @endif
                    @if($company->email) {{ $company->email }} @endif
                  </p>
                </td>
                <td align="right" style="vertical-align:bottom;">
                  <p style="margin:0;color:#cbd5e1;font-size:11px;">
                    Generado por <strong>PymePOS SaaS</strong>
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
