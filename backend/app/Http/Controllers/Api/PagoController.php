<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ArchivoSolicitud;
use App\Models\PagoTransaccion;
use App\Models\Solicitud;
use App\Services\AuditoriaNotificacionService;
use App\Services\PagoFacilService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PagoController extends Controller
{
    protected PagoFacilService $pagoFacilService;

    public function __construct(PagoFacilService $pagoFacilService)
    {
        $this->pagoFacilService = $pagoFacilService;
    }

    /**
     * Generar un cobro por QR para una solicitud aprobada por Dirección
     */
    public function generarQrSolicitud(Request $request, $idSolicitud)
    {
        $user = $request->user();

        $solicitud = Solicitud::with(['estudiante.usuario'])->findOrFail($idSolicitud);

        // Validación de pertenencia / permisos
        if ($solicitud->id_estudiante !== $user->id_usuario && !$user->tieneRol('DIRECTOR') && !$user->tieneRol('PERSONAL_ACADEMICO')) {
            return response()->json(['error' => 'No autorizado para generar pago de esta solicitud.'], 403);
        }

        if ($solicitud->estado !== 'APROBADA_DIRECCION') {
            return response()->json([
                'error' => "Solo se puede generar cobro QR si la solicitud está en estado APROBADA_DIRECCION (actual: {$solicitud->estado})."
            ], 422);
        }

        // Si ya tiene una transacción pagada, no volver a generar
        $pagoExistente = PagoTransaccion::where('id_solicitud', $solicitud->id)
            ->where('estado', 'PAGADO')
            ->first();

        if ($pagoExistente) {
            return response()->json([
                'message' => 'Esta solicitud ya tiene un pago confirmado.',
                'pago' => $pagoExistente
            ]);
        }

        // Si ya hay un QR pendiente y no ha expirado, reutilizarlo
        $qrPendiente = PagoTransaccion::where('id_solicitud', $solicitud->id)
            ->where('estado', 'PENDIENTE')
            ->where(function ($query) {
                $query->whereNull('fecha_expiracion')
                    ->orWhere('fecha_expiracion', '>', now());
            })
            ->latest()
            ->first();

        if ($qrPendiente && !empty($qrPendiente->qr_base64)) {
            return response()->json([
                'message' => 'QR existente pendiente de pago.',
                'pago' => $qrPendiente
            ]);
        }

        // Obtener arancel configurado en el periodo académico (por defecto 10.00 Bs oficial de la UAGRM)
        $monto = (float) ($solicitud->periodo?->costo_arancel_caso ?? env('PAGOFACIL_COSTO_CASO_ESPECIAL', 10.00));
        $numeroPago = 'CASO-' . $solicitud->id . '-' . strtoupper(Str::random(6));

        $nombreCliente = trim(($user->nombre ?? '') . ' ' . ($user->apellidos ?? ''));
        if (empty($nombreCliente)) {
            $nombreCliente = 'Estudiante FICCT';
        }

        $documento = $user->estudiante->registro_universitario ?? ($user->carnet_identidad ?? '1234567');
        $email = $user->email ?? 'estudiante@uagrm.edu.bo';
        $telefono = $user->telefono ?? '70000000';

        $datosGeneracion = [
            'paymentMethod' => (int) env('PAGOFACIL_PAYMENT_METHOD_ID', 123),
            'clientName' => $nombreCliente,
            'documentType' => 1,
            'documentId' => (string) $documento,
            'phoneNumber' => (string) $telefono,
            'email' => $email,
            'paymentNumber' => $numeroPago,
            'amount' => $monto,
            'currency' => (int) env('PAGOFACIL_CURRENCY_ID', 2),
            'clientCode' => (string) ($user->estudiante->registro_universitario ?? $user->id_usuario),
            'descripcion' => "Trámite de Caso Especial #{$solicitud->codigo} - FICCT UAGRM",
        ];

        // Crear registro local en estado PENDIENTE antes de llamar a la pasarela (pauta 1 de la guía)
        $pago = PagoTransaccion::create([
            'id_solicitud' => $solicitud->id,
            'id_usuario' => $user->id_usuario,
            'numero_pago' => $numeroPago,
            'monto' => $monto,
            'moneda' => 'BOB',
            'codigo_moneda' => 2,
            'estado' => 'PENDIENTE',
            'pasarela' => 'PAGOFACIL_QR',
            'payload_generacion' => $datosGeneracion,
        ]);

        try {
            if ($this->pagoFacilService->tieneCredenciales()) {
                // Modo Real contra PagoFácil API
                $res = $this->pagoFacilService->generarQr($datosGeneracion);
                $values = $res['respuesta'];

                $pago->update([
                    'pagofacil_transaction_id' => $values['transactionId'] ?? null,
                    'payment_method_transaction_id' => $values['paymentMethodTransactionId'] ?? null,
                    'qr_base64' => $values['qrBase64'] ?? null,
                    'checkout_url' => $values['checkoutUrl'] ?? null,
                    'deep_link' => $values['deepLink'] ?? null,
                    'qr_content_url' => $values['qrContentUrl'] ?? null,
                    'universal_url' => $values['universalUrl'] ?? null,
                    'fecha_expiracion' => !empty($values['expirationDate']) ? Carbon::parse($values['expirationDate']) : now()->addHours(24),
                ]);
            } else {
                // Modo Sandbox / Demo (cuando las credenciales aún no han sido suministradas)
                // Genera un SVG en Base64 representativo para pruebas visuales inmediatas
                $qrSvgDemo = base64_encode(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200">
                        <rect width="200" height="200" fill="#ffffff"/>
                        <rect x="20" y="20" width="50" height="50" fill="#1e3a8a"/>
                        <rect x="30" y="30" width="30" height="30" fill="#ffffff"/>
                        <rect x="37" y="37" width="16" height="16" fill="#1e3a8a"/>
                        <rect x="130" y="20" width="50" height="50" fill="#1e3a8a"/>
                        <rect x="140" y="30" width="30" height="30" fill="#ffffff"/>
                        <rect x="147" y="37" width="16" height="16" fill="#1e3a8a"/>
                        <rect x="20" y="130" width="50" height="50" fill="#1e3a8a"/>
                        <rect x="30" y="140" width="30" height="30" fill="#ffffff"/>
                        <rect x="37" y="147" width="16" height="16" fill="#1e3a8a"/>
                        <rect x="90" y="30" width="20" height="20" fill="#1e3a8a"/>
                        <rect x="80" y="70" width="40" height="20" fill="#1e3a8a"/>
                        <rect x="30" y="90" width="30" height="20" fill="#1e3a8a"/>
                        <rect x="140" y="90" width="30" height="20" fill="#1e3a8a"/>
                        <rect x="90" y="110" width="20" height="40" fill="#1e3a8a"/>
                        <rect x="130" y="130" width="40" height="20" fill="#1e3a8a"/>
                        <rect x="130" y="160" width="20" height="20" fill="#1e3a8a"/>
                        <text x="100" y="195" font-family="Arial" font-size="9" text-anchor="middle" fill="#64748b">MODO SIMULADOR PAGOFACIL</text>
                    </svg>'
                );

                $pago->update([
                    'pagofacil_transaction_id' => 'DEMO-' . rand(100000, 999999),
                    'payment_method_transaction_id' => $numeroPago,
                    'qr_base64' => $qrSvgDemo,
                    'fecha_expiracion' => now()->addHours(24),
                ]);
            }

            return response()->json([
                'message' => 'Código QR de cobro generado exitosamente.',
                'pago' => $pago,
                'modo_sandbox' => !$this->pagoFacilService->tieneCredenciales(),
            ], 201);

        } catch (Exception $e) {
            $pago->update(['estado' => 'FALLIDO']);
            return response()->json([
                'error' => 'No se pudo generar el QR en PagoFácil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Consultar estado de una transacción de pago
     */
    public function consultarEstado($idPago)
    {
        $pago = PagoTransaccion::with('solicitud')->findOrFail($idPago);

        if ($pago->estado === 'PAGADO') {
            return response()->json([
                'estado' => 'PAGADO',
                'pago' => $pago,
                'mensaje' => 'La transacción ya ha sido pagada y confirmada.'
            ]);
        }

        // Consultar API si tiene credenciales
        if ($this->pagoFacilService->tieneCredenciales() && $pago->numero_pago) {
            try {
                $resultado = $this->pagoFacilService->consultarTransaccion($pago->numero_pago);
                $pago->payload_consulta = $resultado;

                // Si paymentStatus indica pagado (o viene paymentDate confirmada)
                if (!empty($resultado['paymentDate']) || ($resultado['paymentStatus'] ?? null) == 1) {
                    $this->confirmarPagoExitoso($pago, $resultado, 'CONSULTA_API');
                } else {
                    $pago->save();
                }
            } catch (Exception $e) {
                Log::warning('Error consultando transacción PagoFácil', ['error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'estado' => $pago->estado,
            'pago' => $pago,
        ]);
    }

    /**
     * Webhook / Callback recibido desde PagoFácil (Punto 8 de la Guía)
     */
    public function callbackWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('PagoFacil Callback recibido', $payload);

        $pedidoId = $request->input('PedidoID') ?? $request->input('pedidoId') ?? $request->input('companyTransactionId');

        if (!$pedidoId) {
            return response()->json([
                'error' => 1,
                'status' => 0,
                'message' => 'Falta el parámetro PedidoID.',
                'values' => false
            ], 400);
        }

        $pago = PagoTransaccion::where('numero_pago', $pedidoId)->first();

        if (!$pago) {
            return response()->json([
                'error' => 1,
                'status' => 0,
                'message' => "Transacción {$pedidoId} no encontrada.",
                'values' => false
            ], 404);
        }

        // Idempotencia: si ya está pagado, responder de inmediato HTTP 200 OK
        if ($pago->estado === 'PAGADO') {
            return response()->json([
                'error' => 0,
                'status' => 1,
                'message' => 'Notificación recibida (ya procesada previamente).',
                'values' => true
            ], 200);
        }

        // Confirmar pago y actualizar solicitud
        $this->confirmarPagoExitoso($pago, $payload, 'WEBHOOK_CALLBACK');

        // Acuse esperado por PagoFácil (Sección 8 p. 297)
        return response()->json([
            'error' => 0,
            'status' => 1,
            'message' => 'Notificación recibida',
            'values' => true
        ], 200);
    }

    /**
     * Simular pago manual en modo Demo/Desarrollo (facilidad para pruebas antes de recibir credenciales de producción)
     */
    public function simularPagoDemo(Request $request, $idPago)
    {
        $user = $request->user();
        $pago = PagoTransaccion::findOrFail($idPago);

        if ($pago->estado === 'PAGADO') {
            return response()->json(['message' => 'El pago ya estaba confirmado.', 'pago' => $pago]);
        }

        $payloadSimulado = [
            'PedidoID' => $pago->numero_pago,
            'Fecha' => now()->format('Y-m-d'),
            'Hora' => now()->format('H:i:s'),
            'MetodoPago' => 'QR BANCA MOVIL (SIMULADOR)',
            'Estado' => 'COMPLETADO',
            'SimuladoPor' => $user->email,
        ];

        $this->confirmarPagoExitoso($pago, $payloadSimulado, 'SIMULACION_DEMO');

        return response()->json([
            'message' => 'Pago confirmado exitosamente en modo simulación.',
            'pago' => $pago
        ]);
    }

    public function validarPagoManual(Request $request, $idSolicitud)
    {
        $user = $request->user();
        if (!$user->tieneRol('DIRECTOR') && !$user->tieneRol('PERSONAL_ACADEMICO')) {
            return response()->json(['error' => 'No autorizado para validar pagos manualmente.'], 403);
        }

        return DB::transaction(function () use ($user, $idSolicitud) {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($idSolicitud);
            $pago = PagoTransaccion::where('id_solicitud', $solicitud->id)
                ->where('estado', 'PAGADO')
                ->first();

            if ($pago && $solicitud->estado !== 'APROBADA_DIRECCION') {
                return response()->json(['message' => 'El pago ya estaba confirmado.', 'pago' => $pago]);
            }

            if ($solicitud->estado !== 'APROBADA_DIRECCION') {
                return response()->json(['error' => 'La solicitud debe estar aprobada por Dirección para validar el pago.'], 422);
            }

            $pago ??= PagoTransaccion::create([
                'id_solicitud' => $solicitud->id,
                'id_usuario' => $solicitud->id_estudiante,
                'numero_pago' => 'MANUAL-' . $solicitud->id . '-' . strtoupper(Str::random(6)),
                'monto' => $solicitud->periodo?->costo_arancel_caso ?? env('PAGOFACIL_COSTO_CASO_ESPECIAL', 10.00),
                'estado' => 'PAGADO',
                'pasarela' => 'MANUAL',
                'fecha_pago' => now(),
                'metodo_pago_nombre' => 'Validación manual',
            ]);

            AuditoriaNotificacionService::registrarTransicion(
                $solicitud,
                'APROBADA',
                'VALIDACION_MANUAL_PAGO',
                $user->id_usuario,
                "Dirección validó manualmente el arancel (Ref. {$pago->numero_pago})."
            );

            return response()->json(['message' => 'Pago validado manualmente.', 'pago' => $pago]);
        });
    }

    /**
     * Transición atómica a pagado y actualización de la solicitud (Pautas 2 y 3 de la Guía)
     */
    protected function confirmarPagoExitoso(PagoTransaccion $pago, array $payload, string $origen)
    {
        DB::transaction(function () use ($pago, $payload, $origen) {
            $solicitud = $pago->id_solicitud
                ? Solicitud::lockForUpdate()->find($pago->id_solicitud)
                : null;
            $pago->refresh();
            if ($pago->estado === 'PAGADO') {
                return;
            }

            $pago->estado = 'PAGADO';
            $pago->fecha_pago = now();
            $pago->payload_callback = $payload;
            $pago->metodo_pago_nombre = $payload['MetodoPago'] ?? ($payload['paymentMethodName'] ?? 'QR PagoFácil');
            $pago->save();

            // Si la transacción está ligada a un trámite de solicitud
            if ($solicitud && $solicitud->estado === 'APROBADA_DIRECCION') {
                // Registrar comprobante digital en los archivos
                ArchivoSolicitud::create([
                    'id_solicitud' => $solicitud->id,
                    'nombre_archivo' => "Comprobante_PagoFacil_QR_{$pago->numero_pago}.json",
                    'tipo_archivo' => 'COMPROBANTE_PAGO',
                    'ruta_archivo' => "pagos/{$pago->numero_pago}.json",
                    'fecha_subida' => now(),
                ]);

                // Transicionar estado a APROBADA (lista para remisión a CPD)
                AuditoriaNotificacionService::registrarTransicion(
                    $solicitud,
                    'APROBADA',
                    $origen,
                    $pago->id_usuario,
                    "Pago de arancel completado exitosamente por QR PagoFácil (Ref: {$pago->numero_pago}). Trámite pasa a APROBADA para remisión a CPD."
                );
            }
        });
    }

    /**
     * Listar pagos registrados para una solicitud o del usuario actual
     */
    public function listarPagos(Request $request)
    {
        $user = $request->user();
        $query = PagoTransaccion::with(['solicitud', 'usuario'])->latest();

        if ($user->tieneRol('ESTUDIANTE')) {
            $query->where('id_usuario', $user->id_usuario);
        }

        if ($request->has('id_solicitud')) {
            $query->where('id_solicitud', $request->query('id_solicitud'));
        }

        return response()->json($query->paginate(20));
    }
}
