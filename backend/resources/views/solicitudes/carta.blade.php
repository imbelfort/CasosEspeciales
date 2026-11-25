<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carta de solicitud de caso especial - {{ $solicitud->codigo }}</title>
    <style>
        @page { margin: 45pt; }
        body { font-family: "DejaVu Serif", serif; font-size: 11pt; line-height: 1.45; color: #171717; }
        header { text-align: center; border-bottom: 2px solid #171717; padding-bottom: 10pt; }
        header h1, header h2, header p { margin: 2pt 0; }
        header h1 { font-size: 13pt; }
        header h2 { font-size: 11pt; }
        .codigo, .referencia { text-align: right; }
        .motivo { white-space: pre-line; word-wrap: break-word; }
        table { width: 100%; border-collapse: collapse; font-size: 9pt; }
        th, td { border: 1px solid #555; padding: 5pt; text-align: left; word-wrap: break-word; }
        th { background: #f2f2f2; }
        .firma { page-break-inside: avoid; margin: 48pt auto 0; padding-top: 5pt; width: 220pt; text-align: center; border-top: 1px solid #171717; }
    </style>
</head>
<body>
    @php
        $estudiante = $solicitud->estudiante;
        $usuario = $estudiante->usuario;
        $carrera = $estudiante->carrera?->nombre;
        $nombre = trim($usuario->nombre.' '.$usuario->apellidos);
    @endphp
    <header>
        <h1>Universidad Autónoma Gabriel René Moreno</h1>
        <h2>Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones</h2>
        <p>Dirección de Carrera de {{ $carrera }}</p>
    </header>

    <p class="codigo">
        <strong>Trámite N.° {{ $solicitud->codigo }}</strong><br>
        Santa Cruz de la Sierra, {{ date('d/m/Y', strtotime($solicitud->fecha_solicitud)) }}
    </p>

    <p>
        Señor(a)<br>
        <strong>Director(a) de la carrera de {{ $carrera }}</strong><br>
        FICCT · UAGRM<br>
        Presente
    </p>
    <p class="referencia"><strong>Ref. Solicitud de caso especial ({{ $solicitud->tipo_caso }})</strong></p>

    <p>De mi mayor consideración:</p>
    <p>
        Yo, <strong>{{ $nombre }}</strong>, con registro universitario <strong>{{ $estudiante->registro_universitario }}</strong>
        y cédula de identidad <strong>{{ $usuario->ci }}</strong>, estudiante de la carrera de {{ $carrera }}
        del {{ $estudiante->semestre_actual }}.° semestre, en la gestión académica {{ $solicitud->periodo?->gestion }},
        me dirijo a su autoridad para solicitar la consideración del siguiente caso especial.
    </p>
    <p class="motivo">{{ $solicitud->motivo }}</p>

    @if ($solicitud->grupos->isNotEmpty())
        <p><strong>Materias y grupos solicitados</strong></p>
        <table>
            <thead>
                <tr><th>Sigla</th><th>Asignatura</th><th>Grupo</th><th>Observación o motivo</th></tr>
            </thead>
            <tbody>
                @foreach ($solicitud->grupos as $grupoSolicitud)
                    <tr>
                        <td>{{ $grupoSolicitud->grupo?->materia?->sigla }}</td>
                        <td>{{ $grupoSolicitud->grupo?->materia?->nombre }}</td>
                        <td>{{ $grupoSolicitud->grupo?->sigla_grupo }}</td>
                        <td>{{ $grupoSolicitud->motivo }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p>Agradezco la atención prestada a mi solicitud y quedo a la espera de su respuesta.</p>

    <p class="firma">
        <strong>{{ $nombre }}</strong><br>
        Postulante a caso especial<br>
        R.U. {{ $estudiante->registro_universitario }}<br>
        C.I. {{ $usuario->ci }}
    </p>
</body>
</html>
