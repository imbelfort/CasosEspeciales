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
        'estado',
        'fecha_inicio_solicitud',
        'fecha_fin_solicitud',
        'fecha_inicio_evaluacion',
        'fecha_fin_evaluacion',
        'fecha_inicio_ejecucion',
        'fecha_fin_ejecucion',
        'costo_arancel_caso',
        'max_materias_por_caso',
        'max_casos_carrera',
    ];

    protected $casts = [
        'costo_arancel_caso' => 'decimal:2',
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
