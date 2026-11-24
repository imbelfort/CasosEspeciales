<template>
  <div class="role-view">
    <div class="view-header">
      <div>
        <h2>Mis Solicitudes de Casos Especiales (CU2)</h2>
        <p class="view-desc">
          Como estudiante regular, puedes registrar nuevas solicitudes, asociar materias de tu carrera, adjuntar respaldos y subir el comprobante de arancel una vez aprobado por Dirección.
        </p>
      </div>
      <button @click="abrirModalRegistro" class="btn-primary">
        <Plus :size="16" class="btn-icon" /> Registrar Nueva Solicitud (CU2)
      </button>
    </div>

    <!-- BANDEJA DE MIS CASOS -->
    <div class="table-responsive">
      <table class="data-table">
        <thead>
          <tr>
            <th>Código de Solicitud</th>
            <th>Período</th>
            <th>Tipo de Caso</th>
            <th>Estado</th>
            <th>Fecha Envío</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sol in solicitudes" :key="sol.id">
            <td><strong class="code-tag">{{ sol.codigo }}</strong></td>
            <td>{{ sol.periodo?.gestion }}</td>
            <td>{{ sol.tipo_caso }}</td>
            <td><span :class="['status-badge', sol.estado]">{{ sol.estado }}</span></td>
            <td>{{ formatearFecha(sol.fecha_solicitud) }}</td>
            <td class="actions-cell">
              <button @click="verDetalle(sol)" class="btn-action">
                <FileSearch :size="14" class="inline-icon" /> Expediente
              </button>

              <!-- Agregar Materia / Grupo si la solicitud está en ENVIADA -->
              <button v-if="sol.estado === 'ENVIADA'" @click="abrirAgregarGrupo(sol)" class="btn-action btn-add">
                <BookPlus :size="14" class="inline-icon" /> Agregar Materia
              </button>

              <!-- Adjuntar archivo de respaldo si está ENVIADA u OBSERVADA -->
              <button v-if="sol.estado === 'ENVIADA' || sol.estado === 'OBSERVADA'" @click="abrirAdjuntarRespaldo(sol)" class="btn-action btn-file">
                <Paperclip :size="14" class="inline-icon" /> Respaldo
              </button>

              <!-- Módulo de Cobros: Pagar por QR PagoFácil o Adjuntar Comprobante (CU5) -->
              <div v-if="sol.estado === 'APROBADA_DIRECCION'" class="pay-actions-group">
                <button @click="abrirPagoQr(sol)" class="btn-action btn-pay-qr" title="Pagar arancel inmediatamente con QR bancario">
                  <QrCode :size="14" class="inline-icon" /> Pagar con QR
                </button>
                <button @click="abrirPago(sol)" class="btn-action btn-pay" title="Subir boleta física de depósito">
                  <UploadCloud :size="14" class="inline-icon" /> Subir Boleta
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="solicitudes.length === 0">
            <td colspan="6" class="no-records">No tienes casos especiales solicitados. Haz clic en 'Registrar Nueva Solicitud'.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL REGISTRO DE SOLICITUD COMPLETO (CU2) -->
    <div v-if="modalNueva" class="modal-backdrop">
      <div class="modal-box modal-lg">
        <div class="modal-header">
          <h3>CU2: Formulario de Solicitud de Caso Especial</h3>
          <button @click="modalNueva = false" class="btn-close">
            <X :size="18" />
          </button>
        </div>
        <form @submit.prevent="guardarSolicitud" class="modal-form">
          <div class="row">
            <div class="form-group col">
              <label>Período Académico Activo (*):</label>
              <select v-model="formNueva.id_periodo" required>
                <option value="" disabled>Seleccione período...</option>
                <option v-for="p in periodos" :key="p.id" :value="p.id">Gestión {{ p.gestion }} ({{ p.estado }})</option>
              </select>
            </div>
            <div class="form-group col">
              <label>Tipo de Caso Especial (*):</label>
              <select v-model="formNueva.tipo_caso" required>
                <option value="LEVANTAMIENTO_PREREQUISITO">Levantamiento de Prerrequisito</option>
                <option value="EXCESO_MATERIAS">Exceso de Materias</option>
                <option value="PARALELO_LLENO">Paralelo Lleno (Sobrecupo)</option>
                <option value="NIVELACION">Nivelación</option>
                <option value="CASO_ESPECIAL">Caso Especial General</option>
                <option value="CORRECCION_NOTA">Corrección de Nota</option>
                <option value="INCLUSION_MATERIA">Inclusión de Materia</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label>Materia y Grupo Solicitado (Opcional en registro inicial):</label>
            <select v-model="formNueva.id_grupo">
              <option value="">(Asignar más adelante o seleccionar ahora...)</option>
              <optgroup v-for="m in materias" :key="m.id" :label="m.sigla + ' - ' + m.nombre">
                <option v-for="g in m.grupos" :key="g.id" :value="g.id">
                  Grupo {{ g.sigla_grupo }} (Cupo máx: {{ g.cupo_maximo }} | Disponibles: {{ g.cupos_disponibles }})
                </option>
              </optgroup>
            </select>
          </div>

          <div class="form-group">
            <label>Motivo y Justificación Académica (*):</label>
            <textarea v-model="formNueva.motivo" rows="4" required placeholder="Detalla la situación que motiva tu solicitud de caso especial..."></textarea>
          </div>

          <div class="form-group">
            <label>Documento de Respaldo Inicial (Opcional - PDF / Imagen):</label>
            <input type="file" @change="e => archivoRespaldoInicial = e.target.files[0]" accept=".pdf,.jpg,.jpeg,.png" />
          </div>

          <div class="modal-footer">
            <button type="button" @click="modalNueva = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="loading">
              {{ loading ? 'Enviando...' : 'Presentar Solicitud' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL AGREGAR MATERIA / GRUPO (CU2) -->
    <div v-if="modalGrupo" class="modal-backdrop">
      <div class="modal-box">
        <div class="modal-header">
          <h3>Vincular Materia a Solicitud {{ solicitudActiva?.codigo }}</h3>
          <button @click="modalGrupo = false" class="btn-close">
            <X :size="18" />
          </button>
        </div>
        <form @submit.prevent="guardarGrupo" class="modal-form">
          <p class="auth-desc">Solo se permiten materias pertenecientes a tu carrera y plan de estudio.</p>
          <div class="form-group">
            <label>Seleccionar Materia y Grupo (*):</label>
            <select v-model="formGrupo.id_grupo" required>
              <option value="" disabled>Seleccione...</option>
              <optgroup v-for="m in materias" :key="m.id" :label="m.sigla + ' - ' + m.nombre">
                <option v-for="g in m.grupos" :key="g.id" :value="g.id">
                  Grupo {{ g.sigla_grupo }} (Cupos libres: {{ g.cupos_disponibles }})
                </option>
              </optgroup>
            </select>
          </div>
          <div class="form-group">
            <label>Motivo específico para esta materia (opcional):</label>
            <input v-model="formGrupo.motivo" placeholder="Ej: Choque de horario con otra asignatura" />
          </div>
          <div class="modal-footer">
            <button type="button" @click="modalGrupo = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="loading">Agregar Materia</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL ADJUNTAR ARCHIVO RESPALDO (CU2) -->
    <div v-if="modalRespaldo" class="modal-backdrop">
      <div class="modal-box">
        <div class="modal-header">
          <h3>Adjuntar Respaldo a {{ solicitudActiva?.codigo }}</h3>
          <button @click="modalRespaldo = false" class="btn-close">
            <X :size="18" />
          </button>
        </div>
        <form @submit.prevent="subirRespaldo" class="modal-form">
          <div class="form-group">
            <label>Tipo de Documento:</label>
            <select v-model="formRespaldo.tipo_archivo">
              <option value="RESPALDO">Documento de Respaldo Académico</option>
              <option value="FORMULARIO">Formulario de Solicitud Firmado</option>
              <option value="OTRO">Otro Documento</option>
            </select>
          </div>
          <div class="form-group">
            <label>Archivo (PDF / Imagen):</label>
            <input type="file" @change="e => archivoRespaldo = e.target.files[0]" required accept=".pdf,.jpg,.jpeg,.png" />
          </div>
          <div class="modal-footer">
            <button type="button" @click="modalRespaldo = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="loading">Subir Archivo</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL PAGO DEL ARANCEL (CU5) -->
    <div v-if="modalPago" class="modal-backdrop">
      <div class="modal-box">
        <div class="modal-header">
          <h3>CU5: Comprobante de Pago - {{ solicitudActiva?.codigo }}</h3>
          <button @click="modalPago = false" class="btn-close">
            <X :size="18" />
          </button>
        </div>
        <form @submit.prevent="subirComprobante" class="modal-form">
          <div class="alert-box-info">
            Tu caso ha sido <strong>APROBADO POR DIRECCIÓN</strong>. Sube tu boleta de pago bancaria para que tu solicitud pase al estado <strong>APROBADA</strong> y sea incluida en el oficio remitido a CPD.
          </div>
          <div class="form-group mt-3">
            <label>Comprobante de Pago (PDF, JPG, PNG) (*):</label>
            <input type="file" @change="e => archivoComprobante = e.target.files[0]" required accept=".pdf,.jpg,.jpeg,.png" />
          </div>
          <div class="modal-footer">
            <button type="button" @click="modalPago = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="loading">Confirmar Pago</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL INTEGRADO PAGOFÁCIL QR -->
    <PagoQrModal 
      :visible="modalPagoQr" 
      :solicitud="solicitudActiva" 
      @close="modalPagoQr = false" 
      @pago-completado="onPagoQrCompletado" 
    />

    <!-- MODAL DETALLE / EXPEDIENTE COMPLETO (CU7) -->
    <div v-if="modalDetalle && solicitudActiva" class="modal-backdrop">
      <div class="modal-box modal-lg">
        <div class="modal-header">
          <h3>Expediente del Caso {{ solicitudActiva.codigo }}</h3>
          <button @click="modalDetalle = false" class="btn-close">
            <X :size="18" />
          </button>
        </div>
        <div class="modal-body-scroll">
          <div class="expediente-meta">
            <div><strong>Tipo de Caso:</strong> {{ solicitudActiva.tipo_caso }}</div>
            <div><strong>Estado:</strong> <span :class="['status-badge', solicitudActiva.estado]">{{ solicitudActiva.estado }}</span></div>
            <div><strong>Fecha Envío:</strong> {{ formatearFecha(solicitudActiva.fecha_solicitud) }}</div>
          </div>
          <div class="box-note mt-3">
            <strong>Motivo del Estudiante:</strong>
            <p>{{ solicitudActiva.motivo }}</p>
          </div>

          <div v-if="solicitudActiva.evaluacion" class="box-note-eval mt-3">
            <strong>Resolución de Dirección:</strong> {{ solicitudActiva.evaluacion.decision }}<br>
            <span v-if="solicitudActiva.evaluacion.justificacion"><strong>Justificación:</strong> {{ solicitudActiva.evaluacion.justificacion }}</span>
            <span v-if="solicitudActiva.motivo_rechazo" class="text-danger"><br><strong>Motivo de Rechazo:</strong> {{ solicitudActiva.motivo_rechazo }}</span>
          </div>

          <h4 class="mt-4">Materias / Grupos Asignados</h4>
          <ul class="clean-list">
            <li v-for="sg in solicitudActiva.grupos" :key="sg.id">
              <BookOpen :size="15" class="inline-icon text-primary" />
              <strong>{{ sg.grupo?.materia?.sigla }}</strong> - {{ sg.grupo?.materia?.nombre }} (Grupo {{ sg.grupo?.sigla_grupo }})
            </li>
            <li v-if="!solicitudActiva.grupos || solicitudActiva.grupos.length === 0" class="text-muted">
              No se han vinculado materias aún a este caso.
            </li>
          </ul>

          <h4 class="mt-4">Archivos y Comprobantes</h4>
          <ul class="clean-list">
            <li v-for="arc in solicitudActiva.archivos" :key="arc.id">
              <FolderArchive :size="15" class="inline-icon text-info" />
              <strong>{{ arc.tipo_archivo }}:</strong> {{ arc.nombre_archivo }} ({{ formatearFecha(arc.fecha_subida) }})
            </li>
            <li v-if="!solicitudActiva.archivos || solicitudActiva.archivos.length === 0" class="text-muted">
              Sin archivos adjuntos.
            </li>
          </ul>

          <h4 class="mt-4">Línea de Tiempo y Auditoría Transaccional (CU7)</h4>
          <div class="timeline">
            <div v-for="h in solicitudActiva.historial" :key="h.id" class="timeline-item">
              <span class="timeline-dot"></span>
              <div class="timeline-content">
                <strong>{{ h.accion }}</strong>: <span class="badge-sm">{{ h.estado_anterior || 'INICIO' }}</span> <ArrowRight :size="12" class="inline-icon" /> <span class="badge-sm">{{ h.estado_nuevo }}</span>
                <p class="timeline-obs">{{ h.observacion }}</p>
                <small class="text-muted">{{ formatearFecha(h.fecha_evento) }}</small>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button @click="modalDetalle = false" class="btn-secondary">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { 
  Plus, 
  FileSearch, 
  BookPlus, 
  Paperclip, 
  CreditCard, 
  QrCode,
  UploadCloud,
  X, 
  BookOpen, 
  FolderArchive, 
  ArrowRight 
} from '@lucide/vue';
import PagoQrModal from '../../components/pago/PagoQrModal.vue';
import api from '../../services/api';

const solicitudes = ref([]);
const periodos = ref([]);
const materias = ref([]);
const loading = ref(false);

const modalNueva = ref(false);
const modalGrupo = ref(false);
const modalRespaldo = ref(false);
const modalPago = ref(false);
const modalPagoQr = ref(false);
const modalDetalle = ref(false);
const solicitudActiva = ref(null);

const archivoRespaldoInicial = ref(null);
const archivoRespaldo = ref(null);
const archivoComprobante = ref(null);

const formNueva = ref({ id_periodo: '', tipo_caso: 'LEVANTAMIENTO_PREREQUISITO', motivo: '', id_grupo: '' });
const formGrupo = ref({ id_grupo: '', motivo: '' });
const formRespaldo = ref({ tipo_archivo: 'RESPALDO' });

const formatearFecha = (f) => f ? new Date(f).toLocaleString('es-BO') : 'N/A';

const cargarDatos = async () => {
  try {
    const [resS, resP, resM] = await Promise.all([
      api.get('/solicitudes/mis-casos'),
      api.get('/catalogos/periodos'),
      api.get('/catalogos/materias')
    ]);
    solicitudes.value = resS.data;
    periodos.value = resP.data;
    materias.value = resM.data;
    if (periodos.value.length && !formNueva.value.id_periodo) {
      formNueva.value.id_periodo = periodos.value[0].id;
    }
  } catch (e) {
    console.error(e);
  }
};

const abrirModalRegistro = () => {
  if (periodos.value.length) formNueva.value.id_periodo = periodos.value[0].id;
  archivoRespaldoInicial.value = null;
  modalNueva.value = true;
};

const guardarSolicitud = async () => {
  loading.value = true;
  try {
    const fd = new FormData();
    fd.append('id_periodo', formNueva.value.id_periodo);
    fd.append('tipo_caso', formNueva.value.tipo_caso);
    fd.append('motivo', formNueva.value.motivo);
    if (formNueva.value.id_grupo) {
      fd.append('id_grupo', formNueva.value.id_grupo);
    }
    if (archivoRespaldoInicial.value) {
      fd.append('archivo_respaldo', archivoRespaldoInicial.value);
    }

    await api.post('/solicitudes', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    modalNueva.value = false;
    formNueva.value.motivo = '';
    formNueva.value.id_grupo = '';
    archivoRespaldoInicial.value = null;
    alert('Solicitud registrada exitosamente.');
    cargarDatos();
  } catch (e) {
    alert(e.response?.data?.error || 'Error al crear solicitud.');
  } finally {
    loading.value = false;
  }
};

const verDetalle = async (sol) => {
  try {
    const res = await api.get(`/solicitudes/${sol.id}`);
    solicitudActiva.value = res.data;
    modalDetalle.value = true;
  } catch (e) {
    alert('Error al cargar expediente.');
  }
};

const abrirAgregarGrupo = (sol) => {
  solicitudActiva.value = sol;
  formGrupo.value.id_grupo = '';
  formGrupo.value.motivo = '';
  modalGrupo.value = true;
};

const guardarGrupo = async () => {
  loading.value = true;
  try {
    await api.post(`/solicitudes/${solicitudActiva.value.id}/grupos`, formGrupo.value);
    modalGrupo.value = false;
    alert('Materia agregada exitosamente.');
    cargarDatos();
  } catch (e) {
    alert(e.response?.data?.error || 'Error al agregar materia.');
  } finally {
    loading.value = false;
  }
};

const abrirAdjuntarRespaldo = (sol) => {
  solicitudActiva.value = sol;
  archivoRespaldo.value = null;
  modalRespaldo.value = true;
};

const subirRespaldo = async () => {
  if (!archivoRespaldo.value) return;
  loading.value = true;
  try {
    const fd = new FormData();
    fd.append('archivo', archivoRespaldo.value);
    fd.append('tipo_archivo', formRespaldo.value.tipo_archivo);
    await api.post(`/solicitudes/${solicitudActiva.value.id}/archivos`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    modalRespaldo.value = false;
    alert('Documento de respaldo adjuntado correctamente.');
    cargarDatos();
  } catch (e) {
    alert(e.response?.data?.error || 'Error al adjuntar archivo.');
  } finally {
    loading.value = false;
  }
};

const abrirPagoQr = (sol) => {
  solicitudActiva.value = sol;
  modalPagoQr.value = true;
};

const onPagoQrCompletado = (pago) => {
  cargarDatos();
};

const abrirPago = (sol) => {
  solicitudActiva.value = sol;
  archivoComprobante.value = null;
  modalPago.value = true;
};

const subirComprobante = async () => {
  if (!archivoComprobante.value) return;
  loading.value = true;
  try {
    const fd = new FormData();
    fd.append('comprobante', archivoComprobante.value);
    await api.post(`/solicitudes/${solicitudActiva.value.id}/pago`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    modalPago.value = false;
    alert('Comprobante verificado. Estado actualizado a APROBADA.');
    cargarDatos();
  } catch (e) {
    alert(e.response?.data?.error || 'Error al adjuntar comprobante.');
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
.view-desc {
  color: #64748b;
  font-size: 0.9rem;
  max-width: 800px;
  margin: 0.3rem 0 0 0;
}
.btn-primary {
  background: #1e3a8a;
  color: white;
  border: none;
  padding: 0.65rem 1.25rem;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.btn-secondary {
  background: #64748b;
  color: white;
  border: none;
  padding: 0.6rem 1.2rem;
  border-radius: 6px;
  cursor: pointer;
}
.btn-icon, .inline-icon {
  flex-shrink: 0;
  vertical-align: middle;
}
.table-responsive {
  overflow-x: auto;
}
.data-table {
  width: 100%;
  border-collapse: collapse;
}
.data-table th, .data-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  text-align: left;
  font-size: 0.9rem;
}
.data-table th {
  background: #f8fafc;
  color: #475569;
  font-weight: 600;
}
.code-tag {
  background: #f1f5f9;
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
.status-badge.ENVIADA { background: #fef3c7; color: #b45309; }
.status-badge.APROBADA_DIRECCION { background: #dbeafe; color: #1d4ed8; }
.status-badge.APROBADA { background: #dcfce7; color: #15803d; }
.status-badge.RECHAZADA_DIRECCION { background: #fee2e2; color: #b91c1c; }
.status-badge.REMITIDA_A_CPD { background: #f3e8ff; color: #7e22ce; }
.status-badge.HABILITADA { background: #bbf7d0; color: #166534; }
.status-badge.OBSERVADA { background: #ffedd5; color: #c2410c; }
.actions-cell {
  display: flex;
  gap: 0.4rem;
}
.btn-action {
  padding: 0.35rem 0.7rem;
  border: 1px solid #cbd5e1;
  background: white;
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.btn-add { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
.btn-file { background: #f8fafc; color: #334155; }
.btn-pay { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
.pay-actions-group {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}
.btn-pay-qr {
  background: var(--accent, #7c3aed);
  color: #ffffff;
  border-color: transparent;
}
.btn-pay-qr:hover {
  filter: brightness(1.1);
}
.no-records {
  text-align: center;
  padding: 2rem;
  color: #94a3b8;
}
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
.btn-close { background: transparent; border: none; cursor: pointer; color: #64748b; }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.3rem; }
.form-group select, .form-group input, .form-group textarea {
  width: 100%;
  padding: 0.65rem 0.8rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
}
.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 0.8rem;
  margin-top: 1.5rem;
}
.row { display: flex; gap: 1rem; }
.col { flex: 1; }
.alert-box-info {
  background: #eff6ff;
  border-left: 4px solid #3b82f6;
  padding: 0.8rem 1rem;
  font-size: 0.9rem;
  color: #1e40af;
}
.clean-list { list-style: none; padding: 0; }
.clean-list li { padding: 0.4rem 0; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 0.4rem; }
.timeline { border-left: 2px solid #e2e8f0; padding-left: 1rem; margin-top: 1rem; }
.timeline-item { position: relative; margin-bottom: 1rem; }
.timeline-dot { position: absolute; left: -1.35rem; top: 0.2rem; width: 10px; height: 10px; background: #1e3a8a; border-radius: 50%; }
.mt-3 { margin-top: 1rem; }
.mt-4 { margin-top: 1.5rem; }
</style>
