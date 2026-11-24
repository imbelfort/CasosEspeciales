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
        Schema::table('periodos', function (Blueprint $table) {
            $table->date('fecha_inicio_solicitud')->nullable(); // Ej. 2026-03-23
            $table->date('fecha_fin_solicitud')->nullable();    // Ej. 2026-03-24
            $table->date('fecha_inicio_evaluacion')->nullable(); // Ej. 2026-03-25
            $table->date('fecha_fin_evaluacion')->nullable();    // Ej. 2026-03-26
            $table->date('fecha_inicio_ejecucion')->nullable();  // Ej. 2026-03-27
            $table->date('fecha_fin_ejecucion')->nullable();     // Ej. 2026-03-28
            $table->decimal('costo_arancel_caso', 8, 2)->default(10.00); // 10 Bs por defecto
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('periodos', function (Blueprint $table) {
            $table->dropColumn([
                'fecha_inicio_solicitud',
                'fecha_fin_solicitud',
                'fecha_inicio_evaluacion',
                'fecha_fin_evaluacion',
                'fecha_inicio_ejecucion',
                'fecha_fin_ejecucion',
                'costo_arancel_caso'
            ]);
        });
    }
};
