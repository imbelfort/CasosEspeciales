<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DirectorCarrera;
use App\Models\Estudiante;
use App\Models\PersonalCarrera;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioAdminController extends Controller
{
    /**
     * Verificar que el usuario tenga rol administrativo
     */
    private function autorizarAdmin(Request $request)
    {
        $user = $request->user();
        if (!$user->tieneRol('DIRECTOR') && !$user->tieneRol('PERSONAL_ACADEMICO')) {
            abort(403, 'Acceso no autorizado. Exclusivo para Dirección de Carrera.');
        }
    }

    /**
     * CU1: Listar usuarios del sistema con filtros de rol
     */
    public function listar(Request $request)
    {
        $this->autorizarAdmin($request);

        $query = Usuario::with(['roles', 'estudiante.carrera', 'personalCarrera.carrera', 'directorCarrera.carrera']);

        if ($request->has('rol')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('roles.nombre', $request->rol);
            });
        }

        if ($request->has('buscar')) {
            $b = strtolower($request->buscar);
            $query->where(function ($q) use ($b) {
                $q->whereRaw('LOWER(nombre) LIKE ?', ["%{$b}%"])
                  ->orWhereRaw('LOWER(apellidos) LIKE ?', ["%{$b}%"])
                  ->orWhereRaw('LOWER(ci) LIKE ?', ["%{$b}%"])
                  ->orWhereRaw('LOWER(email) LIKE ?', ["%{$b}%"]);
            });
        }

        return response()->json($query->orderBy('id_usuario', 'desc')->paginate(15));
    }

    /**
     * CU1: Registrar Alumno / Estudiante (Exclusivo Dirección / Administrativo)
     * Exige los 11 campos mandatorios
     */
    public function registrarEstudiante(Request $request)
    {
        $this->autorizarAdmin($request);

        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'ci' => 'required|string|max:30|unique:usuario,ci',
            'email' => 'required|email|max:150|unique:usuario,email',
            'telefono' => 'required|string|max:30',
            'direccion' => 'required|string',
            'fecha_nacimiento' => 'required|date',
            'password' => 'required|string|min:6',
            'registro_universitario' => 'required|string|max:30|unique:estudiantes,registro_universitario',
            'id_carrera' => 'required|integer|exists:carrera,id',
            'semestre' => 'required|integer|between:1,12',
        ]);

        return DB::transaction(function () use ($validated) {
            $usuario = Usuario::create([
                'nombre' => $validated['nombre'],
                'apellidos' => $validated['apellidos'],
                'ci' => $validated['ci'],
                'email' => strtolower($validated['email']),
                'telefono' => $validated['telefono'],
                'direccion' => $validated['direccion'],
                'fecha_nacimiento' => $validated['fecha_nacimiento'],
                'usuario_sistema' => $validated['registro_universitario'],
                'password' => Hash::make($validated['password']),
                'estado' => 'ACTIVO',
            ]);

            $rol = Rol::firstOrCreate(['nombre' => 'ESTUDIANTE'], ['descripcion' => 'Rol de estudiante']);
            $usuario->roles()->attach($rol->id, ['estado' => 'ACTIVO']);

            Estudiante::create([
                'id_estudiante' => $usuario->id_usuario,
                'registro_universitario' => $validated['registro_universitario'],
                'carrera_id' => $validated['id_carrera'],
                'semestre_actual' => $validated['semestre'],
            ]);

            return response()->json([
                'mensaje' => 'Estudiante matriculado y registrado exitosamente en el sistema',
                'usuario' => $usuario->load(['roles', 'estudiante.carrera'])
            ], 201);
        });
    }

    /**
     * CU1: Registrar Personal Académico o Director
     */
    public function registrarPersonal(Request $request)
    {
        $this->autorizarAdmin($request);

        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'ci' => 'required|string|max:30|unique:usuario,ci',
            'email' => 'required|email|max:150|unique:usuario,email',
            'telefono' => 'required|string|max:30',
            'direccion' => 'required|string',
            'fecha_nacimiento' => 'required|date',
            'password' => 'required|string|min:6',
            'rol' => 'required|string|in:DIRECTOR,PERSONAL_ACADEMICO,CPD',
            'id_carrera' => 'nullable|integer|exists:carrera,id',
            'cargo' => 'nullable|string|max:100',
            'gestion_inicio' => 'nullable|date',
            'gestion_fin' => 'nullable|date|after_or_equal:gestion_inicio',
        ]);

        return DB::transaction(function () use ($validated) {
            $usuario = Usuario::create([
                'nombre' => $validated['nombre'],
                'apellidos' => $validated['apellidos'],
                'ci' => $validated['ci'],
                'email' => strtolower($validated['email']),
                'telefono' => $validated['telefono'],
                'direccion' => $validated['direccion'],
                'fecha_nacimiento' => $validated['fecha_nacimiento'],
                'usuario_sistema' => strtolower(explode('@', $validated['email'])[0]),
                'password' => Hash::make($validated['password']),
                'estado' => 'ACTIVO',
            ]);

            $rol = Rol::firstOrCreate(['nombre' => $validated['rol']], ['descripcion' => 'Rol ' . $validated['rol']]);
            $usuario->roles()->attach($rol->id, ['estado' => 'ACTIVO']);

            if ($validated['rol'] === 'DIRECTOR') {
                DirectorCarrera::create([
                    'id_director' => $usuario->id_usuario,
                    'carrera_id' => $validated['id_carrera'] ?? 1,
                    'gestion_inicio' => $validated['gestion_inicio'] ?? now(),
                    'gestion_fin' => $validated['gestion_fin'] ?? now()->addYears(3),
                ]);
            } elseif ($validated['rol'] === 'PERSONAL_ACADEMICO') {
                PersonalCarrera::create([
                    'id_personal' => $usuario->id_usuario,
                    'cargo' => $validated['cargo'] ?? 'Personal Administrativo',
                    'carrera_id' => $validated['id_carrera'] ?? 1,
                ]);
            }

            return response()->json([
                'mensaje' => "Usuario con rol {$validated['rol']} registrado correctamente",
                'usuario' => $usuario->load(['roles', 'personalCarrera', 'directorCarrera'])
            ], 201);
        });
    }

    /**
     * CU1: Cambiar estado (ACTIVO, INACTIVO, BLOQUEADO)
     */
    public function cambiarEstado(Request $request, $id)
    {
        $this->autorizarAdmin($request);

        $validated = $request->validate([
            'estado' => 'required|string|in:ACTIVO,INACTIVO,BLOQUEADO'
        ]);

        $usuario = Usuario::findOrFail($id);
        $usuario->estado = $validated['estado'];
        $usuario->save();

        return response()->json([
            'mensaje' => "Estado de usuario actualizado a {$usuario->estado}",
            'usuario' => $usuario
        ]);
    }
}
