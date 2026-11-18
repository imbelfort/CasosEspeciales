<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BloqueoGrupo;
use App\Models\Grupo;
use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CupoController extends Controller
{
    /**
     * CU4: Bloqueo y Reserva de Cupos
     * Aplica SELECT ... FOR UPDATE sobre el grupo, valida disponibilidad, resta cupos y audita en bloqueo_grupo.
     */
    public function reservar(Request $request)
    {
        $user = $request->user();
        if (!$user->tieneRol('DIRECTOR') && !$user->tieneRol('PERSONAL_ACADEMICO')) {
            return response()->json(['error' => 'Acceso no autorizado.'], 403);
        }

        $validated = $request->validate([
            'id_grupo' => 'required|integer|exists:grupo,id',
            'id_solicitud' => 'nullable|integer|exists:solicitud,id',
            'cantidad' => 'required|integer|min:1',
            'motivo' => 'required|string',
        ]);

        return DB::transaction(function () use ($user, $validated) {
            // Lock de fila en grupo para prevenir concurrencia
            $grupo = Grupo::lockForUpdate()->findOrFail($validated['id_grupo']);

            if ($grupo->cupos_disponibles < $validated['cantidad']) {
                return response()->json([
                    'error' => "Cupos insuficientes en el grupo {$grupo->sigla_grupo}. Disponibles: {$grupo->cupos_disponibles}, Solicitados: {$validated['cantidad']}"
                ], 422);
            }

            // Descontar cupos
            $grupo->cupos_disponibles -= $validated['cantidad'];
            $grupo->save();

            // Registrar en bloqueo_grupo
            $bloqueo = BloqueoGrupo::create([
                'id_grupo' => $grupo->id,
                'id_solicitud' => $validated['id_solicitud'] ?? null,
                'cantidad' => $validated['cantidad'],
                'motivo' => $validated['motivo'],
                'estado' => 'RESERVADO',
                'autorizado_por' => $user->id_usuario,
                'fecha_bloqueo' => now(),
            ]);

            return response()->json([
                'mensaje' => 'Cupo reservado exitosamente',
                'bloqueo' => $bloqueo,
                'cupos_restantes' => $grupo->cupos_disponibles
            ], 201);
        });
    }

    /**
     * CU4: Liberación de Cupo Reservado
     */
    public function liberar(Request $request, $idBloqueo)
    {
        $user = $request->user();
        if (!$user->tieneRol('DIRECTOR') && !$user->tieneRol('PERSONAL_ACADEMICO')) {
            return response()->json(['error' => 'Acceso no autorizado.'], 403);
        }

        return DB::transaction(function () use ($user, $idBloqueo) {
            $bloqueo = BloqueoGrupo::lockForUpdate()->findOrFail($idBloqueo);

            if ($bloqueo->estado !== 'RESERVADO') {
                return response()->json(['error' => 'Este bloqueo ya fue liberado o resuelto previamente.'], 422);
            }

            $grupo = Grupo::lockForUpdate()->findOrFail($bloqueo->id_grupo);

            // Reintegrar cupos
            $grupo->cupos_disponibles = min($grupo->cupo_maximo, $grupo->cupos_disponibles + $bloqueo->cantidad);
            $grupo->save();

            $bloqueo->estado = 'LIBERADO';
            $bloqueo->fecha_resolucion = now();
            $bloqueo->observacion = 'Liberado por usuario ' . $user->nombre;
            $bloqueo->save();

            return response()->json([
                'mensaje' => 'Cupo liberado exitosamente',
                'bloqueo' => $bloqueo,
                'cupos_actualizados' => $grupo->cupos_disponibles
            ]);
        });
    }

    /**
     * Reporte o listado de cupos y bloqueos de grupos
     */
    public function listarGruposConCupos(Request $request)
    {
        $query = Grupo::with(['materia', 'periodo', 'horarios', 'bloqueos']);
        if ($request->has('id_periodo')) {
            $query->where('id_periodo', $request->id_periodo);
        }
        return response()->json($query->get());
    }
}
