<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\CpdController;
use App\Http\Controllers\Api\CupoController;
use App\Http\Controllers\Api\EvaluacionController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\OficioCpdController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\SolicitudController;
use Illuminate\Support\Facades\Route;

// Rutas Públicas (Auth y Catálogos iniciales)
Route::post('/auth/registro-estudiante', [AuthController::class, 'registrarEstudiante']);
Route::post('/auth/login', [AuthController::class, 'login'])->name('login');

Route::get('/catalogos/carreras', [CatalogoController::class, 'carreras']);
Route::get('/catalogos/periodos', [CatalogoController::class, 'periodos']);
Route::get('/catalogos/materias', [CatalogoController::class, 'materias']);

// Rutas Protegidas con Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Perfil
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // CU2: Solicitudes
    Route::post('/solicitudes', [SolicitudController::class, 'registrar']);
    Route::post('/solicitudes/{id}/grupos', [SolicitudController::class, 'agregarGrupo']);
    Route::post('/solicitudes/{id}/archivos', [SolicitudController::class, 'adjuntarArchivo']);
    Route::get('/solicitudes/mis-casos', [SolicitudController::class, 'misCasos']);
    Route::get('/solicitudes', [SolicitudController::class, 'listar']);
    Route::get('/solicitudes/{id}', [SolicitudController::class, 'consultar']);

    // CU3: Evaluación por Dirección
    Route::post('/solicitudes/{id}/evaluar', [EvaluacionController::class, 'evaluar']);

    // CU4: Bloqueo y Control de Cupos
    Route::post('/cupos/reservar', [CupoController::class, 'reservar']);
    Route::post('/cupos/bloqueos/{id}/liberar', [CupoController::class, 'liberar']);
    Route::get('/cupos/grupos', [CupoController::class, 'listarGruposConCupos']);

    // CU5: Pagos y Remisión a CPD
    Route::post('/solicitudes/{id}/pago', [OficioCpdController::class, 'registrarPago']);
    Route::post('/oficios-cpd/generar', [OficioCpdController::class, 'generarOficioRemision']);
    Route::get('/oficios-cpd', [OficioCpdController::class, 'listarOficios']);

    // CU6: CPD
    Route::get('/cpd/pendientes', [CpdController::class, 'pendientes']);
    Route::post('/cpd/responder', [CpdController::class, 'responder']);

    // CU7: Notificaciones del Usuario
    Route::get('/notificaciones', [NotificacionController::class, 'misNotificaciones']);
    Route::post('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarLeida']);

    // CU8: Reportes y Estadísticas (Exclusivo para Administrativos / Dirección)
    Route::get('/reportes/general', [ReporteController::class, 'reporteGeneral']);
    Route::get('/reportes/por-estado', [ReporteController::class, 'reportePorEstado']);
    Route::get('/reportes/cupos', [ReporteController::class, 'reporteCupos']);
});
