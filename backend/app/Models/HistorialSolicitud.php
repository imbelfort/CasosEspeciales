<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialSolicitud extends Model
{
    protected $table = 'historial_solicitud';
    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'id_usuario',
        'estado_anterior',
        'estado_nuevo',
        'accion',
        'observacion',
        'fecha_evento'
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
