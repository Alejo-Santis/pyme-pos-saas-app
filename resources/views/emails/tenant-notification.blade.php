<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectText }}</title>
</head>
<body style="margin:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 28px;background:#2563eb;color:#ffffff;">
                            <strong style="font-size:18px;">PymePOS SaaS</strong>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 8px;color:#64748b;font-size:14px;">Hola, {{ $tenant->name }}.</p>
                            <h1 style="margin:0 0 18px;font-size:22px;line-height:1.25;color:#0f172a;">{{ $subjectText }}</h1>
                            <div style="font-size:15px;line-height:1.6;color:#334155;white-space:pre-line;">{{ $messageText }}</div>

                            @if ($actionUrl && $actionLabel)
                                <p style="margin:26px 0 0;">
                                    <a href="{{ $actionUrl }}" style="display:inline-block;background:#2563eb;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:12px 18px;border-radius:8px;">
                                        {{ $actionLabel }}
                                    </a>
                                </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 28px;background:#f8fafc;color:#94a3b8;font-size:12px;">
                            Este mensaje fue enviado desde el portal de administración de PymePOS SaaS.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
