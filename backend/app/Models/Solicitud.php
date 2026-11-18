<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    protected $table = 'solicitud';
    public $timestamps = false;

    protected $fillable = [
        'codigo',
        'id_estudiante',
        'id_periodo',
        'tipo_caso',
        'motivo',
        'estado',
        'fecha_solicitud',
        'fecha_revision',
        'id_revisor',
        'motivo_rechazo',
        'observaciones',
    ];

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class, 'id_estudiante', 'id_estudiante');
    }

    public function periodo()
    {
        return $this->belongsTo(Periodo::class, 'id_periodo');
    }

    public function revisor()
    {
        return $this->belongsTo(Usuario::class, 'id_revisor', 'id_usuario');
    }

    public function grupos()
    {
        return $this->hasMany(SolicitudGrupo::class, 'id_solicitud');
    }

    public function archivos()
    {
        return $this->hasMany(ArchivoSolicitud::class, 'id_solicitud');
    }

    public function evaluacion()
    {
        return $this->hasOne(EvaluacionSolicitud::class, 'id_solicitud');
    }

    public function historial()
    {
        return $this->hasMany(HistorialSolicitud::class, 'id_solicitud');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'id_solicitud');
    }

    public function oficios()
    {
        return $this->belongsToMany(OficioCpd::class, 'oficio_cpd_solicitud', 'id_solicitud', 'id_oficio');
    }
}
