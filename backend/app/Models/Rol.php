<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';
    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado'
    ];

    public function usuarios()
    {
        return $this->belongsToMany(Usuario::class, 'usuario_roles', 'id_rol', 'id_usuario');
    }
}
