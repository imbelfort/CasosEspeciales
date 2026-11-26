<?php

namespace App\Services;

use App\Models\ArchivoSolicitud;
use App\Models\PagoTransaccion;
use App\Models\Solicitud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ConfirmacionPagoService
{
    public function confirmar(PagoTransaccion $pago, array $payload, string $origen): void
    {
        DB::transaction(function () use ($pago, $payload, $origen) {
            $solicitud = $pago->id_solicitud
                ? Solicitud::lockForUpdate()->find($pago->id_solicitud)
                : null;
            $pago->refresh();
            if ($pago->estado === 'PAGADO') {
                return;
            }

            $esStripe = $pago->pasarela === 'STRIPE';
            if ($esStripe) {
                $pago->payment_method_transaction_id ??= $payload['sesion'];
            }

            $pago->estado = 'PAGADO';
            $pago->fecha_pago = now();
            $pago->payload_callback = $payload;
            $pago->metodo_pago_nombre = $payload['MetodoPago'] ?? ($payload['paymentMethodName'] ?? 'QR PagoFácil');
            $pago->save();

            if ($solicitud && PagoTransaccion::where('id_solicitud', $solicitud->id)->where('estado', 'PAGADO')
                ->where('id', '<>', $pago->id)->exists()) {
                Log::warning('Se recibió una confirmación adicional para un arancel ya pagado.', [
                    'id_solicitud' => $solicitud->id,
                    'ref' => $pago->numero_pago,
                ]);
            }

            // Si la transacción está ligada a un trámite de solicitud
            if ($solicitud && $solicitud->estado === 'APROBADA_DIRECCION') {
                $nombreProveedor = $esStripe ? 'Stripe' : 'PagoFacil_QR';
                $ruta = "pagos/{$pago->numero_pago}.json";
                if (!Storage::disk('local')->put($ruta, json_encode($payload, JSON_THROW_ON_ERROR))) {
                    throw new \RuntimeException('No se pudo guardar el comprobante del pago.');
                }
                ArchivoSolicitud::create([
                    'id_solicitud' => $solicitud->id,
                    'nombre_archivo' => "Comprobante_{$nombreProveedor}_{$pago->numero_pago}.json",
                    'tipo_archivo' => 'COMPROBANTE_PAGO',
                    'ruta_archivo' => $ruta,
                    'fecha_subida' => now(),
                ]);

                // Transicionar estado a APROBADA (lista para remisión a CPD)
                AuditoriaNotificacionService::registrarTransicion(
                    $solicitud,
                    'APROBADA',
                    $origen,
                    $pago->id_usuario,
                    "Pago de arancel confirmado por {$pago->metodo_pago_nombre} (Ref: {$pago->numero_pago}).",
                    "Solicitud aprobada - {$solicitud->codigo}",
                    'Se confirmó tu pago. Tu solicitud está aprobada y queda pendiente de remisión a CPD.'
                );
            }
        });
    }
}
