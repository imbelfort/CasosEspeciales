<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorarioGrupo extends Model
{
    protected $table = 'horario_grupo';
    public $timestamps = false;

    protected $fillable = [
        'id_grupo',
        'dia',
        'hora_inicio',
        'hora_fin',
        'aula'
    ];

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'id_grupo');
    }
}
