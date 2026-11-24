<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodos', function (Blueprint $table) {
            $table->integer('max_materias_por_caso')->default(5);
            $table->integer('max_casos_carrera')->default(5);
        });

        Schema::table('solicitud_grupo', function (Blueprint $table) {
            $table->string('observacion_resolucion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('periodos', function (Blueprint $table) {
            $table->dropColumn(['max_materias_por_caso', 'max_casos_carrera']);
        });

        Schema::table('solicitud_grupo', function (Blueprint $table) {
            $table->dropColumn(['observacion_resolucion']);
        });
    }
};
