<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Empresa no encontrada — PymePOS SaaS</title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Nunito', sans-serif;
      background: linear-gradient(135deg, #f0f4ff 0%, #e8eeff 50%, #f5f0ff 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      color: #374151;
    }
    .card {
      background: white;
      border-radius: 1.25rem;
      box-shadow: 0 20px 60px rgba(0,0,0,0.08);
      max-width: 480px;
      width: 100%;
      padding: 3rem 2.5rem;
      text-align: center;
    }
    .logo {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      margin-bottom: 2rem;
    }
    .logo-icon {
      width: 2.5rem;
      height: 2.5rem;
      background: #2563eb;
      border-radius: .75rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
      color: white;
    }
    .logo-text { font-size: 1.1rem; font-weight: 700; color: #1e40af; }
    .logo-sub  { font-size: 1.1rem; font-weight: 300; color: #6b7280; }
    .icon-wrap {
      width: 5rem;
      height: 5rem;
      background: #fef2f2;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 2.2rem;
    }
    h1 { font-size: 1.375rem; font-weight: 700; color: #111827; margin-bottom: .5rem; }
    .domain {
      display: inline-block;
      background: #f1f5f9;
      border: 1px solid #e2e8f0;
      border-radius: .5rem;
      padding: .2rem .75rem;
      font-size: .85rem;
      font-family: monospace;
      color: #475569;
      margin-bottom: 1.25rem;
    }
    p { font-size: .9rem; color: #6b7280; line-height: 1.6; margin-bottom: 1.75rem; }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      background: #2563eb;
      color: white;
      padding: .65rem 1.5rem;
      border-radius: .65rem;
      font-weight: 600;
      font-size: .9rem;
      text-decoration: none;
      transition: background .15s;
    }
    .btn:hover { background: #1d4ed8; }
    .footer { margin-top: 2.5rem; font-size: .78rem; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="card">

    <div class="logo">
      <div class="logo-icon">⚡</div>
      <span class="logo-text">PymePOS</span>
      <span class="logo-sub">SaaS</span>
    </div>

    <div class="icon-wrap">🏢</div>

    <h1>Empresa no encontrada</h1>

    <span class="domain">{{ $domain }}</span>

    <p>
      El subdominio que intentas acceder no corresponde a ninguna empresa
      registrada en nuestra plataforma. Verifica que el enlace sea correcto
      o regístrate para crear tu empresa.
    </p>

    <a href="{{ config('app.url') }}" class="btn">
      ← Volver al inicio
    </a>

    <div class="footer">
      &copy; {{ date('Y') }} PymePOS SaaS · Todos los derechos reservados
    </div>

  </div>
</body>
</html>
