<template>
  <dialog ref="dialogo" class="modal-box expediente-dialogo" :class="{ 'expediente-estudiante': estudiante }"
    aria-labelledby="expediente-titulo" @close="cancelarConsulta">
    <header>
      <h3 id="expediente-titulo">Expediente del caso {{ solicitudActiva?.codigo || codigo }}</h3>
      <form method="dialog">
        <button type="submit" class="btn-action" aria-label="Cerrar expediente"><X :size="18" aria-hidden="true" /></button>
      </form>
    </header>
    <p v-if="cargando" class="expediente-contenido" role="status">Cargando expediente…</p>
    <div v-else-if="error" class="expediente-contenido">
      <p role="alert">{{ error }}</p>
      <button type="button" class="btn-secondary" @click="cargar">Reintentar</button>
    </div>
    <div v-else-if="solicitudActiva" class="expediente-contenido">
      <dl v-if="estudiante" class="resumen-expediente">
        <div><dt>Estado actual</dt><dd><span :class="['status-badge', solicitudActiva.estado]">{{ etiquetaEstado(solicitudActiva.estado) }}</span></dd></div>
        <div><dt>Tipo de caso</dt><dd>{{ humanizar(solicitudActiva.tipo_caso) }}</dd></div>
        <div><dt>Enviada el</dt><dd>{{ fechaEstudiante(solicitudActiva.fecha_solicitud) }}</dd></div>
      </dl>
      <div v-else class="expediente-meta">
        <div v-if="solicitudActiva.estudiante?.usuario">
          <strong>Estudiante:</strong> {{ solicitudActiva.estudiante.usuario.nombre }} {{ solicitudActiva.estudiante.usuario.apellidos }}
        </div>
        <div><strong>Tipo de Caso:</strong> {{ solicitudActiva.tipo_caso }}</div>
        <div><strong>Estado:</strong> <span :class="['status-badge', solicitudActiva.estado]">{{ solicitudActiva.estado }}</span></div>
        <div><strong>Fecha Envío:</strong> {{ formatearFecha(solicitudActiva.fecha_solicitud) }}</div>
      </div>

      <section v-if="estudiante" class="actividad-expediente" aria-labelledby="actividad-expediente-titulo">
        <h4 id="actividad-expediente-titulo">Actividad del caso</h4>
        <ol v-if="eventosOrdenados.length" class="ruta-actividad" role="list">
          <li v-for="(evento, indice) in eventosOrdenados" :key="evento.id" class="paso-actividad">
            <span class="numero-actividad" aria-hidden="true">{{ indice + 1 }}</span>
            <article>
              <header>
                <h5>{{ acciones[evento.accion] ?? humanizar(evento.accion) }}</h5>
                <time v-if="evento.fecha" :datetime="evento.fecha.toISOString()">{{ formatoEstudiante.format(evento.fecha) }}</time>
                <small v-else>Sin fecha</small>
              </header>
              <div v-if="evento.estado_nuevo" class="estados-actividad">
                <template v-if="evento.estado_anterior && evento.estado_anterior !== evento.estado_nuevo">
                  <span :class="['status-badge', evento.estado_anterior]">{{ etiquetaEstado(evento.estado_anterior) }}</span>
                  <ArrowRight :size="14" role="img" aria-label="Cambió a" />
                </template>
                <span :class="['status-badge', evento.estado_nuevo]">{{ etiquetaEstado(evento.estado_nuevo) }}</span>
              </div>
              <p v-if="evento.observacion">{{ evento.observacion }}</p>
            </article>
          </li>
        </ol>
        <p v-else class="text-muted">Todavía no hay actividad registrada.</p>
      </section>

      <div class="box-note mt-3">
        <strong>{{ estudiante ? 'Motivo de la solicitud' : 'Motivo del Estudiante:' }}</strong>
        <p>{{ solicitudActiva.motivo }}</p>
      </div>

      <div v-if="solicitudActiva.evaluacion" class="box-note-eval mt-3">
        <strong>Resolución de Dirección:</strong> {{ estudiante ? humanizar(solicitudActiva.evaluacion.decision) : solicitudActiva.evaluacion.decision }}<br>
        <span v-if="solicitudActiva.evaluacion.justificacion"><strong>Justificación:</strong> {{ solicitudActiva.evaluacion.justificacion }}</span>
        <span v-if="solicitudActiva.motivo_rechazo" class="text-danger"><br><strong>Motivo de Rechazo:</strong> {{ solicitudActiva.motivo_rechazo }}</span>
      </div>

      <h4 class="mt-4">{{ estudiante ? 'Materias y horarios' : 'Materias, Grupos y Horarios Asignados' }}</h4>
      <ul class="clean-list">
        <li v-for="sg in solicitudActiva.grupos" :key="sg.id" style="margin-bottom: 0.5rem;">
          <BookOpen :size="15" class="inline-icon text-primary" />
          <strong>{{ sg.grupo?.materia?.sigla }}</strong> - {{ sg.grupo?.materia?.nombre }}
          <span class="code-tag" style="margin-left: 0.4rem;">Grupo {{ sg.grupo?.sigla_grupo }}</span>
          <div v-if="sg.grupo?.horarios && sg.grupo.horarios.length > 0" class="text-muted text-sm" style="margin-left: 1.5rem; font-size: 0.8rem;">
            <Clock :size="12" class="inline-icon" />
            <span v-for="(h, hi) in sg.grupo.horarios" :key="hi" style="margin-right: 0.6rem;">
              {{ h.dia }} {{ h.hora_inicio?.substring(0, 5) }}-{{ h.hora_fin?.substring(0, 5) }} (Aula {{ h.aula }})
            </span>
          </div>
        </li>
        <li v-if="!solicitudActiva.grupos || solicitudActiva.grupos.length === 0" class="text-muted">
          No se han vinculado materias aún a este caso.
        </li>
      </ul>

      <h4 class="mt-4">{{ estudiante ? 'Archivos adjuntos' : 'Archivos y Comprobantes' }}</h4>
      <ul class="clean-list">
        <li v-for="arc in solicitudActiva.archivos" :key="arc.id">
          <FolderArchive :size="15" class="inline-icon text-info" />
          <strong>{{ estudiante ? humanizar(arc.tipo_archivo) : arc.tipo_archivo }}:</strong> {{ arc.nombre_archivo }}
          ({{ estudiante ? fechaEstudiante(arc.fecha_subida) : formatearFecha(arc.fecha_subida) }})
        </li>
        <li v-if="!solicitudActiva.archivos || solicitudActiva.archivos.length === 0" class="text-muted">
          Sin archivos adjuntos.
        </li>
      </ul>

      <template v-if="!estudiante">
      <h4 class="mt-4">Línea de Tiempo y Auditoría Transaccional (CU7)</h4>
      <div class="timeline">
        <div v-for="h in solicitudActiva.historial" :key="h.id" class="timeline-item">
          <div class="timeline-content">
            <strong>{{ h.accion }}</strong>: <span class="status-badge">{{ h.estado_anterior || 'INICIO' }}</span> <ArrowRight :size="12" class="inline-icon" /> <span class="status-badge">{{ h.estado_nuevo }}</span>
            <p class="timeline-obs">{{ h.observacion }}</p>
            <small class="text-muted">{{ formatearFecha(h.fecha_evento) }}</small>
          </div>
        </div>
      </div>
      </template>
    </div>
    <footer>
      <form method="dialog">
        <button type="submit" class="btn-secondary">Cerrar</button>
      </form>
    </footer>
  </dialog>
</template>

<script setup>
import { computed, onUnmounted, ref } from 'vue';
import { ArrowRight, BookOpen, Clock, FolderArchive, X } from '@lucide/vue';
import api from '../../services/api';

defineProps({ estudiante: Boolean });

const dialogo = ref(null);
const solicitudActiva = ref(null);
const codigo = ref('');
const cargando = ref(false);
const error = ref('');
let idSolicitud;
let consulta;

const formatearFecha = (fecha) => fecha ? new Date(fecha).toLocaleString('es-BO') : 'Sin fecha';

const formatoEstudiante = new Intl.DateTimeFormat('es-BO', {
  dateStyle: 'medium', timeStyle: 'short', timeZone: 'America/La_Paz',
});
const acciones = {
  REGISTRO_SOLICITUD: 'Solicitud enviada',
  EVALUACION_DIRECCION: 'Evaluación de Dirección',
  SUBSANACION_OBSERVACION: 'Documentación corregida',
  BOLETA_OBSERVADA: 'Boleta observada',
  VALIDACION_MANUAL_PAGO: 'Pago validado por Dirección',
  CONSULTA_PAGOFACIL: 'Pago QR confirmado',
  WEBHOOK_CALLBACK: 'Pago QR confirmado',
  CONSULTA_STRIPE: 'Pago con tarjeta confirmado',
  WEBHOOK_STRIPE: 'Pago con tarjeta confirmado',
  'REMISIÓN_CPD': 'Solicitud remitida a CPD',
  REMISION_CPD: 'Solicitud remitida a CPD',
  RESPUESTA_CPD: 'Resolución de CPD',
};
const estados = {
  ENVIADA: 'Enviada',
  APROBADA_DIRECCION: 'Aprobada por Dirección · Pago pendiente',
  APROBADA: 'Lista para remitir',
  RECHAZADA_DIRECCION: 'Rechazada por Dirección',
  REMITIDA_A_CPD: 'Remitida a CPD',
  HABILITADA: 'Habilitada',
  OBSERVADA_CPD: 'Observada por CPD',
  RECHAZADA_CPD: 'Rechazada por CPD',
};
const humanizar = (valor) => valor?.replaceAll('_', ' ').toLocaleLowerCase('es')
  .replace(/^./u, letra => letra.toLocaleUpperCase('es')) || 'Sin información';
const etiquetaEstado = (estado) => estados[estado] ?? humanizar(estado);

function leerFecha(valor) {
  if (!valor) return null;
  const iso = valor.replace(' ', 'T');
  // Laravel guarda los timestamps del expediente en UTC.
  const fecha = new Date(/T/.test(iso) && !/(Z|[+-]\d{2}:?\d{2})$/i.test(iso) ? iso + 'Z' : iso);
  return Number.isNaN(fecha.getTime()) ? null : fecha;
}

function fechaEstudiante(valor) {
  const fecha = leerFecha(valor);
  return fecha ? formatoEstudiante.format(fecha) : 'Sin fecha';
}

const eventosOrdenados = computed(() => (solicitudActiva.value?.historial ?? [])
  .map(evento => ({ ...evento, fecha: leerFecha(evento.fecha_evento) }))
  .sort((a, b) => (a.fecha?.getTime() ?? Infinity) - (b.fecha?.getTime() ?? Infinity) || a.id - b.id));

async function cargar() {
  consulta?.abort();
  consulta = new AbortController();
  const { signal } = consulta;
  cargando.value = true;
  error.value = '';
  try {
    const { data } = await api.get(`/solicitudes/${idSolicitud}`, { signal, timeout: 10000 });
    if (!signal.aborted) solicitudActiva.value = data;
  } catch {
    if (!signal.aborted) error.value = 'No se pudo cargar el expediente.';
  } finally {
    if (!signal.aborted) cargando.value = false;
  }
}

function abrir(solicitud) {
  idSolicitud = solicitud.id;
  codigo.value = solicitud.codigo || '';
  solicitudActiva.value = null;
  dialogo.value.showModal();
  cargar();
}

function cancelarConsulta() {
  if (!dialogo.value?.open) consulta?.abort();
}

onUnmounted(() => consulta?.abort());
defineExpose({ abrir });
</script>

<style scoped>
dialog.modal-box.expediente-dialogo {
  width: min(44rem, calc(100vw - 1.5rem));
  max-width: none;
  max-height: calc(100dvh - 1.5rem);
  margin: auto;
  padding: 0;
  overflow: hidden;
}

.expediente-dialogo[open] {
  display: flex;
  flex-direction: column;
}

dialog.expediente-dialogo:not([open]) {
  display: none;
}

.expediente-dialogo::backdrop {
  background: rgb(10 19 23 / 65%);
}

.expediente-dialogo > :is(header, footer) {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .75rem;
  flex-shrink: 0;
  padding: .75rem 1rem;
  border-bottom: 1px solid var(--border);
}

.expediente-dialogo h3 {
  margin: 0;
  overflow-wrap: anywhere;
}

.expediente-dialogo > footer {
  justify-content: flex-end;
  border-bottom: 0;
  border-top: 1px solid var(--border);
}

.expediente-contenido {
  margin: 0;
  min-height: 0;
  overflow: auto;
  overscroll-behavior: contain;
  overflow-wrap: anywhere;
  padding: 1rem;
}

.expediente-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem 1rem;
}

.box-note,
.box-note-eval {
  background: var(--surface-alt);
  border-radius: var(--radius-lg);
  padding: .75rem;
  margin-top: .75rem;
}

.expediente-contenido h4 {
  margin: 1rem 0 .5rem;
}

.clean-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.clean-list li,
.timeline-item {
  padding-block: .5rem;
  border-bottom: 1px solid var(--border);
}

.box-note p,
.timeline-obs {
  margin: .25rem 0;
}

.text-muted {
  color: var(--text-muted);
}

dialog.expediente-estudiante {
  border-radius: var(--radius-xxl);
}

.expediente-estudiante > header h3 {
  font-size: clamp(1rem, 3vw, 1.25rem);
}

.expediente-estudiante .expediente-contenido {
  padding: clamp(.75rem, 3vw, 1.25rem);
}

.resumen-expediente {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 10rem), 1fr));
  gap: .625rem 1rem;
  margin: 0 0 1rem;
  padding: .75rem;
  background: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
}

.resumen-expediente dt,
.paso-actividad :is(time, small) {
  color: var(--text-muted);
  font-size: .8125rem;
}

.resumen-expediente dd {
  margin: .2rem 0 0;
  font-size: .875rem;
  font-weight: 600;
}

.actividad-expediente {
  margin-bottom: 1rem;
}

.ruta-actividad {
  --diametro-actividad: 1.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.paso-actividad {
  position: relative;
  display: grid;
  grid-template-columns: var(--diametro-actividad) minmax(0, 1fr);
  gap: .625rem;
  padding-bottom: .625rem;
}

.paso-actividad:not(:last-child)::before {
  content: '';
  position: absolute;
  inset-block-start: calc(var(--diametro-actividad) / 2);
  inset-inline-start: calc(var(--diametro-actividad) / 2);
  height: 100%;
  border-inline-start: 1px solid var(--border);
}

.numero-actividad {
  position: relative;
  display: grid;
  place-items: center;
  width: var(--diametro-actividad);
  height: var(--diametro-actividad);
  background: var(--surface);
  color: var(--primary-text);
  border: 1px solid currentColor;
  border-radius: 50%;
  font-size: .8125rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

:global([data-contrast="high"]) .numero-actividad {
  color: var(--text);
}

.paso-actividad article {
  padding: .625rem .75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  font-size: .875rem;
}

.paso-actividad header,
.estados-actividad {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: .375rem .5rem;
}

.paso-actividad header {
  justify-content: space-between;
}

.paso-actividad h5 {
  margin: 0;
  font-size: .9375rem;
}

.estados-actividad {
  align-items: center;
  margin-top: .375rem;
}

.paso-actividad p {
  margin: .375rem 0 0;
}

.expediente-estudiante :is(.box-note, .box-note-eval) {
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
}
</style>
