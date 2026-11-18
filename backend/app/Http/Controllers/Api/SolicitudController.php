<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArchivoSolicitud;
use App\Models\Estudiante;
use App\Models\Grupo;
use App\Models\Periodo;
use App\Models\Solicitud;
use App\Models\SolicitudGrupo;
use App\Services\AuditoriaNotificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SolicitudController extends Controller
{
    /**
     * CU2: Registrar Solicitud de Caso Especial (El Estudiante solicita)
     * - Genera código CE-YYYY-NNNN secuencial.
     * - Valida período y gestión (1-YYYY o 2-YYYY).
     * - Permite registrar grupos y adjuntos de respaldo iniciales en la misma transacción.
     * - Audita en historial_solicitud y notificaciones (RECEPCION_SOLICITUD).
     */
    public function registrar(Request $request)
    {
        $user = $request->user();
        if (!$user->tieneRol('ESTUDIANTE')) {
            return response()->json(['error' => 'Solo los estudiantes pueden registrar solicitudes.'], 403);
        }

        $validated = $request->validate([
            'id_periodo' => 'required|integer|exists:periodos,id',
            'tipo_caso' => 'required|string|in:LEVANTAMIENTO_PREREQUISITO,EXCESO_MATERIAS,PARALELO_LLENO,NIVELACION,CASO_ESPECIAL,CORRECCION_NOTA,INCLUSION_MATERIA',
            'motivo' => 'required|string',
            'id_grupo' => 'nullable|integer|exists:grupo,id',
            'motivo_grupo' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($user, $validated, $request) {
            $periodo = Periodo::findOrFail($validated['id_periodo']);
            if ($periodo->estado !== 'ACTIVO') {
                return response()->json(['error' => 'El período académico no está activo.'], 422);
            }

            // Validar formato de gestión (1-YYYY o 2-YYYY)
            if (!preg_match('/^[12]-\d{4}$/', $periodo->gestion)) {
                return response()->json(['error' => 'La gestión del período debe tener formato 1-YYYY o 2-YYYY.'], 422);
            }

            // Generar secuencial CE-YYYY-NNNN
            $anio = date('Y');
            $ultimoId = DB::table('solicitud')->max('id') ?? 0;
            $siguienteNumero = str_pad($ultimoId + 1, 4, '0', STR_PAD_LEFT);
            $codigo = "CE-{$anio}-{$siguienteNumero}";

            $solicitud = Solicitud::create([
                'codigo' => $codigo,
                'id_estudiante' => $user->id_usuario,
                'id_periodo' => $periodo->id,
                'tipo_caso' => $validated['tipo_caso'],
                'motivo' => $validated['motivo'],
                'estado' => 'ENVIADA',
                'fecha_solicitud' => now(),
            ]);

            // Si envió grupo en la solicitud inicial, validar pertenencia a la carrera del estudiante
            if (!empty($validated['id_grupo'])) {
                $grupo = Grupo::with('materia')->findOrFail($validated['id_grupo']);
                $estudiante = Estudiante::findOrFail($user->id_usuario);

                $perteneceACarrera = DB::table('materia_carrera')
                    ->where('id_materia', $grupo->id_materia)
                    ->where('id_carrera', $estudiante->carrera_id)
                    ->exists();

                if (!$perteneceACarrera) {
                    return response()->json(['error' => 'La materia seleccionada no pertenece a la carrera del estudiante.'], 422);
                }

                SolicitudGrupo::create([
                    'id_solicitud' => $solicitud->id,
                    'id_grupo' => $grupo->id,
                    'motivo' => $validated['motivo_grupo'] ?? 'Materia solicitada en el registro del caso',
                    'estado' => 'PENDIENTE'
                ]);
            }

            // Si adjuntó archivo de respaldo inicial
            if ($request->hasFile('archivo_respaldo')) {
                $file = $request->file('archivo_respaldo');
                $ruta = $file->store("archivos_solicitudes/{$solicitud->id}", 'local');
                ArchivoSolicitud::create([
                    'id_solicitud' => $solicitud->id,
                    'nombre_archivo' => $file->getClientOriginalName(),
                    'tipo_archivo' => 'RESPALDO',
                    'ruta_archivo' => $ruta,
                    'fecha_subida' => now(),
                ]);
            }

            // Auditoría e Historial obligatorio CU7
            AuditoriaNotificacionService::registrarTransicion(
                $solicitud,
                'ENVIADA',
                'REGISTRO_SOLICITUD',
                $user->id_usuario,
                'Registro inicial de la solicitud por el estudiante.',
                'Recepción de Solicitud ' . $solicitud->codigo,
                "Su solicitud de caso especial {$solicitud->codigo} ha sido recibida satisfactoriamente y se encuentra en estado ENVIADA."
            );

            return response()->json([
                'mensaje' => 'Solicitud registrada correctamente',
                'solicitud' => $solicitud->load(['estudiante.usuario', 'periodo', 'grupos.grupo.materia', 'archivos'])
            ], 201);
        });
    }

    /**
     * CU2: Agregar grupo a la solicitud existente
     * Restricción: Solo si estado es ENVIADA, pertenece al estudiante y la materia corresponde a la carrera del estudiante (materia_carrera).
     */
    public function agregarGrupo(Request $request, $idSolicitud)
    {
        $user = $request->user();
        $validated = $request->validate([
            'id_grupo' => 'required|integer|exists:grupo,id',
            'motivo' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($user, $idSolicitud, $validated) {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($idSolicitud);

            if ($solicitud->id_estudiante !== $user->id_usuario && !$user->tieneRol('DIRECTOR') && !$user->tieneRol('PERSONAL_ACADEMICO')) {
                return response()->json(['error' => 'No autorizado para modificar esta solicitud.'], 403);
            }

            if ($solicitud->estado !== 'ENVIADA') {
                return response()->json(['error' => 'Solo se pueden agregar grupos si la solicitud está en estado ENVIADA.'], 422);
            }

            $grupo = Grupo::with('materia')->findOrFail($validated['id_grupo']);
            $estudiante = Estudiante::findOrFail($solicitud->id_estudiante);

            // Validar que la materia pertenezca a la carrera del estudiante
            $perteneceACarrera = DB::table('materia_carrera')
                ->where('id_materia', $grupo->id_materia)
                ->where('id_carrera', $estudiante->carrera_id)
                ->exists();

            if (!$perteneceACarrera) {
                return response()->json(['error' => 'La materia seleccionada no pertenece a la carrera del estudiante.'], 422);
            }

            // Evitar duplicar la misma materia/grupo
            $existe = SolicitudGrupo::where('id_solicitud', $solicitud->id)->where('id_grupo', $grupo->id)->exists();
            if ($existe) {
                return response()->json(['error' => 'El grupo ya fue agregado a esta solicitud.'], 422);
            }

            $solicitudGrupo = SolicitudGrupo::create([
                'id_solicitud' => $solicitud->id,
                'id_grupo' => $grupo->id,
                'motivo' => $validated['motivo'] ?? null,
                'estado' => 'PENDIENTE'
            ]);

            return response()->json([
                'mensaje' => 'Grupo agregado a la solicitud exitosamente',
                'solicitud_grupo' => $solicitudGrupo->load('grupo.materia')
            ], 201);
        });
    }

    /**
     * CU2: Adjuntar Archivo de respaldo
     */
    public function adjuntarArchivo(Request $request, $idSolicitud)
    {
        $user = $request->user();
        $request->validate([
            'archivo' => 'required|file|max:10240',
            'tipo_archivo' => 'required|string|in:RESPALDO,FORMULARIO,OTRO',
        ]);

        $solicitud = Solicitud::findOrFail($idSolicitud);
        if ($solicitud->id_estudiante !== $user->id_usuario && !$user->tieneRol('DIRECTOR') && !$user->tieneRol('PERSONAL_ACADEMICO')) {
            return response()->json(['error' => 'No autorizado.'], 403);
        }

        if ($solicitud->estado !== 'ENVIADA' && $solicitud->estado !== 'OBSERVADA') {
            return response()->json(['error' => 'Solo se pueden adjuntar respaldos cuando la solicitud está ENVIADA u OBSERVADA.'], 422);
        }

        $file = $request->file('archivo');
        $ruta = $file->store("archivos_solicitudes/{$solicitud->id}", 'local');

        $archivo = ArchivoSolicitud::create([
            'id_solicitud' => $solicitud->id,
            'nombre_archivo' => $file->getClientOriginalName(),
            'tipo_archivo' => $request->tipo_archivo,
            'ruta_archivo' => $ruta,
            'fecha_subida' => now(),
        ]);

        return response()->json([
            'mensaje' => 'Archivo adjuntado con éxito',
            'archivo' => $archivo
        ], 201);
    }

    /**
     * Mis Casos (Estudiante)
     */
    public function misCasos(Request $request)
    {
        $user = $request->user();
        $solicitudes = Solicitud::where('id_estudiante', $user->id_usuario)
            ->with(['periodo', 'grupos.grupo.materia', 'archivos', 'historial', 'evaluacion'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($solicitudes);
    }

    /**
     * Listar Solicitudes (Administración / Director / CPD)
     */
    public function listar(Request $request)
    {
        $query = Solicitud::with(['estudiante.usuario', 'periodo', 'grupos.grupo.materia', 'archivos', 'evaluacion']);

        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->has('id_periodo')) {
            $query->where('id_periodo', $request->id_periodo);
        }

        return response()->json($query->orderBy('id', 'desc')->paginate(20));
    }

    public function consultar($id)
    {
        $solicitud = Solicitud::with([
            'estudiante.usuario',
            'estudiante.carrera',
            'periodo',
            'grupos.grupo.materia',
            'grupos.grupo.horarios',
            'archivos',
            'evaluacion.evaluador',
            'historial.usuario',
            'notificaciones'
        ])->findOrFail($id);

        return response()->json($solicitud);
    }
}
