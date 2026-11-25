<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_cpd', function (Blueprint $table) {
            $table->id();
            $table->integer('id_usuario')->nullable();
            $table->string('cuenta', 254)->nullable();
            $table->string('evento', 32);
            $table->ipAddress('ip')->nullable();
            $table->string('metodo', 10);
            $table->string('recurso', 200);
            $table->string('referencia', 30)->nullable();
            $table->unsignedSmallInteger('estado_http');
            $table->timestamp('fecha');

            $table->index(['fecha', 'id']);
            $table->index(['evento', 'fecha']);
            $table->index(['ip', 'fecha']);
            $table->index(['metodo', 'recurso', 'fecha']);
            $table->index(['cuenta', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_cpd');
    }
};
