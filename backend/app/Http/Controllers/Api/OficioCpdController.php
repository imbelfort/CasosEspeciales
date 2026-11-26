<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArchivoSolicitud;
use App\Models\OficioCpd;
use App\Models\Periodo;
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

    /**
     * CU5: Remisión por Lotes a CPD (OficioCpdService)
     * Busca solicitudes APROBADA con comprobante de pago sin oficio asignado.
     * Genera código OF-CPD-YYYYMMDD-XXXXXXXX, crea CSV consolidado y pasa a REMITIDA_A_CPD.
     */
    public function generarOficioRemision(Request $request)
    {
        $user = $request->user();
        if (!$user->tieneRol('DIRECTOR') && !$user->tieneRol('PERSONAL_ACADEMICO')) {
            return response()->json(['error' => 'Acceso denegado. Exclusivo para autoridades de carrera.'], 403);
        }

        $validated = $request->validate([
            'id_periodo' => 'required|integer|exists:periodos,id',
            'destinatario' => 'required|string|max:100',
            'referencia' => 'required|string|max:200',
            'detalle' => 'nullable|string',
            'solicitudes_ids' => 'required|array|min:1',
            'solicitudes_ids.*' => 'integer|exists:solicitud,id'
        ]);

        return DB::transaction(function () use ($user, $validated) {
            $solicitudes = Solicitud::lockForUpdate()
                ->whereIn('id', $validated['solicitudes_ids'])
                ->where('id_periodo', $validated['id_periodo'])
                ->where('estado', 'APROBADA')
                ->with(['estudiante.usuario', 'estudiante.carrera', 'grupos.grupo.materia', 'archivos'])
                ->get();

            if ($solicitudes->isEmpty()) {
                return response()->json([
                    'error' => 'Ninguna de las solicitudes seleccionadas cumple con los requisitos (Estado APROBADA con pago).'
                ], 422);
            }

            // Generar código único OF-CPD-YYYYMMDD-XXXXXXXX
            $fechaStr = date('Ymd');
            $randomHex = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $numeroOficio = "OF-CPD-{$fechaStr}-{$randomHex}";

            // Construir CSV de remisión con codificación UTF-8 BOM
            $csvRows = [];
            $csvRows[] = "CODIGO_SOLICITUD,REGISTRO_ESTUDIANTE,NOMBRE_ESTUDIANTE,CARRERA,TIPO_CASO,SIGLA_MATERIA,GRUPO,COMPROBANTE_PAGO";

            foreach ($solicitudes as $sol) {
                $nombreCompleto = '"' . str_replace('"', '""', $sol->estudiante->usuario->nombre . ' ' . $sol->estudiante->usuario->apellidos) . '"';
                $reg = $sol->estudiante->registro_universitario;
                $carrera = '"' . str_replace('"', '""', $sol->estudiante->carrera->nombre ?? '') . '"';
                $pago = $sol->archivos->where('tipo_archivo', 'COMPROBANTE_PAGO')->first();
                $rutaPago = $pago ? $pago->ruta_archivo : 'SIN_COMPROBANTE';

                foreach ($sol->grupos as $sg) {
                    $siglaMat = $sg->grupo->materia->sigla ?? 'N/A';
                    $siglaGrupo = $sg->grupo->sigla_grupo ?? 'N/A';
                    $csvRows[] = "{$sol->codigo},{$reg},{$nombreCompleto},{$carrera},{$sol->tipo_caso},{$siglaMat},{$siglaGrupo},{$rutaPago}";
                }
            }

            $csvContent = "\xEF\xBB\xBF" . implode("\r\n", $csvRows);
            $rutaDocumento = "oficios_cpd/{$numeroOficio}.csv";
            Storage::disk('local')->put($rutaDocumento, $csvContent);

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

            return response()->json([
                'mensaje' => 'Oficio de remisión a CPD generado exitosamente',
                'oficio' => $oficio,
                'solicitudes_remitidas' => $solicitudes->count(),
            ], 201);
        });
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
