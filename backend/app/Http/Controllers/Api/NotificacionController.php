<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    /**
     * CU7: Listar notificaciones del usuario autenticado
     */
    public function misNotificaciones(Request $request)
    {
        $user = $request->user();
        $notificaciones = Notificacion::where('id_usuario', $user->id_usuario)
            ->with('solicitud')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($notificaciones);
    }

    /**
     * CU7: Marcar notificación como leída
     */
    public function marcarLeida(Request $request, $id)
    {
        $user = $request->user();
        $notificacion = Notificacion::where('id_usuario', $user->id_usuario)->findOrFail($id);
        Notificacion::whereKey($notificacion->id)->whereNull('fecha_lectura')->update(['fecha_lectura' => now()]);
        $notificacion->refresh();

        return response()->json(['mensaje' => 'Notificación marcada como leída.', 'notificacion' => $notificacion]);
    }
}
