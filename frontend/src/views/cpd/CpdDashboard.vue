<template>
  <div class="role-view">
    <div class="view-header">
      <div class="header-text">
        <h2>Centro de Procesamiento de Datos (CU6 - CPD)</h2>
        <p class="view-desc">Procesamiento técnico final de casos especiales remitidos por Dirección y gestión del cronograma.</p>
      </div>
      <div class="header-action-buttons">
        <button @click="modalConfigPeriodo = true" class="btn-primary">
          <Calendar :size="15" class="inline-icon" /> {{ esCpd ? 'Parámetros y arancel' : 'Parámetros' }}
        </button>
        <button @click="actualizarPanel" class="btn-secondary">
          <RefreshCw :size="15" class="inline-icon" /> Actualizar
        </button>
      </div>
    </div>

    <p v-if="avisoResolucion" role="status">{{ avisoResolucion }}</p>

    <nav class="secciones-cpd" aria-label="Vistas de CPD">
      <button v-for="seccion in secciones" :key="seccion.id" type="button" class="btn-action"
        :aria-pressed="vista === seccion.id" @click="seleccionarVista(seccion.id)">{{ seccion.nombre }}</button>
    </nav>

    <section v-if="vista === 'pendientes'" aria-label="Solicitudes pendientes de CPD">
      <p v-if="errorPendientes" role="alert">{{ errorPendientes }}</p>
      <p v-else-if="cargandoPendientes" role="status">Cargando solicitudes pendientes…</p>
      <!-- OFICIOS REMITIDOS A CPD -->
      <div v-for="oficio in oficios" :key="oficio.id" class="oficio-card">
        <div class="oficio-header">
          <div>
            <strong class="code-tag">{{ oficio.numero_oficio }}</strong>
            <span class="oficio-ref">{{ oficio.referencia }}</span>
          </div>
          <span class="status-badge REMITIDA_A_CPD">Emitido</span>
        </div>

        <div class="table-responsive mt-3" tabindex="0" role="region" aria-label="Solicitudes del oficio">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Código Solicitud</th>
                <th scope="col">Estudiante</th>
                <th scope="col">Registro</th>
                <th scope="col">Materia / Grupo</th>
                <th scope="col">Acción Técnica</th>
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
                  <button type="button" @click="abrirRespuesta(oficio, sol)" :disabled="loading" class="btn-action btn-pay">
                    <CheckSquare :size="14" class="inline-icon" /> Resolver CPD (CU6)
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="!cargandoPendientes && !errorPendientes && oficios.length === 0" class="no-records">
        No hay oficios pendientes de habilitación en CPD.
      </div>
    </section>

    <section v-else class="oficio-card" aria-labelledby="titulo-oficios-emitidos">
      <h3 id="titulo-oficios-emitidos">Oficios emitidos</h3>
      <p v-if="errorEmitidos" role="alert">{{ errorEmitidos }}</p>
      <p v-if="cargandoEmitidos" role="status">Cargando oficios…</p>
      <p v-else-if="emitidos && !emitidos.data.length">Aún no hay oficios emitidos.</p>
      <template v-if="emitidos?.data.length">
        <div class="table-responsive" tabindex="0" role="region" aria-label="Historial de oficios emitidos">
          <table class="data-table">
            <thead>
              <tr><th scope="col">Oficio</th><th scope="col">Fecha de remisión</th><th scope="col">Destinatario</th><th scope="col">Casos remitidos</th><th scope="col">Pendientes en CPD</th></tr>
            </thead>
            <tbody>
              <tr v-for="oficio in emitidos.data" :key="oficio.id">
                <th scope="row">{{ oficio.numero_oficio }}<small v-if="oficio.referencia" class="referencia-emitido">{{ oficio.referencia }}</small></th>
                <td>{{ fechaOficio(oficio.fecha_remision) }}</td>
                <td>{{ oficio.destinatario }}</td>
                <td>{{ entero.format(oficio.solicitudes_count) }}</td>
                <td>{{ oficio.pendientes_count ? entero.format(oficio.pendientes_count) : 'Sin pendientes' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <nav v-if="emitidos.last_page > 1" class="paginas-emitidos" aria-label="Páginas de oficios emitidos">
          <button type="button" class="btn-action" :disabled="cargandoEmitidos || emitidos.current_page <= 1" @click="cargarEmitidos(emitidos.current_page - 1)">Anterior</button>
          <span>Página {{ emitidos.current_page }} de {{ emitidos.last_page }}</span>
          <button type="button" class="btn-action" :disabled="cargandoEmitidos || !emitidos.next_page_url" @click="cargarEmitidos(emitidos.current_page + 1)">Siguiente</button>
        </nav>
      </template>
    </section>

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
              Configura las fechas del cronograma{{ esCpd ? ' y el monto del arancel' : '' }}. El plazo de pago va del inicio de evaluación al fin de inscripción en CPD.
            </div>
            
            <div class="row">
              <label v-if="esCpd" class="form-group col">
                Arancel por caso especial (Bs.)
                <input v-model.number="formConfig.costo_arancel_caso" type="number" min="0" step="0.01" required />
              </label>
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
      <div class="modal-box modal-respuesta" role="dialog" aria-modal="true" aria-labelledby="titulo-respuesta-cpd">
        <div class="modal-header">
          <h3 id="titulo-respuesta-cpd">CU6: Resolución Técnica para {{ solicitudActiva.codigo }}</h3>
          <button type="button" @click="modalRespuesta = false" class="btn-close" aria-label="Cerrar" :disabled="loading"><X :size="18" /></button>
        </div>
        <form @submit.prevent="ejecutarRespuesta" class="modal-form">
          <div class="respuesta-campos">
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
              <div class="table-responsive" tabindex="0" role="region" aria-label="Respuesta por materia">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th scope="col">Materia / Grupo</th>
                      <th scope="col">Estado de Habilitación</th>
                      <th scope="col">Observación Técnica</th>
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
              <label for="codigo-respuesta-cpd">Código Único de Respuesta (*):</label>
              <input id="codigo-respuesta-cpd" type="text" v-model="formResp.codigo_respuesta" required />
            </div>
            <div class="form-group">
              <label>Observaciones Técnicas:</label>
              <textarea v-model="formResp.observaciones" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" @click="modalRespuesta = false" class="btn-secondary" :disabled="loading">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="loading">{{ loading ? 'Guardando…' : 'Confirmar resolución' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { RefreshCw, CheckSquare, X, Calendar } from '@lucide/vue';
import api from '../../services/api';

const props = defineProps({ usuario: Object });
const esCpd = computed(() => props.usuario?.roles?.some(rol => rol.nombre === 'CPD'));
const secciones = [
  { id: 'pendientes', nombre: 'Pendientes' },
  { id: 'emitidos', nombre: 'Oficios emitidos' },
];
const vista = ref('pendientes');
const emitidos = ref(null);
const cargandoEmitidos = ref(false);
const errorEmitidos = ref('');
const entero = new Intl.NumberFormat('es-BO');
const fechaRemision = new Intl.DateTimeFormat('es-BO', { day: 'numeric', month: 'short', year: 'numeric' });
const fechaOficio = (fecha) => fecha ? fechaRemision.format(new Date(fecha.slice(0, 10) + 'T12:00:00')) : '—';
let revisionEmitidos = 0;

async function cargarEmitidos(pagina = 1) {
  const revision = ++revisionEmitidos;
  cargandoEmitidos.value = true;
  errorEmitidos.value = '';
  try {
    const { data } = await api.get('/cpd/oficios-emitidos', { params: { page: pagina } });
    if (revision === revisionEmitidos) emitidos.value = data;
  } catch {
    if (revision === revisionEmitidos) errorEmitidos.value = 'No se pudieron cargar los oficios. Pulsa Actualizar para reintentar.';
  } finally {
    if (revision === revisionEmitidos) cargandoEmitidos.value = false;
  }
}

function seleccionarVista(seccion) {
  vista.value = seccion;
  if (seccion === 'emitidos' && !emitidos.value && !cargandoEmitidos.value) cargarEmitidos();
}

function actualizarPanel() {
  cargarPendientes();
  if (vista.value === 'emitidos' || emitidos.value || cargandoEmitidos.value) cargarEmitidos(emitidos.value?.current_page);
}

const oficios = ref([]);
const cargandoPendientes = ref(false);
const errorPendientes = ref('');
const avisoResolucion = ref('');
let cargaPendientes = 0;
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
  const carga = ++cargaPendientes;
  cargandoPendientes.value = true;
  errorPendientes.value = '';
  try {
    const [resO, resP] = await Promise.all([
      api.get('/cpd/pendientes'),
      api.get('/catalogos/periodos')
    ]);
    if (carga !== cargaPendientes) return;
    oficios.value = resO.data
      .map(oficio => ({ ...oficio, solicitudes: oficio.solicitudes.filter(sol => sol.estado === 'REMITIDA_A_CPD') }))
      .filter(oficio => oficio.solicitudes.length);
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
  } catch {
    if (carga === cargaPendientes) errorPendientes.value = 'No se pudo cargar la bandeja. Pulsa Actualizar para reintentar.';
  } finally {
    if (carga === cargaPendientes) cargandoPendientes.value = false;
  }
};

const guardarConfigPeriodo = async () => {
  if (!periodoActivo.value?.id) return;
  guardandoConfig.value = true;
  try {
    const datos = { ...formConfig.value };
    if (!esCpd.value) delete datos.costo_arancel_caso;
    const res = await api.put(`/cpd/periodo/${periodoActivo.value.id}/config`, datos);
    periodoActivo.value = res.data.periodo;
    modalConfigPeriodo.value = false;
    alert('Parámetros actualizados correctamente.');
  } catch (e) {
    alert(e.response?.data?.error || 'Error al guardar configuración.');
  } finally {
    guardandoConfig.value = false;
  }
};

const abrirRespuesta = (oficio, sol) => {
  if (sol.estado !== 'REMITIDA_A_CPD' || loading.value) return;
  oficioActivo.value = oficio;
  solicitudActiva.value = sol;
  formResp.value.resultado = 'HABILITADO';
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

const retirarDePendientes = (id) => {
  cargaPendientes++;
  oficios.value = oficios.value
    .map(oficio => ({ ...oficio, solicitudes: oficio.solicitudes.filter(sol => sol.id !== id) }))
    .filter(oficio => oficio.solicitudes.length);
  modalRespuesta.value = false;
  actualizarPanel();
};

const estadosResolucion = {
  HABILITADA: 'Habilitada',
  OBSERVADA_CPD: 'Observada por CPD',
  RECHAZADA_CPD: 'Rechazada por CPD',
};

const ejecutarRespuesta = async () => {
  if (loading.value) return;
  const solicitud = solicitudActiva.value;
  verificarMateriasCpd();
  loading.value = true;
  try {
    const { data } = await api.post('/cpd/responder', {
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
    const estado = estadosResolucion[data.solicitud?.estado];
    avisoResolucion.value = estado
      ? `${solicitud.codigo} · ${estado}. Resolución guardada.`
      : `${solicitud.codigo} · Resolución guardada.`;
    retirarDePendientes(solicitud.id);
  } catch (e) {
    if (e.response?.data?.codigo === 'SOLICITUD_NO_PENDIENTE') {
      const estado = estadosResolucion[e.response.data.estado];
      avisoResolucion.value = estado
        ? `${solicitud.codigo} · ${estado}. La bandeja se actualizó.`
        : `${solicitud.codigo} ya no está pendiente de resolución.`;
      retirarDePendientes(solicitud.id);
    } else {
      alert(e.response?.data?.error || e.response?.data?.message || 'Error al procesar respuesta CPD.');
    }
  } finally {
    loading.value = false;
  }
};

onMounted(actualizarPanel);
</script>

<style scoped>
.secciones-cpd,
.paginas-emitidos {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .5rem;
}

.secciones-cpd {
  margin-bottom: 1rem;
}

.secciones-cpd [aria-pressed="true"] {
  background: var(--primary-subtle);
  border-color: var(--primary-text);
  color: var(--text);
  font-weight: 700;
}

:global([data-contrast="high"]) .secciones-cpd [aria-pressed="true"] {
  background: var(--text);
  color: var(--surface);
  border-color: var(--text);
}

.referencia-emitido {
  display: block;
  color: var(--text-muted);
  font-size: .875rem;
  font-weight: 400;
}

.paginas-emitidos {
  justify-content: flex-end;
  margin-top: .75rem;
}

.role-view {
  background: var(--surface);
  border-radius: var(--radius-xl);
  padding: clamp(1rem, 3vw, 1.5rem);
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
  min-width: min(100%, 17.5rem);
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
  flex-wrap: wrap;
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
  color: var(--text-muted);
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

.modal-box.modal-respuesta {
  max-width: 44rem;
  max-height: calc(100dvh - 2rem);
  padding: clamp(.75rem, 3vw, 1.5rem);
}

.modal-respuesta .modal-header,
.modal-respuesta .modal-footer {
  flex-shrink: 0;
}

.modal-respuesta .modal-form {
  display: flex;
  flex-direction: column;
  flex: 1 1 auto;
  min-height: 0;
  min-width: 0;
}

.respuesta-campos {
  min-height: 0;
  overflow: auto;
  overscroll-behavior: contain;
  padding: .125rem .25rem;
}

.modal-respuesta .table-responsive select,
.modal-respuesta .table-responsive input {
  min-width: 8rem;
}

.modal-respuesta .modal-footer {
  flex-wrap: wrap;
  margin-top: .5rem;
  padding-top: .75rem;
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
