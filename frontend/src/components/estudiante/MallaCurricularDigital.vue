<template>
  <div class="malla-digital-container">
    <div class="malla-header">
      <div class="malla-title-info">
        <GraduationCap :size="18" class="text-primary inline-icon" />
        <h4>Malla Curricular Digitalizada</h4>
        <span class="malla-sub">Plan de Estudios Oficial &bull; Sin requerir fotocopias ni papeles físicos</span>
      </div>

      <!-- Leyenda Oficial de Colores exigida por la FICCT -->
      <div class="malla-legend">
        <div class="legend-item">
          <span class="legend-dot cursada"></span>
          <span>Aprobada / Cursada</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot inscrita"></span>
          <span>Inscrita Regular</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot solicitada"></span>
          <span>Solicitada en Caso Especial</span>
        </div>
        <div class="legend-item">
          <span class="legend-dot disponible"></span>
          <span>Disponible / Pendiente</span>
        </div>
      </div>
    </div>

    <!-- Cuadrícula Semestral de Materias -->
    <div class="semestres-grid">
      <div v-for="sem in semestresList" :key="sem" class="semestre-column">
        <div class="semestre-header">Semestre {{ sem }}</div>
        <div class="materias-stack">
          <div 
            v-for="m in materiasPorSemestre[sem]" 
            :key="m.id"
            class="materia-card"
            :class="getEstadoMateria(m)"
            @click="onMateriaClick(m)"
            :title="`Haga clic para ${materiaSeleccionada?.id === m.id ? 'deseleccionar' : 'solicitar'} ${m.sigla}`"
          >
            <div class="card-sigla">{{ m.sigla }}</div>
            <div class="card-nombre">{{ m.nombre }}</div>
            <div class="card-status-label">{{ getEstadoLabel(m) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { GraduationCap } from '@lucide/vue';

const props = defineProps({
  materias: {
    type: Array,
    default: () => []
  },
  materiasSolicitadasIds: {
    type: Array,
    default: () => []
  },
  materiasHistoricoMap: {
    type: Object,
    default: () => ({})
  },
  materiaSeleccionada: {
    type: Object,
    default: null
  },
  semestreActualEstudiante: {
    type: Number,
    default: 5
  }
});

const emit = defineEmits(['select-materia']);

const semestresList = computed(() => {
  const sems = new Set(props.materias.map(m => m.nivel_semestre || 1));
  const arr = Array.from(sems).sort((a, b) => a - b);
  return arr.length > 0 ? arr.slice(0, 8) : [1, 2, 3, 4, 5, 6, 7, 8];
});

const materiasPorSemestre = computed(() => {
  const map = {};
  semestresList.value.forEach(s => {
    map[s] = props.materias.filter(m => (m.nivel_semestre || 1) === s);
  });
  return map;
});

// Determinación exacta del estado usando el Histórico Académico de la BD
const getEstadoMateria = (m) => {
  if (props.materiasSolicitadasIds.includes(m.id) || props.materiaSeleccionada?.id === m.id) {
    return 'status-solicitada';
  }
  const registro = props.materiasHistoricoMap[m.id];
  if (registro) {
    if (registro.estado_materia === 'APROBADA') return 'status-cursada';
    if (registro.estado_materia === 'CURSANDO') return 'status-inscrita';
  }
  // Si no está en el mapa, fallback por semestre
  if ((m.nivel_semestre || 1) < props.semestreActualEstudiante) {
    return 'status-cursada';
  }
  if ((m.nivel_semestre || 1) === props.semestreActualEstudiante) {
    return 'status-inscrita';
  }
  return 'status-disponible';
};

const getEstadoLabel = (m) => {
  if (props.materiasSolicitadasIds.includes(m.id) || props.materiaSeleccionada?.id === m.id) {
    return 'Caso Solicitado';
  }
  const registro = props.materiasHistoricoMap[m.id];
  if (registro) {
    if (registro.estado_materia === 'APROBADA') return `Aprobada (${registro.nota_final || 'OK'})`;
    if (registro.estado_materia === 'CURSANDO') return 'Inscrita Actual';
  }
  if ((m.nivel_semestre || 1) < props.semestreActualEstudiante) {
    return 'Aprobada';
  }
  if ((m.nivel_semestre || 1) === props.semestreActualEstudiante) {
    return 'Inscrita';
  }
  return 'Disponible';
};

const onMateriaClick = (m) => {
  emit('select-materia', m);
};
</script>

<style scoped>
.malla-digital-container {
  background-color: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl, 16px);
  padding: 1.2rem;
  margin-bottom: 1.2rem;
}

.malla-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.8rem;
  border-bottom: 1px solid var(--border);
  padding-bottom: 0.9rem;
  margin-bottom: 1rem;
}

.malla-title-info h4 {
  margin: 0;
  font-size: 1rem;
  font-weight: 700;
  color: var(--text);
  display: inline-block;
  margin-left: 0.4rem;
}
.malla-sub {
  display: block;
  font-size: 0.78rem;
  color: var(--text-muted);
  margin-top: 0.2rem;
}

.malla-legend {
  display: flex;
  gap: 0.9rem;
  flex-wrap: wrap;
}
.legend-item {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.76rem;
  font-weight: 600;
  color: var(--text-muted);
}
.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}
.legend-dot.cursada { background-color: #10b981; }
.legend-dot.inscrita { background-color: #3b82f6; }
.legend-dot.solicitada { background-color: #f59e0b; }
.legend-dot.disponible { background-color: #94a3b8; }

.semestres-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(135px, 1fr));
  gap: 0.75rem;
  overflow-x: auto;
}

.semestre-column {
  background-color: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg, 8px);
  padding: 0.6rem;
}
.semestre-header {
  font-size: 0.75rem;
  font-weight: 800;
  text-transform: uppercase;
  color: var(--text-muted);
  text-align: center;
  padding-bottom: 0.4rem;
  border-bottom: 1px dashed var(--border);
  margin-bottom: 0.5rem;
}

.materias-stack {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.materia-card {
  padding: 0.45rem 0.55rem;
  border-radius: 6px;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.15s ease;
  user-select: none;
}
.materia-card:hover {
  transform: translateY(-1px);
}

.card-sigla {
  font-size: 0.76rem;
  font-weight: 800;
}
.card-nombre {
  font-size: 0.72rem;
  line-height: 1.2;
  margin: 0.15rem 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.card-status-label {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  margin-top: 0.2rem;
}

/* Colores de Estado */
.materia-card.status-cursada {
  background-color: rgba(16, 185, 129, 0.12);
  border-color: #10b981;
  color: #065f46;
}
.materia-card.status-inscrita {
  background-color: rgba(59, 130, 246, 0.12);
  border-color: #3b82f6;
  color: #1e40af;
}
.materia-card.status-solicitada {
  background-color: rgba(245, 158, 11, 0.2);
  border-color: #f59e0b;
  color: #92400e;
  box-shadow: 0 0 0 1px #f59e0b;
}
.materia-card.status-disponible {
  background-color: var(--surface);
  border-color: var(--border);
  color: var(--text-muted);
}
</style>
