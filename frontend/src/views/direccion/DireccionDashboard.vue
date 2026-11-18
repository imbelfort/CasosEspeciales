<template>
  <div class="role-view">
    <div class="view-header">
      <div>
        <h2>Panel de Dirección de Carrera (CU3, CU4, CU5)</h2>
        <p class="view-desc">Evaluación resolutiva, bloqueo transaccional de cupos y emisión de oficios por lotes a CPD.</p>
      </div>
      <div class="header-actions">
        <button @click="submodulo = 'solicitudes'" :class="{ active: submodulo === 'solicitudes' }" class="btn-sub">
          <FileCheck :size="15" class="inline-icon" /> Solicitudes (CU3)
        </button>
        <button @click="submodulo = 'cupos'" :class="{ active: submodulo === 'cupos' }" class="btn-sub">
          <Lock :size="15" class="inline-icon" /> Control de Cupos (CU4)
        </button>
        <button @click="submodulo = 'oficios'" :class="{ active: submodulo === 'oficios' }" class="btn-sub">
          <Send :size="15" class="inline-icon" /> Oficios a CPD (CU5)
        </button>
      </div>
    </div>

    <!-- SUBMODULO 1: SOLICITUDES Y EVALUACION CU3 -->
    <div v-if="submodulo === 'solicitudes'">
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Código</th>
              <th>Estudiante / RU</th>
              <th>Tipo de Caso</th>
              <th>Estado</th>
              <th>Fecha</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="sol in solicitudes" :key="sol.id">
              <td><strong class="code-tag">{{ sol.codigo }}</strong></td>
              <td>
                {{ sol.estudiante?.usuario?.nombre }} {{ sol.estudiante?.usuario?.apellidos }}<br>
                <small class="text-muted">RU: {{ sol.estudiante?.registro_universitario }}</small>
              </td>
              <td>{{ sol.tipo_caso }}</td>
              <td><span :class="['status-badge', sol.estado]">{{ sol.estado }}</span></td>
              <td>{{ formatearFecha(sol.fecha_solicitud) }}</td>
              <td class="actions-cell">
                <button @click="verDetalle(sol)" class="btn-action">
                  <FileSearch :size="14" class="inline-icon" /> Expediente
                </button>
                <button v-if="sol.estado === 'ENVIADA' || sol.estado === 'OBSERVADA'" @click="abrirEvaluar(sol)" class="btn-action btn-eval">
                  <Scale :size="14" class="inline-icon" /> Evaluar (CU3)
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SUBMODULO 2: CONTROL Y RESERVA DE CUPOS CU4 -->
    <div v-if="submodulo === 'cupos'">
      <div class="sub-header">
        <h3>Grupos y Reservas con Bloqueo Transaccional (FOR UPDATE)</h3>
        <button @click="modalReserva = true" class="btn-primary">
          <Plus :size="15" class="inline-icon" /> Reservar / Bloquear Cupo
        </button>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Materia</th>
              <th>Grupo</th>
              <th>Cupo Máximo</th>
              <th>Cupos Libres</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in grupos" :key="g.id">
              <td><strong>{{ g.materia?.sigla }}</strong> - {{ g.materia?.nombre }}</td>
              <td><span class="code-tag">{{ g.sigla_grupo }}</span></td>
              <td>{{ g.cupo_maximo }}</td>
              <td><strong>{{ g.cupos_disponibles }}</strong></td>
              <td><span class="status-badge ABIERTO">{{ g.estado }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SUBMODULO 3: OFICIOS A CPD CU5 -->
    <div v-if="submodulo === 'oficios'">
      <div class="sub-header">
        <h3>Emisión de Oficios y CSV para CPD</h3>
        <button @click="abrirGenerarOficio" class="btn-primary">
          <Plus :size="15" class="inline-icon" /> Generar Nuevo Oficio (CU5)
        </button>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Número Oficio</th>
              <th>Destinatario</th>
              <th>Referencia</th>
              <th>Fecha Remisión</th>
              <th>Casos Asignados</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in oficios" :key="o.id">
              <td><strong class="code-tag">{{ o.numero_oficio }}</strong></td>
              <td>{{ o.destinatario }}</td>
              <td>{{ o.referencia }}</td>
              <td>{{ formatearFecha(o.fecha_remision) }}</td>
              <td>{{ o.solicitudes?.length || 0 }} solicitud(es)</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL EVALUAR (CU3) -->
    <div v-if="modalEvaluar && solicitudActiva" class="modal-backdrop">
      <div class="modal-box">
        <div class="modal-header">
          <h3>CU3: Evaluar {{ solicitudActiva.codigo }}</h3>
          <button @click="modalEvaluar = false" class="btn-close"><X :size="18" /></button>
        </div>
        <form @submit.prevent="ejecutarEvaluacion" class="modal-form">
          <div class="form-group">
            <label>Decisión Formal (*):</label>
            <select v-model="formEval.decision" required>
              <option value="APROBADA">APROBADA (Habilita pago de arancel)</option>
              <option value="RECHAZADA">RECHAZADA</option>
              <option value="OBSERVADA">OBSERVADA</option>
            </select>
          </div>
          <div v-if="formEval.decision === 'RECHAZADA'" class="form-group">
            <label>Motivo de Rechazo (*):</label>
            <textarea v-model="formEval.motivo_rechazo" rows="3" required></textarea>
          </div>
          <div class="form-group">
            <label>Justificación / Observaciones:</label>
            <textarea v-model="formEval.justificacion" rows="3"></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" @click="modalEvaluar = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="loading">Guardar Resolución</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL RESERVAR CUPO (CU4) -->
    <div v-if="modalReserva" class="modal-backdrop">
      <div class="modal-box">
        <div class="modal-header">
          <h3>CU4: Reservar Cupo Transaccional</h3>
          <button @click="modalReserva = false" class="btn-close"><X :size="18" /></button>
        </div>
        <form @submit.prevent="guardarReserva" class="modal-form">
          <div class="form-group">
            <label>Grupo a Bloquear (*):</label>
            <select v-model="formReserva.id_grupo" required>
              <option value="" disabled>Seleccione grupo...</option>
              <option v-for="g in grupos" :key="g.id" :value="g.id">
                {{ g.materia?.sigla }} - Gr. {{ g.sigla_grupo }} (Disponibles: {{ g.cupos_disponibles }})
              </option>
            </select>
          </div>
          <div class="form-group">
            <label>Cantidad de cupos a bloquear (*):</label>
            <input v-model.number="formReserva.cantidad" type="number" min="1" required />
          </div>
          <div class="form-group">
            <label>Motivo de reserva (*):</label>
            <input v-model="formReserva.motivo" required placeholder="Reserva para caso especial..." />
          </div>
          <div class="modal-footer">
            <button type="button" @click="modalReserva = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="loading">Ejecutar Reserva</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL GENERAR OFICIO A CPD (CU5) -->
    <div v-if="modalOficio" class="modal-backdrop">
      <div class="modal-box modal-lg">
        <div class="modal-header">
          <h3>CU5: Emisión de Oficio Consolidado a CPD</h3>
          <button @click="modalOficio = false" class="btn-close"><X :size="18" /></button>
        </div>
        <form @submit.prevent="emitirOficio" class="modal-form">
          <div class="row">
            <div class="form-group col">
              <label>Destinatario (*):</label>
              <input v-model="formOficio.destinatario" required placeholder="Lic. Director de CPD - UAGRM" />
            </div>
            <div class="form-group col">
              <label>Referencia (*):</label>
              <input v-model="formOficio.referencia" required placeholder="Remisión de casos especiales aprobados con pago" />
            </div>
          </div>
          <h4>Seleccione los casos APROBADOS a consolidar en este lote:</h4>
          <div class="sol-check-list">
            <div v-for="sol in solicitudesAprobadas" :key="sol.id" class="sol-check-item">
              <label>
                <input type="checkbox" :value="sol.id" v-model="formOficio.solicitudes_ids" />
                <strong>{{ sol.codigo }}</strong> - {{ sol.estudiante?.usuario?.nombre }} {{ sol.estudiante?.usuario?.apellidos }} ({{ sol.tipo_caso }})
              </label>
            </div>
            <div v-if="solicitudesAprobadas.length === 0" class="no-records">
              No hay casos con pago en estado APROBADA listos para remitir.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="modalOficio = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="loading || formOficio.solicitudes_ids.length === 0">
              Emitir Oficio y Generar CSV
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { 
  FileCheck, 
  Lock, 
  Send, 
  FileSearch, 
  Scale, 
  Plus, 
  X 
} from '@lucide/vue';
import api from '../../services/api';

const submodulo = ref('solicitudes');
const solicitudes = ref([]);
const grupos = ref([]);
const oficios = ref([]);
const periodos = ref([]);
const loading = ref(false);

const modalEvaluar = ref(false);
const modalReserva = ref(false);
const modalOficio = ref(false);
const solicitudActiva = ref(null);

const formEval = ref({ decision: 'APROBADA', justificacion: '', motivo_rechazo: '' });
const formReserva = ref({ id_grupo: '', cantidad: 1, motivo: '' });
const formOficio = ref({ id_periodo: '', destinatario: 'Director CPD - UAGRM', referencia: 'Remisión de Casos Especiales Aprobados', solicitudes_ids: [] });

const formatearFecha = (f) => f ? new Date(f).toLocaleString('es-BO') : 'N/A';

const solicitudesAprobadas = computed(() => {
  return solicitudes.value.filter(s => s.estado === 'APROBADA');
});

const cargarDatos = async () => {
  try {
    const [resS, resG, resO, resP] = await Promise.all([
      api.get('/solicitudes'),
      api.get('/cupos/grupos'),
      api.get('/oficios-cpd'),
      api.get('/catalogos/periodos')
    ]);
    solicitudes.value = resS.data.data || resS.data;
    grupos.value = resG.data;
    oficios.value = resO.data;
    periodos.value = resP.data;
    if (periodos.value.length) formOficio.value.id_periodo = periodos.value[0].id;
  } catch (e) {
    console.error(e);
  }
};

const abrirEvaluar = (sol) => {
  solicitudActiva.value = sol;
  formEval.value.decision = 'APROBADA';
  formEval.value.justificacion = '';
  formEval.value.motivo_rechazo = '';
  modalEvaluar.value = true;
};

const ejecutarEvaluacion = async () => {
  loading.value = true;
  try {
    await api.post(`/solicitudes/${solicitudActiva.value.id}/evaluar`, formEval.value);
    modalEvaluar.value = false;
    alert('Evaluación guardada con éxito.');
    cargarDatos();
  } catch (e) {
    alert(e.response?.data?.error || 'Error al evaluar.');
  } finally {
    loading.value = false;
  }
};

const guardarReserva = async () => {
  loading.value = true;
  try {
    await api.post('/cupos/reservar', formReserva.value);
    modalReserva.value = false;
    alert('Cupo reservado.');
    cargarDatos();
  } catch (e) {
    alert(e.response?.data?.error || 'Error al reservar cupo.');
  } finally {
    loading.value = false;
  }
};

const abrirGenerarOficio = () => {
  formOficio.value.solicitudes_ids = solicitudesAprobadas.value.map(s => s.id);
  modalOficio.value = true;
};

const emitirOficio = async () => {
  loading.value = true;
  try {
    await api.post('/oficios-cpd/generar', formOficio.value);
    modalOficio.value = false;
    alert('Oficio y archivo CSV emitidos exitosamente hacia CPD.');
    cargarDatos();
  } catch (e) {
    alert(e.response?.data?.error || 'Error al emitir oficio.');
  } finally {
    loading.value = false;
  }
};

onMounted(cargarDatos);
</script>

<style scoped>
.role-view {
  background: white;
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid #e2e8f0;
}
.view-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}
.header-actions {
  display: flex;
  gap: 0.5rem;
}
.btn-sub {
  padding: 0.5rem 1rem;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.btn-sub.active {
  background: #1e3a8a;
  color: white;
}
.sub-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}
.btn-primary {
  background: #1e3a8a;
  color: white;
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.inline-icon {
  vertical-align: middle;
}
.data-table {
  width: 100%;
  border-collapse: collapse;
}
.data-table th, .data-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
}
.status-badge {
  padding: 0.25rem 0.65rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
}
.status-badge.ENVIADA { background: #fef3c7; color: #b45309; }
.status-badge.APROBADA_DIRECCION { background: #dbeafe; color: #1d4ed8; }
.status-badge.APROBADA { background: #dcfce7; color: #15803d; }
.status-badge.RECHAZADA_DIRECCION { background: #fee2e2; color: #b91c1c; }
.status-badge.REMITIDA_A_CPD { background: #f3e8ff; color: #7e22ce; }
.btn-action {
  padding: 0.35rem 0.7rem;
  border: 1px solid #cbd5e1;
  background: white;
  border-radius: 4px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.btn-eval { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}
.modal-box {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 520px;
  padding: 1.5rem;
}
.modal-lg { max-width: 760px; }
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.8rem;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.8rem;
  margin-top: 1.5rem;
}
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.3rem; }
.form-group select, .form-group input, .form-group textarea {
  width: 100%;
  padding: 0.65rem 0.8rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
}
.sol-check-list {
  max-height: 200px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  padding: 0.5rem;
  border-radius: 6px;
}
.sol-check-item {
  padding: 0.4rem;
  border-bottom: 1px solid #f1f5f9;
}
</style>
