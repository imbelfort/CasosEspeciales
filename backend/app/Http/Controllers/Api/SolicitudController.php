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
            'tipo_caso' => 'nullable|string',
            'motivo' => 'required|string',
            'grupos' => 'nullable|array',
            'grupos.*.id_grupo' => 'required|integer|exists:grupo,id',
            'grupos.*.motivo' => 'nullable|string',
            'id_grupo' => 'nullable|integer|exists:grupo,id',
            'motivo_grupo' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($user, $validated, $request) {
            $periodo = Periodo::findOrFail($validated['id_periodo']);
            if ($periodo->estado !== 'ACTIVO') {
                return response()->json(['error' => 'El período académico no está activo.'], 422);
            }

            // Validar restricción de fechas de recepción de solicitudes si están definidas
            $hoy = date('Y-m-d');
            if ($periodo->fecha_inicio_solicitud && $hoy < $periodo->fecha_inicio_solicitud) {
                return response()->json(['error' => "El período de recepción de casos especiales aún no ha iniciado (Inicia el {$periodo->fecha_inicio_solicitud})."], 422);
            }
            if ($periodo->fecha_fin_solicitud && $hoy > $periodo->fecha_fin_solicitud) {
                return response()->json(['error' => "El período de recepción de casos especiales ya ha finalizado (Concluyó el {$periodo->fecha_fin_solicitud})."], 422);
            }

            // Regla UAGRM: Solo se permite 1 solicitud por temporada/período
            $solicitudExistente = Solicitud::where('id_estudiante', $user->id_usuario)
                ->where('id_periodo', $periodo->id)
                ->whereNotIn('estado', ['RECHAZADA_DIRECCION', 'RECHAZADA_CPD'])
                ->first();
            if ($solicitudExistente) {
                return response()->json([
                    'error' => "Ya tienes un caso especial registrado en este período ({$solicitudExistente->codigo} en estado {$solicitudExistente->estado}). Solo se permite 1 solicitud por temporada."
                ], 422);
            }

            // Regla UAGRM: Límite histórico de casos por estudiante en toda su carrera (por defecto 5)
            $limiteCarrera = $periodo->max_casos_carrera ?? 5;
            $totalCasosHistoricos = Solicitud::where('id_estudiante', $user->id_usuario)
                ->whereIn('estado', ['HABILITADA', 'APROBADA', 'APROBADA_DIRECCION', 'REMITIDA_A_CPD'])
                ->count();
            if ($totalCasosHistoricos >= $limiteCarrera) {
                return response()->json([
                    'error' => "Has alcanzado el límite institucional máximo de {$limiteCarrera} casos especiales aprobados en tu carrera universitaria."
                ], 422);
            }

            // Validar formato de gestión (1-YYYY o 2-YYYY)
            if (!preg_match('/^[12]-\d{4}$/', $periodo->gestion)) {
                return response()->json(['error' => 'La gestión del período debe tener formato 1-YYYY o 2-YYYY.'], 422);
            }

            // Consolidar lista de grupos a solicitar (múltiples materias o individual)
            $gruposASolicitar = [];
            if (!empty($validated['grupos']) && is_array($validated['grupos'])) {
                $gruposASolicitar = $validated['grupos'];
            } elseif (!empty($validated['id_grupo'])) {
                $gruposASolicitar[] = [
                    'id_grupo' => $validated['id_grupo'],
                    'motivo' => $validated['motivo_grupo'] ?? 'Materia solicitada en el registro del caso'
                ];
            }

            // Límite de materias que CPD/Facultad permite solicitar por caso (por defecto 5)
            $maxMaterias = $periodo->max_materias_por_caso ?? 5;
            if (count($gruposASolicitar) > $maxMaterias) {
                return response()->json([
                    'error' => "No puedes solicitar más de {$maxMaterias} materias en este caso especial."
                ], 422);
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
                'tipo_caso' => $validated['tipo_caso'] ?? 'CASO_ESPECIAL',
                'motivo' => $validated['motivo'],
                'estado' => 'ENVIADA',
                'fecha_solicitud' => now(),
            ]);

            $estudiante = Estudiante::findOrFail($user->id_usuario);
            $gruposRegistradosIds = [];

            foreach ($gruposASolicitar as $itemGrupo) {
                $idG = $itemGrupo['id_grupo'];
                if (in_array($idG, $gruposRegistradosIds)) continue;

                $grupo = Grupo::with('materia')->findOrFail($idG);

                $perteneceACarrera = DB::table('materia_carrera')
                    ->where('id_materia', $grupo->id_materia)
                    ->where('id_carrera', $estudiante->carrera_id)
                    ->exists();

                if (!$perteneceACarrera) {
                    return response()->json(['error' => "La materia {$grupo->materia?->sigla} no pertenece a la carrera del estudiante."], 422);
                }

                SolicitudGrupo::create([
                    'id_solicitud' => $solicitud->id,
                    'id_grupo' => $grupo->id,
                    'motivo' => $itemGrupo['motivo'] ?? 'Materia solicitada en el registro del caso',
                    'estado' => 'PENDIENTE'
                ]);
                $gruposRegistradosIds[] = $idG;
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

        // Una vez enviada la solicitud, queda cerrada para evaluación de Dirección.
        // Solo se admiten nuevos respaldos si la solicitud fue explícitamente OBSERVADA.
        if ($solicitud->estado !== 'OBSERVADA') {
            return response()->json(['error' => 'La solicitud ya fue enviada y se encuentra en revisión. Solo se pueden adjuntar nuevos respaldos si la solicitud está OBSERVADA.'], 422);
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

    /**
     * Obtener el Avance e Histórico Académico del Estudiante
     */
    public function miHistorico(Request $request)
    {
        $user = $request->user();
        $historico = DB::table('estudiante_materia_historial')
            ->join('materia', 'estudiante_materia_historial.id_materia', '=', 'materia.id')
            ->where('estudiante_materia_historial.id_estudiante', $user->id_usuario)
            ->select(
                'estudiante_materia_historial.id',
                'estudiante_materia_historial.id_materia',
                'materia.sigla',
                'materia.nombre as materia_nombre',
                'materia.nivel_semestre',
                'materia.creditos',
                'estudiante_materia_historial.gestion',
                'estudiante_materia_historial.estado_materia',
                'estudiante_materia_historial.nota_final',
                'estudiante_materia_historial.sigla_grupo'
            )
            ->orderBy('materia.nivel_semestre')
            ->orderBy('materia.sigla')
            ->get();

        return response()->json($historico);
    }

    /**
     * Generar la Carta Oficial de Solicitud en formato imprimible/PDF oficial
     */
    public function descargarCarta($id)
    {
        $solicitud = Solicitud::with([
            'estudiante.usuario',
            'estudiante.carrera',
            'periodo',
            'grupos.grupo.materia'
        ])->findOrFail($id);

        $estudiante = $solicitud->estudiante;
        $usuario = $estudiante?->usuario;
        $carrera = $estudiante?->carrera;

        $materiasDetalle = '';
        foreach ($solicitud->grupos as $sg) {
            $materiasDetalle .= "<tr>
                <td style='border:1px solid #000; padding:6px;'>{$sg->grupo?->materia?->sigla}</td>
                <td style='border:1px solid #000; padding:6px;'>{$sg->grupo?->materia?->nombre}</td>
                <td style='border:1px solid #000; padding:6px; text-align:center;'>Grupo {$sg->grupo?->sigla_grupo}</td>
                <td style='border:1px solid #000; padding:6px;'>{$sg->motivo}</td>
            </tr>";
        }

        $html = "<!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <title>Carta Solicitud Caso Especial - {$solicitud->codigo}</title>
            <style>
                body { font-family: 'Times New Roman', serif; font-size: 13pt; line-height: 1.5; margin: 40px; color: #000; }
                .header-inst { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 25px; }
                .header-inst h3, .header-inst h4, .header-inst p { margin: 2px; }
                .codigo-box { text-align: right; font-weight: bold; margin-bottom: 15px; }
                .destinatario { margin-bottom: 25px; line-height: 1.3; }
                .cuerpo { text-align: justify; margin-bottom: 25px; text-indent: 40px; }
                table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 11pt; }
                .firma-box { margin-top: 70px; text-align: center; width: 300px; margin-left: auto; margin-right: auto; border-top: 1px solid #000; padding-top: 5px; }
                @media print {
                    body { margin: 20mm; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class='no-print' style='background:#f1f5f9; padding:10px 15px; margin-bottom:20px; border-radius:6px; display:flex; justify-content:space-between; align-items:center;'>
                <span>Vista preliminar de la Carta de Solicitud de Caso Especial (Documento Oficial UAGRM).</span>
                <button onclick='window.print()' style='background:#0064e0; color:#fff; border:none; padding:8px 16px; border-radius:20px; font-weight:bold; cursor:pointer;'>Imprimir o Guardar en PDF</button>
            </div>

            <div class='header-inst'>
                <h3>UNIVERSIDAD AUTÓNOMA GABRIEL RENÉ MORENO</h3>
                <h4>FACULTAD DE INGENIERÍA EN CIENCIAS DE LA COMPUTACIÓN Y TELECOMUNICACIONES</h4>
                <p>Dirección de Carrera de {$carrera?->nombre}</p>
            </div>

            <div class='codigo-box'>
                Trámite N°: {$solicitud->codigo}<br>
                Santa Cruz de la Sierra, " . date('d/m/Y', strtotime($solicitud->fecha_solicitud)) . "
            </div>

            <div class='destinatario'>
                Señor(a):<br>
                <strong>DIRECTOR(A) DE CARRERA DE " . strtoupper($carrera?->nombre ?? '') . "</strong><br>
                FICCT - UAGRM<br>
                <u>Presente.-</u>
            </div>

            <div style='text-align: right; font-weight: bold; margin-bottom: 20px;'>
                Ref.: <u>SOLICITUD DE CASO ESPECIAL ({$solicitud->tipo_caso})</u>
            </div>

            <div class='cuerpo'>
                De mi mayor consideración:<br><br>
                Por medio de la presente, yo, <strong>{$usuario?->nombre} {$usuario?->apellidos}</strong>, con Registro Universitario N° <strong>{$estudiante?->registro_universitario}</strong> y Cédula de Identidad N° <strong>{$usuario?->ci}</strong>, estudiante regular de la carrera de <strong>{$carrera?->nombre}</strong> cursando actualmente el <strong>{$estudiante?->semestre_actual}° Semestre</strong> de la Gestión Académica <strong>{$solicitud->periodo?->gestion}</strong>, me dirijo a su digna autoridad para exponer y solicitar lo siguiente:
            </div>

            <div class='cuerpo'>
                " . nl2br(htmlspecialchars($solicitud->motivo)) . "
            </div>

            <div style='margin-bottom: 10px; font-weight: bold;'>
                Detalle de Materia(s) y Grupo(s) Solicitado(s):
            </div>

            <table>
                <thead>
                    <tr style='background: #f2f2f2;'>
                        <th style='border:1px solid #000; padding:6px;'>Sigla</th>
                        <th style='border:1px solid #000; padding:6px;'>Asignatura</th>
                        <th style='border:1px solid #000; padding:6px;'>Grupo</th>
                        <th style='border:1px solid #000; padding:6px;'>Observación / Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    {$materiasDetalle}
                </tbody>
            </table>

            <div class='cuerpo'>
                Sin otro particular y esperando una respuesta favorable a la presente solicitud para no perjudicar mi normal avance curricular, me despido con las consideraciones más distinguidas.
            </div>

            <div class='firma-box'>
                <strong>{$usuario?->nombre} {$usuario?->apellidos}</strong><br>
                Postulante a Caso Especial<br>
                R.U.: {$estudiante?->registro_universitario}<br>
                C.I.: {$usuario?->ci}
            </div>
        </body>
        </html>";

        return response($html)->header('Content-Type', 'text/html');
    }
}
