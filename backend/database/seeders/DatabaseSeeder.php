<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with initial academic and user test data.
     */
    public function run(): void
    {
        // 1. Roles del Sistema según Sección 4 de base_datos_actual.md
        $roles = [
            ['id' => 1, 'nombre' => 'ESTUDIANTE', 'descripcion' => 'Estudiante regular de la FICCT', 'estado' => 'ACTIVO'],
            ['id' => 2, 'nombre' => 'DIRECTOR', 'descripcion' => 'Director de Carrera', 'estado' => 'ACTIVO'],
            ['id' => 3, 'nombre' => 'PERSONAL_ACADEMICO', 'descripcion' => 'Personal Administrativo Académico', 'estado' => 'ACTIVO'],
            ['id' => 4, 'nombre' => 'CPD', 'descripcion' => 'Centro de Procesamiento de Datos', 'estado' => 'ACTIVO'],
        ];

        foreach ($roles as $r) {
            DB::table('roles')->updateOrInsert(['id' => $r['id']], $r);
        }

        // 2. Carreras de la FICCT (Sincronizadas con las 4 mallas curriculares)
        $carreras = [
            ['id' => 1, 'codigo' => '187-6', 'nombre' => 'Ingeniería Informática', 'facultad' => 'Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones'],
            ['id' => 2, 'codigo' => '187-4', 'nombre' => 'Ingeniería en Sistemas', 'facultad' => 'Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones'],
            ['id' => 3, 'codigo' => '187-5', 'nombre' => 'Ingeniería en Redes y Telecomunicaciones', 'facultad' => 'Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones'],
            ['id' => 4, 'codigo' => '323-0', 'nombre' => 'Ingeniería Robótica', 'facultad' => 'Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones'],
        ];

        foreach ($carreras as $c) {
            DB::table('carrera')->updateOrInsert(['id' => $c['id']], $c);
        }

        // 3. Período Académico Activo y Cronograma de Casos Especiales (23 al 28 de marzo)
        DB::table('periodos')->updateOrInsert(
            ['gestion' => '1-2026'],
            [
                'fecha_inicio' => '2026-03-23',
                'fecha_fin' => '2026-07-31',
                'estado' => 'ACTIVO',
                'fecha_inicio_solicitud' => '2026-03-23',
                'fecha_fin_solicitud' => '2026-03-24',
                'fecha_inicio_evaluacion' => '2026-03-25',
                'fecha_fin_evaluacion' => '2026-03-26',
                'fecha_inicio_ejecucion' => '2026-03-27',
                'fecha_fin_ejecucion' => '2026-03-28',
                'costo_arancel_caso' => 10.00,
            ]
        );

        $periodoId = DB::table('periodos')->where('gestion', '1-2026')->value('id');

        // 4. Poblar Mallas Curriculares Oficiales de la FICCT desde los archivos CSV
        $this->call(MallaCurricularSeeder::class);

        // 6. Horarios de los grupos
        $horarios = [
            ['id_grupo' => 1, 'dia' => 'LUNES', 'hora_inicio' => '07:00:00', 'hora_fin' => '09:15:00', 'aula' => '236-12'],
            ['id_grupo' => 1, 'dia' => 'MIERCOLES', 'hora_inicio' => '07:00:00', 'hora_fin' => '09:15:00', 'aula' => '236-12'],
            ['id_grupo' => 2, 'dia' => 'MARTES', 'hora_inicio' => '09:15:00', 'hora_fin' => '11:30:00', 'aula' => '236-14'],
            ['id_grupo' => 2, 'dia' => 'JUEVES', 'hora_inicio' => '09:15:00', 'hora_fin' => '11:30:00', 'aula' => '236-14'],
        ];

        foreach ($horarios as $h) {
            DB::table('horario_grupo')->updateOrInsert(
                ['id_grupo' => $h['id_grupo'], 'dia' => $h['dia'], 'hora_inicio' => $h['hora_inicio']],
                $h
            );
        }

        // 7. Cuentas Administrativas y Personal
        $passwordHash = Hash::make('password123');

        // A) Director de Carrera
        DB::table('usuario')->updateOrInsert(
            ['email' => 'director@ficct.uagrm.edu.bo'],
            [
                'nombre' => 'Alberto',
                'apellidos' => 'Mendoza Vaca',
                'ci' => '3920192',
                'telefono' => '71029384',
                'direccion' => 'Av. Bush Campus Universitario',
                'usuario_sistema' => 'director_inf',
                'password' => $passwordHash,
                'estado' => 'ACTIVO',
                'fecha_nacimiento' => '1975-04-12',
            ]
        );
        $idDir = DB::table('usuario')->where('email', 'director@ficct.uagrm.edu.bo')->value('id_usuario');
        DB::table('usuario_roles')->updateOrInsert(['id_usuario' => $idDir, 'id_rol' => 2], ['estado' => 'ACTIVO']);
        DB::table('director_carrera')->updateOrInsert(
            ['id_director' => $idDir],
            ['carrera_id' => 1, 'gestion_inicio' => '2024-01-01', 'gestion_fin' => '2027-12-31']
        );

        // B) Personal Académico
        DB::table('usuario')->updateOrInsert(
            ['email' => 'academico@ficct.uagrm.edu.bo'],
            [
                'nombre' => 'Mariana',
                'apellidos' => 'Roca Justiniano',
                'ci' => '4839201',
                'telefono' => '78493021',
                'direccion' => 'Barrio Sirari Calle 2',
                'usuario_sistema' => 'acad_sec',
                'password' => $passwordHash,
                'estado' => 'ACTIVO',
                'fecha_nacimiento' => '1985-08-20',
            ]
        );
        $idAcad = DB::table('usuario')->where('email', 'academico@ficct.uagrm.edu.bo')->value('id_usuario');
        DB::table('usuario_roles')->updateOrInsert(['id_usuario' => $idAcad, 'id_rol' => 3], ['estado' => 'ACTIVO']);
        DB::table('personal_carrera')->updateOrInsert(
            ['id_personal' => $idAcad],
            ['cargo' => 'Jefe de Asuntos Estudiantiles', 'carrera_id' => 1]
        );

        // C) CPD (Centro de Procesamiento de Datos)
        DB::table('usuario')->updateOrInsert(
            ['email' => 'cpd@uagrm.edu.bo'],
            [
                'nombre' => 'Carlos',
                'apellidos' => 'Torrez Medina',
                'ci' => '5930291',
                'telefono' => '70098231',
                'direccion' => 'Edificio Central CPD UAGRM',
                'usuario_sistema' => 'cpd_operador',
                'password' => $passwordHash,
                'estado' => 'ACTIVO',
                'fecha_nacimiento' => '1988-11-03',
            ]
        );
        $idCpd = DB::table('usuario')->where('email', 'cpd@uagrm.edu.bo')->value('id_usuario');
        DB::table('usuario_roles')->updateOrInsert(['id_usuario' => $idCpd, 'id_rol' => 4], ['estado' => 'ACTIVO']);

        // 8. Población de Estudiantes Pre-registrados en Base de Datos
        $estudiantesData = [
            [
                'nombre' => 'Rodrigo',
                'apellidos' => 'Suarez Banegas',
                'ci' => '9483920',
                'email' => 'estudiante@ficct.uagrm.edu.bo',
                'telefono' => '77392019',
                'direccion' => 'Av. Alemania 3er Anillo',
                'ru' => '219082341',
                'carrera_id' => 1,
                'semestre' => 5
            ],
            [
                'nombre' => 'María Belén',
                'apellidos' => 'Gonzales Prado',
                'ci' => '8746201',
                'email' => 'maria.gonzales@ficct.uagrm.edu.bo',
                'telefono' => '76049281',
                'direccion' => 'Av. Santos Dumont 4to Anillo',
                'ru' => '220094812',
                'carrera_id' => 2,
                'semestre' => 4
            ],
            [
                'nombre' => 'Mateo',
                'apellidos' => 'Camacho Soliz',
                'ci' => '10293847',
                'email' => 'mateo.camacho@ficct.uagrm.edu.bo',
                'telefono' => '78901234',
                'direccion' => 'Av. Cristo Redentor 5to Anillo',
                'ru' => '221049283',
                'carrera_id' => 1,
                'semestre' => 6
            ],
            [
                'nombre' => 'Sofía',
                'apellidos' => 'Justiniano Ribera',
                'ci' => '9048392',
                'email' => 'sofia.justiniano@ficct.uagrm.edu.bo',
                'telefono' => '70123456',
                'direccion' => 'Av. Beni Calle Los Sauces',
                'ru' => '218049182',
                'carrera_id' => 3,
                'semestre' => 7
            ]
        ];

        foreach ($estudiantesData as $est) {
            DB::table('usuario')->updateOrInsert(
                ['email' => $est['email']],
                [
                    'nombre' => $est['nombre'],
                    'apellidos' => $est['apellidos'],
                    'ci' => $est['ci'],
                    'telefono' => $est['telefono'],
                    'direccion' => $est['direccion'],
                    'usuario_sistema' => $est['ru'],
                    'password' => $passwordHash,
                    'estado' => 'ACTIVO',
                    'fecha_nacimiento' => '2002-05-18',
                ]
            );

            $idU = DB::table('usuario')->where('email', $est['email'])->value('id_usuario');
            DB::table('usuario_roles')->updateOrInsert(['id_usuario' => $idU, 'id_rol' => 1], ['estado' => 'ACTIVO']);
            DB::table('estudiantes')->updateOrInsert(
                ['id_estudiante' => $idU],
                [
                    'registro_universitario' => $est['ru'],
                    'carrera_id' => $est['carrera_id'],
                    'semestre_actual' => $est['semestre']
                ]
            );

            // Simular Histórico Académico según la carrera y semestre del estudiante
            // Materias de semestres anteriores quedan APROBADAS (con nota entre 65 y 95)
            // Materias del semestre actual quedan CURSANDO (semestre 1-2026)
            $materiasCarrera = DB::table('materia_carrera')
                ->join('materia', 'materia_carrera.id_materia', '=', 'materia.id')
                ->where('materia_carrera.id_carrera', $est['carrera_id'])
                ->select('materia.id', 'materia.nivel_semestre')
                ->get();

            foreach ($materiasCarrera as $mc) {
                if ($mc->nivel_semestre < $est['semestre']) {
                    DB::table('estudiante_materia_historial')->updateOrInsert(
                        [
                            'id_estudiante' => $idU,
                            'id_materia' => $mc->id,
                            'gestion' => '1-2025'
                        ],
                        [
                            'estado_materia' => 'APROBADA',
                            'nota_final' => rand(65, 95),
                            'sigla_grupo' => 'SA',
                        ]
                    );
                } elseif ($mc->nivel_semestre == $est['semestre']) {
                    DB::table('estudiante_materia_historial')->updateOrInsert(
                        [
                            'id_estudiante' => $idU,
                            'id_materia' => $mc->id,
                            'gestion' => '1-2026'
                        ],
                        [
                            'estado_materia' => 'CURSANDO',
                            'nota_final' => null,
                            'sigla_grupo' => 'SB',
                        ]
                    );
                }
            }
        }

        // 9. Solicitudes de prueba existentes
        $idEst1 = DB::table('usuario')->where('email', 'estudiante@ficct.uagrm.edu.bo')->value('id_usuario');
        $idEst2 = DB::table('usuario')->where('email', 'maria.gonzales@ficct.uagrm.edu.bo')->value('id_usuario');

        // Solicitud 1: ENVIADA
        DB::table('solicitud')->updateOrInsert(
            ['codigo' => 'CE-2026-0001'],
            [
                'id_estudiante' => $idEst1,
                'id_periodo' => $periodoId,
                'tipo_caso' => 'PARALELO_LLENO',
                'motivo' => 'Solicitud de sobrecupo en INF310 paralelo lleno para regularizar avance de semestre.',
                'estado' => 'ENVIADA',
                'fecha_solicitud' => now()->subDays(2),
            ]
        );
        $sol1Id = DB::table('solicitud')->where('codigo', 'CE-2026-0001')->value('id');
        DB::table('solicitud_grupo')->updateOrInsert(
            ['id_solicitud' => $sol1Id, 'id_grupo' => 3],
            ['motivo' => 'Sobrecupo solicitado en paralelo SC', 'estado' => 'PENDIENTE']
        );

        // Solicitud 2: APROBADA_DIRECCION (Lista para que la estudiante suba comprobante de pago)
        DB::table('solicitud')->updateOrInsert(
            ['codigo' => 'CE-2026-0002'],
            [
                'id_estudiante' => $idEst2,
                'id_periodo' => $periodoId,
                'tipo_caso' => 'LEVANTAMIENTO_PREREQUISITO',
                'motivo' => 'Levantamiento de prerrequisito en INF210 habiendo aprobado el 80% de créditos del nivel.',
                'estado' => 'APROBADA_DIRECCION',
                'fecha_solicitud' => now()->subDay(),
                'fecha_revision' => now()->subHours(5),
                'id_revisor' => $idDir,
            ]
        );
        $sol2Id = DB::table('solicitud')->where('codigo', 'CE-2026-0002')->value('id');
        DB::table('solicitud_grupo')->updateOrInsert(
            ['id_solicitud' => $sol2Id, 'id_grupo' => 2],
            ['motivo' => 'Materia solicitada para levantamiento', 'estado' => 'PENDIENTE']
        );
        DB::table('evaluacion_solicitud')->updateOrInsert(
            ['id_solicitud' => $sol2Id],
            [
                'id_evaluador' => $idDir,
                'decision' => 'APROBADA',
                'justificacion' => 'Cumple con el promedio ponderado y avance curricular.',
                'fecha_evaluacion' => now()->subHours(5),
            ]
        );
    }
}
