<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoTransaccion extends Model
{
    protected $table = 'pago_transaccion';

    protected $fillable = [
        'id_solicitud',
        'id_usuario',
        'numero_pago',
        'monto',
        'moneda',
        'codigo_moneda',
        'estado',
        'pasarela',
        'pagofacil_transaction_id',
        'payment_method_transaction_id',
        'payment_method_id',
        'qr_base64',
        'checkout_url',
        'deep_link',
        'qr_content_url',
        'universal_url',
        'fecha_expiracion',
        'fecha_pago',
        'metodo_pago_nombre',
        'payload_generacion',
        'payload_callback',
        'payload_consulta',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'payload_generacion' => 'array',
        'payload_callback' => 'array',
        'payload_consulta' => 'array',
        'fecha_expiracion' => 'datetime',
        'fecha_pago' => 'datetime',
    ];

    public function solicitud()
    {
        return $this->belongsTo(Solicitud::class, 'id_solicitud');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
