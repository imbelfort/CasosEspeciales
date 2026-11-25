<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reporte;
use App\Models\Solicitud;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    /**
     * CU8: Reportes y Estadísticas
     * - Reporte General: Total, por estados clave.
     * - Reporte por Estado: Agrupado con cálculo porcentual dinámico ROUND(COUNT(*) * 100.0 / total, 2).
     * - Reporte por Período: Desglose por gestión académica y solicitudes.
     * - Reporte de Cupos: Desglose por materia, grupo, cupo máx, disponible y reservado.
     * Auditoría: Registra en reporte (id_usuario, tipo_reporte, parametros).
     */
    public function reporteGeneral(Request $request)
    {
        $user = $this->usuarioReportes($request);

        $total = Solicitud::count();
        $pendientesEvaluacion = Solicitud::where('estado', 'ENVIADA')->count();
        $aprobadasDireccion = Solicitud::where('estado', 'APROBADA_DIRECCION')->count();
        $rechazadasDireccion = Solicitud::where('estado', 'RECHAZADA_DIRECCION')->count();
        $aprobadasPago = Solicitud::where('estado', 'APROBADA')->count();
        $remitidasCpd = Solicitud::where('estado', 'REMITIDA_A_CPD')->count();
        $habilitadasCpd = Solicitud::where('estado', 'HABILITADA')->count();
        $observadas = Solicitud::whereIn('estado', ['OBSERVADA', 'OBSERVADA_CPD'])->count();

        // Auditoría CU8
        Reporte::create([
            'id_usuario' => $user->id_usuario,
            'tipo_reporte' => 'GENERAL',
            'parametros' => json_encode($request->all()),
            'fecha_generacion' => now()
        ]);

        return response()->json([
            'total_solicitudes' => $total,
            'pendientes_evaluacion' => $pendientesEvaluacion,
            'aprobadas_direccion' => $aprobadasDireccion,
            'rechazadas_direccion' => $rechazadasDireccion,
            'aprobadas_con_pago' => $aprobadasPago,
            'remitidas_cpd' => $remitidasCpd,
            'habilitadas_cpd' => $habilitadasCpd,
            'observadas' => $observadas,
        ]);
    }

    public function reportePorEstado(Request $request)
    {
        $user = $this->usuarioReportes($request);

        $conteos = DB::table('solicitud')
            ->select('estado', DB::raw('COUNT(*) as total_estado'))
            ->groupBy('estado')
            ->get();
        $total = $conteos->sum('total_estado');
        $estados = $conteos
            ->map(function ($item) use ($total) {
                $porcentaje = $total > 0 ? round(($item->total_estado * 100.0) / $total, 2) : 0;
                return [
                    'estado' => $item->estado,
                    'cantidad' => $item->total_estado,
                    'porcentaje' => $porcentaje
                ];
            });

        Reporte::create([
            'id_usuario' => $user->id_usuario,
            'tipo_reporte' => 'POR_ESTADO',
            'parametros' => json_encode($request->all()),
            'fecha_generacion' => now()
        ]);

        return response()->json([
            'total_general' => $total,
            'estados' => $estados
        ]);
    }

    public function reporteCupos(Request $request)
    {
        $user = $this->usuarioReportes($request);

        $cupos = DB::table('grupo as g')
            ->join('materia as m', 'g.id_materia', '=', 'm.id')
            ->join('periodos as p', 'g.id_periodo', '=', 'p.id')
            ->leftJoin('bloqueo_grupo as b', function ($join) {
                $join->on('g.id', '=', 'b.id_grupo')
                     ->where('b.estado', '=', 'RESERVADO');
            })
            ->select(
                'g.id as id_grupo',
                'p.gestion',
                'm.sigla as materia_sigla',
                'm.nombre as materia_nombre',
                'g.sigla_grupo',
                'g.cupo_maximo',
                'g.cupos_disponibles',
                DB::raw('COALESCE(SUM(b.cantidad), 0) as cupos_reservados')
            )
            ->groupBy('g.id', 'p.gestion', 'm.sigla', 'm.nombre', 'g.sigla_grupo', 'g.cupo_maximo', 'g.cupos_disponibles')
            ->get();

        Reporte::create([
            'id_usuario' => $user->id_usuario,
            'tipo_reporte' => 'CUPOS_GRUPOS',
            'parametros' => json_encode($request->all()),
            'fecha_generacion' => now()
        ]);

        return response()->json($cupos);
    }

    private function usuarioReportes(Request $request): Usuario
    {
        $usuario = $request->user();

        abort_unless(
            $usuario->estado === 'ACTIVO'
                && $usuario->roles->pluck('nombre')->intersect(['CPD', 'DIRECTOR', 'PERSONAL_ACADEMICO'])->isNotEmpty(),
            403,
            'Acceso restringido a Reportes.'
        );

        return $usuario;
    }
}
