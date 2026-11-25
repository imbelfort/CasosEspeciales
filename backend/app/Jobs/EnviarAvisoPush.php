<?php

namespace App\Jobs;

use App\Models\Notificacion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

class EnviarAvisoPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $notificacionId)
    {
    }

    public function handle(): void
    {
        $aviso = Notificacion::find($this->notificacionId);
        if (! $aviso || $aviso->fecha_lectura || ! config('webpush.public_key')
            || ! config('webpush.private_key') || ! config('webpush.subject')) {
            return;
        }

        $suscripciones = DB::table('push_subscriptions')->where('id_usuario', $aviso->id_usuario)->get();
        if ($suscripciones->isEmpty()) {
            return;
        }

        $push = new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'),
            'publicKey' => config('webpush.public_key'),
            'privateKey' => config('webpush.private_key'),
        ]], ['TTL' => 86400], 5, ['allow_redirects' => false]);

        // El aviso del sistema puede aparecer en una pantalla bloqueada.
        $mensaje = json_encode(['id' => $aviso->id], JSON_THROW_ON_ERROR);
        $reintentar = false;

        foreach ($suscripciones as $suscripcion) {
            $resultado = $push->sendOneNotification(Subscription::create([
                'endpoint' => $suscripcion->endpoint,
                'publicKey' => $suscripcion->public_key,
                'authToken' => $suscripcion->auth_secret,
                'contentEncoding' => 'aes128gcm',
            ]), $mensaje);

            if ($resultado->isSubscriptionExpired()) {
                DB::table('push_subscriptions')->where('endpoint_hash', $suscripcion->endpoint_hash)
                    ->where('token_id', $suscripcion->token_id)->delete();
            } elseif (! $resultado->isSuccess()) {
                $estado = $resultado->getResponse()?->getStatusCode();
                $reintentar = $reintentar || ! $estado || $estado === 429 || $estado >= 500;
            }
        }

        if ($reintentar) {
            throw new RuntimeException('El proveedor de Web Push no confirmó la entrega.');
        }
    }
}
