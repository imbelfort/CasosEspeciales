<?php

namespace App\Services;

use App\Models\PagoTransaccion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function __construct(private ConfirmacionPagoService $confirmacion) {}

    // Los llamadores mantienen bloqueada la solicitud mientras operan sobre la sesión.
    public function sesion(PagoTransaccion $pago): ?Session
    {
        $cliente = $this->cliente();
        if ($pago->payment_method_transaction_id) {
            return $cliente->checkout->sessions->retrieve($pago->payment_method_transaction_id);
        }

        // Stripe puede eliminar la clave de idempotencia. Busca la sesión anterior
        // por referencia antes de liberar una reserva que quedó sin respuesta.
        if ($pago->created_at->lte(now()->subHours(23))) {
            $sesiones = $cliente->checkout->sessions->all([
                'created' => [
                    'gte' => $pago->created_at->copy()->subMinutes(5)->timestamp,
                    'lte' => $pago->created_at->copy()->addHours(24)->timestamp,
                ],
                'limit' => 100,
            ]);
            foreach ($sesiones->autoPagingIterator() as $sesion) {
                if ($sesion->client_reference_id === $pago->numero_pago) {
                    return $this->guardarSesion($pago, $sesion);
                }
            }

            $pago->update(['estado' => 'EXPIRADO']);
            return null;
        }

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

        return $this->guardarSesion($pago, $sesion);
    }

    private function guardarSesion(PagoTransaccion $pago, Session $sesion): Session
    {
        $pago->update([
            'payment_method_transaction_id' => $sesion->id,
            'fecha_expiracion' => Carbon::createFromTimestamp($sesion->expires_at, config('app.timezone')),
        ]);

        return $sesion;
    }

    public function sincronizar(PagoTransaccion $pago, Session $sesion, ?string $evento = null, bool $falloDefinitivo = false): void
    {
        abort_unless(
            $sesion->client_reference_id === $pago->numero_pago
                && (!$pago->payment_method_transaction_id || $pago->payment_method_transaction_id === $sesion->id)
                && $sesion->mode === 'payment'
                && $sesion->currency === strtolower($pago->moneda)
                && $sesion->amount_total === $this->centavos($pago)
                && $sesion->livemode === str_starts_with(config('stripe.secret'), 'sk_live_'),
            400,
            'La sesión no coincide con el arancel registrado.'
        );

        if ($sesion->status === 'complete' && $sesion->payment_status === 'paid' && $sesion->payment_intent) {
            $this->confirmacion->confirmar($pago, [
                'evento' => $evento,
                'sesion' => $sesion->id,
                'payment_intent' => $sesion->payment_intent,
                'amount_total' => $sesion->amount_total,
                'currency' => $sesion->currency,
                'MetodoPago' => 'Tarjeta Stripe',
            ], $evento ? 'WEBHOOK_STRIPE' : 'CONSULTA_STRIPE');
        } elseif ($sesion->status === 'expired' || ($falloDefinitivo && $sesion->status === 'complete' && $sesion->payment_status === 'unpaid')) {
            PagoTransaccion::whereKey($pago->id)->where('estado', 'PENDIENTE')->update([
                'estado' => $sesion->status === 'expired' ? 'EXPIRADO' : 'FALLIDO',
            ]);
        }

        $pago->refresh();
    }

    public function cerrar(PagoTransaccion $pago): void
    {
        $sesion = $this->sesion($pago);
        if (!$sesion) {
            return;
        }
        if ($sesion->status === 'open') {
            try {
                $sesion = $this->cliente()->checkout->sessions->expire($sesion->id);
            } catch (InvalidRequestException) {
                // La sesión pudo completarse o expirar durante el cierre.
                $sesion = $this->sesion($pago);
            }
        }

        if ($sesion) {
            $this->sincronizar($pago, $sesion);
        }
    }

    public function cerrarPendiente(int $idSolicitud): ?PagoTransaccion
    {
        $pago = PagoTransaccion::where('id_solicitud', $idSolicitud)
            ->where('pasarela', 'STRIPE')->where('estado', 'PENDIENTE')->first();
        if ($pago) {
            $this->cerrar($pago);
        }

        return $pago;
    }

    public function errorProveedor(ApiErrorException $error)
    {
        Log::warning('No se pudo completar la operación de Stripe', ['codigo' => $error->getStripeCode()]);

        return response()->json(['message' => 'No se pudo verificar el intento con Stripe. Vuelve a intentarlo en unos minutos.'], 502);
    }

    public function disponible(): bool
    {
        $clave = (string) config('stripe.secret');

        return filled(config('stripe.webhook_secret')) && (str_starts_with($clave, 'sk_live_')
            || (app()->environment('local', 'testing') && str_starts_with($clave, 'sk_test_')));
    }

    private function cliente(): StripeClient
    {
        $clave = (string) config('stripe.secret');
        abort_unless($clave !== '', 503, 'Stripe no está configurado. El intento anterior sigue pendiente de verificación.');

        return new StripeClient($clave);
    }

    private function centavos(PagoTransaccion $pago): int
    {
        return (int) str_replace('.', '', $pago->monto);
    }

}
