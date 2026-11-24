<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudGrupo extends Model
{
    protected $table = 'solicitud_grupo';
    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'id_grupo',
        'motivo',
        'estado',
        'observacion_resolucion'
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud');
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'id_grupo');
    }
}
