#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# start-worker.sh — Procesador de colas para desarrollo local (sin Redis)
#
# Usa el driver "database" (tabla jobs en PostgreSQL).
# Ejecutar en una terminal separada mientras desarrollas.
#
# Uso:
#   chmod +x start-worker.sh
#   ./start-worker.sh
#
# Para detenerlo: Ctrl+C
# ─────────────────────────────────────────────────────────────────────────────

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  NextPOS SaaS — Queue Worker (driver: database)"
echo "  Jobs: ProcessElectronicInvoice, ProcessElectronicCreditNote,"
echo "        ProcessElectronicSupportDocument"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Verificar que QUEUE_CONNECTION=database esté en .env
if grep -q "QUEUE_CONNECTION=database" .env 2>/dev/null; then
    echo "✓ QUEUE_CONNECTION=database detectado"
else
    echo "⚠  Asegúrate de tener QUEUE_CONNECTION=database en tu .env"
fi

echo ""
echo "Iniciando worker... (Ctrl+C para detener)"
echo ""

# --sleep 3     → espera 3 s si no hay jobs (evita saturar CPU)
# --tries 3     → máximo 3 intentos por job antes de marcarlo como fallido
# --timeout 120 → jobs que tarden más de 120 s son cancelados
# --queue default,high → procesa colas en orden de prioridad
php artisan queue:work database \
    --queue=default,high \
    --sleep=3 \
    --tries=3 \
    --timeout=120 \
    --max-time=3600 \
    --verbose
