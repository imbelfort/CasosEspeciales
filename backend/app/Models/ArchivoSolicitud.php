<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivoSolicitud extends Model
{
    protected $table = 'archivo_solicitud';
    public $timestamps = false;

    protected $fillable = [
        'id_solicitud',
        'nombre_archivo',
        'tipo_archivo',
        'ruta_archivo',
        'fecha_subida'
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud');
    }
}
