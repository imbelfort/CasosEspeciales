<template>
  <div class="malla-digital-container">
    <div class="malla-legend">
      <div class="legend-item">
        <span class="legend-dot cursada"></span>
        <span>Aprobada</span>
      </div>
      <div class="legend-item">
        <span class="legend-dot inscrita"></span>
        <span>Inscrita o en curso</span>
      </div>
      <div class="legend-item">
        <span class="legend-dot solicitada"></span>
        <span>Solicitada en Caso Especial</span>
      </div>
      <div class="legend-item">
        <span class="legend-dot disponible"></span>
        <span>Pendiente</span>
      </div>
    </div>

    <!-- Cuadrícula Semestral de Materias -->
    <div class="semestres-grid">
      <div v-for="sem in semestresList" :key="sem" class="semestre-column">
        <div class="semestre-header">Semestre {{ sem }}</div>
        <div class="materias-stack">
          <button
            v-for="{ materia: m, estado, etiqueta, elegible } in materiasPorSemestre[sem]"
            :key="m.id"
            type="button"
            class="materia-card"
            :class="estado"
            :disabled="!elegible"
            @click="emit('select-materia', m)"
          >
            <span class="card-sigla">{{ m.sigla }}</span>
            <span class="card-nombre">{{ m.nombre }}</span>
            <span class="card-status-label">{{ etiqueta }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  materias: {
    type: Array,
    default: () => []
  },
  materiasSolicitadasIds: {
    type: Array,
    default: () => []
  },
  materiasAprobadasIds: {
    type: Set,
    required: true
  },
  materiasInscritasIds: {
    type: Set,
    required: true
  }
});

const emit = defineEmits(['select-materia']);

const materiasPorSemestre = computed(() => {
  const semestres = {};
  for (const materia of props.materias) {
    const aprobada = props.materiasAprobadasIds.has(materia.id);
    const inscrita = props.materiasInscritasIds.has(materia.id);
    const solicitada = props.materiasSolicitadasIds.includes(materia.id);
    const tieneGrupos = Boolean(materia.grupos?.length);
    let estado = 'status-disponible';
    let etiqueta = tieneGrupos ? 'Disponible para solicitar' : 'Sin grupos';
    if (aprobada) {
      estado = 'status-cursada';
      etiqueta = 'Aprobada';
    } else if (inscrita) {
      estado = 'status-inscrita';
      etiqueta = 'Inscrita o en curso';
    } else if (solicitada) {
      estado = 'status-solicitada';
      etiqueta = 'Caso solicitado';
    }
    const semestre = materia.nivel_semestre || 1;
    (semestres[semestre] ??= []).push({
      materia, estado, etiqueta, elegible: !aprobada && !inscrita && tieneGrupos
    });
  }
  return semestres;
});
const semestresList = computed(() => Object.keys(materiasPorSemestre.value).map(Number).sort((a, b) => a - b));
</script>

<style scoped>
.malla-legend {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 1rem;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.625rem;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--surface);
  color: var(--text-muted);
  font-size: 0.8125rem;
}

.legend-dot {
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
}

.legend-dot.cursada { background: var(--success); }
.legend-dot.inscrita { background: var(--primary); }
.legend-dot.solicitada { background: var(--warning); }
.legend-dot.disponible { background: var(--text-muted); }

.semestres-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(10rem, 100%), 1fr));
  align-items: start;
  gap: 0.75rem;
}

.semestre-column {
  min-width: 0;
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}

.semestre-header {
  padding: 0.75rem;
  border-bottom: 1px solid var(--border);
  color: var(--text);
  font-size: 0.875rem;
  font-weight: 700;
}

.materias-stack {
  display: grid;
  gap: 0.375rem;
  padding: 0.5rem;
}

.materia-card {
  --materia-estado: var(--text-muted);
  display: flex;
  flex-direction: column;
  align-items: start;
  gap: 0.25rem;
  min-width: 0;
  padding: 0.625rem;
  border: 0;
  border-radius: var(--radius-lg);
  background: var(--bg);
  color: var(--text);
  text-align: start;
  cursor: pointer;
}

.materia-card:disabled { cursor: default; }

.materia-card:enabled:hover { background: var(--primary-subtle); }

.card-sigla {
  color: var(--primary-text);
  font-size: 0.75rem;
  font-weight: 700;
}

.card-nombre {
  font-size: 0.8125rem;
  font-weight: 650;
  line-height: 1.35;
  overflow-wrap: anywhere;
}

.card-status-label {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  color: var(--text-muted);
  font-size: 0.75rem;
}

.card-status-label::before {
  content: '';
  flex: none;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: 50%;
  background: var(--materia-estado);
}

.status-cursada { --materia-estado: var(--success); }
.status-inscrita { --materia-estado: var(--primary); }
.status-solicitada { --materia-estado: var(--warning); }
</style>
