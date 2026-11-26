<template>
  <div v-if="visible" class="modal-backdrop">
    <div class="modal-box modal-xl">
      <div class="modal-header">
        <div class="modal-title-with-icon">
          <GraduationCap :size="22" class="text-primary inline-icon" />
          <div>
            <h3>Visor de Historial y Avance Académico</h3>
            <p class="modal-subtitle">
              Consulta tus materias aprobadas, cursadas y haz clic en una materia disponible para postularla a Caso Especial.
            </p>
          </div>
        </div>
        <button @click="$emit('close')" class="btn-close">
          <X :size="20" />
        </button>
      </div>

      <div class="modal-tabs">
        <button 
          @click="tab = 'malla'" 
          :class="{ active: tab === 'malla' }" 
          class="tab-btn"
        >
          <LayoutGrid :size="16" class="inline-icon" /> Malla Curricular Digital
        </button>
        <button 
          @click="tab = 'lista'" 
          :class="{ active: tab === 'lista' }" 
          class="tab-btn"
        >
          <ListChecks :size="16" class="inline-icon" /> Lista Detallada de Materias e Histórico
        </button>
      </div>

      <div class="modal-body-scroll">
        <!-- VISTA 1: MALLA CURRICULAR GRÁFICA -->
        <div v-if="tab === 'malla'">
          <MallaCurricularDigital 
            :materias="materias" 
            :materias-solicitadas-ids="materiasSolicitadasIds"
            :materias-historico-map="historicoMap"
            :materia-seleccionada="materiaSeleccionada"
            :semestre-actual-estudiante="semestreActual"
            @select-materia="onSelectMateria"
          />
        </div>

        <!-- VISTA 2: TABLA DE HISTÓRICO ACADÉMICO -->
        <div v-if="tab === 'lista'" class="table-responsive" tabindex="0" role="region" aria-label="Historial académico">
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
                <th scope="col">Acción</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="h in historicoLista" :key="h.id || h.id_materia">
                <td>Semestre {{ h.nivel_semestre }}</td>
                <td><strong class="code-tag">{{ h.sigla }}</strong></td>
                <td>{{ h.materia_nombre || h.nombre }}</td>
                <td>{{ h.creditos || 4 }} cr.</td>
                <td>
                  <span :class="['status-badge', h.estado_materia]">
                    {{ getEstadoTexto(h.estado_materia) }}
                  </span>
                </td>
                <td>
                  <strong :class="h.nota_final >= 51 ? 'text-success' : 'text-danger'">
                    {{ h.nota_final ?? '-' }}
                  </strong>
                </td>
                <td>{{ h.gestion || 'Histórico' }}</td>
                <td>
                  <button 
                    v-if="esMateriaPostulable(h)" 
                    @click="postularMateria(h)" 
                    class="btn-action btn-add-sm"
                    title="Solicitar esta materia por Caso Especial"
                  >
                    + Solicitar
                  </button>
                  <span v-else class="text-muted" style="font-size: 0.8rem;">
                    {{ h.estado_materia === 'APROBADA' ? 'Completada' : 'No elegible' }}
                  </span>
                </td>
              </tr>
              <tr v-if="historicoLista.length === 0">
                <td colspan="8" class="text-center py-4 text-muted">
                  No se encontraron registros en el historial académico.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer">
        <div class="footer-tip">
          <Info :size="15" class="inline-icon text-info" />
          <span>Tip: Al pulsar sobre cualquier materia pendiente o disponible, se abrirá el formulario para registrarla.</span>
        </div>
        <button @click="$emit('close')" class="btn-primary">Cerrar Visor</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { 
  GraduationCap, 
  X, 
  LayoutGrid, 
  ListChecks, 
  Info 
} from '@lucide/vue';
import MallaCurricularDigital from './MallaCurricularDigital.vue';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  materias: {
    type: Array,
    default: () => []
  },
  historicoLista: {
    type: Array,
    default: () => []
  },
  historicoMap: {
    type: Object,
    default: () => ({})
  },
  materiasSolicitadasIds: {
    type: Array,
    default: () => []
  },
  materiaSeleccionada: {
    type: Object,
    default: null
  },
  semestreActual: {
    type: Number,
    default: 5
  }
});

const emit = defineEmits(['close', 'select-materia']);

const tab = ref('malla');

const onSelectMateria = (materia) => {
  emit('select-materia', materia);
};

const postularMateria = (h) => {
  const materiaObj = props.materias.find(m => m.id === h.id_materia || m.sigla === h.sigla);
  if (materiaObj) {
    emit('select-materia', materiaObj);
  }
};

const esMateriaPostulable = (h) => {
  return h.estado_materia !== 'APROBADA';
};

const getEstadoTexto = (estado) => {
  switch (estado) {
    case 'APROBADA': return 'Aprobada';
    case 'REPROBADA': return 'Reprobada';
    case 'INSCRITA': return 'Inscrita Regular';
    case 'CONVALIDADA': return 'Convalidada';
    default: return estado || 'Pendiente';
  }
};
</script>

<style scoped>
.modal-xl {
  max-width: 1200px !important;
  width: 95vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border);
  padding-bottom: 1rem;
}
.modal-title-with-icon {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}
.modal-title-with-icon h3 {
  margin: 0;
  font-size: 1.25rem;
  color: var(--text);
}
.modal-subtitle {
  margin: 2px 0 0 0;
  font-size: 0.85rem;
  color: var(--text-muted);
}
.modal-tabs {
  display: flex;
  gap: 0.5rem;
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--border);
}
.tab-btn {
  background: transparent;
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  padding: 0.4rem 0.9rem;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  transition: all 0.2s;
}
.tab-btn.active {
  background: var(--primary);
  color: var(--on-primary);
  border-color: var(--primary);
}
.modal-body-scroll {
  overflow-y: auto;
  flex: 1;
  padding: 1rem 0;
}
.modal-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid var(--border);
  padding-top: 1rem;
}
.footer-tip {
  font-size: 0.85rem;
  color: var(--text-muted);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.btn-add-sm {
  background: var(--surface);
  color: var(--text);
  border: 1px solid var(--border);
  font-weight: 600;
  font-size: 0.75rem;
  padding: 3px 8px;
  border-radius: var(--radius-pill);
  cursor: pointer;
}
.btn-add-sm:hover {
  background: var(--surface-alt);
  color: var(--text);
}
</style>
