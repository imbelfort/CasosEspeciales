<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false; // Maneja fecha_creacion / fecha_modificacion manualmente o por trigger

    protected $fillable = [
        'nombre',
        'apellidos',
        'ci',
        'email',
        'telefono',
        'direccion',
        'foto_perfil',
        'usuario_sistema',
        'password',
        'estado',
        'fecha_nacimiento',
    ];

    protected $hidden = [
        'password',
    ];

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'usuario_roles', 'id_usuario', 'id_rol')
                    ->wherePivot('estado', 'ACTIVO')
                    ->withPivot('fecha_asignacion', 'estado');
    }

    public function sesiones()
    {
        return $this->hasMany(SesionUsuario::class, 'id_usuario');
    }

    public function estudiante()
    {
        return $this->hasOne(Estudiante::class, 'id_estudiante', 'id_usuario');
    }

    public function personalCarrera()
    {
        return $this->hasOne(PersonalCarrera::class, 'id_personal', 'id_usuario');
    }

    public function directorCarrera()
    {
        return $this->hasOne(DirectorCarrera::class, 'id_director', 'id_usuario');
    }

    public function tieneRol(string $nombreRol): bool
    {
        return $this->roles->contains('nombre', $nombreRol);
    }
}
