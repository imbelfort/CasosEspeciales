<template>
  <div class="role-view">
    <div class="view-header">
      <div class="header-text">
        <h2>Centro de Procesamiento de Datos (CU6 - CPD)</h2>
        <p class="view-desc">Procesamiento técnico final de casos especiales remitidos por Dirección y gestión del cronograma.</p>
      </div>
      <div class="header-action-buttons">
        <button @click="modalConfigPeriodo = true" class="btn-primary">
          <Calendar :size="15" class="inline-icon" /> Parámetros y Arancel
        </button>
        <button @click="cargarPendientes" class="btn-secondary">
          <RefreshCw :size="15" class="inline-icon" /> Actualizar
        </button>
      </div>
    </div>

    <!-- OFICIOS REMITIDOS A CPD -->
    <div v-for="oficio in oficios" :key="oficio.id" class="oficio-card">
      <div class="oficio-header">
        <div>
          <strong class="code-tag">{{ oficio.numero_oficio }}</strong>
          <span class="oficio-ref">{{ oficio.referencia }}</span>
        </div>
        <span class="status-badge REMITIDA_A_CPD">Emitido</span>
      </div>

      <table class="data-table mt-3">
        <thead>
          <tr>
            <th>Código Solicitud</th>
            <th>Estudiante</th>
            <th>Registro</th>
            <th>Materia / Grupo</th>
            <th>Acción Técnica</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sol in oficio.solicitudes" :key="sol.id">
            <td><strong>{{ sol.codigo }}</strong></td>
            <td>{{ sol.estudiante?.usuario?.nombre }} {{ sol.estudiante?.usuario?.apellidos }}</td>
            <td>{{ sol.estudiante?.registro_universitario }}</td>
            <td>
              <div v-for="sg in sol.grupos" :key="sg.id">
                {{ sg.grupo?.materia?.sigla }} - Gr. {{ sg.grupo?.sigla_grupo }}
              </div>
            </td>
            <td>
              <button @click="abrirRespuesta(oficio, sol)" class="btn-action btn-pay">
                <CheckSquare :size="14" class="inline-icon" /> Resolver CPD (CU6)
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="oficios.length === 0" class="no-records">
      No hay oficios pendientes de habilitación en CPD.
    </div>

    <!-- MODAL CONFIGURACIÓN DE PARÁMETROS, CRONOGRAMA Y ARANCEL (CPD) -->
    <div v-if="modalConfigPeriodo" class="modal-backdrop">
      <div class="modal-box modal-lg">
        <div class="modal-header">
          <div>
            <h3>Parámetros de Casos Especiales</h3>
            <p class="modal-subtitle">Gestión Semestral {{ periodoActivo?.gestion || '1-2026' }} &bull; Parámetros oficiales UAGRM</p>
          </div>
          <button @click="modalConfigPeriodo = false" class="btn-close">
            <X :size="18" />
          </button>
        </div>
        
        <div class="modal-body-scroll">
          <form @submit.prevent="guardarConfigPeriodo" class="modal-form">
            <div class="alert-box-info mb-3">
              Configura las fechas del cronograma y el monto del arancel. El plazo de pago va del inicio de evaluación al fin de inscripción en CPD.
            </div>
            
            <div class="row">
              <div class="form-group col">
                <label>Costo Arancel por Caso Especial (Bs.):</label>
                <input v-model.number="formConfig.costo_arancel_caso" type="number" min="0" step="0.01" required />
              </div>
              <div class="form-group col">
                <label>Estado del Período:</label>
                <input :value="periodoActivo?.estado || 'ACTIVO'" disabled />
              </div>
            </div>

            <h4 class="section-title">Límites y Reglas Institucionales (CPD / UAGRM)</h4>
            <div class="row">
              <div class="form-group col">
                <label>Máx. Materias por Solicitud:</label>
                <input v-model.number="formConfig.max_materias_por_caso" type="number" min="1" max="10" required />
                <small class="text-muted">Habitual: 2 a 5 materias (CPD define cuántas habilita).</small>
              </div>
              <div class="form-group col">
                <label>Límite Histórico Carrera por Alumno:</label>
                <input v-model.number="formConfig.max_casos_carrera" type="number" min="1" max="10" required />
                <small class="text-muted">Norma: máximo 5 casos en toda la carrera.</small>
              </div>
            </div>

            <h4 class="section-title">Período Oficial de Recepción de Solicitudes</h4>
            <div class="row">
              <div class="form-group col">
                <label>Fecha Inicio de Recepción:</label>
                <input v-model="formConfig.fecha_inicio_solicitud" type="date" required />
              </div>
              <div class="form-group col">
                <label>Fecha Límite de Recepción:</label>
                <input v-model="formConfig.fecha_fin_solicitud" type="date" :min="formConfig.fecha_inicio_solicitud" required />
              </div>
            </div>

            <h4 class="section-title">Evaluación de Dirección</h4>
            <div class="row">
              <label class="form-group col">
                Inicio de evaluación
                <input v-model="formConfig.fecha_inicio_evaluacion" type="date" required />
              </label>
              <label class="form-group col">
                Fin de evaluación
                <input v-model="formConfig.fecha_fin_evaluacion" type="date" :min="formConfig.fecha_inicio_evaluacion" required />
              </label>
            </div>

            <h4 class="section-title">Inscripción en CPD</h4>
            <div class="row">
              <label class="form-group col">
                Inicio de inscripción
                <input v-model="formConfig.fecha_inicio_ejecucion" type="date" required />
              </label>
              <label class="form-group col">
                Fin de inscripción
                <input v-model="formConfig.fecha_fin_ejecucion" type="date" :min="formConfig.fecha_inicio_ejecucion" required />
              </label>
            </div>

            <div class="modal-footer">
              <button type="button" @click="modalConfigPeriodo = false" class="btn-secondary">Cancelar</button>
              <button type="submit" class="btn-primary" :disabled="guardandoConfig">
                {{ guardandoConfig ? 'Guardando...' : 'Guardar Parámetros' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL RESPONDER CPD (CU6) -->
    <div v-if="modalRespuesta && solicitudActiva" class="modal-backdrop">
      <div class="modal-box">
        <div class="modal-header">
          <h3>CU6: Resolución Técnica para {{ solicitudActiva.codigo }}</h3>
          <button @click="modalRespuesta = false" class="btn-close"><X :size="18" /></button>
        </div>
        <form @submit.prevent="ejecutarRespuesta" class="modal-form">
          <div class="form-group">
            <label>Decisión Técnica (*):</label>
            <select v-model="formResp.resultado" @change="cambiarResultadoGeneral" required>
              <option value="HABILITADO">HABILITADO (Inscripción confirmada)</option>
              <option value="OBSERVADO">OBSERVADO</option>
              <option value="RECHAZADO">RECHAZADO</option>
            </select>
          </div>

          <!-- Selección granular de materias habilitadas por CPD -->
          <div v-if="solicitudActiva.grupos && solicitudActiva.grupos.length > 0" class="form-group">
            <label>Habilitación Técnica por Asignatura (CPD habilita cupo final):</label>
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Materia / Grupo</th>
                    <th>Estado de Habilitación</th>
                    <th>Observación Técnica</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="sg in formRespMaterias" :key="sg.id">
                    <td><strong>{{ sg.materia_sigla }}</strong> (Gr. {{ sg.sigla_grupo }})</td>
                    <td>
                      <select v-model="sg.estado" @change="verificarMateriasCpd" class="form-control-sm">
                        <option value="HABILITADA">HABILITADA (Cupo asignado)</option>
                        <option value="RECHAZADA_CPD">RECHAZADA (Sin cupo CPD)</option>
                        <option value="PENDIENTE">PENDIENTE</option>
                      </select>
                    </td>
                    <td>
                      <input v-model="sg.observacion" placeholder="Detalle técnico CPD..." class="form-control-sm" />
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            <small class="text-muted">Si todas las materias quedan rechazadas por falta de cupo, el trámite pasa a RECHAZADO automáticamente.</small>
          </div>
          <div class="form-group">
            <label>Código Único de Respuesta (*):</label>
            <input v-model="formResp.codigo_respuesta" required />
          </div>
          <div class="form-group">
            <label>Observaciones Técnicas:</label>
            <textarea v-model="formResp.observaciones" rows="3"></textarea>
          </div>
          <div class="modal-footer">
            <button type="button" @click="modalRespuesta = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="loading">Guardar Respuesta</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { RefreshCw, CheckSquare, X, Calendar } from '@lucide/vue';
import api from '../../services/api';

const oficios = ref([]);
const periodos = ref([]);
const periodoActivo = ref(null);
const loading = ref(false);
const guardandoConfig = ref(false);
const modalRespuesta = ref(false);
const modalConfigPeriodo = ref(false);
const oficioActivo = ref(null);
const solicitudActiva = ref(null);

const formResp = ref({ resultado: 'HABILITADO', codigo_respuesta: '', observaciones: '' });
const formRespMaterias = ref([]);
const formConfig = ref({
  costo_arancel_caso: 10,
  fecha_inicio_solicitud: '',
  fecha_fin_solicitud: '',
  fecha_inicio_evaluacion: '',
  fecha_fin_evaluacion: '',
  fecha_inicio_ejecucion: '',
  fecha_fin_ejecucion: '',
});

const cargarPendientes = async () => {
  try {
    const [resO, resP] = await Promise.all([
      api.get('/cpd/pendientes'),
      api.get('/catalogos/periodos')
    ]);
    oficios.value = resO.data;
    periodos.value = resP.data;
    if (periodos.value.length) {
      periodoActivo.value = periodos.value[0];
      formConfig.value.costo_arancel_caso = periodoActivo.value.costo_arancel_caso ?? 10;
      formConfig.value.max_materias_por_caso = periodoActivo.value.max_materias_por_caso || 5;
      formConfig.value.max_casos_carrera = periodoActivo.value.max_casos_carrera || 5;
      formConfig.value.fecha_inicio_solicitud = periodoActivo.value.fecha_inicio_solicitud || '';
      formConfig.value.fecha_fin_solicitud = periodoActivo.value.fecha_fin_solicitud || '';
      formConfig.value.fecha_inicio_evaluacion = periodoActivo.value.fecha_inicio_evaluacion || '';
      formConfig.value.fecha_fin_evaluacion = periodoActivo.value.fecha_fin_evaluacion || '';
      formConfig.value.fecha_inicio_ejecucion = periodoActivo.value.fecha_inicio_ejecucion || '';
      formConfig.value.fecha_fin_ejecucion = periodoActivo.value.fecha_fin_ejecucion || '';
    }
  } catch (e) {
    console.error(e);
  }
};

const guardarConfigPeriodo = async () => {
  if (!periodoActivo.value?.id) return;
  guardandoConfig.value = true;
  try {
    const res = await api.put(`/cpd/periodo/${periodoActivo.value.id}/config`, formConfig.value);
    periodoActivo.value = res.data.periodo;
    modalConfigPeriodo.value = false;
    alert('Parámetros de cronograma y arancel de casos especiales actualizados exitosamente.');
  } catch (e) {
    alert(e.response?.data?.error || 'Error al guardar configuración.');
  } finally {
    guardandoConfig.value = false;
  }
};

const abrirRespuesta = (oficio, sol) => {
  oficioActivo.value = oficio;
  solicitudActiva.value = sol;
  formResp.value.codigo_respuesta = `CPD-RES-${Date.now().toString().slice(-6)}`;
  formResp.value.observaciones = '';
  formRespMaterias.value = (sol.grupos || []).map(sg => ({
    id: sg.id,
    materia_sigla: sg.grupo?.materia?.sigla || 'MAT',
    sigla_grupo: sg.grupo?.sigla_grupo || 'SA',
    estado: sg.estado === 'RECHAZADA' ? 'RECHAZADA_CPD' : 'HABILITADA',
    observacion: sg.observacion_resolucion || ''
  }));
  verificarMateriasCpd();
  modalRespuesta.value = true;
};

const verificarMateriasCpd = () => {
  if (!formRespMaterias.value.length) return;
  const tieneHabilitadas = formRespMaterias.value.some(m => m.estado === 'HABILITADA');
  if (!tieneHabilitadas) {
    formResp.value.resultado = 'RECHAZADO';
    if (!formResp.value.observaciones) {
      formResp.value.observaciones = 'Sin cupo técnico disponible en las materias solicitadas.';
    }
  }
};

const cambiarResultadoGeneral = () => {
  if (formResp.value.resultado === 'HABILITADO') {
    const tieneHabilitadas = formRespMaterias.value.some(m => m.estado === 'HABILITADA');
    if (!tieneHabilitadas && formRespMaterias.value.length > 0) {
      formRespMaterias.value[0].estado = 'HABILITADA';
    }
  } else if (formResp.value.resultado === 'RECHAZADO') {
    formRespMaterias.value.forEach(m => m.estado = 'RECHAZADA_CPD');
  }
};

const ejecutarRespuesta = async () => {
  verificarMateriasCpd();
  loading.value = true;
  try {
    await api.post('/cpd/responder', {
      id_oficio: oficioActivo.value.id,
      id_solicitud: solicitudActiva.value.id,
      resultado: formResp.value.resultado,
      codigo_respuesta: formResp.value.codigo_respuesta,
      observaciones: formResp.value.observaciones,
      materias_habilitadas: formRespMaterias.value.map(m => ({
        id: m.id,
        estado: m.estado,
        observacion: m.observacion
      }))
    });
    modalRespuesta.value = false;
    alert('Respuesta CPD procesada. Estudiante y Dirección notificados.');
    cargarPendientes();
  } catch (e) {
    alert(e.response?.data?.error || 'Error al procesar respuesta CPD.');
  } finally {
    loading.value = false;
  }
};

onMounted(cargarPendientes);
</script>

<style scoped>
.role-view {
  background: var(--surface);
  border-radius: 12px;
  padding: 1.5rem;
  border: 1px solid var(--border);
}
.view-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
  gap: 1.5rem;
  flex-wrap: wrap;
}
.header-text {
  flex: 1;
  min-width: 280px;
}
.header-text h2 {
  margin: 0;
  color: var(--text);
}
.view-desc {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin: 0.3rem 0 0 0;
}
.header-action-buttons {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
}
.inline-icon {
  vertical-align: middle;
  flex-shrink: 0;
}
.oficio-card {
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1.5rem;
  background: var(--surface-alt);
}
.oficio-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.oficio-ref {
  margin-left: 0.8rem;
  font-size: 0.9rem;
  color: #475569;
}
.data-table {
  width: 100%;
  border-collapse: collapse;
  background: var(--surface);
  border-radius: 6px;
  overflow: hidden;
}
.data-table th, .data-table td {
  padding: 0.8rem 1rem;
  border-bottom: 1px solid var(--border);
}
.code-tag {
  background: #e2e8f0;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  font-family: monospace;
}
.status-badge {
  padding: 0.25rem 0.65rem;
  border-radius: 20px;
  font-size: 0.75rem;
  font-weight: 700;
}
.status-badge.REMITIDA_A_CPD { background: #f3e8ff; color: #7e22ce; }
.btn-action {
  padding: 0.35rem 0.7rem;
  border: 1px solid var(--border);
  background: var(--surface);
  border-radius: 4px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.btn-pay { background: var(--primary); color: var(--on-primary); border-color: var(--primary); }
.btn-pay:hover { background: var(--primary-hover); border-color: var(--primary-hover); }
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 1rem;
}
.modal-box {
  background: var(--surface);
  border-radius: 12px;
  width: 100%;
  max-width: 540px;
  padding: 1.5rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}
.modal-box.modal-lg {
  max-width: 680px;
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 1px solid var(--border);
  padding-bottom: 0.8rem;
  margin-bottom: 1rem;
}
.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  color: var(--text);
}
.modal-subtitle {
  margin: 0.2rem 0 0 0;
  font-size: 0.85rem;
  color: var(--text-muted);
}
.btn-close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  padding: 0.3rem;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-close:hover {
  background: var(--surface-alt);
  color: var(--text);
}
.modal-body-scroll {
  overflow-y: auto;
  max-height: calc(90vh - 120px);
  padding-right: 0.5rem;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.8rem;
  margin-top: 1.5rem;
  border-top: 1px solid var(--border);
  padding-top: 1rem;
}
.section-title {
  font-size: 0.98rem;
  font-weight: 700;
  color: var(--text);
  margin: 1.2rem 0 0.6rem 0;
  padding-bottom: 0.3rem;
  border-bottom: 1px solid var(--border);
}
.alert-box-info {
  background: var(--primary-subtle);
  border: 1px solid var(--primary-text);
  color: var(--text);
  padding: 0.75rem 1rem;
  border-radius: 6px;
  font-size: 0.85rem;
}
.row {
  display: flex;
  gap: 1rem;
  margin-bottom: 0.5rem;
}
.col {
  flex: 1;
}
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.3rem; color: var(--text); }
.form-group select, .form-group input, .form-group textarea {
  width: 100%;
  padding: 0.65rem 0.8rem;
  border: 1px solid var(--border);
  border-radius: 6px;
  box-sizing: border-box;
  font-size: 0.9rem;
}
.form-group select:focus, .form-group input:focus, .form-group textarea:focus {
  outline: none;
  border-color: var(--border-focus);
  box-shadow: 0 0 0 2px rgba(0, 100, 224, 0.15);
}
.form-control-sm {
  padding: 0.35rem 0.5rem !important;
  font-size: 0.85rem !important;
}
.text-muted {
  color: var(--text-muted);
  font-size: 0.8rem;
  display: block;
  margin-top: 0.2rem;
}
</style>
