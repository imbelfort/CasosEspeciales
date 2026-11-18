<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estudiante;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * CU1: Registro de Estudiante
     * Exige los 11 campos obligatorios:
     * nombre, apellidos, ci, email, telefono, direccion, fecha_nacimiento, password, registro_universitario, id_carrera, semestre
     */
    public function registrarEstudiante(Request $request)
    {
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

            // Asignar rol ESTUDIANTE
            $rol = Rol::firstOrCreate(['nombre' => 'ESTUDIANTE'], ['descripcion' => 'Rol de estudiante']);
            $usuario->roles()->attach($rol->id, ['estado' => 'ACTIVO']);

            // Crear perfil en tabla estudiantes
            Estudiante::create([
                'id_estudiante' => $usuario->id_usuario,
                'registro_universitario' => $validated['registro_universitario'],
                'carrera_id' => $validated['id_carrera'],
                'semestre_actual' => $validated['semestre'],
            ]);

            $token = $usuario->createToken('auth_token')->plainTextToken;

            return response()->json([
                'mensaje' => 'Estudiante registrado satisfactoriamente',
                'usuario' => $usuario->load(['roles', 'estudiante.carrera']),
                'token' => $token,
            ], 201);
        });
    }

    /**
     * CU1: Login y emisión de token Sanctum
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $usuario = Usuario::whereRaw('LOWER(email) = ?', [strtolower($request->email)])->first();

        if (! $usuario || ! Hash::check($request->password, $usuario->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if ($usuario->estado !== 'ACTIVO') {
            return response()->json(['error' => 'Usuario inactivo o bloqueado.'], 403);
        }

        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'mensaje' => 'Sesión iniciada correctamente',
            'usuario' => $usuario->load(['roles', 'estudiante.carrera', 'personalCarrera', 'directorCarrera']),
            'token' => $token,
        ]);
    }

    /**
     * CU1: Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['mensaje' => 'Sesión finalizada exitosamente']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load(['roles', 'estudiante.carrera', 'personalCarrera', 'directorCarrera']));
    }
}
