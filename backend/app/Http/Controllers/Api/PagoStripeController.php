<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PagoTransaccion;
use App\Models\Solicitud;
use App\Services\ConfirmacionPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class PagoStripeController extends Controller
{
    public function configuracion()
    {
        return response()->json(['disponible' => $this->disponible()]);
    }

    public function iniciar(Request $request, int $id)
    {
        abort_unless($this->disponible(), 503, 'El pago con tarjeta no está habilitado.');

        $pago = DB::transaction(function () use ($request, $id) {
            $solicitud = Solicitud::where('id_estudiante', $request->user()->id_usuario)->lockForUpdate()->findOrFail($id);
            abort_unless($solicitud->estado === 'APROBADA_DIRECCION', 422, 'La solicitud no está pendiente de pago.');
            abort_if(PagoTransaccion::where('id_solicitud', $id)->where('estado', 'PAGADO')->exists(), 409, 'Esta solicitud ya tiene un pago confirmado.');

            $pendiente = PagoTransaccion::where('id_solicitud', $id)
                ->where('estado', 'PENDIENTE')
                ->where(function ($query) {
                    $query->where('pasarela', 'STRIPE')->orWhereNull('fecha_expiracion')->orWhere('fecha_expiracion', '>', now());
                })->orderByDesc('id')->first();
            abort_if($pendiente && $pendiente->pasarela !== 'STRIPE', 409, 'Ya tienes un QR vigente para esta solicitud.');
            if ($pendiente) {
                return $pendiente;
            }

            $monto = $solicitud->periodo?->costo_arancel_caso ?? env('PAGOFACIL_COSTO_CASO_ESPECIAL', 10.00);
            abort_unless($monto > 0, 422, 'El arancel de esta solicitud debe ser mayor que cero.');

            return PagoTransaccion::create([
                'id_solicitud' => $id,
                'id_usuario' => $solicitud->id_estudiante,
                'numero_pago' => 'STRIPE-'.Str::uuid(),
                'monto' => $monto,
                'moneda' => 'BOB',
                'estado' => 'PENDIENTE',
                'pasarela' => 'STRIPE',
            ]);
        });

        try {
            $cliente = $this->cliente();
            if ($pago->payment_method_transaction_id) {
                $sesion = $cliente->checkout->sessions->retrieve($pago->payment_method_transaction_id);
                if ($sesion->status === 'expired') {
                    $this->expirar($pago);
                    return response()->json(['message' => 'La sesión venció. Vuelve a pulsar Pagar con tarjeta.'], 409);
                }
            } else {
                $retorno = rtrim(config('stripe.frontend_url'), '/').'/estudiante?pago_tarjeta='.$pago->id;
                $sesion = $cliente->checkout->sessions->create([
                    'mode' => 'payment',
                    'payment_method_types' => ['card'],
                    'adaptive_pricing' => ['enabled' => false],
                    'client_reference_id' => $pago->numero_pago,
                    'success_url' => $retorno.'&resultado=confirmar',
                    'cancel_url' => $retorno.'&resultado=cancelar',
                    'locale' => 'es',
                    'line_items' => [[
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => 'bob',
                            'unit_amount' => $this->centavos($pago),
                            'product_data' => ['name' => 'Arancel de caso especial'],
                        ],
                    ]],
                ], ['idempotency_key' => $pago->numero_pago]);

                $pago->update([
                    'payment_method_transaction_id' => $sesion->id,
                    'fecha_expiracion' => Carbon::createFromTimestamp($sesion->expires_at, config('app.timezone')),
                ]);
            }
        } catch (AuthenticationException|InvalidRequestException $error) {
            // Stripe rechazó los parámetros antes de crear una sesión.
            if (!$pago->payment_method_transaction_id && in_array($error->getHttpStatus(), [400, 401], true)) {
                PagoTransaccion::whereKey($pago->id)->where('estado', 'PENDIENTE')->update(['estado' => 'FALLIDO']);
            }
            return $this->errorProveedor($error);
        } catch (ApiErrorException $error) {
            return $this->errorProveedor($error);
        }

        abort_unless($sesion->status === 'open' && $sesion->url, 409, 'El pago ya fue enviado a Stripe. Espera su confirmación.');

        return response()->json(['url' => $sesion->url]);
    }

    public function estado(Request $request, int $id)
    {
        return response()->json(['estado' => $this->pagoDelAlumno($request, $id)->estado]);
    }

    public function cancelar(Request $request, int $id)
    {
        $pago = $this->pagoDelAlumno($request, $id);
        if ($pago->estado === 'PENDIENTE' && $pago->payment_method_transaction_id && $this->disponible()) {
            try {
                $sesion = $this->cliente()->checkout->sessions->retrieve($pago->payment_method_transaction_id);
                if ($sesion->status === 'open') {
                    $sesion = $this->cliente()->checkout->sessions->expire($sesion->id);
                }
                if ($sesion->status === 'expired') {
                    $this->expirar($pago);
                }
            } catch (ApiErrorException $error) {
                return $this->errorProveedor($error);
            }
        }

        return response()->json(['estado' => $pago->fresh()->estado]);
    }

    public function webhook(Request $request, ConfirmacionPagoService $confirmacion)
    {
        abort_unless($this->disponible(), 503);
        try {
            $evento = Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature', ''), config('stripe.webhook_secret'));
        } catch (SignatureVerificationException|\UnexpectedValueException) {
            abort(400, 'Evento de Stripe inválido.');
        }

        if (!in_array($evento->type, ['checkout.session.completed', 'checkout.session.expired'], true)) {
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

        abort_unless(
            (!$pago->payment_method_transaction_id || $pago->payment_method_transaction_id === $sesion->id)
                && $sesion->mode === 'payment'
                && $sesion->currency === 'bob'
                && $sesion->amount_total === $this->centavos($pago)
                && $evento->livemode === str_starts_with(config('stripe.secret'), 'sk_live_'),
            400,
            'La sesión no coincide con el arancel registrado.'
        );

        if ($evento->type === 'checkout.session.expired') {
            $this->expirar($pago);
        } elseif ($sesion->status === 'complete' && $sesion->payment_status === 'paid' && $sesion->payment_intent) {
            $confirmacion->confirmar($pago, [
                'evento' => $evento->id,
                'sesion' => $sesion->id,
                'payment_intent' => $sesion->payment_intent,
                'amount_total' => $sesion->amount_total,
                'currency' => $sesion->currency,
                'MetodoPago' => 'Tarjeta Stripe',
            ], 'WEBHOOK_STRIPE');
        }

        return response()->noContent();
    }

    private function disponible(): bool
    {
        $clave = (string) config('stripe.secret');

        return filled(config('stripe.webhook_secret')) && (str_starts_with($clave, 'sk_live_')
            || (app()->environment('local', 'testing') && str_starts_with($clave, 'sk_test_')));
    }

    private function cliente(): StripeClient
    {
        return new StripeClient(config('stripe.secret'));
    }

    private function centavos(PagoTransaccion $pago): int
    {
        return (int) str_replace('.', '', $pago->monto);
    }

    private function pagoDelAlumno(Request $request, int $id): PagoTransaccion
    {
        return PagoTransaccion::where('pasarela', 'STRIPE')->where('id_usuario', $request->user()->id_usuario)->findOrFail($id);
    }

    private function expirar(PagoTransaccion $pago): void
    {
        PagoTransaccion::whereKey($pago->id)->where('estado', 'PENDIENTE')->update(['estado' => 'EXPIRADO']);
    }

    private function errorProveedor(ApiErrorException $error)
    {
        Log::warning('No se pudo completar la operación de Stripe', ['codigo' => $error->getStripeCode()]);

        return response()->json(['message' => 'No se pudo conectar con Stripe. Inténtalo de nuevo.'], 502);
    }
}
