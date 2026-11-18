<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Materia;
use App\Models\Periodo;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    public function carreras()
    {
        return response()->json(Carrera::all());
    }

    public function periodos()
    {
        return response()->json(Periodo::orderBy('id', 'desc')->get());
    }

    public function materias(Request $request)
    {
        $query = Materia::with(['grupos.horarios', 'carreras']);
        if ($request->has('carrera_id')) {
            $query->whereHas('carreras', function ($q) use ($request) {
                $q->where('carrera.id', $request->carrera_id);
            });
        }
        return response()->json($query->get());
    }
}
