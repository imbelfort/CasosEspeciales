<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'id_solicitud',
        'destinatario',
        'tipo',
        'asunto',
        'mensaje',
        'canal',
        'estado',
        'intentos',
        'ultimo_error',
        'fecha_creacion',
        'fecha_envio',
        'fecha_lectura'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud');
    }
}
