<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PagoTransaccion;
use App\Models\Solicitud;
use App\Services\AuditoriaNotificacionService;
use App\Services\ConfirmacionPagoService;
use App\Services\PagoFacilService;
use App\Services\StripeCheckoutService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;

class PagoController extends Controller
{
    protected PagoFacilService $pagoFacilService;

    public function __construct(PagoFacilService $pagoFacilService, private StripeCheckoutService $stripe)
    {
        $this->pagoFacilService = $pagoFacilService;
    }

    /**
     * Generar un cobro por QR para una solicitud aprobada por Dirección
     */
    public function generarQrSolicitud(Request $request, $idSolicitud)
    {
        return DB::transaction(fn () => $this->generarQrConBloqueo($request, $idSolicitud));
    }

    private function generarQrConBloqueo(Request $request, $idSolicitud)
    {
        $user = $request->user();
        $solicitud = Solicitud::with(['estudiante.usuario'])->lockForUpdate()->findOrFail($idSolicitud);

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

        if ($solicitud->archivos()->where('tipo_archivo', 'BOLETA_PENDIENTE')->exists()) {
            return response()->json(['error' => 'Tu boleta está pendiente de revisión por Dirección.'], 409);
        }

        if (!$request->boolean('cerrar_stripe') && PagoTransaccion::where('id_solicitud', $solicitud->id)
            ->where('pasarela', 'STRIPE')->where('estado', 'PENDIENTE')->exists()) {
            return response()->json([
                'error' => 'Hay un intento de pago por Stripe. Para usar QR, primero debe cerrarse.',
                'codigo' => 'STRIPE_PENDIENTE',
            ], 409);
        }

        try {
            $tarjeta = $this->stripe->cerrarPendiente($solicitud->id);
        } catch (ApiErrorException $error) {
            return $this->stripe->errorProveedor($error);
        }
        if ($tarjeta?->estado === 'PAGADO') {
            return response()->json(['pago' => $tarjeta]);
        }
        if ($tarjeta?->estado === 'PENDIENTE') {
            return response()->json(['error' => 'Stripe aún está procesando el pago. Vuelve a consultar en unos minutos.'], 409);
        }

        $qrPendiente = PagoTransaccion::where('id_solicitud', $solicitud->id)
            ->where('pasarela', 'PAGOFACIL_QR')->where('estado', 'PENDIENTE')->first();
        if ($qrPendiente && (!$qrPendiente->qr_base64 || $qrPendiente->fecha_expiracion?->isPast())) {
            try {
                $this->pagoFacilService->sincronizar($qrPendiente);
            } catch (Exception $error) {
                report($error);
                return response()->json(['error' => 'No se pudo verificar el QR anterior. Inténtalo en unos minutos.'], 502);
            }
        }
        if ($qrPendiente?->estado === 'PAGADO') {
            return response()->json(['pago' => $qrPendiente]);
        }
        if ($qrPendiente?->estado === 'PENDIENTE') {
            if (!$qrPendiente->qr_base64) {
                return response()->json(['error' => 'Hay un cobro QR pendiente de conciliación.'], 409);
            }
            return response()->json([
                'message' => 'QR existente pendiente de pago.',
                'pago' => $qrPendiente,
                'modo_sandbox' => str_starts_with($qrPendiente->pagofacil_transaction_id ?? '', 'DEMO-'),
            ]);
        }

        // Obtener arancel configurado en el periodo académico (por defecto 10.00 Bs oficial de la UAGRM)
        $monto = (float) ($solicitud->periodo?->costo_arancel_caso ?? env('PAGOFACIL_COSTO_CASO_ESPECIAL', 10.00));
        if ($monto <= 0) {
            return response()->json(['error' => 'El arancel debe ser mayor que cero.'], 422);
        }
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
                    'fecha_expiracion' => !empty($values['expirationDate']) ? Carbon::parse($values['expirationDate']) : null,
                ]);
            } else {
                if (!app()->environment('local', 'testing')) {
                    $pago->update(['estado' => 'FALLIDO']);
                    return response()->json(['error' => 'El pago por QR no está disponible en este momento.'], 503);
                }
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
            $incierto = $e instanceof ConnectionException || ($e instanceof RequestException && $e->response->serverError());
            if (!$incierto) {
                $pago->update(['estado' => 'FALLIDO']);
            }
            return response()->json([
                'error' => $incierto
                    ? 'PagoFácil no respondió. El intento queda pendiente de verificación.'
                    : 'No se pudo generar el QR en PagoFácil. Inténtalo nuevamente.',
            ], 502);
        }
    }

    /**
     * Consultar estado de una transacción de pago
     */
    public function consultarEstado(Request $request, $idPago)
    {
        $pago = PagoTransaccion::with('solicitud')->where('pasarela', 'PAGOFACIL_QR')->findOrFail($idPago);
        $user = $request->user();
        abort_unless($pago->solicitud?->id_estudiante === $user->id_usuario || $pago->id_usuario === $user->id_usuario
            || $user->tieneRol('DIRECTOR') || $user->tieneRol('PERSONAL_ACADEMICO'), 403);

        try {
            $this->pagoFacilService->sincronizar($pago);
        } catch (Exception $error) {
            report($error);
            return response()->json(['error' => 'No se pudo consultar el pago en PagoFácil.'], 502);
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
        $pedidoId = $request->input('PedidoID') ?? $request->input('pedidoId') ?? $request->input('companyTransactionId');

        if (!$pedidoId) {
            return response()->json([
                'error' => 1,
                'status' => 0,
                'message' => 'Falta el parámetro PedidoID.',
                'values' => false
            ], 400);
        }

        $pago = PagoTransaccion::where('pasarela', 'PAGOFACIL_QR')->where('numero_pago', $pedidoId)->first();

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

        // El callback inicia la consulta. El estado se verifica por el canal autenticado.
        try {
            $this->pagoFacilService->sincronizar($pago);
        } catch (Exception $error) {
            report($error);
            return response()->json(['error' => 1, 'status' => 0, 'message' => 'No se pudo verificar el pago.', 'values' => false], 503);
        }
        if ($pago->estado === 'PENDIENTE') {
            return response()->json(['error' => 1, 'status' => 0, 'message' => 'Pago pendiente de confirmación.', 'values' => false], 503);
        }

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
        abort_unless(app()->environment('local', 'testing') && !$this->pagoFacilService->tieneCredenciales(), 403);
        $pago = PagoTransaccion::where('pasarela', 'PAGOFACIL_QR')->where('id_usuario', $user->id_usuario)->findOrFail($idPago);

        return DB::transaction(function () use ($pago, $user) {
            $solicitud = Solicitud::lockForUpdate()->findOrFail($pago->id_solicitud);
            $pago->refresh();
            if ($pago->estado === 'PAGADO') {
                return response()->json(['message' => 'El pago ya estaba confirmado.', 'pago' => $pago]);
            }

            abort_unless($pago->estado === 'PENDIENTE' && $pago->fecha_expiracion?->isFuture()
                && str_starts_with($pago->pagofacil_transaction_id ?? '', 'DEMO-')
                && $solicitud->estado === 'APROBADA_DIRECCION', 409, 'El QR simulado ya no está vigente.');

            app(ConfirmacionPagoService::class)->confirmar($pago, [
                'PedidoID' => $pago->numero_pago,
                'MetodoPago' => 'QR BANCA MOVIL (SIMULADOR)',
                'Estado' => 'COMPLETADO',
                'SimuladoPor' => $user->email,
            ], 'SIMULACION_DEMO');

            return response()->json(['message' => 'Pago confirmado en modo simulación.', 'pago' => $pago]);
        });
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

            try {
                $tarjeta = $this->stripe->cerrarPendiente($solicitud->id);
            } catch (ApiErrorException $error) {
                return $this->stripe->errorProveedor($error);
            }
            if ($tarjeta?->estado === 'PAGADO') {
                return response()->json(['message' => 'Stripe ya confirmó el pago.', 'pago' => $tarjeta]);
            }
            if ($tarjeta?->estado === 'PENDIENTE') {
                return response()->json(['error' => 'Stripe aún está procesando el pago.'], 409);
            }

            try {
                $qr = $this->pagoFacilService->conciliarPendiente($solicitud->id);
            } catch (Exception $error) {
                report($error);
                return response()->json(['error' => 'No se pudo verificar el pago QR. Inténtalo en unos minutos.'], 502);
            }
            if ($qr?->estado === 'PAGADO') {
                return response()->json(['message' => 'PagoFácil ya confirmó el pago.', 'pago' => $qr]);
            }
            if ($qr?->estado === 'PENDIENTE') {
                return response()->json(['error' => 'Hay un QR vigente. Espera a que venza o se confirme antes de validar otro pago.'], 409);
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

            $solicitud->archivos()->where('tipo_archivo', 'BOLETA_PENDIENTE')->update(['tipo_archivo' => 'COMPROBANTE_PAGO']);

            AuditoriaNotificacionService::registrarTransicion(
                $solicitud,
                'APROBADA',
                'VALIDACION_MANUAL_PAGO',
                $user->id_usuario,
                "Dirección validó manualmente el arancel (Ref. {$pago->numero_pago}).",
                "Solicitud aprobada - {$solicitud->codigo}",
                'Dirección validó tu pago. Tu solicitud está aprobada y queda pendiente de remisión a CPD.'
            );

            return response()->json(['message' => 'Pago validado manualmente.', 'pago' => $pago]);
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
        } elseif (!$user->tieneRol('CPD')) {
            $query->where('pasarela', '<>', 'STRIPE');
        }

        if ($request->has('id_solicitud')) {
            $query->where('id_solicitud', $request->query('id_solicitud'));
        }

        return response()->json($query->paginate(20));
    }
}
