<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $table = 'grupo';
    public $timestamps = false;

    protected $fillable = [
        'id_materia',
        'id_periodo',
        'sigla_grupo',
        'docente',
        'cupo_maximo',
        'cupos_disponibles',
        'cupo_excepcional_maximo',
        'estado'
    ];

    public function materia()
    {
        return $this->belongsTo(Materia::class, 'id_materia');
    }

    public function periodo()
    {
        return $this->belongsTo(Periodo::class, 'id_periodo');
    }

    public function horarios()
    {
        return $this->hasMany(HorarioGrupo::class, 'id_grupo');
    }

    public function bloqueos()
    {
        return $this->hasMany(BloqueoGrupo::class, 'id_grupo');
    }
}
