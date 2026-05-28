<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>@yield('title', 'Error') — PymePOS SaaS</title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=2">
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
      max-width: 520px;
      width: 100%;
      padding: 3rem 2.5rem;
      text-align: center;
    }
    .logo {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      margin-bottom: 2rem;
      text-decoration: none;
    }
    .logo-icon {
      width: 2.5rem; height: 2.5rem;
      background: #2563eb;
      border-radius: .75rem;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem; color: white;
    }
    .logo-text { font-size: 1.1rem; font-weight: 700; color: #1e40af; }
    .logo-sub  { font-size: 1.1rem; font-weight: 300; color: #6b7280; }

    .code {
      font-size: 5rem;
      font-weight: 800;
      line-height: 1;
      margin-bottom: .5rem;
      background: linear-gradient(135deg, @yield('code-color-from', '#2563eb'), @yield('code-color-to', '#7c3aed'));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .icon-wrap {
      width: 5rem; height: 5rem;
      background: @yield('icon-bg', '#eff6ff');
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 2.2rem;
    }
    h1 { font-size: 1.375rem; font-weight: 700; color: #111827; margin-bottom: .5rem; }
    p  { font-size: .9rem; color: #6b7280; line-height: 1.6; margin-bottom: 1.75rem; }

    .actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }
    .btn {
      display: inline-flex; align-items: center; gap: .5rem;
      padding: .65rem 1.5rem;
      border-radius: .65rem;
      font-weight: 600; font-size: .9rem;
      text-decoration: none; transition: background .15s;
    }
    .btn-primary { background: #2563eb; color: white; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .btn-secondary:hover { background: #e2e8f0; }

    .detail {
      margin-top: 1.5rem;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: .75rem;
      padding: .75rem 1rem;
      font-size: .78rem;
      color: #94a3b8;
      font-family: monospace;
      text-align: left;
      word-break: break-all;
    }
    .footer { margin-top: 2.5rem; font-size: .78rem; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="card">
    <a href="{{ config('app.url') }}" class="logo">
      <div class="logo-icon">⚡</div>
      <span class="logo-text">PymePOS</span>
      <span class="logo-sub"> SaaS</span>
    </a>

    <div class="icon-wrap">@yield('icon', '⚠️')</div>

    <div class="code">@yield('code', '?')</div>

    <h1>@yield('title', 'Algo salió mal')</h1>

    <p>@yield('message', 'Ocurrió un error inesperado.')</p>

    <div class="actions">
      @yield('actions')
    </div>

    @hasSection('detail')
      <div class="detail">@yield('detail')</div>
    @endif

    <div class="footer">
      &copy; {{ date('Y') }} PymePOS SaaS · Todos los derechos reservados
    </div>
  </div>
</body>
</html>
