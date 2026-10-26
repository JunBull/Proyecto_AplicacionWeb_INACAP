#!/usr/bin/env bash
# ==============================================================================
# SISTEMA DE GESTIÓN DE PAÑOL - INACAP SEDE TEMUCO
# SCRIPT DE RESPALDO AUTOMATIZADO DIARIO (SLA-08, SLA-09)
# ==============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Iniciando proceso de respaldo institucional ==="

# Ejecutar el comando Artisan oficial app:backup
cd "${APP_DIR}"
php artisan app:backup --keep-days=30

echo "=== [$(date '+%Y-%m-%d %H:%M:%S')] Proceso de respaldo culminado exitosamente ==="
