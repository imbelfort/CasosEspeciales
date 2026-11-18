<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    protected $table = 'estudiantes';
    protected $primaryKey = 'id_estudiante';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_estudiante',
        'registro_universitario',
        'carrera_id',
        'semestre_actual'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_estudiante', 'id_usuario');
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'id_estudiante', 'id_estudiante');
    }
}
