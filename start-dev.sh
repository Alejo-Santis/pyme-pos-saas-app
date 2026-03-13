#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# start-dev.sh — Arranca todos los procesos necesarios para desarrollo local
#
# Abre 3 terminales en paralelo:
#   1. php artisan serve     → servidor HTTP (si no usas Laragon)
#   2. start-worker.sh       → procesador de colas (jobs DIAN, emails)
#   3. start-scheduler.sh    → tareas programadas
#   4. npm run dev           → Vite (hot-reload del frontend Svelte)
#
# Con Laragon: el servidor HTTP y Vite pueden ya estar corriendo.
# En ese caso comenta las líneas que no necesites.
#
# Uso:
#   chmod +x start-dev.sh
#   ./start-dev.sh
# ─────────────────────────────────────────────────────────────────────────────

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  NextPOS SaaS — Entorno de desarrollo local"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Verificar .env
if [ ! -f .env ]; then
    echo "✗ Archivo .env no encontrado. Copia .env.example y configúralo."
    exit 1
fi

# Verificar QUEUE_CONNECTION
if ! grep -q "QUEUE_CONNECTION=database" .env 2>/dev/null; then
    echo "⚠  Agrega QUEUE_CONNECTION=database a tu .env"
fi

# Crear tabla de jobs si no existe
echo "→ Verificando tabla de jobs..."
php artisan queue:table 2>/dev/null || true
php artisan migrate --path=database/migrations/landlord --force 2>/dev/null || true

echo ""
echo "Iniciando procesos en background..."
echo ""

# Queue worker en background
bash start-worker.sh &
WORKER_PID=$!
echo "✓ Queue worker iniciado (PID: $WORKER_PID)"

# Scheduler en background
bash start-scheduler.sh &
SCHED_PID=$!
echo "✓ Scheduler iniciado (PID: $SCHED_PID)"

# Frontend Vite (comenta si ya corre en Laragon)
# npm run dev &
# VITE_PID=$!
# echo "✓ Vite iniciado (PID: $VITE_PID)"

echo ""
echo "Todos los procesos corriendo. Presiona Ctrl+C para detener todo."
echo ""

# Esperar y capturar Ctrl+C para matar los hijos
trap "kill $WORKER_PID $SCHED_PID 2>/dev/null; echo ''; echo 'Procesos detenidos.'; exit 0" INT TERM

wait
