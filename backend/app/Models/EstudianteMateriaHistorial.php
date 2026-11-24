<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstudianteMateriaHistorial extends Model
{
    protected $table = 'estudiante_materia_historial';

    protected $fillable = [
        'id_estudiante',
        'id_materia',
        'gestion',
        'estado_materia',
        'nota_final',
        'sigla_grupo',
    ];

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'id_materia');
    }
}
