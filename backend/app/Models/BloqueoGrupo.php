<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloqueoGrupo extends Model
{
    protected $table = 'bloqueo_grupo';
    public $timestamps = false;

    protected $fillable = [
        'id_grupo',
        'id_solicitud',
        'cantidad',
        'motivo',
        'estado',
        'autorizado_por',
        'fecha_bloqueo',
        'fecha_resolucion',
        'observacion'
    ];

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'id_grupo');
    }

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud');
    }

    public function autorizador()
    {
        return $this->belongsTo(Usuario::class, 'autorizado_por', 'id_usuario');
    }
}
