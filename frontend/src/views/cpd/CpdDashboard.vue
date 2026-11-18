<template>
  <div class="role-view">
    <div class="view-header">
      <div>
        <h2>Centro de Procesamiento de Datos (CU6 - CPD)</h2>
        <p class="view-desc">Procesamiento técnico final de casos especiales remitidos por Dirección.</p>
      </div>
      <button @click="cargarPendientes" class="btn-secondary">
        <RefreshCw :size="15" class="inline-icon" /> Actualizar Bandeja
      </button>
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
            <select v-model="formResp.resultado" required>
              <option value="HABILITADO">HABILITADO (Inscripción confirmada)</option>
              <option value="OBSERVADO">OBSERVADO</option>
              <option value="RECHAZADO">RECHAZADO</option>
            </select>
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
import { RefreshCw, CheckSquare, X } from '@lucide/vue';
import api from '../../services/api';

const oficios = ref([]);
const loading = ref(false);
const modalRespuesta = ref(false);
const oficioActivo = ref(null);
const solicitudActiva = ref(null);

const formResp = ref({ resultado: 'HABILITADO', codigo_respuesta: '', observaciones: '' });

const cargarPendientes = async () => {
  try {
    const res = await api.get('/cpd/pendientes');
    oficios.value = res.data;
  } catch (e) {
    console.error(e);
  }
};

const abrirRespuesta = (oficio, sol) => {
  oficioActivo.value = oficio;
  solicitudActiva.value = sol;
  formResp.value.codigo_respuesta = `CPD-RES-${Date.now().toString().slice(-6)}`;
  formResp.value.observaciones = '';
  modalRespuesta.value = true;
};

const ejecutarRespuesta = async () => {
  loading.value = true;
  try {
    await api.post('/cpd/responder', {
      id_oficio: oficioActivo.value.id,
      id_solicitud: solicitudActiva.value.id,
      resultado: formResp.value.resultado,
      codigo_respuesta: formResp.value.codigo_respuesta,
      observaciones: formResp.value.observaciones
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
.btn-secondary {
  background: #64748b;
  color: white;
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.inline-icon {
  vertical-align: middle;
}
.oficio-card {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1.5rem;
  background: #f8fafc;
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
  background: white;
  border-radius: 6px;
  overflow: hidden;
}
.data-table th, .data-table td {
  padding: 0.8rem 1rem;
  border-bottom: 1px solid #e2e8f0;
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
  border: 1px solid #cbd5e1;
  background: white;
  border-radius: 4px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.btn-pay { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
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
</style>
