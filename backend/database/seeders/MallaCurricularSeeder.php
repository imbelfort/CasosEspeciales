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

                // 4. Crear Grupos académicos en 'grupo' con sus respectivos horarios reales de la UAGRM
                // Grupos típicos FICCT:
                // - SA: Mañana (Lun-Mie-Vie 1h30m, ej: 07:00 a 08:30)
                // - SB: Tarde (Mar-Jue 2h15m, ej: 14:00 a 16:15)
                // - SC: Noche (Lun-Mie-Vie 1h30m, ej: 18:15 a 19:45 o Mar-Jue 18:15 a 20:30)
                $configGrupos = [
                    'SA' => [
                        'turno' => 'Mañana',
                        'dias' => ['LUNES', 'MIERCOLES', 'VIERNES'],
                        'inicio' => '07:00:00',
                        'fin' => '08:30:00',
                        'aula' => '236-' . rand(10, 25),
                        'cupos' => rand(3, 12)
                    ],
                    'SB' => [
                        'turno' => 'Tarde',
                        'dias' => ['MARTES', 'JUEVES'],
                        'inicio' => '14:00:00',
                        'fin' => '16:15:00',
                        'aula' => '236-' . rand(10, 25),
                        'cupos' => 0 // Cupo lleno para probar Caso Especial de Paralelo Lleno / Sobrecupo
                    ],
                    'SC' => [
                        'turno' => 'Noche',
                        'dias' => ['MARTES', 'JUEVES'],
                        'inicio' => '18:15:00',
                        'fin' => '20:30:00',
                        'aula' => '236-' . rand(10, 25),
                        'cupos' => rand(0, 8)
                    ],
                ];

                $gIdx = 0;
                foreach ($configGrupos as $siglaGrupo => $cfg) {
                    $cupoMax = 40;
                    $cuposLibres = $cfg['cupos'];
                    $docenteAsignado = $docentes[($materiaId + $gIdx) % count($docentes)];
                    $gIdx++;

                    $grupoId = DB::table('grupo')
                        ->where('id_materia', $materiaId)
                        ->where('id_periodo', $periodoId)
                        ->where('sigla_grupo', $siglaGrupo)
                        ->value('id');

                    if (!$grupoId) {
                        $grupoId = DB::table('grupo')->insertGetId([
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

                    // Insertar horarios de clases para este grupo
                    foreach ($cfg['dias'] as $dia) {
                        DB::table('horario_grupo')->updateOrInsert(
                            [
                                'id_grupo' => $grupoId,
                                'dia' => $dia,
                            ],
                            [
                                'hora_inicio' => $cfg['inicio'],
                                'hora_fin' => $cfg['fin'],
                                'aula' => $cfg['aula']
                            ]
                        );
                    }
                }
            }
        }
    }
}
