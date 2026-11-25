<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BitacoraCpdController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(
            $request->user()->estado === 'ACTIVO' && $request->user()->tieneRol('CPD'),
            403,
            'Acceso reservado al personal de CPD.'
        );

        $filtros = $request->validate([
            'evento' => ['nullable', Rule::in(['INICIO_SESION', 'INTENTO_FALLIDO', 'CIERRE_SESION', 'ACCESO', 'ACCESO_DENEGADO', 'ERROR'])],
            'ip' => 'nullable|ip',
            'cuenta' => 'nullable|email|max:254',
            'metodo' => 'nullable|string|max:10',
            'recurso' => 'nullable|string|max:200',
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => 'nullable|date_format:Y-m-d|after_or_equal:desde',
            'page' => 'sometimes|integer|min:1',
            'hasta_id' => 'sometimes|integer|min:0',
        ]);

        $hastaId = $filtros['hasta_id'] ?? (int) DB::table('bitacora_cpd')->max('id');
        $consulta = DB::table('bitacora_cpd')->where('id', '<=', $hastaId);
        foreach (['evento', 'ip', 'cuenta', 'metodo', 'recurso'] as $campo) {
            if (isset($filtros[$campo])) {
                $consulta->where($campo, $campo === 'cuenta' ? strtolower($filtros[$campo]) : $filtros[$campo]);
            }
        }

        if (isset($filtros['desde'])) {
            $consulta->where('fecha', '>=', Carbon::parse($filtros['desde'], 'America/La_Paz')->utc());
        }
        if (isset($filtros['hasta'])) {
            $consulta->where('fecha', '<', Carbon::parse($filtros['hasta'], 'America/La_Paz')->addDay()->utc());
        }

        $recursos = (clone $consulta)->select('metodo', 'recurso')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN estado_http BETWEEN 200 AND 299 THEN 1 ELSE 0 END) AS exitosos')
            ->selectRaw('SUM(CASE WHEN estado_http >= 400 THEN 1 ELSE 0 END) AS fallidos')
            ->selectRaw('COUNT(DISTINCT ip) AS ips')
            ->selectRaw('MAX(fecha) AS ultimo_acceso')
            ->groupBy('metodo', 'recurso')
            ->orderByDesc('total')->orderBy('recurso')->orderBy('metodo')
            ->get()
            ->each(fn ($recurso) => $recurso->ultimo_acceso = Carbon::parse($recurso->ultimo_acceso, 'UTC')->toISOString());

        $eventos = $consulta->orderByDesc('fecha')->orderByDesc('id')->paginate(25)
            ->through(function ($evento) {
                $evento->fecha = Carbon::parse($evento->fecha, 'UTC')->toISOString();

                return $evento;
            });

        return response()->json(['eventos' => $eventos, 'recursos' => $recursos, 'hasta_id' => $hastaId])
            ->header('Cache-Control', 'no-store');
    }
}
