<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Periodo extends Model
{
    protected $table = 'periodos';
    public $timestamps = false;

    protected $fillable = [
        'gestion',
        'fecha_inicio',
        'fecha_fin',
        'estado'
    ];

    public function grupos()
    {
        return $this->hasMany(Grupo::class, 'id_periodo');
    }

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'id_periodo');
    }
}
