<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reporte extends Model
{
    protected $table = 'reporte';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'tipo_reporte',
        'parametros',
        'fecha_generacion'
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario');
    }
}
