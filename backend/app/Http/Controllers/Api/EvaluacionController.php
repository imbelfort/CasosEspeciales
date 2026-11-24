<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EvaluacionSolicitud;
use App\Models\Solicitud;
use App\Services\AuditoriaNotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluacionController extends Controller
{
    /**
     * CU3: Evaluación por Dirección de Carrera
     * Acceso: Exclusivo para DIRECTOR o PERSONAL_ACADEMICO.
     * Decisiones: APROBADA (pasa a APROBADA_DIRECCION), RECHAZADA (pasa a RECHAZADA_DIRECCION), OBSERVADA.
     * Bloqueo concurrente: SELECT ... FOR UPDATE
     */
    public function evaluar(Request $request, $idSolicitud)
    {
        $user = $request->user();
        if (!$user->tieneRol('DIRECTOR') && !$user->tieneRol('PERSONAL_ACADEMICO')) {
            return response()->json(['error' => 'Acceso denegado. Exclusivo para Dirección de Carrera.'], 403);
        }

        $validated = $request->validate([
            'decision' => 'required|string|in:APROBADA,RECHAZADA,OBSERVADA',
            'justificacion' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'motivo_rechazo' => 'required_if:decision,RECHAZADA|nullable|string',
            'materias_resolucion' => 'nullable|array',
            'materias_resolucion.*.id' => 'required_with:materias_resolucion|integer|exists:solicitud_grupo,id',
            'materias_resolucion.*.estado' => 'required_with:materias_resolucion|string|in:APROBADA,RECHAZADA,PENDIENTE',
            'materias_resolucion.*.observacion' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($user, $idSolicitud, $validated) {
            // Bloqueo de fila estricto contra concurrencia
            $solicitud = Solicitud::lockForUpdate()->findOrFail($idSolicitud);

            if ($solicitud->estado !== 'ENVIADA' && $solicitud->estado !== 'OBSERVADA') {
                return response()->json([
                    'error' => "La solicitud no puede ser evaluada en su estado actual ({$solicitud->estado})."
                ], 422);
            }

            $nuevoEstado = match ($validated['decision']) {
                'APROBADA' => 'APROBADA_DIRECCION',
                'RECHAZADA' => 'RECHAZADA_DIRECCION',
                'OBSERVADA' => 'OBSERVADA',
            };

            $solicitud->fecha_revision = now();
            $solicitud->id_revisor = $user->id_usuario;
            if ($validated['decision'] === 'RECHAZADA') {
                $solicitud->motivo_rechazo = $validated['motivo_rechazo'];
            }
            $solicitud->observaciones = $validated['observaciones'] ?? null;
            $solicitud->save();

            // Guardar o actualizar registro en evaluacion_solicitud
            EvaluacionSolicitud::updateOrCreate(
                ['id_solicitud' => $solicitud->id],
                [
                    'id_evaluador' => $user->id_usuario,
                    'decision' => $validated['decision'],
                    'justificacion' => $validated['justificacion'] ?? null,
                    'observaciones' => $validated['observaciones'] ?? null,
                    'fecha_evaluacion' => now(),
                ]
            );

            // Actualizar resolución granular por materia solicitada (de 5 pueden aprobar 2, 3, etc.)
            if (!empty($validated['materias_resolucion'])) {
                foreach ($validated['materias_resolucion'] as $mr) {
                    \App\Models\SolicitudGrupo::where('id', $mr['id'])
                        ->where('id_solicitud', $solicitud->id)
                        ->update([
                            'estado' => $mr['estado'],
                            'observacion_resolucion' => $mr['observacion'] ?? null
                        ]);
                }
            } else {
                // Si la decisión es APROBADA general, marcar grupos como APROBADA
                if ($validated['decision'] === 'APROBADA') {
                    \App\Models\SolicitudGrupo::where('id_solicitud', $solicitud->id)
                        ->update(['estado' => 'APROBADA']);
                } elseif ($validated['decision'] === 'RECHAZADA') {
                    \App\Models\SolicitudGrupo::where('id_solicitud', $solicitud->id)
                        ->update(['estado' => 'RECHAZADA']);
                }
            }

            // Mensajes de notificación según decisión
            $asunto = "Resolución de Solicitud {$solicitud->codigo}: {$validated['decision']}";
            $mensaje = match ($validated['decision']) {
                'APROBADA' => "Su solicitud ha sido APROBADA por Dirección de Carrera. Por favor efectúe el pago del arancel correspondiente y suba el comprobante.",
                'RECHAZADA' => "Su solicitud ha sido RECHAZADA por Dirección. Motivo: " . ($validated['motivo_rechazo'] ?? 'No especificado.'),
                'OBSERVADA' => "Su solicitud tiene observaciones: " . ($validated['observaciones'] ?? 'Revise los requisitos pendientes.'),
            };

            // Auditoría e Historial (CU7)
            AuditoriaNotificacionService::registrarTransicion(
                $solicitud,
                $nuevoEstado,
                'EVALUACION_DIRECCION',
                $user->id_usuario,
                "Evaluación registrada: {$validated['decision']}. " . ($validated['justificacion'] ?? ''),
                $asunto,
                $mensaje
            );

            return response()->json([
                'mensaje' => "Evaluación registrada exitosamente. Estado: {$nuevoEstado}",
                'solicitud' => $solicitud->load('evaluacion')
            ]);
        });
    }
}
