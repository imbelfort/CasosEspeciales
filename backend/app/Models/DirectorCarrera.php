<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DirectorCarrera extends Model
{
    protected $table = 'director_carrera';
    protected $primaryKey = 'id_director';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_director',
        'carrera_id',
        'gestion_inicio',
        'gestion_fin',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_director', 'id_usuario');
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }
}
