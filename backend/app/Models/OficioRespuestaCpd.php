<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OficioRespuestaCpd extends Model
{
    protected $table = 'oficio_respuesta_cpd';
    public $timestamps = false;

    protected $fillable = [
        'id_oficio',
        'id_solicitud',
        'codigo_respuesta',
        'resultado',
        'observaciones',
        'referencia_respaldo',
        'fecha_respuesta',
        'fecha_habilitacion_efectiva',
        'id_responsable_cpd',
        'estado'
    ];

    public function oficio()
    {
        return $this->belongsTo(OficioCpd::class, 'id_oficio');
    }

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud');
    }

    public function responsableCpd()
    {
        return $this->belongsTo(Usuario::class, 'id_responsable_cpd', 'id_usuario');
    }
}
