#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# start-scheduler.sh — Scheduler de tareas programadas para desarrollo local
#
# Equivale a tener el cron `* * * * * php artisan schedule:run` pero sin
# necesitar configurar el cron del sistema operativo.
#
# Ejecutar en una terminal separada mientras desarrollas.
#
# Uso:
#   chmod +x start-scheduler.sh
#   ./start-scheduler.sh
#
# Para detenerlo: Ctrl+C
# ─────────────────────────────────────────────────────────────────────────────

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  NextPOS SaaS — Task Scheduler"
echo "  Tareas: limpieza de logs, Telescope, notificaciones, etc."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Iniciando scheduler... (Ctrl+C para detener)"
echo ""

# schedule:work ejecuta schedule:run cada minuto sin necesitar cron
php artisan schedule:work --verbose
