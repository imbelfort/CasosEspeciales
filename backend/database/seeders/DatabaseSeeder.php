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

        // 2. Carreras de la FICCT
        $carreras = [
            ['id' => 1, 'codigo' => '187-3', 'nombre' => 'Ingeniería Informática', 'facultad' => 'FICCT'],
            ['id' => 2, 'codigo' => '187-4', 'nombre' => 'Ingeniería en Sistemas', 'facultad' => 'FICCT'],
            ['id' => 3, 'codigo' => '187-5', 'nombre' => 'Ingeniería en Redes y Telecomunicaciones', 'facultad' => 'FICCT'],
        ];

        foreach ($carreras as $c) {
            DB::table('carrera')->updateOrInsert(['id' => $c['id']], $c);
        }

        // 3. Período Académico Activo (Formato estricto 1-YYYY o 2-YYYY)
        DB::table('periodos')->updateOrInsert(
            ['gestion' => '1-2026'],
            [
                'fecha_inicio' => '2026-02-15',
                'fecha_fin' => '2026-07-15',
                'estado' => 'ACTIVO',
            ]
        );

        $periodoId = DB::table('periodos')->where('gestion', '1-2026')->value('id');

        // 4. Materias
        $materias = [
            ['id' => 1, 'sigla' => 'INF110', 'nombre' => 'Introducción a la Informática', 'nivel_semestre' => 1, 'creditos' => 4],
            ['id' => 2, 'sigla' => 'INF210', 'nombre' => 'Programación I', 'nivel_semestre' => 2, 'creditos' => 4],
            ['id' => 3, 'sigla' => 'INF310', 'nombre' => 'Estructuras de Datos I', 'nivel_semestre' => 3, 'creditos' => 5],
            ['id' => 4, 'sigla' => 'INF418', 'nombre' => 'Sistemas de Información I', 'nivel_semestre' => 4, 'creditos' => 5],
            ['id' => 5, 'sigla' => 'MAT101', 'nombre' => 'Cálculo I', 'nivel_semestre' => 1, 'creditos' => 4],
        ];

        foreach ($materias as $m) {
            DB::table('materia')->updateOrInsert(['id' => $m['id']], $m);

            // Asociar materias a las carreras (materia_carrera)
            DB::table('materia_carrera')->updateOrInsert(['id_materia' => $m['id'], 'id_carrera' => 1]);
            DB::table('materia_carrera')->updateOrInsert(['id_materia' => $m['id'], 'id_carrera' => 2]);
        }

        // 5. Grupos de las materias con cupos
        $grupos = [
            ['id' => 1, 'id_materia' => 1, 'id_periodo' => $periodoId, 'sigla_grupo' => 'SA', 'docente' => 'Ing. Juan Carlos Peinado', 'cupo_maximo' => 40, 'cupos_disponibles' => 15, 'cupo_excepcional_maximo' => 5, 'estado' => 'ABIERTO'],
            ['id' => 2, 'id_materia' => 2, 'id_periodo' => $periodoId, 'sigla_grupo' => 'SB', 'docente' => 'Ing. Evans Balcazar', 'cupo_maximo' => 40, 'cupos_disponibles' => 5, 'cupo_excepcional_maximo' => 5, 'estado' => 'ABIERTO'],
            ['id' => 3, 'id_materia' => 3, 'id_periodo' => $periodoId, 'sigla_grupo' => 'SC', 'docente' => 'Lic. Mario López', 'cupo_maximo' => 40, 'cupos_disponibles' => 0, 'cupo_excepcional_maximo' => 5, 'estado' => 'ABIERTO'],
            ['id' => 4, 'id_materia' => 4, 'id_periodo' => $periodoId, 'sigla_grupo' => 'SD', 'docente' => 'Ing. Katime Busch', 'cupo_maximo' => 40, 'cupos_disponibles' => 8, 'cupo_excepcional_maximo' => 5, 'estado' => 'ABIERTO'],
        ];

        foreach ($grupos as $g) {
            DB::table('grupo')->updateOrInsert(['id' => $g['id']], $g);
        }

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
