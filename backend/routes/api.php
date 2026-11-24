<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\CpdController;
use App\Http\Controllers\Api\CupoController;
use App\Http\Controllers\Api\EvaluacionController;
use App\Http\Controllers\Api\NotificacionController;
use App\Http\Controllers\Api\OficioCpdController;
use App\Http\Controllers\Api\PagoController;
use App\Http\Controllers\Api\ReporteController;
use App\Http\Controllers\Api\SolicitudController;
use App\Http\Controllers\Api\UsuarioAdminController;
use Illuminate\Support\Facades\Route;

// Rutas Públicas (Auth y Catálogos iniciales)
Route::post('/auth/login', [AuthController::class, 'login'])->name('login');

// Callback público de PagoFácil QR (Webhook de notificación)
Route::post('/pagos/pagofacil/callback', [PagoController::class, 'callbackWebhook']);

Route::get('/catalogos/carreras', [CatalogoController::class, 'carreras']);
Route::get('/catalogos/periodos', [CatalogoController::class, 'periodos']);
Route::get('/catalogos/materias', [CatalogoController::class, 'materias']);

// Rutas Protegidas con Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Auth & Perfil
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // CU1: Gestión de Usuarios y Roles (Exclusivo Dirección / Personal Académico)
    Route::get('/admin/usuarios', [UsuarioAdminController::class, 'listar']);
    Route::post('/admin/usuarios/estudiante', [UsuarioAdminController::class, 'registrarEstudiante']);
    Route::post('/admin/usuarios/personal', [UsuarioAdminController::class, 'registrarPersonal']);
    Route::patch('/admin/usuarios/{id}/estado', [UsuarioAdminController::class, 'cambiarEstado']);

    // CU2: Solicitudes (Registro por Estudiante)
    Route::post('/solicitudes', [SolicitudController::class, 'registrar']);
    Route::post('/solicitudes/{id}/grupos', [SolicitudController::class, 'agregarGrupo']);
    Route::post('/solicitudes/{id}/archivos', [SolicitudController::class, 'adjuntarArchivo']);
    Route::get('/solicitudes/mis-casos', [SolicitudController::class, 'misCasos']);
    Route::get('/solicitudes/mi-historico', [SolicitudController::class, 'miHistorico']);
    Route::get('/solicitudes/{id}/carta-pdf', [SolicitudController::class, 'descargarCarta']);
    Route::get('/solicitudes', [SolicitudController::class, 'listar']);
    Route::get('/solicitudes/{id}', [SolicitudController::class, 'consultar']);

    // CU3: Evaluación por Dirección
    Route::post('/solicitudes/{id}/evaluar', [EvaluacionController::class, 'evaluar']);

    // CU4: Bloqueo y Control de Cupos
    Route::post('/cupos/reservar', [CupoController::class, 'reservar']);
    Route::post('/cupos/ampliar', [CupoController::class, 'ampliarCupos']);
    Route::post('/cupos/bloqueos/{id}/liberar', [CupoController::class, 'liberar']);
    Route::get('/cupos/grupos', [CupoController::class, 'listarGruposConCupos']);

    // CU5: Procesamiento de Pagos Tradicional / Oficios CPD
    Route::post('/solicitudes/{id}/pago', [OficioCpdController::class, 'registrarPago']);
    Route::post('/oficios-cpd/generar', [OficioCpdController::class, 'generarOficioRemision']);
    Route::get('/oficios-cpd', [OficioCpdController::class, 'listarOficios']);

    // MÓDULO DE COBROS Y PAGOS QR (PagoFácil API v2)
    Route::post('/solicitudes/{id}/pagar-qr', [PagoController::class, 'generarQrSolicitud']);
    Route::get('/pagos/{id}/consultar', [PagoController::class, 'consultarEstado']);
    Route::post('/pagos/{id}/simular', [PagoController::class, 'simularPagoDemo']);
    Route::get('/pagos', [PagoController::class, 'listarPagos']);

    // CU6: CPD
    Route::get('/cpd/pendientes', [CpdController::class, 'pendientes']);
    Route::post('/cpd/responder', [CpdController::class, 'responder']);
    Route::put('/cpd/periodo/{id}/config', [CpdController::class, 'actualizarPeriodoConfig']);

    // CU7: Notificaciones del Usuario
    Route::get('/notificaciones', [NotificacionController::class, 'misNotificaciones']);
    Route::post('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarLeida']);

    // CU8: Reportes y Estadísticas (Exclusivo para Administrativos / Dirección)
    Route::get('/reportes/general', [ReporteController::class, 'reporteGeneral']);
    Route::get('/reportes/por-estado', [ReporteController::class, 'reportePorEstado']);
    Route::get('/reportes/cupos', [ReporteController::class, 'reporteCupos']);
});
