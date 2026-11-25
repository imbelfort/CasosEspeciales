<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class RegistrarBitacoraCpd
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $route = $request->route();
        $recurso = $route->uri();
        $login = $route->named('login');
        $logout = $route->named('auth.logout');

        try {
            $usuario = $login ? $request->attributes->get('bitacora_usuario') : $request->user();
            if (! $login && ! $logout && ! str_starts_with($recurso, 'api/cpd/')
                && ! $usuario?->tieneRol('CPD')) {
                return $response;
            }

            $estado = $response->getStatusCode();
            $evento = match (true) {
                $login && $response->isSuccessful() => 'INICIO_SESION',
                $login && $estado >= 500 => 'ERROR',
                $login => 'INTENTO_FALLIDO',
                $logout && $response->isSuccessful() => 'CIERRE_SESION',
                in_array($estado, [401, 403], true) => 'ACCESO_DENEGADO',
                $estado >= 400 => 'ERROR',
                default => 'ACCESO',
            };

            $email = $login ? $request->input('email') : null;
            $cuentaIntentada = is_string($email) && strlen($email) <= 254
                && filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : null;
            $referencia = $request->attributes->get('bitacora_referencia') ?? $route->parameter('id');

            DB::table('bitacora_cpd')->insert([
                'id_usuario' => $usuario?->id_usuario,
                'cuenta' => $usuario ? strtolower($usuario->email) : $cuentaIntentada,
                'evento' => $evento,
                'ip' => $request->ip(),
                'metodo' => $request->method(),
                'recurso' => '/'.$recurso,
                'referencia' => is_scalar($referencia) && ctype_digit((string) $referencia) ? substr((string) $referencia, 0, 30) : null,
                'estado_http' => $estado,
                'fecha' => now(),
            ]);
        } catch (Throwable $error) {
            report($error);
        }

        return $response;
    }
}
