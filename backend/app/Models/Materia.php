<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materia extends Model
{
    protected $table = 'materia';
    public $timestamps = false;

    protected $fillable = [
        'sigla',
        'nombre',
        'nivel_semestre',
        'creditos'
    ];

    public function carreras()
    {
        return $this->belongsToMany(Carrera::class, 'materia_carrera', 'id_materia', 'id_carrera');
    }

    public function grupos()
    {
        return $this->hasMany(Grupo::class, 'id_materia');
    }
}
