<template>
  <ModalVisorAcademico
    :visible="visible"
    titulo="Historial académico"
    subtitulo="Consulta tus notas y estados académicos."
    :icono="ListChecks"
    texto-cierre="Cerrar Historial"
    @close="$emit('close')"
  >
    <template #antes-del-contenido>
      <div class="kpi-resumen-grid">
        <div class="kpi-card">
          <span class="kpi-label">Materias Aprobadas</span>
          <strong class="kpi-val text-success">{{ totalAprobadas }}</strong>
        </div>
        <div class="kpi-card">
          <span class="kpi-label">Cursando Actual</span>
          <strong class="kpi-val text-primary">{{ totalCursando }}</strong>
        </div>
        <div class="kpi-card">
          <span class="kpi-label">Promedio ponderado de aprobadas</span>
          <strong class="kpi-val">{{ promedioNotas }} <small>/100</small></strong>
        </div>
        <div class="kpi-card">
          <span class="kpi-label">Créditos Acumulados</span>
          <strong class="kpi-val text-info">{{ creditosTotales }} cr.</strong>
        </div>
      </div>

      <!-- Filtros y Búsqueda -->
      <div class="filtros-historico">
        <div class="filtro-estados">
          <button
            type="button"
            :class="['filtro-chip', { active: filtroEstado === 'TODOS' }]"
            :aria-pressed="filtroEstado === 'TODOS'"
            @click="filtroEstado = 'TODOS'"
          >
            Todas ({{ historicoLista.length }})
          </button>
          <button
            type="button"
            :class="['filtro-chip', { active: filtroEstado === 'APROBADA' }]"
            :aria-pressed="filtroEstado === 'APROBADA'"
            @click="filtroEstado = 'APROBADA'"
          >
            Aprobadas ({{ totalAprobadas }})
          </button>
          <button
            type="button"
            :class="['filtro-chip', { active: filtroEstado === 'CURSANDO' }]"
            :aria-pressed="filtroEstado === 'CURSANDO'"
            @click="filtroEstado = 'CURSANDO'"
          >
            Cursando ({{ totalCursando }})
          </button>
        </div>

        <div class="busqueda-input-wrapper">
          <Search :size="15" class="search-icon" />
          <input
            v-model="busquedaTexto"
            type="search"
            aria-label="Buscar por sigla o nombre"
            placeholder="Buscar por sigla o nombre..."
            class="input-busqueda"
          />
        </div>
      </div>
    </template>

    <div class="table-responsive" tabindex="0" role="region" aria-label="Historial académico detallado">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Semestre</th>
            <th scope="col">Sigla</th>
            <th scope="col">Asignatura</th>
            <th scope="col">Créditos</th>
            <th scope="col">Estado Académico</th>
            <th scope="col">Nota Final</th>
            <th scope="col">Gestión</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="h in listaFiltrada" :key="h.id || h.id_materia">
            <td>Semestre {{ h.nivel_semestre }}</td>
            <td><strong class="code-tag">{{ h.sigla }}</strong></td>
            <td>{{ h.materia_nombre || h.nombre }}</td>
            <td>{{ obtenerCreditos(h) }} cr.</td>
            <td>
              <span :class="['status-badge', h.estado_materia]">
                {{ getEstadoTexto(h.estado_materia) }}
              </span>
            </td>
            <td>
              <strong :class="h.nota_final >= 51 ? 'text-success' : (h.nota_final != null ? 'text-danger' : 'text-muted')">
                {{ h.nota_final ?? 'En curso' }}
              </strong>
            </td>
            <td>{{ h.gestion || 'Histórico' }}</td>
          </tr>
          <tr v-if="listaFiltrada.length === 0">
            <td colspan="7" class="text-center py-4 text-muted">
              No se encontraron materias con el filtro aplicado.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </ModalVisorAcademico>
</template>

<script setup>
import { ref, computed } from 'vue';
import { ListChecks, Search } from '@lucide/vue';
import ModalVisorAcademico from './ModalVisorAcademico.vue';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  historicoLista: {
    type: Array,
    default: () => []
  }
});

defineEmits(['close']);

const filtroEstado = ref('TODOS');
const busquedaTexto = ref('');

const registrosAprobados = computed(() => props.historicoLista.filter(h => h.estado_materia === 'APROBADA'));
const totalAprobadas = computed(() => registrosAprobados.value.length);
const totalCursando = computed(() => props.historicoLista.filter(h => h.estado_materia === 'CURSANDO').length);

const obtenerCreditos = (h) => Number(h.creditos) || 4;

const creditosTotales = computed(() => {
  return registrosAprobados.value.reduce((suma, h) => suma + obtenerCreditos(h), 0);
});

const promedioNotas = computed(() => {
  let totalPonderado = 0;
  let totalCreditos = 0;
  for (const h of registrosAprobados.value) {
    if (h.nota_final == null || !Number.isFinite(Number(h.nota_final))) continue;
    const creditos = obtenerCreditos(h);
    totalPonderado += Number(h.nota_final) * creditos;
    totalCreditos += creditos;
  }
  return totalCreditos ? (totalPonderado / totalCreditos).toFixed(1) : '0.0';
});

const listaFiltrada = computed(() => {
  let list = props.historicoLista;

  if (filtroEstado.value === 'APROBADA') {
    list = list.filter(h => h.estado_materia === 'APROBADA');
  } else if (filtroEstado.value === 'CURSANDO') {
    list = list.filter(h => h.estado_materia === 'CURSANDO');
  }

  if (busquedaTexto.value.trim()) {
    const q = busquedaTexto.value.toLowerCase().trim();
    list = list.filter(h => {
      const sigla = (h.sigla || '').toLowerCase();
      const nombre = (h.materia_nombre || h.nombre || '').toLowerCase();
      return sigla.includes(q) || nombre.includes(q);
    });
  }

  return list;
});

const getEstadoTexto = (estado) => {
  switch (estado) {
    case 'APROBADA': return 'Aprobada';
    case 'CURSANDO': return 'Cursando Actual';
    case 'REPROBADA': return 'Reprobada';
    case 'INSCRITA': return 'Inscrita Regular';
    case 'CONVALIDADA': return 'Convalidada';
    default: return estado || 'Pendiente';
  }
};
</script>

<style scoped>
.kpi-resumen-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 0.75rem;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
  padding: 0.875rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}

.kpi-label {
  font-size: 0.8125rem;
  color: var(--text-muted);
}

.kpi-val {
  margin-top: auto;
  color: var(--text);
  font-size: 1.375rem;
  font-variant-numeric: tabular-nums;
}

.filtros-historico,
.filtro-estados {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.filtros-historico {
  justify-content: space-between;
  gap: 0.75rem;
  margin: 1rem 0 0.75rem;
}

.filtro-chip {
  min-height: 2.25rem;
  padding: 0.375rem 0.75rem;
  border: 0;
  border-radius: 999px;
  background: var(--surface);
  color: var(--text-muted);
  font-size: 0.8125rem;
  font-weight: 650;
  cursor: pointer;
}

.filtro-chip:hover { background: var(--surface-alt); }

.filtro-chip.active {
  background: var(--primary-subtle);
  color: var(--primary-text);
}

.busqueda-input-wrapper {
  position: relative;
  flex: 0 1 15rem;
}

.search-icon {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  pointer-events: none;
}

.input-busqueda {
  width: 100%;
  min-height: 2.5rem;
  padding: 0.5rem 0.75rem 0.5rem 2.25rem;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--surface);
  color: var(--text);
  box-shadow: none;
}

.input-busqueda::placeholder { color: var(--text-muted); }

.table-responsive { border-radius: var(--radius-xl); }

.data-table {
  min-width: 48rem;
  border-color: var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}

.data-table :is(th, td) {
  border-color: var(--border);
  color: var(--text);
}

.data-table thead th,
.data-table tbody tr:is(:hover, :focus-within) > * {
  background: var(--bg);
}

.data-table thead th { color: var(--text-muted); }

@media (max-width: 600px) {
  .kpi-resumen-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .busqueda-input-wrapper { flex-basis: 100%; }
}
</style>
