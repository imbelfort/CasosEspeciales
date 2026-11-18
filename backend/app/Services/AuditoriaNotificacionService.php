<?php

namespace App\Services;

use App\Models\HistorialSolicitud;
use App\Models\Notificacion;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

class AuditoriaNotificacionService
{
    /**
     * CU7: Transición y Auditoría de Historial + Generación de Notificación
     */
    public static function registrarTransicion(
        Solicitud $solicitud,
        string $estadoNuevo,
        string $accion,
        ?int $idUsuarioAccion,
        ?string $observacion = null,
        ?string $asuntoNotif = null,
        ?string $mensajeNotif = null
    ): void {
        $estadoAnterior = $solicitud->estado;
        
        // 1. Actualizar estado de la solicitud
        $solicitud->estado = $estadoNuevo;
        $solicitud->save();

        // 2. Registrar en historial_solicitud
        HistorialSolicitud::create([
            'id_solicitud' => $solicitud->id,
            'id_usuario' => $idUsuarioAccion,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'accion' => $accion,
            'observacion' => $observacion,
            'fecha_evento' => now()
        ]);

        // 3. Crear notificación si aplica
        if ($asuntoNotif && $mensajeNotif) {
            $estudianteUsuario = Usuario::find($solicitud->id_estudiante);
            if ($estudianteUsuario) {
                Notificacion::create([
                    'id_usuario' => $estudianteUsuario->id_usuario,
                    'id_solicitud' => $solicitud->id,
                    'destinatario' => $estudianteUsuario->email,
                    'tipo' => $accion,
                    'asunto' => $asuntoNotif,
                    'mensaje' => $mensajeNotif,
                    'canal' => 'EMAIL',
                    'estado' => 'PENDIENTE',
                    'intentos' => 0,
                    'fecha_creacion' => now()
                ]);
            }
        }
    }
}
