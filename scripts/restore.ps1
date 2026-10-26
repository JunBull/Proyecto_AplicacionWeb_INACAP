# ==============================================================================
# SISTEMA DE GESTIÓN DE PAÑOL - INACAP SEDE TEMUCO
# SCRIPT POWERSHELL DE RESTAURACIÓN ANTE DESASTRES Y SIMULACRO DRP (PI-08, SLA-10)
# ==============================================================================
param(
    [string]$TargetDb = "panol_inacap",
    [switch]$Simulate = $false
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$AppDir = Split-Path -Parent $ScriptDir

$env:Path = "C:\Users\Jorge\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe;C:\Program Files\PostgreSQL\18\bin;" + $env:Path

Write-Host "=== [$((Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))] Iniciando protocolo de recuperación ===" -ForegroundColor Cyan
Set-Location $AppDir

if ($Simulate) {
    Write-Host "Ejecutando en MODO SIMULACRO DRP..." -ForegroundColor Yellow
    php artisan app:restore --simulate --target-db=$TargetDb
} else {
    Write-Host "Restaurando base de datos: $TargetDb..." -ForegroundColor Yellow
    php artisan app:restore --target-db=$TargetDb --force
}

Write-Host "=== [$((Get-Date).ToString('yyyy-MM-dd HH:mm:ss'))] Protocolo de recuperación culminado ===" -ForegroundColor Green
