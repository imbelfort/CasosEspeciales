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
        Schema::create('estudiante_materia_historial', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_estudiante'); // fk a estudiantes.id_estudiante
            $table->unsignedBigInteger('id_materia');    // fk a materia.id
            $table->string('gestion', 20);              // ej. 1-2024, 2-2024, 1-2025
            $table->string('estado_materia', 30);       // 'APROBADA', 'CURSANDO', 'REPROBADA', 'RETIRADA'
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->string('sigla_grupo', 10)->nullable();
            $table->timestamps();

            $table->foreign('id_estudiante')->references('id_estudiante')->on('estudiantes')->onDelete('cascade');
            $table->foreign('id_materia')->references('id')->on('materia')->onDelete('cascade');
            $table->unique(['id_estudiante', 'id_materia', 'gestion'], 'uk_est_mat_gestion');
            $table->index(['id_estudiante', 'estado_materia']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estudiante_materia_historial');
    }
};
