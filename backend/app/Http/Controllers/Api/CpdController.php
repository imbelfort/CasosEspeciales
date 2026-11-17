<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OficioCpd;
use App\Models\OficioRespuestaCpd;
use App\Models\PagoTransaccion;
use App\Models\Solicitud;
use App\Models\SolicitudGrupo;
use App\Services\AuditoriaNotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CpdController extends Controller
{
    public function oficiosEmitidos(Request $request)
    {
        $this->autorizarConsulta($request);

        return OficioCpd::query()
            ->where('estado', 'EMITIDO')
            ->select(['id', 'numero_oficio', 'destinatario', 'referencia', 'fecha_remision', 'id_solicitud'])
            ->withCount(['solicitudes', 'solicitudes as pendientes_count' => fn ($query) => $query->where('solicitud.estado', 'REMITIDA_A_CPD')])
            ->with('solicitudPrincipal:id,estado')
            ->orderByDesc('fecha_remision')
            ->orderByDesc('id')
            ->paginate(12)
            ->through(fn (OficioCpd $oficio) => [
                'id' => $oficio->id,
                'numero_oficio' => $oficio->numero_oficio,
                'destinatario' => $oficio->destinatario,
                'referencia' => $oficio->referencia,
                'fecha_remision' => $oficio->fecha_remision,
                'solicitudes_count' => $oficio->solicitudes_count ?: (int) ($oficio->solicitudPrincipal !== null),
                'pendientes_count' => $oficio->solicitudes_count
                    ? $oficio->pendientes_count
                    : (int) ($oficio->solicitudPrincipal?->estado === 'REMITIDA_A_CPD'),
            ]);
    }

    private function autorizarConsulta(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->tieneRol('CPD') || $user->tieneRol('DIRECTOR'), 403, 'Acceso restringido a CPD.');
    }

    public function pagosStripe(Request $request)
    {
        abort_unless($request->user()->tieneRol('CPD'), 403);

        return PagoTransaccion::query()
            ->where('pasarela', 'STRIPE')
            ->select('id', 'id_solicitud', 'id_usuario', 'monto', 'moneda', 'estado', 'fecha_pago', 'created_at')
            ->with(['solicitud:id,codigo', 'usuario:id_usuario,nombre,apellidos'])
            ->orderByDesc('id')
            ->paginate(20);
    }

    /**
     * Listar pendientes para CPD (Oficios y Solicitudes remitidas)
     */
    public function pendientes(Request $request)
    {
        $user = $request->user();
        if (!$user->tieneRol('CPD') && !$user->tieneRol('DIRECTOR')) {
            return response()->json(['error' => 'Acceso restringido a CPD.'], 403);
        }

        $detalle = [
            'estudiante.usuario', 'periodo',
            'grupos' => fn ($query) => $query->where('estado', 'APROBADA')->with('grupo.materia'),
        ];
        $pendiente = fn ($query) => $query->where('solicitud.estado', 'REMITIDA_A_CPD');

        return OficioCpd::where('estado', 'EMITIDO')
            ->where(fn ($query) => $query->whereHas('solicitudes', $pendiente)
                ->orWhere(fn ($oficio) => $oficio->whereDoesntHave('solicitudes')
                    ->whereHas('solicitudPrincipal', $pendiente)))
            ->with([
                'solicitudes' => fn ($query) => $pendiente($query)->with($detalle),
                'solicitudPrincipal' => fn ($query) => $pendiente($query)->with($detalle),
            ])
            ->orderByDesc('id')
            ->get()
            ->each(function (OficioCpd $oficio) {
                if ($oficio->solicitudes->isEmpty() && $oficio->solicitudPrincipal) {
                    $oficio->setRelation('solicitudes', collect([$oficio->solicitudPrincipal]));
                }
                $oficio->unsetRelation('solicitudPrincipal');
            });
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
            'codigo_respuesta' => 'required|string|max:50',
            'resultado' => 'required|string|in:HABILITADO,OBSERVADO,RECHAZADO',
            'observaciones' => 'nullable|string',
            'referencia_respaldo' => 'nullable|string|max:150',
            'materias_habilitadas' => 'nullable|array',
            'materias_habilitadas.*.id' => 'required_with:materias_habilitadas|integer|distinct|exists:solicitud_grupo,id',
            'materias_habilitadas.*.estado' => 'required_with:materias_habilitadas|string|in:HABILITADA,RECHAZADA_CPD,PENDIENTE',
            'materias_habilitadas.*.observacion' => 'nullable|string',
        ]);

        $request->attributes->set('bitacora_referencia', $validated['id_solicitud']);

        return DB::transaction(function () use ($user, $validated) {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($validated['id_solicitud']);

            if ($solicitud->estado !== 'REMITIDA_A_CPD') {
                return response()->json([
                    'error' => 'La solicitud ya no está pendiente de resolución en CPD.',
                    'codigo' => 'SOLICITUD_NO_PENDIENTE',
                    'estado' => $solicitud->estado,
                ], 409);
            }

            $oficio = OficioCpd::findOrFail($validated['id_oficio']);
            $pertenece = $oficio->solicitudes()->where('solicitud.id', $solicitud->id)->exists()
                || ((int) $oficio->id_solicitud === (int) $solicitud->id && !$oficio->solicitudes()->exists());
            if ($oficio->estado !== 'EMITIDO' || !$pertenece) {
                return response()->json(['error' => 'La solicitud no pertenece a este oficio emitido.'], 422);
            }

            Validator::make($validated, [
                'codigo_respuesta' => 'unique:oficio_respuesta_cpd,codigo_respuesta',
            ])->validate();

            $resultadoFinal = $validated['resultado'];
            $observacionesFinal = $validated['observaciones'] ?? null;

            $aprobadas = $solicitud->grupos()->where('estado', 'APROBADA')->lockForUpdate()->get()->keyBy('id');
            $resoluciones = collect($validated['materias_habilitadas'] ?? [])->keyBy('id');
            if ($resoluciones->keys()->diff($aprobadas->keys())->isNotEmpty()) {
                return response()->json(['error' => 'Solo se pueden resolver materias aprobadas de esta solicitud.'], 422);
            }

            if ($resultadoFinal === 'HABILITADO') {
                if ($aprobadas->isEmpty() || $aprobadas->keys()->diff($resoluciones->keys())->isNotEmpty()
                    || $resoluciones->contains('estado', 'PENDIENTE')) {
                    return response()->json(['error' => 'Confirma el resultado de cada materia aprobada antes de habilitar el caso.'], 422);
                }

                if (!$resoluciones->contains('estado', 'HABILITADA')) {
                    $resultadoFinal = 'RECHAZADO';
                    $observacionesFinal = $observacionesFinal ?: 'Ninguna de las materias aprobadas fue habilitada por CPD.';
                }
            }

            if ($resultadoFinal !== 'OBSERVADO') {
                foreach ($aprobadas as $id => $asignacion) {
                    $asignacion->update([
                        'estado' => $resultadoFinal === 'RECHAZADO' ? 'RECHAZADA_CPD' : $resoluciones[$id]['estado'],
                        'observacion_resolucion' => $resoluciones[$id]['observacion'] ?? null,
                    ]);
                }
            }

            $respuesta = $this->registrarRespuesta($solicitud, $user->id_usuario, $validated, $resultadoFinal, $observacionesFinal);

            return response()->json([
                'mensaje' => "Respuesta de CPD registrada. Solicitud actualizada a {$solicitud->estado}.",
                'respuesta' => $respuesta,
                'solicitud' => $solicitud,
            ], 201);
        });
    }

    public function habilitarOficio(Request $request, int $id)
    {
        $this->autorizarConsulta($request);
        $datos = $request->validate([
            'solicitudes_ids' => 'required|array|min:1',
            'solicitudes_ids.*' => 'required|integer|distinct|min:1',
        ]);
        $confirmadas = collect($datos['solicitudes_ids'])->map(fn ($id) => (int) $id)->sort()->values();
        $idUsuario = $request->user()->id_usuario;

        return DB::transaction(function () use ($id, $confirmadas, $idUsuario) {
            $oficio = OficioCpd::lockForUpdate()->findOrFail($id);
            abort_unless($oficio->estado === 'EMITIDO', 422, 'El oficio debe estar emitido.');

            $ids = $oficio->solicitudes()->pluck('solicitud.id');
            if ($ids->isEmpty() && $oficio->id_solicitud) {
                $ids = collect([$oficio->id_solicitud]);
            }
            $pendientes = Solicitud::whereIn('id', $ids)
                ->where('estado', 'REMITIDA_A_CPD')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($pendientes->pluck('id')->map(fn ($id) => (int) $id)->all() !== $confirmadas->all()) {
                return response()->json([
                    'error' => 'Los casos pendientes del oficio cambiaron. Revisa la lista actualizada.',
                ], 409);
            }

            $materias = SolicitudGrupo::whereIn('id_solicitud', $pendientes->modelKeys())
                ->where('estado', 'APROBADA')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('id_solicitud');

            foreach ($pendientes as $solicitud) {
                if (!$materias->has($solicitud->id)) {
                    return response()->json([
                        'error' => "El caso {$solicitud->codigo} no tiene materias aprobadas. Revísalo individualmente antes de confirmar el oficio.",
                    ], 422);
                }
            }

            foreach ($pendientes as $solicitud) {
                foreach ($materias[$solicitud->id] as $materia) {
                    $materia->update(['estado' => 'HABILITADA', 'observacion_resolucion' => null]);
                }

                $this->registrarRespuesta($solicitud, $idUsuario, [
                    'id_oficio' => $oficio->id,
                    'codigo_respuesta' => 'CPD-' . Str::ulid(),
                ], 'HABILITADO', "Habilitación en lote del oficio {$oficio->numero_oficio}.");
            }

            return response()->json([
                'casos_habilitados' => $pendientes->count(),
                'cupos_habilitados' => $materias->sum(fn ($grupos) => $grupos->count()),
            ], 201);
        });
    }

    private function registrarRespuesta(
        Solicitud $solicitud,
        int $idUsuario,
        array $datos,
        string $resultado,
        ?string $observaciones
    ): OficioRespuestaCpd {
        $nuevoEstado = match ($resultado) {
            'HABILITADO' => 'HABILITADA',
            'OBSERVADO' => 'OBSERVADA_CPD',
            'RECHAZADO' => 'RECHAZADA_CPD',
        };

        $fechaHabilitacion = $resultado === 'HABILITADO' ? now() : null;

        // Registrar en oficio_respuesta_cpd
        $respuesta = OficioRespuestaCpd::create([
            'id_oficio' => $datos['id_oficio'],
            'id_solicitud' => $solicitud->id,
            'codigo_respuesta' => $datos['codigo_respuesta'],
            'resultado' => $resultado,
            'observaciones' => $observaciones,
            'referencia_respaldo' => $datos['referencia_respaldo'] ?? null,
            'fecha_respuesta' => now(),
            'fecha_habilitacion_efectiva' => $fechaHabilitacion,
            'id_responsable_cpd' => $idUsuario,
            'estado' => 'REGISTRADO'
        ]);

        // Auditoría y Notificaciones al estudiante y dirección
        $asunto = "Resultado de Procesamiento CPD: {$resultado} - {$solicitud->codigo}";
        $mensaje = match ($resultado) {
            'HABILITADO' => "Felicidades. El Centro de Procesamiento de Datos (CPD) ha HABILITADO su inscripción para el caso especial {$solicitud->codigo}.",
            'OBSERVADO' => "El CPD ha emitido observaciones a su caso: " . ($observaciones ?? 'Sin detalle adicional.'),
            'RECHAZADO' => "El CPD ha RECHAZADO la habilitación técnica: " . ($observaciones ?? 'Sin motivo especificado.'),
        };

        AuditoriaNotificacionService::registrarTransicion(
            $solicitud,
            $nuevoEstado,
            'RESPUESTA_CPD',
            $idUsuario,
            "Respuesta registrada por CPD: {$resultado} con código {$datos['codigo_respuesta']}",
            $asunto,
            $mensaje
        );

        return $respuesta;
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

        abort_if(!$user->tieneRol('CPD') && array_key_exists('costo_arancel_caso', $request->all()), 403, 'Solo CPD puede modificar el arancel.');

        $validated = $request->validate([
            'fecha_inicio_solicitud' => 'nullable|date',
            'fecha_fin_solicitud' => 'nullable|date',
            'fecha_inicio_evaluacion' => 'nullable|date',
            'fecha_fin_evaluacion' => 'nullable|date',
            'fecha_inicio_ejecucion' => 'nullable|date',
            'fecha_fin_ejecucion' => 'nullable|date',
            'costo_arancel_caso' => 'sometimes|required|numeric|min:0|max:99999999.99|decimal:0,2',
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
