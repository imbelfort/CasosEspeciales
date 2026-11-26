<template>
  <div class="role-view">
    <div class="view-header">
      <div>
        <h2>Historial de solicitudes</h2>
      </div>
      <button type="button" @click="cargarCasos" class="btn-secondary" :disabled="cargando">
        <RefreshCw :size="15" class="inline-icon" aria-hidden="true" /> Actualizar
      </button>
    </div>

    <p v-if="error" role="alert">{{ error }}</p>
    <p v-if="cargando" role="status">Cargando historial…</p>
    <div class="historial-container">
      <article v-for="sol in solicitudes" :key="sol.id" class="historial-card">
        <div class="historial-card-header">
          <div class="card-title-group">
            <h3 class="code-tag">{{ sol.codigo }}</h3>
            <span class="tipo-tag">{{ sol.tipo_caso }}</span>
            <span v-if="sol.periodo?.gestion" class="periodo-tag">Gestión {{ sol.periodo.gestion }}</span>
          </div>
          <span :class="['status-badge', sol.estado]">{{ sol.estado }}</span>
        </div>

        <div class="historial-meta">
          <p><strong>Motivo inicial:</strong> {{ sol.motivo }}</p>
          <div v-if="sol.evaluacion" class="eval-note">
            <strong>Resolución de Dirección:</strong> {{ sol.evaluacion.decision }}
            <span v-if="sol.evaluacion.justificacion"> - {{ sol.evaluacion.justificacion }}</span>
            <p v-if="sol.motivo_rechazo"><strong>Motivo del rechazo:</strong> {{ sol.motivo_rechazo }}</p>
          </div>
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
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { RefreshCw, ArrowRight, History } from '@lucide/vue';
import api from '../../services/api';

const solicitudes = ref([]);
const cargando = ref(true);
const error = ref('');

const formatearFecha = (f) => f ? new Date(f).toLocaleString('es-BO') : 'N/A';

const cargarCasos = async () => {
  cargando.value = true;
  error.value = '';
  try {
    const res = await api.get('/solicitudes/mis-casos');
    solicitudes.value = res.data;
  } catch {
    error.value = 'No se pudo cargar el historial. Intenta actualizarlo.';
  } finally {
    cargando.value = false;
  }
};

onMounted(cargarCasos);
</script>

<style scoped>
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
