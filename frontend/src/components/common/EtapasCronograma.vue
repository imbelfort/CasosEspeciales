<template>
  <div class="etapas-widget-container">
    <div class="etapas-header">
      <div class="header-left">
        <Sparkles :size="18" class="text-primary inline-icon" />
        <div>
          <h4 class="etapas-title">Proceso Oficial de Casos Especiales (Gestión Semestral {{ gestionFormateada }})</h4>
          <p class="etapas-subtitle">
            Seguimiento en tiempo real de las fases oficiales &bull; Estado actual: 
            <strong class="text-primary">{{ etapaActualInfo.nombre }}</strong>
          </p>
        </div>
      </div>
      <div class="header-badge" :class="etapaActualInfo.claseBadge">
        <Clock :size="13" class="inline-icon" />
        <span>{{ etapaActualInfo.estadoTexto }}</span>
      </div>
    </div>

    <!-- Stepper Dinámico de Etapas Pro -->
    <div class="stepper-track">
      <div 
        v-for="(etapa, idx) in etapas" 
        :key="etapa.id" 
        class="step-node"
        :class="[getStepState(etapa, idx)]"
      >
        <div class="step-connector" v-if="idx > 0"></div>
        <div class="step-indicator">
          <Check v-if="getStepState(etapa, idx) === 'completed'" :size="14" />
          <span v-else>{{ idx + 1 }}</span>
        </div>
        <div class="step-content">
          <span class="step-num-label">Etapa {{ idx + 1 }}</span>
          <strong class="step-name">{{ etapa.nombre }}</strong>
          <span class="step-dates">{{ etapa.fechasTexto }}</span>
          <span class="step-badge-tag">{{ getStepBadgeLabel(etapa, idx) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Sparkles, Clock, Check } from '@lucide/vue';

const props = defineProps({
  periodo: {
    type: Object,
    default: null
  }
});

const gestionFormateada = computed(() => {
  const g = props.periodo?.gestion || '1-2026';
  const parts = g.split('-');
  if (parts.length === 2) {
    const sem = parts[0] === '1' ? 'I' : (parts[0] === '2' ? 'II' : parts[0]);
    return `${sem}-${parts[1]} (${parts[0]}-${parts[1]})`;
  }
  return g;
});

// Definición oficial de las etapas del proceso
const etapas = computed(() => {
  const p = props.periodo;
  return [
    {
      id: 1,
      nombre: 'Recepción de Solicitudes',
      descripcion: 'Envío digital de casos especiales y selección de materias en el sistema.',
      fechaInicio: p?.fecha_inicio_solicitud || '2026-03-23',
      fechaFin: p?.fecha_fin_solicitud || '2026-03-24',
      fechasTexto: '23–24 Marzo'
    },
    {
      id: 2,
      nombre: 'Evaluación de Dirección',
      descripcion: 'Revisión académica, control de cupos y resolución por las autoridades.',
      fechaInicio: p?.fecha_inicio_evaluacion || '2026-03-25',
      fechaFin: p?.fecha_fin_evaluacion || '2026-03-26',
      fechasTexto: '25–26 Marzo'
    },
    {
      id: 3,
      nombre: 'Cancelación de Arancel (10 Bs)',
      descripcion: 'Pago del arancel universitario mediante QR PagoFácil tras la aprobación.',
      fechaInicio: p?.fecha_inicio_evaluacion || '2026-03-25',
      fechaFin: p?.fecha_fin_ejecucion || '2026-03-28',
      fechasTexto: '25–28 Marzo'
    },
    {
      id: 4,
      nombre: 'Inscripción en CPD',
      descripcion: 'Habilitación técnica e inclusión definitiva en el sistema universitario.',
      fechaInicio: p?.fecha_inicio_ejecucion || '2026-03-27',
      fechaFin: p?.fecha_fin_ejecucion || '2026-03-28',
      fechasTexto: '27–28 Marzo'
    }
  ];
});

// Determinar dinámicamente en qué etapa estamos respecto a la fecha actual
const hoyStr = computed(() => {
  const now = new Date();
  const y = now.getFullYear();
  const m = String(now.getMonth() + 1).padStart(2, '0');
  const d = String(now.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
});

// Índice de etapa activa actual (por defecto 0: Etapa 1)
const indiceEtapaActiva = computed(() => {
  const hoy = hoyStr.value;
  for (let i = 0; i < etapas.value.length; i++) {
    const et = etapas.value[i];
    if (hoy >= et.fechaInicio && hoy <= et.fechaFin) {
      return i;
    }
  }
  // Si estamos antes de empezar
  if (etapas.value.length && hoy < etapas.value[0].fechaInicio) return 0;
  // Si ya pasó
  return etapas.value.length - 1;
});

const getStepState = (etapa, idx) => {
  if (idx < indiceEtapaActiva.value) return 'completed';
  if (idx === indiceEtapaActiva.value) return 'active';
  return 'pending';
};

const getStepBadgeLabel = (etapa, idx) => {
  const state = getStepState(etapa, idx);
  if (state === 'completed') return 'Concluida';
  if (state === 'active') return 'En Curso Ahora';
  return 'Próxima';
};

const etapaActualInfo = computed(() => {
  const etapa = etapas.value[indiceEtapaActiva.value] || etapas.value[0];
  return {
    nombre: `Etapa ${indiceEtapaActiva.value + 1}: ${etapa.nombre}`,
    estadoTexto: `Fase Activa (${etapa.fechasTexto})`,
    claseBadge: 'badge-active'
  };
});
</script>

<style scoped>
.etapas-widget-container {
  background-color: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl, 16px);
  padding: 1.25rem 1.5rem;
  margin-bottom: 1.4rem;
  box-shadow: 0 1px 3px var(--shadow-color);
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

.etapas-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.8rem;
  border-bottom: 1px solid var(--border);
  padding-bottom: 0.9rem;
  margin-bottom: 1.2rem;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.etapas-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text);
  letter-spacing: -0.2px;
}
.etapas-subtitle {
  margin: 0.2rem 0 0 0;
  font-size: 0.8rem;
  color: var(--text-muted);
}

.header-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.3rem 0.8rem;
  border-radius: var(--radius-pill, 100px);
  font-size: 0.76rem;
  font-weight: 700;
  background-color: var(--surface-alt);
  color: var(--primary);
  border: 1px solid var(--border);
}
.header-badge.badge-active {
  background-color: var(--primary);
  color: #ffffff;
  border-color: var(--primary);
}

/* Stepper horizontal Pro */
.stepper-track {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.8rem;
  position: relative;
}

.step-node {
  display: flex;
  flex-direction: column;
  position: relative;
  background-color: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg, 12px);
  padding: 0.85rem 1rem;
  transition: all 0.2s ease;
}

/* Conector horizontal visual */
.step-connector {
  display: none;
}

/* Indicador Circular de Número / Check */
.step-indicator {
  width: 26px;
  height: 26px;
  border-radius: var(--radius-circle, 50%);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.78rem;
  font-weight: 800;
  margin-bottom: 0.5rem;
  background-color: var(--surface);
  color: var(--text-muted);
  border: 1px solid var(--border);
  transition: all 0.2s ease;
}

.step-content {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}

.step-num-label {
  font-size: 0.68rem;
  text-transform: uppercase;
  font-weight: 800;
  letter-spacing: 0.5px;
  color: var(--text-muted);
  margin-bottom: 0.2rem;
}

.step-name {
  font-size: 0.86rem;
  color: var(--text);
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.step-dates {
  font-size: 0.76rem;
  color: var(--text-muted);
  font-weight: 500;
  margin-bottom: 0.5rem;
}

.step-badge-tag {
  align-self: flex-start;
  font-size: 0.66rem;
  font-weight: 800;
  padding: 0.15rem 0.5rem;
  border-radius: var(--radius-pill, 100px);
  text-transform: uppercase;
  letter-spacing: 0.4px;
  background-color: var(--surface);
  color: var(--text-muted);
  border: 1px solid var(--border);
}

/* Estados Visuales Pro */

/* 1. Activa / En Curso */
.step-node.active {
  background-color: var(--surface);
  border-color: var(--primary);
  box-shadow: 0 0 0 1px var(--primary);
}
.step-node.active .step-indicator {
  background-color: var(--primary);
  color: #ffffff;
  border-color: var(--primary);
}
.step-node.active .step-num-label {
  color: var(--primary);
}
.step-node.active .step-badge-tag {
  background-color: var(--primary);
  color: #ffffff;
  border-color: var(--primary);
}

/* 2. Completada / Concluida */
.step-node.completed {
  opacity: 0.85;
}
.step-node.completed .step-indicator {
  background-color: var(--success);
  color: #ffffff;
  border-color: var(--success);
}
.step-node.completed .step-badge-tag {
  background-color: rgba(49, 162, 76, 0.12);
  color: var(--success);
  border-color: var(--success);
}

/* 3. Próxima / Pendiente */
.step-node.pending {
  opacity: 0.7;
}

@media (max-width: 900px) {
  .stepper-track {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 540px) {
  .stepper-track {
    grid-template-columns: 1fr;
  }
}
</style>
