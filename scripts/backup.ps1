# ==============================================================================
# SISTEMA DE GESTIÓN DE PAÑOL - INACAP SEDE TEMUCO
# SCRIPT POWERSHELL DE RESPALDO AUTOMATIZADO DIARIO (SLA-08, SLA-09)
# ==============================================================================
$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppDir = Split-Path -Parent $ScriptDir

# Asegurar PHP y PostgreSQL en PATH si es necesario
$env:Path = "C:\Users\Jorge\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe;C:\Program Files\PostgreSQL\18\bin;" + $env:Path

Write-Host "=== [$((Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))] Iniciando respaldo institucional ===" -ForegroundColor Cyan
Set-Location $AppDir

php artisan app:backup --keep-days=30

Write-Host "=== [$((Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))] Respaldo finalizado exitosamente ===" -ForegroundColor Green
