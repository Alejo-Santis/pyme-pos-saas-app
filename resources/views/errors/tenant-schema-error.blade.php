<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sesión expirada — PymePOS SaaS</title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=2">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Nunito', sans-serif;
      background: linear-gradient(135deg, #f0f4ff 0%, #e8eeff 50%, #f5f0ff 100%);
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      padding: 1.5rem; color: #374151;
    }
    .card {
      background: white; border-radius: 1.25rem;
      box-shadow: 0 20px 60px rgba(0,0,0,0.08);
      max-width: 520px; width: 100%;
      padding: 3rem 2.5rem; text-align: center;
    }
    .logo {
      display: inline-flex; align-items: center; gap: .5rem;
      margin-bottom: 2rem; text-decoration: none;
    }
    .logo-icon {
      width: 2.5rem; height: 2.5rem; background: #2563eb;
      border-radius: .75rem; display: flex; align-items: center;
      justify-content: center; font-size: 1.1rem; color: white;
    }
    .logo-text { font-size: 1.1rem; font-weight: 700; color: #1e40af; }
    .logo-sub  { font-size: 1.1rem; font-weight: 300; color: #6b7280; }
    .icon-wrap {
      width: 5rem; height: 5rem; background: #fef3c7;
      border-radius: 50%; display: flex; align-items: center;
      justify-content: center; margin: 0 auto 1.5rem; font-size: 2.2rem;
    }
    h1 { font-size: 1.375rem; font-weight: 700; color: #111827; margin-bottom: .5rem; }
    .domain {
      display: inline-block; background: #f1f5f9; border: 1px solid #e2e8f0;
      border-radius: .5rem; padding: .2rem .75rem; font-size: .85rem;
      font-family: monospace; color: #475569; margin-bottom: 1.25rem;
    }
    p { font-size: .9rem; color: #6b7280; line-height: 1.6; margin-bottom: 1.5rem; }
    .alert {
      background: #fffbeb; border: 1px solid #fde68a; border-radius: .75rem;
      padding: .875rem 1rem; font-size: .85rem; color: #92400e;
      text-align: left; margin-bottom: 1.75rem; line-height: 1.5;
    }
    .alert strong { display: block; margin-bottom: .25rem; }
    .actions { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }
    .btn {
      display: inline-flex; align-items: center; gap: .5rem;
      padding: .65rem 1.5rem; border-radius: .65rem;
      font-weight: 600; font-size: .9rem;
      text-decoration: none; transition: background .15s;
    }
    .btn-primary { background: #2563eb; color: white; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .btn-secondary:hover { background: #e2e8f0; }
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

    <div class="icon-wrap">🔄</div>

    <h1>Sesión desactualizada</h1>

    <span class="domain">{{ $domain }}</span>

    <p>
      Tu sesión hace referencia a datos que ya no están disponibles,
      posiblemente porque la empresa fue reconfigurada o el sistema
      fue actualizado mientras estabas conectado.
    </p>

    <div class="alert">
      <strong>¿Qué hacer?</strong>
      Inicia sesión nuevamente. Si el problema persiste, contacta
      al administrador del sistema o registra tu empresa de nuevo.
    </div>

    <div class="actions">
      <a href="/login" class="btn btn-primary">Iniciar sesión</a>
      <a href="{{ config('app.url') }}" class="btn btn-secondary">Volver al inicio</a>
    </div>

    <div class="footer">
      &copy; {{ date('Y') }} PymePOS SaaS · Todos los derechos reservados
    </div>
  </div>
</body>
</html>
