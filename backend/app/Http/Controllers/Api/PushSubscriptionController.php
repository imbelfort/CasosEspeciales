<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PushSubscriptionController extends Controller
{
    public function configuracion(Request $request)
    {
        $this->autorizarEstudiante($request);

        return response()->json([
            'clavePublica' => config('webpush.private_key') && config('webpush.subject')
                ? config('webpush.public_key')
                : null,
        ]);
    }

    public function guardar(Request $request)
    {
        $token = $this->autorizarEstudiante($request);
        abort_unless(config('webpush.public_key') && config('webpush.private_key') && config('webpush.subject'), 503);

        $datos = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048', 'url:https'],
            'keys.p256dh' => ['required', 'regex:/\A[A-Za-z0-9_-]{87}\z/'],
            'keys.auth' => ['required', 'regex:/\A[A-Za-z0-9_-]{22}\z/'],
        ]);

        // Limitar los destinos a proveedores de Push reconocidos.
        $url = parse_url($datos['endpoint']);
        $host = strtolower($url['host'] ?? '');
        $permitido = in_array($host, [
            'fcm.googleapis.com',
            'updates.push.services.mozilla.com',
            'web.push.apple.com',
        ], true) || preg_match('/\A[a-z0-9-]+\.notify\.windows\.com\z/', $host);

        if (! $permitido || isset($url['user']) || isset($url['pass']) || isset($url['fragment'])
            || ($url['port'] ?? 443) !== 443) {
            throw ValidationException::withMessages(['endpoint' => 'Proveedor de notificaciones no compatible.']);
        }

        DB::table('push_subscriptions')->upsert([[
            'endpoint_hash' => hash('sha256', $datos['endpoint']),
            'token_id' => $token->id,
            'id_usuario' => $request->user()->id_usuario,
            'endpoint' => $datos['endpoint'],
            'public_key' => $datos['keys']['p256dh'],
            'auth_secret' => $datos['keys']['auth'],
        ]], ['endpoint_hash'], ['token_id', 'id_usuario', 'endpoint', 'public_key', 'auth_secret']);

        return response()->noContent();
    }

    public function eliminar(Request $request)
    {
        $token = $this->autorizarEstudiante($request);
        $datos = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        DB::table('push_subscriptions')
            ->where('token_id', $token->id)
            ->where('endpoint_hash', hash('sha256', $datos['endpoint']))
            ->delete();

        return response()->noContent();
    }

    private function autorizarEstudiante(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        abort_unless(isset($token->id) && $request->user()->roles()->where('nombre', 'ESTUDIANTE')->exists(), 403);

        return $token;
    }
}
