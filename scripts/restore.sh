#!/usr/bin/env bash
# ==============================================================================
# SISTEMA DE GESTIÓN DE PAÑOL - INACAP SEDE TEMUCO
# SCRIPT DE RESTAURACIÓN ANTE DESASTRES Y SIMULACRO DRP (PI-08, SLA-10)
# ==============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

TARGET_DB="${1:-panol_inacap}"
SIMULATE_FLAG="${2:-}"

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Iniciando protocolo de recuperación ante desastres ==="

cd "${APP_DIR}"

if [[ "${SIMULATE_FLAG}" == "--simulate" || "${SIMULATE_FLAG}" == "-s" ]]; then
    echo "Ejecutando en MODO SIMULACRO DRP..."
    php artisan app:restore --simulate --target-db="${TARGET_DB}"
else
    echo "Restaurando base de datos objetivo: ${TARGET_DB}..."
    php artisan app:restore --target-db="${TARGET_DB}" --force
fi

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Protocolo de recuperación culminado ==="
