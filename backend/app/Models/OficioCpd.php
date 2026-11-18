<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OficioCpd extends Model
{
    protected $table = 'oficio_cpd';
    public $timestamps = false;

    protected $fillable = [
        'numero_oficio',
        'id_solicitud',
        'destinatario',
        'referencia',
        'detalle',
        'id_responsable_remision',
        'fecha_remision',
        'estado',
        'ruta_documento'
    ];

    public function responsable()
    {
        return $this->belongsTo(Usuario::class, 'id_responsable_remision', 'id_usuario');
    }

    public function solicitudes()
    {
        return $this->belongsToMany(Solicitud::class, 'oficio_cpd_solicitud', 'id_oficio', 'id_solicitud');
    }

    public function respuestas()
    {
        return $this->hasMany(OficioRespuestaCpd::class, 'id_oficio');
    }
}
