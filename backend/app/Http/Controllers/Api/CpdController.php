<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OficioCpd;
use App\Models\OficioRespuestaCpd;
use App\Models\Solicitud;
use App\Services\AuditoriaNotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CpdController extends Controller
{
    /**
     * Listar pendientes para CPD (Oficios y Solicitudes remitidas)
     */
    public function pendientes(Request $request)
    {
        $user = $request->user();
        if (!$user->tieneRol('CPD') && !$user->tieneRol('DIRECTOR')) {
            return response()->json(['error' => 'Acceso restringido a CPD.'], 403);
        }

        $oficios = OficioCpd::with(['responsable', 'solicitudes.estudiante.usuario', 'solicitudes.grupos.grupo.materia', 'solicitudes.archivos'])
            ->where('estado', 'EMITIDO')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($oficios);
    }

    /**
     * CU6: Confirmación y Habilitación por CPD (cpd responder)
     * Decisiones:
     * - HABILITADO -> solicitud pasa a HABILITADA (fecha_habilitacion_efectiva = now())
     * - OBSERVADO -> solicitud pasa a OBSERVADA_CPD
     * - RECHAZADO -> solicitud pasa a RECHAZADA_CPD
     */
    public function responder(Request $request)
    {
        $user = $request->user();
        if (!$user->tieneRol('CPD') && !$user->tieneRol('DIRECTOR')) {
            return response()->json(['error' => 'Acceso denegado. Exclusivo para personal de CPD.'], 403);
        }

        $validated = $request->validate([
            'id_oficio' => 'required|integer|exists:oficio_cpd,id',
            'id_solicitud' => 'required|integer|exists:solicitud,id',
            'codigo_respuesta' => 'required|string|max:50|unique:oficio_respuesta_cpd,codigo_respuesta',
            'resultado' => 'required|string|in:HABILITADO,OBSERVADO,RECHAZADO',
            'observaciones' => 'nullable|string',
            'referencia_respaldo' => 'nullable|string|max:150',
        ]);

        return DB::transaction(function () use ($user, $validated) {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($validated['id_solicitud']);

            if ($solicitud->estado !== 'REMITIDA_A_CPD') {
                return response()->json([
                    'error' => "La solicitud no se encuentra en estado REMITIDA_A_CPD (actual: {$solicitud->estado})."
                ], 422);
            }

            $nuevoEstado = match ($validated['resultado']) {
                'HABILITADO' => 'HABILITADA',
                'OBSERVADO' => 'OBSERVADA_CPD',
                'RECHAZADO' => 'RECHAZADA_CPD',
            };

            $fechaHabilitacion = $validated['resultado'] === 'HABILITADO' ? now() : null;

            // Registrar en oficio_respuesta_cpd
            $respuesta = OficioRespuestaCpd::create([
                'id_oficio' => $validated['id_oficio'],
                'id_solicitud' => $solicitud->id,
                'codigo_respuesta' => $validated['codigo_respuesta'],
                'resultado' => $validated['resultado'],
                'observaciones' => $validated['observaciones'] ?? null,
                'referencia_respaldo' => $validated['referencia_respaldo'] ?? null,
                'fecha_respuesta' => now(),
                'fecha_habilitacion_efectiva' => $fechaHabilitacion,
                'id_responsable_cpd' => $user->id_usuario,
                'estado' => 'REGISTRADO'
            ]);

            // Auditoría y Notificaciones al estudiante y dirección
            $asunto = "Resultado de Procesamiento CPD: {$validated['resultado']} - {$solicitud->codigo}";
            $mensaje = match ($validated['resultado']) {
                'HABILITADO' => "Felicidades. El Centro de Procesamiento de Datos (CPD) ha HABILITADO su inscripción para el caso especial {$solicitud->codigo}.",
                'OBSERVADO' => "El CPD ha emitido observaciones a su caso: " . ($validated['observaciones'] ?? 'Sin detalle adicional.'),
                'RECHAZADO' => "El CPD ha RECHAZADO la habilitación técnica: " . ($validated['observaciones'] ?? 'Sin motivo especificado.'),
            };

            AuditoriaNotificacionService::registrarTransicion(
                $solicitud,
                $nuevoEstado,
                'RESPUESTA_CPD',
                $user->id_usuario,
                "Respuesta registrada por CPD: {$validated['resultado']} con código {$validated['codigo_respuesta']}",
                $asunto,
                $mensaje
            );

            return response()->json([
                'mensaje' => "Respuesta de CPD registrada. Solicitud actualizada a {$nuevoEstado}.",
                'respuesta' => $respuesta,
                'solicitud' => $solicitud
            ], 201);
        });
    }
}
