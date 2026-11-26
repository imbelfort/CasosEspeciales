<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PagoTransaccion;
use App\Models\Solicitud;
use App\Services\PagoFacilService;
use App\Services\StripeCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class PagoStripeController extends Controller
{
    public function __construct(private StripeCheckoutService $stripe, private PagoFacilService $qr) {}

    public function configuracion()
    {
        return response()->json(['disponible' => $this->stripe->disponible()]);
    }

    public function iniciar(Request $request, int $id)
    {
        abort_unless($this->stripe->disponible(), 503, 'El pago con tarjeta no está habilitado.');

        return DB::transaction(function () use ($request, $id) {
            $solicitud = Solicitud::where('id_estudiante', $request->user()->id_usuario)->lockForUpdate()->findOrFail($id);
            abort_unless($solicitud->estado === 'APROBADA_DIRECCION', 422, 'La solicitud no está pendiente de pago.');
            abort_if(PagoTransaccion::where('id_solicitud', $id)->where('estado', 'PAGADO')->exists(), 409, 'Esta solicitud ya tiene un pago confirmado.');
            abort_if($solicitud->archivos()->where('tipo_archivo', 'BOLETA_PENDIENTE')->exists(), 409, 'Tu boleta está pendiente de revisión por Dirección.');

            try {
                $qr = $this->qr->conciliarPendiente($id);
            } catch (\Exception $error) {
                report($error);
                return response()->json(['message' => 'No se pudo verificar el QR anterior. Inténtalo en unos minutos.'], 502);
            }
            if ($qr?->estado === 'PAGADO') {
                return response()->json(['estado' => 'PAGADO']);
            }
            if ($qr?->estado === 'PENDIENTE') {
                return response()->json(['message' => 'Ya tienes un QR vigente. Puedes pagarlo o esperar a que venza para usar Stripe.'], 409);
            }

            $pendiente = PagoTransaccion::where('id_solicitud', $id)
                ->where('pasarela', 'STRIPE')->where('estado', 'PENDIENTE')->latest('id')->first();

            $monto = $solicitud->periodo?->costo_arancel_caso ?? env('PAGOFACIL_COSTO_CASO_ESPECIAL', 10.00);
            abort_unless($monto > 0, 422, 'El arancel de esta solicitud debe ser mayor que cero.');

            $pago = $pendiente ?? PagoTransaccion::create([
                'id_solicitud' => $id,
                'id_usuario' => $solicitud->id_estudiante,
                'numero_pago' => 'STRIPE-'.Str::uuid(),
                'monto' => $monto,
                'moneda' => 'BOB',
                'estado' => 'PENDIENTE',
                'pasarela' => 'STRIPE',
            ]);

            try {
                $sesion = $this->stripe->sesion($pago);
                if ($sesion) {
                    $this->stripe->sincronizar($pago, $sesion);
                }
            } catch (AuthenticationException|InvalidRequestException $error) {
                // Stripe rechazó los parámetros antes de crear una sesión.
                if (!$pendiente && !$pago->payment_method_transaction_id && in_array($error->getHttpStatus(), [400, 401], true)) {
                    PagoTransaccion::whereKey($pago->id)->where('estado', 'PENDIENTE')->update(['estado' => 'FALLIDO']);
                }
                return $this->stripe->errorProveedor($error);
            } catch (ApiErrorException $error) {
                return $this->stripe->errorProveedor($error);
            }

            if ($pago->estado === 'PAGADO') {
                return response()->json(['estado' => 'PAGADO']);
            }
            if (!$sesion || $pago->estado === 'EXPIRADO') {
                return response()->json(['message' => 'La sesión venció. Puedes volver a elegir Stripe o QR.'], 409);
            }
            if ($sesion->status !== 'open' || !$sesion->url) {
                return response()->json(['message' => 'Stripe todavía está procesando el pago. Consulta el estado en unos minutos.'], 409);
            }

            return response()->json(['url' => $sesion->url]);
        });
    }

    public function estado(Request $request, int $id)
    {
        return $this->actualizar($this->pagoDelAlumno($request, $id), false);
    }

    public function cancelar(Request $request, int $id)
    {
        return $this->actualizar($this->pagoDelAlumno($request, $id), true);
    }

    private function actualizar(PagoTransaccion $pago, bool $cerrar)
    {
        return DB::transaction(function () use ($pago, $cerrar) {
            Solicitud::lockForUpdate()->findOrFail($pago->id_solicitud);
            $pago->refresh();
            if ($pago->estado === 'PENDIENTE') {
                try {
                    if ($cerrar) {
                        $this->stripe->cerrar($pago);
                    } elseif ($pago->payment_method_transaction_id && ($sesion = $this->stripe->sesion($pago))) {
                        $this->stripe->sincronizar($pago, $sesion);
                    }
                } catch (ApiErrorException $error) {
                    return $this->stripe->errorProveedor($error);
                }
            }

            return response()->json(['estado' => $pago->estado]);
        });
    }

    public function webhook(Request $request)
    {
        abort_unless($this->stripe->disponible(), 503);
        try {
            $evento = Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature', ''), config('stripe.webhook_secret'));
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            abort(400, 'Evento de Stripe inválido.');
        }

        if (!in_array($evento->type, [
            'checkout.session.completed',
            'checkout.session.expired',
            'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed',
        ], true)) {
            return response()->noContent();
        }

        $sesion = $evento->data->object;
        if (!$sesion instanceof Session || !$sesion->client_reference_id) {
            return response()->noContent();
        }

        $pago = PagoTransaccion::where('pasarela', 'STRIPE')->where('numero_pago', $sesion->client_reference_id)->first();
        if (!$pago) {
            return response()->noContent();
        }

        $this->stripe->sincronizar($pago, $sesion, $evento->id, $evento->type === 'checkout.session.async_payment_failed');

        return response()->noContent();
    }

    private function pagoDelAlumno(Request $request, int $id): PagoTransaccion
    {
        return PagoTransaccion::where('pasarela', 'STRIPE')->where('id_usuario', $request->user()->id_usuario)->findOrFail($id);
    }

}
