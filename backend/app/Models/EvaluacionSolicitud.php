<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluacionSolicitud extends Model
{
    protected $table = 'evaluacion_solicitud';
    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'id_evaluador',
        'decision',
        'justificacion',
        'observaciones',
        'fecha_evaluacion'
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud');
    }

    public function evaluador()
    {
        return $this->belongsTo(Usuario::class, 'id_evaluador', 'id_usuario');
    }
}
