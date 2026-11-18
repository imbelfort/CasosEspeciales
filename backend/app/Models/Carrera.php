<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Carrera extends Model
{
    protected $table = 'carrera';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'nombre',
        'facultad'
    ];

    public function estudiantes()
    {
        return $this->hasMany(Estudiante::class, 'carrera_id');
    }

    public function materias()
    {
        return $this->belongsToMany(Materia::class, 'materia_carrera', 'id_carrera', 'id_materia');
    }
}
