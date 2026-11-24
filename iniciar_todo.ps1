# ==============================================================================
# Script de Inicio Automático - Sistema de Casos Especiales (FICCT UAGRM)
# ==============================================================================
# 1. Limpia y reinicia la base de datos PostgreSQL desde cero (migrate:fresh --seed)
# 2. Inicia el servidor backend Laravel en http://localhost:8000
# 3. Inicia el frontend Vite en http://localhost:5173
# 4. Abre automáticamente el navegador en la aplicación
# ==============================================================================

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   SISTEMA DE CASOS ESPECIALES FICCT - INICIO COMPLETO   " -ForegroundColor White
Write-Host "==========================================================" -ForegroundColor Cyan

$Raiz = $PSScriptRoot
if (-not $Raiz) { $Raiz = Get-Location }

# 1. Detener procesos previos si estaban corriendo en los puertos 8000 o 5173
Write-Host "`n[1/4] Verificando y liberando puertos anteriores..." -ForegroundColor Yellow
$Puertos = @(8000, 5173)
foreach ($Puerto in $Puertos) {
    try {
        $Net = Get-NetTCPConnection -LocalPort $Puerto -ErrorAction SilentlyContinue
        if ($Net) {
            $PidEncontrado = $Net.OwningProcess | Select-Object -Unique
            foreach ($P in $PidEncontrado) {
                if ($P -gt 0) {
                    Write-Host "       Liberando puerto $Puerto (PID: $P)..." -ForegroundColor DarkGray
                    Stop-Process -Id $P -Force -ErrorAction SilentlyContinue
                }
            }
        }
    } catch {}
}

# 2. Reiniciar Base de Datos desde Cero (Migrate Fresh + Seed)
Write-Host "`n[2/4] Reiniciando Base de Datos desde Cero (migrate:fresh --seed)..." -ForegroundColor Yellow
Push-Location "$Raiz\backend"
try {
    php artisan migrate:fresh --seed --force
    if ($LASTEXITCODE -ne 0) {
        Write-Host "      [ADVERTENCIA] Error durante migrate:fresh. Verifica que PostgreSQL esté corriendo." -ForegroundColor Red
    } else {
        Write-Host "      [OK] Base de datos poblada con estudiantes, materias y roles iniciales." -ForegroundColor Green
    }
} catch {
    Write-Host "      [ERROR] $($_.Exception.Message)" -ForegroundColor Red
}
Pop-Location

# 3. Lanzar Servidor Backend Laravel (Puerto 8000)
Write-Host "`n[3/4] Iniciando Backend Laravel API (http://localhost:8000)..." -ForegroundColor Yellow
$BackendJob = Start-Process -FilePath "powershell" -ArgumentList "-NoExit", "-Command", "cd '$Raiz\backend'; Write-Host '--- BACKEND LARAVEL SERVE (PUERTO 8000) ---' -ForegroundColor Green; php artisan serve --port=8000" -PassThru

# 4. Lanzar Servidor Frontend Vite (Puerto 5173)
Write-Host "`n[4/4] Iniciando Frontend Vite (http://localhost:5173)..." -ForegroundColor Yellow
$FrontendJob = Start-Process -FilePath "powershell" -ArgumentList "-NoExit", "-Command", "cd '$Raiz\frontend'; Write-Host '--- FRONTEND VITE (PUERTO 5173) ---' -ForegroundColor Cyan; npm run dev -- --host --port 5173" -PassThru

# Esperar unos segundos para que los servidores levanten
Start-Sleep -Seconds 3

# Abrir el navegador
Write-Host "`nAbriendo aplicación en el navegador..." -ForegroundColor Green
Start-Process "http://localhost:5173"

Write-Host "`n==========================================================" -ForegroundColor Green
Write-Host "   TODO LISTO Y FUNCIONANDO!" -ForegroundColor White
Write-Host "   - Backend API:  http://localhost:8000" -ForegroundColor Gray
Write-Host "   - Frontend UI:  http://localhost:5173" -ForegroundColor Gray
Write-Host "   - Cuentas Demo: estudiante@ficct.uagrm.edu.bo" -ForegroundColor Gray
Write-Host "                   director@ficct.uagrm.edu.bo" -ForegroundColor Gray
Write-Host "                   password: password123" -ForegroundColor Gray
Write-Host "==========================================================" -ForegroundColor Green
