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
            'materias_habilitadas' => 'nullable|array',
            'materias_habilitadas.*.id' => 'required_with:materias_habilitadas|integer|exists:solicitud_grupo,id',
            'materias_habilitadas.*.estado' => 'required_with:materias_habilitadas|string|in:HABILITADA,RECHAZADA_CPD,PENDIENTE',
            'materias_habilitadas.*.observacion' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($user, $validated) {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($validated['id_solicitud']);

            if ($solicitud->estado !== 'REMITIDA_A_CPD') {
                return response()->json([
                    'error' => "La solicitud no se encuentra en estado REMITIDA_A_CPD (actual: {$solicitud->estado})."
                ], 422);
            }

            $resultadoFinal = $validated['resultado'];
            $observacionesFinal = $validated['observaciones'] ?? null;

            // Actualización granular de habilitación en CPD por cada materia/grupo
            if (!empty($validated['materias_habilitadas'])) {
                $habilitadasCount = 0;
                foreach ($validated['materias_habilitadas'] as $mh) {
                    if ($mh['estado'] === 'HABILITADA') {
                        $habilitadasCount++;
                    }
                    \App\Models\SolicitudGrupo::where('id', $mh['id'])
                        ->where('id_solicitud', $solicitud->id)
                        ->update([
                            'estado' => $mh['estado'],
                            'observacion_resolucion' => $mh['observacion'] ?? null
                        ]);
                }

                // Si CPD rechaza todas las materias, el caso debe pasar a RECHAZADA_CPD
                if ($habilitadasCount === 0 && $resultadoFinal === 'HABILITADO') {
                    $resultadoFinal = 'RECHAZADO';
                    if (empty($observacionesFinal)) {
                        $observacionesFinal = 'Todas las materias solicitadas fueron rechazadas por CPD por falta de cupo técnico o incompatibilidad.';
                    }
                }
            } else {
                if ($validated['resultado'] === 'HABILITADO') {
                    \App\Models\SolicitudGrupo::where('id_solicitud', $solicitud->id)
                        ->where('estado', 'APROBADA')
                        ->update(['estado' => 'HABILITADA']);
                }
            }

            $nuevoEstado = match ($resultadoFinal) {
                'HABILITADO' => 'HABILITADA',
                'OBSERVADO' => 'OBSERVADA_CPD',
                'RECHAZADO' => 'RECHAZADA_CPD',
            };

            $fechaHabilitacion = $resultadoFinal === 'HABILITADO' ? now() : null;

            // Registrar en oficio_respuesta_cpd
            $respuesta = OficioRespuestaCpd::create([
                'id_oficio' => $validated['id_oficio'],
                'id_solicitud' => $solicitud->id,
                'codigo_respuesta' => $validated['codigo_respuesta'],
                'resultado' => $resultadoFinal,
                'observaciones' => $observacionesFinal,
                'referencia_respaldo' => $validated['referencia_respaldo'] ?? null,
                'fecha_respuesta' => now(),
                'fecha_habilitacion_efectiva' => $fechaHabilitacion,
                'id_responsable_cpd' => $user->id_usuario,
                'estado' => 'REGISTRADO'
            ]);

            // Auditoría y Notificaciones al estudiante y dirección
            $asunto = "Resultado de Procesamiento CPD: {$resultadoFinal} - {$solicitud->codigo}";
            $mensaje = match ($resultadoFinal) {
                'HABILITADO' => "Felicidades. El Centro de Procesamiento de Datos (CPD) ha HABILITADO su inscripción para el caso especial {$solicitud->codigo}.",
                'OBSERVADO' => "El CPD ha emitido observaciones a su caso: " . ($observacionesFinal ?? 'Sin detalle adicional.'),
                'RECHAZADO' => "El CPD ha RECHAZADO la habilitación técnica: " . ($observacionesFinal ?? 'Sin motivo especificado.'),
            };

            AuditoriaNotificacionService::registrarTransicion(
                $solicitud,
                $nuevoEstado,
                'RESPUESTA_CPD',
                $user->id_usuario,
                "Respuesta registrada por CPD: {$resultadoFinal} con código {$validated['codigo_respuesta']}",
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

    /**
     * Configuración del Período Académico, Cronograma de Fechas y Arancel (CPD / Autoridades)
     */
    public function actualizarPeriodoConfig(Request $request, $idPeriodo)
    {
        $user = $request->user();
        if (!$user->tieneRol('CPD') && !$user->tieneRol('DIRECTOR')) {
            return response()->json(['error' => 'Acceso denegado. Exclusivo para personal de CPD o Dirección.'], 403);
        }

        $validated = $request->validate([
            'fecha_inicio_solicitud' => 'nullable|date',
            'fecha_fin_solicitud' => 'nullable|date',
            'fecha_inicio_evaluacion' => 'nullable|date',
            'fecha_fin_evaluacion' => 'nullable|date',
            'fecha_inicio_ejecucion' => 'nullable|date',
            'fecha_fin_ejecucion' => 'nullable|date',
            'costo_arancel_caso' => 'nullable|numeric|min:0',
            'max_materias_por_caso' => 'nullable|integer|min:1|max:10',
            'max_casos_carrera' => 'nullable|integer|min:1|max:10',
        ]);

        $periodo = \App\Models\Periodo::findOrFail($idPeriodo);
        $periodo->update($validated);

        return response()->json([
            'mensaje' => 'Configuración de fechas y costo de arancel de Casos Especiales actualizada correctamente.',
            'periodo' => $periodo
        ]);
    }
}
