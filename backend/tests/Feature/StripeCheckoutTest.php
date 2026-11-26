<?php

namespace Tests\Feature;

use App\Models\PagoTransaccion;
use App\Services\ConfirmacionPagoService;
use App\Services\StripeCheckoutService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Stripe\Checkout\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StripeCheckoutTest extends TestCase
{
    public function test_consulta_confirma_un_pago_aunque_no_haya_llegado_el_webhook(): void
    {
        $pago = $this->pago();
        $confirmacion = Mockery::mock(ConfirmacionPagoService::class);
        $confirmacion->shouldReceive('confirmar')->once()->withArgs(
            fn ($transaccion, $datos, $origen) => $transaccion === $pago
                && $datos['sesion'] === 'cs_test_a'
                && $datos['payment_intent'] === 'pi_test_a'
                && $origen === 'CONSULTA_STRIPE'
        );
        $pago->shouldReceive('refresh')->once()->andReturnSelf();

        (new StripeCheckoutService($confirmacion))->sincronizar($pago, $this->sesion([
            'status' => 'complete',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_a',
        ]));
    }

    public function test_un_intento_abierto_e_impago_permite_reintentar_sin_confirmar_el_arancel(): void
    {
        $pago = $this->pago();
        $pago->shouldReceive('refresh')->once()->andReturnSelf();
        $confirmacion = Mockery::mock(ConfirmacionPagoService::class);
        $confirmacion->shouldNotReceive('confirmar');

        (new StripeCheckoutService($confirmacion))->sincronizar($pago, $this->sesion());

        $this->assertSame('PENDIENTE', $pago->estado);
    }

    public function test_no_confirma_un_importe_distinto_del_arancel(): void
    {
        $confirmacion = Mockery::mock(ConfirmacionPagoService::class);
        $confirmacion->shouldNotReceive('confirmar');

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('La sesión no coincide con el arancel registrado.');

        (new StripeCheckoutService($confirmacion))->sincronizar($this->pago(), $this->sesion([
            'status' => 'complete',
            'payment_status' => 'paid',
            'payment_intent' => 'pi_test_a',
            'amount_total' => 999,
        ]));
    }

    public function test_un_evento_de_expiracion_libera_el_intento_y_conserva_un_pago_confirmado(): void
    {
        Schema::create('pago_transaccion', function (Blueprint $table) {
            $table->id();
            $table->string('numero_pago');
            $table->string('payment_method_transaction_id');
            $table->string('moneda');
            $table->decimal('monto', 10, 2);
            $table->string('estado');
            $table->timestamps();
        });

        $pago = PagoTransaccion::create($this->pago()->getAttributes());
        $confirmacion = Mockery::mock(ConfirmacionPagoService::class);
        $confirmacion->shouldNotReceive('confirmar');
        $stripe = new StripeCheckoutService($confirmacion);
        $sesion = $this->sesion(['status' => 'expired']);

        $stripe->sincronizar($pago, $sesion, 'evt_expirado');
        $this->assertSame('EXPIRADO', $pago->estado);

        $pago->update(['estado' => 'PAGADO']);
        $stripe->sincronizar($pago, $sesion, 'evt_expirado_repetido');
        $this->assertSame('PAGADO', $pago->estado);
    }

    private function pago(): PagoTransaccion
    {
        config(['stripe.secret' => 'sk_test_checkout']);

        return Mockery::mock(PagoTransaccion::class)->makePartial()->forceFill([
            'numero_pago' => 'STRIPE-A',
            'payment_method_transaction_id' => 'cs_test_a',
            'moneda' => 'BOB',
            'monto' => '10.00',
            'estado' => 'PENDIENTE',
        ]);
    }

    private function sesion(array $datos = []): Session
    {
        return Session::constructFrom(array_replace([
            'id' => 'cs_test_a',
            'client_reference_id' => 'STRIPE-A',
            'mode' => 'payment',
            'currency' => 'bob',
            'amount_total' => 1000,
            'livemode' => false,
            'status' => 'open',
            'payment_status' => 'unpaid',
        ], $datos));
    }
}
