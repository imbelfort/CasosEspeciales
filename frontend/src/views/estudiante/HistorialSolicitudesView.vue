<template>
  <div class="role-view">
    <header class="view-header">
      <div>
        <h2>Historial de solicitudes</h2>
        <p>Consulta el estado y la actividad de tus casos especiales.</p>
      </div>
      <button type="button" @click="cargarCasos" class="actualizar" :disabled="cargando">
        <RefreshCw :size="15" class="inline-icon" aria-hidden="true" /> Actualizar
      </button>
    </header>

    <p v-if="error" class="aviso aviso-error" role="alert">{{ error }}</p>
    <p v-if="cargando" class="aviso" role="status">Cargando historial…</p>
    <div class="historial-layout">
      <section v-if="historialCargado" class="resumen-card" aria-labelledby="titulo-resultados">
        <header class="resumen-header">
          <span class="resumen-icono"><ChartBar :size="20" aria-hidden="true" /></span>
          <div>
            <h3 id="titulo-resultados">Resultados de tus casos</h3>
            <p>Todas las gestiones</p>
          </div>
        </header>

        <ul class="grafico-barras" aria-label="Cantidad de casos por resultado">
          <li v-for="resultado in resultados" :key="resultado.tipo" :class="resultado.tipo">
            <div class="barra-etiqueta">
              <span>{{ resultado.etiqueta }}</span>
              <strong>{{ resultado.cantidad }}</strong>
            </div>
            <div class="barra-pista" aria-hidden="true">
              <div class="barra-relleno" :style="{ width: resultado.cantidad / escala * 100 + '%' }"></div>
            </div>
          </li>
        </ul>
        <p v-if="resultados.every(resultado => resultado.cantidad === 0)" class="resumen-vacio">
          Todavía no tienes casos aprobados ni rechazados.
        </p>
      </section>

      <section class="historial-container" aria-label="Solicitudes registradas">
        <article v-for="sol in solicitudes" :key="sol.id" class="historial-card">
          <div class="historial-card-header">
            <div class="card-title-group">
              <h3 class="code-tag">{{ sol.codigo }}</h3>
              <span class="tipo-tag">{{ sol.tipo_caso === 'CASO_ESPECIAL' ? 'Caso especial' : sol.tipo_caso }}</span>
              <span v-if="sol.periodo?.gestion" class="periodo-tag">Gestión {{ sol.periodo.gestion }}</span>
            </div>
            <span :class="['status-badge', sol.estado]">{{ sol.estado }}</span>
          </div>

          <div class="historial-meta">
            <p><strong>Motivo inicial:</strong> {{ sol.motivo }}</p>
            <div v-if="sol.evaluacion" class="eval-note">
              <strong>Resolución de Dirección:</strong> {{ sol.evaluacion.decision }}
              <span v-if="sol.evaluacion.justificacion"> - {{ sol.evaluacion.justificacion }}</span>
            </div>
            <p v-if="sol.motivo_rechazo"><strong>Motivo del rechazo:</strong> {{ sol.motivo_rechazo }}</p>
          </div>

          <h4 class="timeline-title">Actividad</h4>
          <ol v-if="sol.historial?.length" class="timeline">
            <li v-for="h in sol.historial" :key="h.id" class="timeline-item">
              <span class="timeline-dot" aria-hidden="true"></span>
              <div class="timeline-content">
                <div class="timeline-header">
                  <strong>{{ h.accion }}</strong>
                  <time class="timeline-date" :datetime="h.fecha_evento">{{ formatearFecha(h.fecha_evento) }}</time>
                </div>
                <div class="timeline-transicion">
                  <span :class="['status-badge', h.estado_anterior]">{{ h.estado_anterior || 'Inicio' }}</span>
                  <ArrowRight :size="14" role="img" aria-label="Cambió a" />
                  <span :class="['status-badge', h.estado_nuevo]">{{ h.estado_nuevo }}</span>
                </div>
                <p v-if="h.observacion" class="timeline-obs">{{ h.observacion }}</p>
              </div>
            </li>
          </ol>
          <p v-else class="timeline-date">Sin actividad registrada.</p>
        </article>

        <div v-if="!cargando && !error && solicitudes.length === 0" class="no-records">
          <History :size="28" aria-hidden="true" />
          <p>Aún no tienes solicitudes registradas.</p>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { ArrowRight, ChartBar, History, RefreshCw } from '@lucide/vue';
import api from '../../services/api';

const solicitudes = ref([]);
const cargando = ref(true);
const error = ref('');
const historialCargado = ref(false);

const estadosAprobados = new Set(['APROBADA_DIRECCION', 'APROBADA', 'REMITIDA_A_CPD', 'HABILITADA']);
const estadosRechazados = new Set(['RECHAZADA', 'RECHAZADA_DIRECCION', 'RECHAZADA_CPD']);

const resultados = computed(() => {
  let aprobados = 0;
  let rechazados = 0;
  for (const solicitud of solicitudes.value) {
    if (estadosAprobados.has(solicitud.estado)) aprobados++;
    else if (estadosRechazados.has(solicitud.estado)) rechazados++;
  }
  return [
    { tipo: 'aprobados', etiqueta: 'Aprobados', cantidad: aprobados },
    { tipo: 'rechazados', etiqueta: 'Rechazados', cantidad: rechazados }
  ];
});

const escala = computed(() => Math.max(1, ...resultados.value.map(resultado => resultado.cantidad)));

const formatearFecha = (f) => f ? new Date(f).toLocaleString('es-BO') : 'N/A';

const cargarCasos = async () => {
  cargando.value = true;
  error.value = '';
  try {
    const res = await api.get('/solicitudes/mis-casos');
    solicitudes.value = res.data;
    historialCargado.value = true;
  } catch {
    error.value = 'No se pudo cargar el historial. Intenta actualizarlo.';
  } finally {
    cargando.value = false;
  }
};

onMounted(cargarCasos);
</script>

<style scoped>
.historial-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  align-items: start;
  gap: 1rem;
}

.resumen-card,
.view-header {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  padding: clamp(1rem, 3vw, 1.5rem);
}

.view-header {
  border-block-start: 3px solid var(--primary);
}

.view-header p,
.resumen-header p {
  margin: 0.25rem 0 0;
  color: var(--text-muted);
  font-size: 0.875rem;
}

.actualizar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 2.5rem;
  padding: 0.5rem 0.875rem;
  border: 1px solid transparent;
  border-radius: var(--radius-lg);
  background: var(--primary-subtle);
  color: var(--primary-text);
  cursor: pointer;
}

.actualizar:hover:enabled { background: var(--surface-alt); }

.actualizar:focus-visible {
  outline: 2px solid var(--border-focus);
  outline-offset: 2px;
}

.aviso {
  margin: 0;
  color: var(--text-muted);
}

.aviso-error {
  padding: 0.75rem 1rem;
  border: 1px solid var(--danger);
  border-radius: var(--radius-lg);
  background: var(--surface);
  color: var(--text);
}

.resumen-header {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.resumen-header h3 {
  margin: 0;
  font-size: 1.0625rem;
  line-height: 1.25;
}

.resumen-icono {
  display: grid;
  flex: none;
  place-items: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-lg);
  background: var(--primary-subtle);
  color: var(--primary-text);
}

.grafico-barras {
  display: grid;
  gap: 1rem;
  margin: 1.25rem 0 0;
  padding: 0;
  list-style: none;
}

.barra-etiqueta {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 0.75rem;
  margin-bottom: 0.375rem;
  font-size: 0.875rem;
  font-weight: 650;
}

.barra-etiqueta strong {
  font-size: 1.25rem;
  font-variant-numeric: tabular-nums;
}

.barra-pista {
  height: 1rem;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  background: var(--surface-alt);
}

.barra-relleno { height: 100%; border-radius: inherit; }
.aprobados .barra-relleno { background: var(--success); }
.rechazados .barra-relleno { background: var(--danger); }

.resumen-vacio {
  margin: 1rem 0 0;
  color: var(--text-muted);
  font-size: 0.875rem;
}

.tipo-tag {
  padding: 0.25rem 0.625rem;
  border-radius: var(--radius-pill);
  background: var(--primary-subtle);
  color: var(--primary-text);
  font-size: 0.75rem;
}

@media (min-width: 64rem) {
  .historial-layout:has(.resumen-card) {
    grid-template-columns: minmax(0, 1fr) minmax(16rem, 20rem);
  }

  .resumen-card { grid-column: 2; grid-row: 1; }
  .historial-container { grid-column: 1; grid-row: 1; }
}

.role-view,
.historial-container {
  display: grid;
  gap: 1rem;
}

.view-header,
.historial-card-header,
.timeline-header,
.card-title-group,
.timeline-transicion {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .5rem .75rem;
}

.view-header,
.historial-card-header,
.timeline-header {
  justify-content: space-between;
}

.view-header h2,
.card-title-group h3,
.timeline-title {
  margin: 0;
}

.view-header h2 {
  font-size: calc(1.5rem * var(--font-scale, 1));
}

.historial-card,
.no-records {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  padding: clamp(1rem, 3vw, 1.5rem);
  min-width: 0;
  overflow-wrap: anywhere;
}

.historial-card-header {
  padding-bottom: .75rem;
  border-bottom: 1px solid var(--border);
}

.card-title-group h3 {
  font-size: calc(1rem * var(--font-scale, 1));
}

.periodo-tag,
.timeline-date,
.no-records {
  color: var(--text-muted);
}

.historial-meta,
.timeline-content,
.timeline-date {
  font-size: calc(.875rem * var(--font-scale, 1));
}

.eval-note {
  padding: .75rem 1rem;
  border-radius: var(--radius-lg);
  background: var(--surface-alt);
  color: var(--text);
}

.eval-note p {
  margin: .375rem 0 0;
}

.timeline-title {
  margin-block: 1rem .75rem;
}

.timeline {
  list-style: none;
  margin: 0 0 0 .3125rem;
  padding: 0 0 0 1rem;
  border-inline-start: 1px solid var(--border);
}

.timeline-item {
  position: relative;
  padding-block: .625rem;
}

.timeline-item + .timeline-item {
  border-top: 1px solid var(--border);
}

.timeline-dot {
  position: absolute;
  inset-inline-start: -1.3125rem;
  top: 1rem;
  width: .5625rem;
  height: .5625rem;
  border: 1px solid var(--surface);
  outline: 1px solid var(--text);
  background: var(--primary-text);
  border-radius: 50%;
}

.timeline-transicion,
.timeline-obs {
  margin: .375rem 0 0;
}

.no-records {
  text-align: center;
}
</style>
