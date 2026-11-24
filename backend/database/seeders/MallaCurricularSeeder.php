<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MallaCurricularSeeder extends Seeder
{
    /**
     * Mapeo de archivos CSV a las carreras de la FICCT:
     * - 187-6: Ingeniería Informática
     * - 187-4: Ingeniería en Sistemas
     * - 187-5: Ingeniería en Redes y Telecomunicaciones
     * - 323-0: Ingeniería Robótica
     */
    protected array $mallas = [
        [
            'codigo' => '187-6',
            'nombre' => 'Ingeniería Informática',
            'facultad' => 'Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones',
            'archivo' => 'malla_curricular_ingenieria_informatica_187-6.csv'
        ],
        [
            'codigo' => '187-4',
            'nombre' => 'Ingeniería en Sistemas',
            'facultad' => 'Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones',
            'archivo' => 'malla_curricular_ingenieria_sistemas_187-4.csv'
        ],
        [
            'codigo' => '187-5',
            'nombre' => 'Ingeniería en Redes y Telecomunicaciones',
            'facultad' => 'Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones',
            'archivo' => 'malla_curricular_redes_telecomunicaciones_187-5.csv'
        ],
        [
            'codigo' => '323-0',
            'nombre' => 'Ingeniería Robótica',
            'facultad' => 'Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones',
            'archivo' => 'malla_curricular_ingenieria_robotica_323-0.csv'
        ],
    ];

    public function run(): void
    {
        $directorioBase = base_path('../malla_curricular');
        if (!File::exists($directorioBase)) {
            // Alternativa por si se ejecuta desde otra ruta
            $directorioBase = base_path('malla_curricular');
        }

        // Obtener el período académico activo para asociar los grupos
        $periodoId = DB::table('periodos')->where('estado', 'ACTIVO')->value('id') ?? 1;

        $docentes = [
            'Ing. Evans Balcázar',
            'Ing. Alberto Mollo',
            'Ing. Gino Barroso',
            'Ing. Katime Rosa',
            'Lic. Juan Carlos Contreras',
            'Ing. Rolando Martínez',
            'Ing. Ciro Román',
            'Ing. Mario López'
        ];

        $paralelos = ['SA', 'SB', 'SC', 'Z1', 'Z2'];

        foreach ($this->mallas as $carreraInfo) {
            // 1. Obtener o registrar la Carrera
            $carrera = DB::table('carrera')->where('codigo', $carreraInfo['codigo'])->first();
            if (!$carrera) {
                $carreraId = DB::table('carrera')->insertGetId([
                    'codigo' => $carreraInfo['codigo'],
                    'nombre' => $carreraInfo['nombre'],
                    'facultad' => $carreraInfo['facultad']
                ]);
            } else {
                $carreraId = $carrera->id;
            }
            $rutaCsv = $directorioBase . DIRECTORY_SEPARATOR . $carreraInfo['archivo'];

            if (!File::exists($rutaCsv)) {
                $this->command?->warn("No se encontró el archivo: {$rutaCsv}");
                continue;
            }

            $filas = array_map('str_getcsv', file($rutaCsv));
            $header = array_shift($filas); // Quitar fila de cabecera: Semestre,Sigla,Materia,Tipo,Prerrequisitos

            foreach ($filas as $fila) {
                if (empty($fila) || count($fila) < 3) continue;

                $semestre = (int) trim($fila[0] ?? '1');
                $sigla = strtoupper(trim($fila[1] ?? ''));
                $nombreMateria = trim($fila[2] ?? '');

                if (empty($sigla) || empty($nombreMateria)) continue;

                // 2. Insertar o actualizar la Materia (Esquema físico exacto de tabla 'materia')
                DB::table('materia')->updateOrInsert(
                    ['sigla' => $sigla],
                    [
                        'nombre' => $nombreMateria,
                        'nivel_semestre' => ($semestre >= 1 && $semestre <= 12) ? $semestre : 1,
                        'creditos' => 4
                    ]
                );

                $materiaId = DB::table('materia')->where('sigla', $sigla)->value('id');

                // 3. Vincular Materia con su Carrera en 'materia_carrera'
                DB::table('materia_carrera')->updateOrInsert(
                    [
                        'id_materia' => $materiaId,
                        'id_carrera' => $carreraId
                    ]
                );

                // 4. Crear Grupos académicos en 'grupo' para que los estudiantes puedan solicitarlos en Casos Especiales
                // (Creamos al menos 2 grupos: SA con cupo disponible y SB lleno/sobrecupo)
                foreach (['SA', 'SB'] as $idx => $siglaGrupo) {
                    $cupoMax = 40;
                    // El grupo SB se crea con 0 cupos libres para probar casos de PARALELO_LLENO / SOBRECUPO
                    $cuposLibres = ($siglaGrupo === 'SB') ? 0 : rand(3, 15);
                    $docenteAsignado = $docentes[($materiaId + $idx) % count($docentes)];

                    $existeGrupo = DB::table('grupo')
                        ->where('id_materia', $materiaId)
                        ->where('id_periodo', $periodoId)
                        ->where('sigla_grupo', $siglaGrupo)
                        ->exists();

                    if (!$existeGrupo) {
                        DB::table('grupo')->insert([
                            'id_materia' => $materiaId,
                            'id_periodo' => $periodoId,
                            'sigla_grupo' => $siglaGrupo,
                            'docente' => $docenteAsignado,
                            'cupo_maximo' => $cupoMax,
                            'cupos_disponibles' => $cuposLibres,
                            'cupo_excepcional_maximo' => 5,
                            'estado' => 'ABIERTO'
                        ]);
                    }
                }
            }
        }
    }
}
