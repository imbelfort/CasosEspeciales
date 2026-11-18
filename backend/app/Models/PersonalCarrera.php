<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalCarrera extends Model
{
    protected $table = 'personal_carrera';
    protected $primaryKey = 'id_personal';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_personal',
        'cargo',
        'carrera_id',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_personal', 'id_usuario');
    }

    public function carrera()
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }
}
