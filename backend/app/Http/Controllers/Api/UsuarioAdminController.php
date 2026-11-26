<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DirectorCarrera;
use App\Models\Estudiante;
use App\Models\PersonalCarrera;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsuarioAdminController extends Controller
{
    /**
     * Verificar que el usuario tenga rol administrativo
     */
    private function autorizarAdmin(Request $request, bool $permitirCpd = false)
    {
        $user = $request->user();
        if (!$user->tieneRol('DIRECTOR') && !$user->tieneRol('PERSONAL_ACADEMICO') && !($permitirCpd && $user->tieneRol('CPD'))) {
            abort(403, 'Tu rol no tiene permiso para realizar esta acción.');
        }
    }

    /**
     * CU1: Listar usuarios del sistema con filtros de rol
     */
    public function listar(Request $request)
    {
        $this->autorizarAdmin($request);

        return response()->json($this->consultaUsuarios($request)->paginate(15));
    }

    public function listarCpd(Request $request)
    {
        abort_unless($request->user()->tieneRol('CPD'), 403);

        return $this->consultaUsuarios($request)
            ->select(['id_usuario', 'nombre', 'apellidos', 'email', 'estado'])
            ->paginate(15)
            ->through(fn (Usuario $usuario) => [
                'id' => $usuario->id_usuario,
                'editable' => (int) $usuario->id_usuario !== (int) $request->user()->id_usuario,
                'nombre' => trim($usuario->nombre.' '.$usuario->apellidos),
                'email' => $usuario->email,
                'estado' => $usuario->estado,
                'roles' => $usuario->roles->pluck('nombre')->values(),
                'registro_universitario' => $usuario->estudiante?->registro_universitario,
                'carrera' => $usuario->estudiante?->carrera?->nombre
                    ?? $usuario->personalCarrera?->carrera?->nombre
                    ?? $usuario->directorCarrera?->carrera?->nombre,
            ]);
    }

    private function autorizarEdicionCpd(Request $request, int $id): void
    {
        $actor = $request->user();
        abort_unless($actor->tieneRol('CPD'), 403);
        abort_if((int) $actor->id_usuario === $id, 403, 'Tu propia cuenta no se puede editar desde CPD.');
    }

    public function consultarEdicionCpd(Request $request, int $id)
    {
        $this->autorizarEdicionCpd($request, $id);
        $usuario = Usuario::with(['estudiante', 'personalCarrera', 'directorCarrera'])->findOrFail($id);

        return response()->json([
            ...$usuario->only(['nombre', 'apellidos', 'ci', 'email', 'telefono', 'direccion', 'fecha_nacimiento']),
            'rol' => match (true) {
                $usuario->estudiante !== null => 'ESTUDIANTE',
                $usuario->directorCarrera !== null => 'DIRECTOR',
                $usuario->personalCarrera !== null => 'PERSONAL_ACADEMICO',
                default => null,
            },
            'id_carrera' => $usuario->estudiante?->carrera_id
                ?? $usuario->directorCarrera?->carrera_id ?? $usuario->personalCarrera?->carrera_id,
            'registro_universitario' => $usuario->estudiante?->registro_universitario,
            'semestre' => $usuario->estudiante?->semestre_actual,
            'cargo' => $usuario->personalCarrera?->cargo,
        ]);
    }

    public function actualizarCpd(Request $request, int $id)
    {
        $this->autorizarEdicionCpd($request, $id);

        return DB::transaction(function () use ($request, $id) {
            $usuario = Usuario::with(['estudiante', 'directorCarrera', 'personalCarrera'])->lockForUpdate()->findOrFail($id);
            $perfil = $usuario->estudiante ?? $usuario->directorCarrera ?? $usuario->personalCarrera;
            $datos = $this->validarDatosUsuario($request, [
                'registro_universitario' => 'prohibited',
                'usuario_sistema' => 'prohibited',
                'rol' => 'prohibited',
                'id_carrera' => $perfil ? 'required|integer|exists:carrera,id' : 'prohibited',
                'semestre' => $perfil instanceof Estudiante ? 'required|integer|between:1,12' : 'prohibited',
                'cargo' => $perfil instanceof PersonalCarrera ? 'nullable|string|max:100' : 'prohibited',
            ], $usuario);

            $usuario->update(array_intersect_key($datos, array_flip(['nombre', 'apellidos', 'ci', 'email', 'telefono', 'direccion', 'fecha_nacimiento'])));
            if ($perfil) {
                $datosPerfil = ['carrera_id' => $datos['id_carrera']];
                if ($perfil instanceof Estudiante) {
                    $datosPerfil['semestre_actual'] = $datos['semestre'];
                } elseif ($perfil instanceof PersonalCarrera) {
                    $datosPerfil['cargo'] = $datos['cargo'] ?? $perfil->cargo;
                }
                $perfil->update($datosPerfil);
            }
            $request->attributes->set('bitacora_referencia', $id);

            return response()->json(['mensaje' => 'Usuario actualizado.']);
        });
    }

    private function consultaUsuarios(Request $request)
    {
        $filtros = $request->validate([
            'rol' => 'nullable|string|max:50',
            'buscar' => 'nullable|string|max:100',
        ]);
        $query = Usuario::with(['roles', 'estudiante.carrera', 'personalCarrera.carrera', 'directorCarrera.carrera']);
        if (!empty($filtros['rol'])) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.nombre', $filtros['rol']));
        }
        foreach (preg_split('/\s+/u', trim($filtros['buscar'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) as $termino) {
            $patron = '%'.strtr(Str::lower($termino), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $query->where(function ($q) use ($patron) {
                $q->whereRaw("LOWER(nombre) LIKE ? ESCAPE '!'", [$patron])
                  ->orWhereRaw("LOWER(apellidos) LIKE ? ESCAPE '!'", [$patron])
                  ->orWhereRaw("LOWER(ci) LIKE ? ESCAPE '!'", [$patron])
                  ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$patron])
                  ->orWhereHas('estudiante', fn ($estudiante) => $estudiante
                      ->whereRaw("LOWER(registro_universitario) LIKE ? ESCAPE '!'", [$patron]));
            });
        }

        return $query->orderByDesc('id_usuario');
    }

    private function validarDatosUsuario(Request $request, array $reglasPerfil, ?Usuario $usuario = null): array
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower($request->input('email'))]);
        }

        return $request->validate([
            'nombre' => 'required|string|max:100',
            'apellidos' => 'required|string|max:100',
            'ci' => ['required', 'string', 'max:30', Rule::unique('usuario', 'ci')->ignore($usuario?->id_usuario, 'id_usuario')],
            'email' => ['bail', 'required', 'email', 'max:150', function ($campo, $correo, $fallar) use ($usuario) {
                if (Usuario::whereRaw('LOWER(email) = ?', [$correo])->when($usuario, fn ($query) => $query->whereKeyNot($usuario->id_usuario))->exists()) {
                    $fallar('Ya existe una cuenta con ese correo electrónico.');
                }
            }],
            'telefono' => [$usuario ? 'nullable' : 'required', 'string', 'max:30', 'regex:/\A[0-9]+\z/'],
            'direccion' => [$usuario ? 'nullable' : 'required', 'string', 'max:500'],
            'fecha_nacimiento' => [
                $usuario ? 'nullable' : 'required', 'date_format:Y-m-d', 'before_or_equal:'.Carbon::today('America/La_Paz')->toDateString(),
            ],
            'password' => $usuario ? 'prohibited' : 'required|string|min:6',
            ...$reglasPerfil,
        ], [
            'required' => 'Completa el campo :attribute.',
            'unique' => 'Ya existe una cuenta con ese :attribute.',
            'telefono.regex' => 'El teléfono debe contener solo números.',
            'fecha_nacimiento.date_format' => 'Ingresa una fecha de nacimiento válida.',
            'fecha_nacimiento.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'semestre.between' => 'El semestre debe estar entre 1 y 12.',
            'registro_universitario.prohibited' => 'El registro universitario es único y no se puede cambiar.',
        ], [
            'ci' => 'carnet de identidad',
            'email' => 'correo electrónico',
            'telefono' => 'teléfono',
            'registro_universitario' => 'registro universitario',
            'id_carrera' => 'carrera',
            'fecha_nacimiento' => 'fecha de nacimiento',
            'password' => 'contraseña',
        ]);
    }

    /**
     * CU1: Registrar Alumno / Estudiante
     * Exige los 11 campos mandatorios
     */
    public function registrarEstudiante(Request $request)
    {
        $this->autorizarAdmin($request, permitirCpd: true);

        $validated = $this->validarDatosUsuario($request, [
            'registro_universitario' => 'required|string|max:30|unique:estudiantes,registro_universitario|unique:usuario,usuario_sistema',
            'id_carrera' => 'required|integer|exists:carrera,id',
            'semestre' => 'required|integer|between:1,12',
        ]);

        return DB::transaction(function () use ($request, $validated) {
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

            $request->attributes->set('bitacora_referencia', $usuario->id_usuario);

            return response()->json([
                'mensaje' => 'Estudiante matriculado y registrado exitosamente en el sistema',
                'usuario' => $usuario->load(['roles', 'estudiante.carrera'])
            ], 201);
        });
    }

    /**
     * CU1: Registrar personal de Dirección, Personal Académico o CPD
     */
    public function registrarPersonal(Request $request)
    {
        $this->autorizarAdmin($request, permitirCpd: true);

        $validated = $this->validarDatosUsuario($request, [
            'rol' => 'required|string|in:DIRECTOR,PERSONAL_ACADEMICO,CPD',
            'id_carrera' => 'required_if:rol,DIRECTOR,PERSONAL_ACADEMICO|nullable|integer|exists:carrera,id',
            'cargo' => 'nullable|string|max:100',
            'gestion_inicio' => 'nullable|date',
            'gestion_fin' => 'nullable|date|after_or_equal:gestion_inicio',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            $alias = Str::limit(Str::before($validated['email'], '@'), 70, '').'-'.Str::ulid();

            $usuario = Usuario::create([
                'nombre' => $validated['nombre'],
                'apellidos' => $validated['apellidos'],
                'ci' => $validated['ci'],
                'email' => strtolower($validated['email']),
                'telefono' => $validated['telefono'],
                'direccion' => $validated['direccion'],
                'fecha_nacimiento' => $validated['fecha_nacimiento'],
                'usuario_sistema' => $alias,
                'password' => Hash::make($validated['password']),
                'estado' => 'ACTIVO',
            ]);

            $rol = Rol::firstOrCreate(['nombre' => $validated['rol']], ['descripcion' => 'Rol ' . $validated['rol']]);
            $usuario->roles()->attach($rol->id, ['estado' => 'ACTIVO']);

            if ($validated['rol'] === 'DIRECTOR') {
                DirectorCarrera::create([
                    'id_director' => $usuario->id_usuario,
                    'carrera_id' => $validated['id_carrera'],
                    'gestion_inicio' => $validated['gestion_inicio'] ?? now(),
                    'gestion_fin' => $validated['gestion_fin'] ?? now()->addYears(3),
                ]);
            } elseif ($validated['rol'] === 'PERSONAL_ACADEMICO') {
                PersonalCarrera::create([
                    'id_personal' => $usuario->id_usuario,
                    'cargo' => $validated['cargo'] ?? 'Personal Administrativo',
                    'carrera_id' => $validated['id_carrera'],
                ]);
            }

            $request->attributes->set('bitacora_referencia', $usuario->id_usuario);

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
