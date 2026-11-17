<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArchivoSolicitud;
use App\Models\OficioCpd;
use App\Models\Solicitud;
use App\Services\AuditoriaNotificacionService;
use App\Services\PagoFacilService;
use App\Services\StripeCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;

class OficioCpdController extends Controller
{
    /**
     * CU5: Procesamiento de Pagos (PagoService)
     * Solo admitido si la solicitud se encuentra en APROBADA_DIRECCION.
     * Dirección contrasta el comprobante antes de aprobar el pago.
     */
    public function registrarPago(Request $request, $idSolicitud, StripeCheckoutService $stripe, PagoFacilService $qr)
    {
        $user = $request->user();
        $request->validate([
            'comprobante' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        return DB::transaction(function () use ($user, $idSolicitud, $request, $stripe, $qr) {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($idSolicitud);

            if ($solicitud->id_estudiante !== $user->id_usuario && !$user->tieneRol('DIRECTOR') && !$user->tieneRol('PERSONAL_ACADEMICO')) {
                return response()->json(['error' => 'No autorizado para subir pago de esta solicitud.'], 403);
            }

            if ($solicitud->estado !== 'APROBADA_DIRECCION') {
                return response()->json([
                    'error' => "Solo se puede adjuntar comprobante de pago si la solicitud está en estado APROBADA_DIRECCION (actual: {$solicitud->estado})."
                ], 422);
            }

            if ($solicitud->archivos()->where('tipo_archivo', 'BOLETA_PENDIENTE')->exists()) {
                return response()->json(['error' => 'Ya enviaste una boleta para revisión.'], 409);
            }

            try {
                $tarjeta = $stripe->cerrarPendiente($solicitud->id);
            } catch (ApiErrorException $error) {
                return $stripe->errorProveedor($error);
            }
            if ($tarjeta?->estado === 'PAGADO') {
                return response()->json(['estado' => 'PAGADO']);
            }
            if ($tarjeta?->estado === 'PENDIENTE') {
                return response()->json(['error' => 'Stripe todavía está procesando el pago.'], 409);
            }

            try {
                $pagoQr = $qr->conciliarPendiente($solicitud->id);
            } catch (\Exception $error) {
                report($error);
                return response()->json(['error' => 'No se pudieron verificar los intentos anteriores.'], 502);
            }
            if ($pagoQr?->estado === 'PAGADO') {
                return response()->json(['estado' => 'PAGADO']);
            }
            if ($pagoQr?->estado === 'PENDIENTE') {
                return response()->json(['error' => 'Hay un cobro pendiente. Espera su confirmación o vencimiento.'], 409);
            }

            $file = $request->file('comprobante');
            $uuid = Str::uuid()->toString();
            $nombreOriginal = $file->getClientOriginalName();
            $nombreGuardado = "{$uuid}_{$nombreOriginal}";
            $ruta = $file->storeAs("archivos_solicitudes/{$solicitud->id}", $nombreGuardado, 'local');
            abort_unless($ruta, 500, 'No se pudo guardar la boleta.');

            $archivo = ArchivoSolicitud::create([
                'id_solicitud' => $solicitud->id,
                'nombre_archivo' => $nombreOriginal,
                'tipo_archivo' => 'BOLETA_PENDIENTE',
                'ruta_archivo' => $ruta,
                'fecha_subida' => now(),
            ]);

            return response()->json([
                'mensaje' => 'Boleta enviada para revisión por Dirección.',
                'archivo' => $archivo,
                'solicitud' => $solicitud
            ], 201);
        });
    }

    public function descargarBoleta(Request $request, int $id, int $archivo)
    {
        $user = $request->user();
        abort_unless($user->tieneRol('DIRECTOR') || $user->tieneRol('PERSONAL_ACADEMICO'), 403);
        $boleta = Solicitud::findOrFail($id)->archivos()->where('tipo_archivo', 'BOLETA_PENDIENTE')->findOrFail($archivo);

        abort_unless(Storage::disk('local')->exists($boleta->ruta_archivo), 404);
        return Storage::disk('local')->download($boleta->ruta_archivo, $boleta->nombre_archivo);
    }

    public function observarBoleta(Request $request, int $id, int $archivo)
    {
        $user = $request->user();
        abort_unless($user->tieneRol('DIRECTOR') || $user->tieneRol('PERSONAL_ACADEMICO'), 403);
        $datos = $request->validate(['motivo' => 'required|string|max:500']);

        return DB::transaction(function () use ($id, $archivo, $user, $datos) {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($id);
            abort_unless($solicitud->estado === 'APROBADA_DIRECCION', 409, 'El arancel ya fue resuelto.');
            $boleta = $solicitud->archivos()->where('tipo_archivo', 'BOLETA_PENDIENTE')->findOrFail($archivo);
            $boleta->update(['tipo_archivo' => 'BOLETA_OBSERVADA']);

            AuditoriaNotificacionService::registrarTransicion(
                $solicitud,
                $solicitud->estado,
                'BOLETA_OBSERVADA',
                $user->id_usuario,
                $datos['motivo'],
                "Boleta observada - {$solicitud->codigo}",
                $datos['motivo']
            );

            return response()->noContent();
        });
    }

    public function casosRemitibles(Request $request)
    {
        $this->autorizarEmision($request);

        return Solicitud::where('estado', 'APROBADA')
            ->whereDoesntHave('oficios')
            ->whereHas('grupos', fn ($query) => $query->where('estado', 'APROBADA'))
            ->with([
                'estudiante.usuario:id_usuario,nombre,apellidos',
                'grupos' => fn ($query) => $query->where('estado', 'APROBADA')->with('grupo.materia'),
            ])
            ->orderBy('id')
            ->get();
    }

    private function autorizarEmision(Request $request): void
    {
        $user = $request->user();
        abort_unless($user->tieneRol('DIRECTOR') || $user->tieneRol('PERSONAL_ACADEMICO'), 403);
    }

    public function descargarCsv(Request $request, int $id)
    {
        if (!$request->user()->tieneRol('CPD')) {
            $this->autorizarEmision($request);
        }

        $oficio = OficioCpd::findOrFail($id);
        $disco = Storage::disk('local');
        abort_unless($oficio->ruta_documento && $disco->exists($oficio->ruta_documento), 404, 'El CSV del oficio no está disponible.');

        return $disco->download($oficio->ruta_documento, $oficio->numero_oficio . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * CU5: Remisión por Lotes a CPD (OficioCpdService)
     * Busca solicitudes APROBADA con comprobante de pago sin oficio asignado.
     * Genera código OF-CPD-YYYYMMDD-XXXXXXXX, crea CSV consolidado y pasa a REMITIDA_A_CPD.
     */
    public function generarOficioRemision(Request $request)
    {
        $this->autorizarEmision($request);
        $user = $request->user();

        $validated = $request->validate([
            'id_periodo' => 'required|integer|exists:periodos,id',
            'destinatario' => 'required|string|max:100',
            'referencia' => 'required|string|max:200',
            'detalle' => 'nullable|string',
            'solicitudes_ids' => 'required|array|min:1',
            'solicitudes_ids.*' => 'required|integer|distinct|exists:solicitud,id'
        ]);

        return DB::transaction(function () use ($user, $validated) {
            $solicitudes = Solicitud::lockForUpdate()
                ->whereIn('id', $validated['solicitudes_ids'])
                ->where('id_periodo', $validated['id_periodo'])
                ->where('estado', 'APROBADA')
                ->whereDoesntHave('oficios')
                ->whereHas('grupos', fn ($query) => $query->where('estado', 'APROBADA'))
                ->with([
                    'estudiante.usuario', 'estudiante.carrera', 'archivos', 'periodo',
                    'grupos' => fn ($query) => $query->where('estado', 'APROBADA')->with('grupo.materia')->orderBy('id'),
                ])
                ->orderBy('id')
                ->get();

            if ($solicitudes->count() !== count($validated['solicitudes_ids'])) {
                return response()->json([
                    'error' => 'El lote cambió o incluye casos de otra gestión. Actualiza la selección y vuelve a intentar.'
                ], 422);
            }

            // Generar código único OF-CPD-YYYYMMDD-XXXXXXXX
            $fechaStr = date('Ymd');
            $randomHex = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $numeroOficio = "OF-CPD-{$fechaStr}-{$randomHex}";

            $rutaDocumento = "oficios_cpd/{$numeroOficio}.csv";
            $csv = $this->construirCsv($solicitudes);

            // Crear registro en oficio_cpd
            $oficio = OficioCpd::create([
                'numero_oficio' => $numeroOficio,
                'id_solicitud' => $solicitudes->first()->id,
                'destinatario' => $validated['destinatario'],
                'referencia' => $validated['referencia'],
                'detalle' => $validated['detalle'] ?? null,
                'id_responsable_remision' => $user->id_usuario,
                'fecha_remision' => now(),
                'estado' => 'EMITIDO',
                'ruta_documento' => $rutaDocumento,
            ]);

            // Asociar solicitudes en la tabla pivote y actualizar estados
            foreach ($solicitudes as $sol) {
                DB::table('oficio_cpd_solicitud')->insert([
                    'id_oficio' => $oficio->id,
                    'id_solicitud' => $sol->id
                ]);

                AuditoriaNotificacionService::registrarTransicion(
                    $sol,
                    'REMITIDA_A_CPD',
                    'REMISIÓN_CPD',
                    $user->id_usuario,
                    "Remitida a CPD en el oficio Nro {$numeroOficio}",
                    "Solicitud Remitida a CPD - {$sol->codigo}",
                    "Su solicitud ha sido formalmente remitida al Centro de Procesamiento de Datos (CPD) mediante el oficio {$numeroOficio}."
                );
            }

            abort_unless(Storage::disk('local')->put($rutaDocumento, $csv), 500, 'No se pudo guardar el CSV del oficio.');

            return response()->json([
                'mensaje' => 'Oficio de remisión a CPD generado exitosamente',
                'oficio' => $oficio,
                'solicitudes_remitidas' => $solicitudes->count(),
                'cupos_remitidos' => $solicitudes->sum(fn (Solicitud $solicitud) => $solicitud->grupos->count()),
            ], 201);
        });
    }

    private function construirCsv(iterable $solicitudes): string
    {
        $archivo = fopen('php://temp', 'w+');
        try {
            fwrite($archivo, "\xEF\xBB\xBF");
            fputcsv($archivo, [
                'CODIGO_SOLICITUD', 'REGISTRO_ESTUDIANTE', 'NOMBRE_ESTUDIANTE', 'CARRERA', 'TIPO_CASO',
                'SIGLA_MATERIA', 'GRUPO', 'COMPROBANTE_PAGO', 'NOMBRE_MATERIA', 'GESTION', 'CUPOS',
            ], ',', '"', '', "\r\n");

            foreach ($solicitudes as $solicitud) {
                $estudiante = $solicitud->estudiante;
                $usuario = $estudiante->usuario;
                $comprobante = $solicitud->archivos->firstWhere('tipo_archivo', 'COMPROBANTE_PAGO');

                foreach ($solicitud->grupos as $asignacion) {
                    $grupo = $asignacion->grupo;
                    $fila = [
                        $solicitud->codigo, $estudiante->registro_universitario,
                        trim("{$usuario->nombre} {$usuario->apellidos}"), $estudiante->carrera?->nombre,
                        $solicitud->tipo_caso, $grupo->materia->sigla, $grupo->sigla_grupo,
                        $comprobante?->nombre_archivo, $grupo->materia->nombre, $solicitud->periodo->gestion, 1,
                    ];
                    $fila = array_map(static function ($valor) {
                        $texto = (string) $valor;
                        return preg_match('/^\s*[=+@-]|^[\t\r\n]/u', $texto) ? "'" . $texto : $texto;
                    }, $fila);
                    fputcsv($archivo, $fila, ',', '"', '', "\r\n");
                }
            }

            rewind($archivo);
            return stream_get_contents($archivo);
        } finally {
            fclose($archivo);
        }
    }

    /**
     * Listar oficios generados
     */
    public function listarOficios()
    {
        $oficios = OficioCpd::with(['responsable', 'solicitudes', 'respuestas'])->orderBy('id', 'desc')->get();
        return response()->json($oficios);
    }
}
