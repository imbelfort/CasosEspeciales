<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pago_transaccion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_solicitud')->nullable();
            $table->unsignedBigInteger('id_usuario');
            $table->string('numero_pago', 60)->unique(); // paymentNumber / PedidoID (e.g. CASO-2026-0001)
            $table->decimal('monto', 10, 2);
            $table->string('moneda', 10)->default('BOB');
            $table->integer('codigo_moneda')->default(2);
            $table->string('estado', 30)->default('PENDIENTE'); // PENDIENTE, PAGADO, EXPIRADO, CANCELADO, FALLIDO
            $table->string('pasarela', 50)->default('PAGOFACIL_QR');
            
            // Datos devueltos por PagoFácil QR
            $table->string('pagofacil_transaction_id', 100)->nullable();
            $table->string('payment_method_transaction_id', 100)->nullable();
            $table->integer('payment_method_id')->nullable();
            $table->text('qr_base64')->nullable();
            $table->string('checkout_url')->nullable();
            $table->string('deep_link')->nullable();
            $table->string('qr_content_url')->nullable();
            $table->string('universal_url')->nullable();
            $table->timestamp('fecha_expiracion')->nullable();
            $table->timestamp('fecha_pago')->nullable();
            $table->text('metodo_pago_nombre')->nullable();
            $table->json('payload_generacion')->nullable();
            $table->json('payload_callback')->nullable();
            $table->json('payload_consulta')->nullable();
            
            $table->timestamps();

            $table->foreign('id_solicitud')->references('id')->on('solicitud')->onDelete('cascade');
            $table->foreign('id_usuario')->references('id_usuario')->on('usuario');
            $table->index(['numero_pago', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pago_transaccion');
    }
};
