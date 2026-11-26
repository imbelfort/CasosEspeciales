<template>
  <div class="role-view">
    <section v-if="nombreAlumno" class="bienvenida-alumno" aria-labelledby="saludo-alumno">
      <h1 id="saludo-alumno">Hola, {{ nombreAlumno }}</h1>
      <p v-if="props.usuario?.estudiante?.registro_universitario">
        Registro universitario <strong>{{ props.usuario.estudiante.registro_universitario }}</strong>
      </p>
    </section>
    <EtapasCronograma :periodo="periodoActivo" compacto />

    <!-- BARRA RESUMEN LIMPIA CON ACCIONES PRINCIPALES -->
    <div class="view-header">
      <div class="header-text">
        <h2>Mis Solicitudes de Casos Especiales (CU2)</h2>
        <p class="view-desc">
          Plan de estudios verificado automáticamente en el sistema central. Puedes solicitar de 1 hasta {{ limiteMateriasCaso }} asignaturas por caso (habitualmente 2). Límite institucional: 1 solicitud por temporada y máx. 5 casos en toda la carrera.
        </p>
      </div>
      <div class="header-action-buttons">
        <!-- BOTONES PARA VISORES SEPARADOS DE MALLA E HISTÓRICO (CU2) -->
        <button 
          @click="modalMalla = true"
          class="btn-malla"
          title="Ver Malla Curricular Digital interactiva"
        >
          <GraduationCap :size="16" class="btn-icon" /> Malla Curricular
        </button>

        <button
          @click="modalHistorial = true"
          class="btn-secondary"
          title="Ver Historial Académico de asignaturas y notas"
        >
          <ListChecks :size="16" class="btn-icon" /> Historial Académico
        </button>

        <button 
          @click="abrirModalRegistro" 
          class="btn-primary" 
          :disabled="limiteCarreraAlcanzado"
          :title="limiteCarreraAlcanzado ? 'Has alcanzado el límite máximo de casos en tu carrera (5).' : 'Registrar nueva solicitud'"
        >
          <Plus :size="16" class="btn-icon" /> Registrar Solicitud de Caso Especial
        </button>
      </div>
    </div>

    <!-- BANNER DE RESTRICCIONES INSTITUCIONALES -->
    <div v-if="limiteCarreraAlcanzado" class="banner-aviso-warn">
      <AlertCircle :size="16" class="inline-icon text-warning" />
      <span>Has alcanzado el límite institucional máximo de 5 casos especiales aprobados en tu carrera universitaria.</span>
    </div>

    <p v-if="estadoStripe" class="banner-aviso-warn" role="status">{{ estadoStripe }}</p>

    <div class="actualizacion-solicitudes">
      <button type="button" class="btn-action" :disabled="actualizandoSolicitudes || pausaActualizacion"
        @click="actualizarSolicitudes()">
        <RefreshCw :size="15" aria-hidden="true" />
        {{ actualizandoSolicitudes ? 'Actualizando…' : 'Actualizar' }}
      </button>
      <p v-if="errorSolicitudes" role="status">{{ errorSolicitudes }}</p>
      <p v-else-if="ultimaActualizacion">Actualizado a las {{ ultimaActualizacion }}</p>
    </div>

    <!-- BANDEJA DE MIS CASOS -->
    <div class="table-responsive" tabindex="0" role="region" aria-label="Solicitudes de casos especiales"
      :aria-busy="actualizandoSolicitudes">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Código de Solicitud</th>
            <th scope="col">Período</th>
            <th scope="col">Tipo de Caso</th>
            <th scope="col">Estado</th>
            <th scope="col">Fecha Envío</th>
            <th scope="col">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="sol in solicitudes" :key="sol.id">
            <td><strong class="code-tag">{{ sol.codigo }}</strong></td>
            <td>{{ sol.periodo?.gestion }}</td>
            <td>{{ sol.tipo_caso }}</td>
            <td><span :class="['status-badge', sol.estado]">{{ sol.estado }}</span></td>
            <td>{{ formatearFecha(sol.fecha_solicitud) }}</td>
            <td>
              <div class="actions-cell">
                <button @click="verDetalle(sol)" class="btn-action" title="Ver expediente completo">
                  <FileSearch :size="14" class="inline-icon" /> Expediente
                </button>

                <button type="button" class="btn-action btn-pdf" :disabled="descargasCarta.has(sol.id)"
                  title="Descargar la carta de solicitud para firmarla" @click="descargarCarta(sol)">
                  <Download :size="14" class="inline-icon" aria-hidden="true" />
                  {{ descargasCarta.has(sol.id) ? 'Descargando…' : 'Descargar carta PDF' }}
                </button>

                <!-- Subsanar respaldo SOLO si la solicitud está OBSERVADA por Dirección -->
                <button v-if="sol.estado === 'OBSERVADA'" @click="abrirAdjuntarRespaldo(sol)" class="btn-action btn-file" title="Subir nuevo respaldo solicitado en la observación">
                  <Paperclip :size="14" class="inline-icon" /> Subsanar Respaldo
                </button>

                <template v-if="sol.estado === 'APROBADA_DIRECCION'">
                  <span v-if="sol.archivos?.some(a => a.tipo_archivo === 'BOLETA_PENDIENTE')">
                    Boleta en revisión por Dirección
                  </span>
                  <button v-else type="button" @click="elegirPago(sol)" class="btn-primary">
                    <Wallet :size="16" aria-hidden="true" /> Pagar
                  </button>
                </template>
              </div>
            </td>
          </tr>
          <tr v-if="ultimaActualizacion && solicitudes.length === 0">
            <td colspan="6" class="no-records">No tienes casos especiales solicitados. Haz clic en 'Registrar Nueva Solicitud'.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- MODAL REGISTRO DE SOLICITUD COMPLETO (CU2) -->
    <div v-if="modalNueva" class="modal-backdrop">
      <div class="modal-box modal-lg modal-container-dialog">
        <div class="modal-header">
          <h3 ref="tituloRegistro" tabindex="-1">CU2: Formulario de Solicitud de Caso Especial</h3>
          <button @click="modalNueva = false" class="btn-close">
            <X :size="18" />
          </button>
        </div>
        <form @submit.prevent="guardarSolicitud" class="modal-form modal-scrollable-form">
          <div class="alert-box-info mb-3">
            <strong>Normativa Universitaria FICCT - UAGRM:</strong> Puedes solicitar entre 1 y {{ limiteMateriasCaso }} asignaturas (habitualmente 2). CPD evaluará y habilitará los cupos técnicos disponibles (de las solicitadas te pueden habilitar un subconjunto según cupos y prerrequisitos). Solo se permite 1 trámite por período.
          </div>

          <div class="form-group">
            <label>Período Académico Activo (*):</label>
            <select v-model="formNueva.id_periodo" required>
              <option value="" disabled>Seleccione período...</option>
              <option v-for="p in periodos" :key="p.id" :value="p.id">Gestión Semestral {{ formatearGestion(p.gestion) }} - {{ p.estado }}</option>
            </select>
          </div>

          <!-- SELECCIONADOR DE MULTIPLES MATERIAS -->
          <div class="materias-builder-section">
            <div class="builder-header">
              <label><strong>Materias a Solicitar ({{ materiasSeleccionadasForm.length }} de máx. {{ limiteMateriasCaso }}):</strong></label>
            </div>

            <!-- Listado de materias añadidas a la solicitud -->
            <div class="table-responsive mb-2" tabindex="0" role="region" aria-label="Materias solicitadas">
              <table class="data-table">
                <thead>
                  <tr>
                    <th scope="col">Materia</th>
                    <th scope="col">Grupo Solicitado</th>
                    <th scope="col">Motivo de la Materia</th>
                    <th scope="col">Quitar</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(mItem, idx) in materiasSeleccionadasForm" :key="idx">
                    <td>
                      <strong>{{ getNombreMateriaPorGrupo(mItem.id_grupo) }}</strong><br>
                      <small class="text-muted"><Clock :size="12" class="inline-icon" /> {{ getHorarioPorGrupoId(mItem.id_grupo) }}</small>
                    </td>
                    <td><span class="code-tag">Grupo {{ getSiglaGrupo(mItem.id_grupo) }}</span></td>
                    <td>
                      <input v-model="mItem.motivo" placeholder="Ej: Choque, levantamiento..." class="form-control-sm" />
                    </td>
                    <td>
                      <button type="button" @click="quitarMateriaForm(idx)" class="btn-action btn-danger-action" title="Quitar materia">
                        <Trash2 :size="14" />
                      </button>
                    </td>
                  </tr>
                  <tr v-if="materiasSeleccionadasForm.length === 0">
                    <td colspan="4" class="text-muted text-center py-2">
                      Aún no has agregado materias. Selecciona una materia abajo y pulsa "+ Agregar Materia".
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Controles para agregar otra materia -->
            <div v-if="materiasSeleccionadasForm.length < limiteMateriasCaso" class="add-materia-row">
              <div class="form-group col-select">
                <select v-model="grupoParaAgregar">
                  <option value="" disabled>Seleccione la asignatura, grupo (SA, SB, SC) y horario...</option>
                  <optgroup v-for="m in materiasDisponiblesParaAgregar" :key="m.id" :label="m.sigla + ' - ' + m.nombre">
                    <option v-for="g in m.grupos" :key="g.id" :value="g.id">
                      {{ m.sigla }} &bull; {{ getDetalleGrupoHorario(g) }}
                    </option>
                  </optgroup>
                </select>
              </div>
              <button 
                type="button" 
                @click="agregarMateriaAlForm" 
                class="btn-secondary btn-sm"
                :disabled="!grupoParaAgregar"
              >
                <Plus :size="14" class="inline-icon" /> Agregar Asignatura
              </button>
            </div>
          </div>

          <div class="form-group mt-3">
            <label>Motivo y Justificación General del Caso Especial (*):</label>
            <textarea v-model="formNueva.motivo" rows="3" required placeholder="Explica detalladamente las razones de tu solicitud académica dirigida a Dirección de Carrera..."></textarea>
          </div>

          <!-- DOCUMENTOS REQUERIDOS: CARTA OFICIAL FIRMADA Y RESPALDOS -->
          <div class="documentos-card-box mt-3">
            <h4>Documentación del Caso Especial (Física / Digital)</h4>
            <p class="text-muted text-sm">
              La carta formal en PDF debe ser firmada por el estudiante. Puedes descargar el borrador oficial desde tus solicitudes generadas, firmarla físicamente (o digitalmente) y adjuntarla aquí o en la bandeja:
            </p>
            <div class="form-group mb-0">
              <label>Carta Firmada / Documento de Respaldo (PDF o Imagen escaneada):</label>
              <input type="file" @change="e => archivoRespaldoInicial = e.target.files[0]" accept=".pdf,.jpg,.jpeg,.png" />
            </div>
          </div>

          <div class="modal-footer mt-3">
            <button type="button" @click="modalNueva = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="loading || materiasSeleccionadasForm.length === 0">
              {{ loading ? 'Enviando...' : `Presentar Solicitud (${materiasSeleccionadasForm.length} materias)` }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL SUBSANAR ARCHIVO RESPALDO (CU2) -->
    <div v-if="modalRespaldo" class="modal-backdrop">
      <div class="modal-box">
        <div class="modal-header">
          <h3>Subsanar Respaldo para Solicitud {{ solicitudActiva?.codigo }}</h3>
          <button @click="modalRespaldo = false" class="btn-close">
            <X :size="18" />
          </button>
        </div>
        <form @submit.prevent="subirRespaldo" class="modal-form">
          <p class="auth-desc" style="margin-bottom: 1rem; color: #b45309; background: #fef3c7; padding: 0.6rem; border-radius: 6px;">
            <strong>Observación de Dirección:</strong> {{ solicitudActiva?.observaciones || solicitudActiva?.evaluacion?.observaciones || 'Favor adjuntar el respaldo académico requerido.' }}
          </p>
          <div class="form-group">
            <label>Tipo de Documento:</label>
            <select v-model="formRespaldo.tipo_archivo">
              <option value="RESPALDO">Documento de Respaldo Académico</option>
              <option value="FORMULARIO">Formulario de Solicitud Firmado</option>
              <option value="OTRO">Otro Documento</option>
            </select>
          </div>
          <div class="form-group">
            <label>Archivo (PDF / Imagen) (*):</label>
            <input type="file" @change="e => archivoRespaldo = e.target.files[0]" required accept=".pdf,.jpg,.jpeg,.png" />
          </div>
          <div class="modal-footer">
            <button type="button" @click="modalRespaldo = false" class="btn-secondary">Cancelar</button>
            <button type="submit" class="btn-primary" :disabled="loading">
              {{ loading ? 'Enviando...' : 'Subir Respaldo y Subsanar Trámite' }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <dialog ref="selectorPago" class="modal-box payment-dialog selector-pago" aria-labelledby="titulo-selector-pago"
      @cancel="stripeAbriendo !== null && $event.preventDefault()">
      <header>
        <div>
          <h3 id="titulo-selector-pago">Elige cómo pagar</h3>
          <small>{{ solicitudActiva?.codigo }}</small>
        </div>
        <form method="dialog">
          <button type="submit" class="btn-action" aria-label="Cerrar selector" :disabled="stripeAbriendo !== null">
            <X :size="18" aria-hidden="true" />
          </button>
        </form>
      </header>
      <div class="opciones-pago">
        <button type="button" class="btn-action opcion-pago" :disabled="stripeAbriendo !== null" @click="abrirPagoQr">
          <span class="marca-pago"><QrCode :size="24" aria-hidden="true" /></span>
          <span><strong>QR bancario</strong><small>Escanea con tu aplicación bancaria</small></span>
        </button>
        <button type="button" class="btn-action opcion-pago" :disabled="stripeDisponible !== true || stripeAbriendo !== null"
          @click="pagarConTarjeta(solicitudActiva)">
          <span class="marca-pago"><img :src="logoStripe" alt="Stripe" /></span>
          <span><strong>Tarjeta</strong><small>{{ etiquetaStripe }}</small></span>
        </button>
        <p v-if="errorStripe" role="status">{{ errorStripe }}</p>
        <button v-if="stripeDisponible === 'error'" type="button" class="btn-action" @click="consultarStripe">
          Revisar disponibilidad de Stripe
        </button>
        <button type="button" class="btn-action opcion-boleta" :disabled="stripeAbriendo !== null" @click="abrirPago">
          <UploadCloud :size="16" aria-hidden="true" /> Ya pagué por depósito · Enviar boleta
        </button>
      </div>
    </dialog>

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
            Si ya pagaste por depósito, envía la boleta bancaria para que Dirección valide el pago.
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
      @close="cerrarPagoQr"
      @pago-completado="onPagoQrCompletado" 
    />

    <ExpedienteSolicitudDialog ref="expediente" estudiante />

    <!-- MODAL SEPARADO 1: MALLA CURRICULAR DIGITAL -->
    <ModalMallaCurricular
      :visible="modalMalla"
      :materias="materias"
      :materias-aprobadas-ids="materiasAprobadasIds"
      :materias-inscritas-ids="materiasInscritasIds"
      :materias-solicitadas-ids="materiasSolicitadasActivas"
      @close="modalMalla = false"
      @select-materia="solicitarMateriaDesdeVisor"
    />

    <!-- MODAL SEPARADO 2: HISTORIAL ACADÉMICO -->
    <ModalHistorialAcademico
      :visible="modalHistorial"
      :historico-lista="historicoAcademico"
      @close="modalHistorial = false"
    />

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { 
  Plus, 
  FileSearch, 
  BookPlus, 
  Paperclip, 
  QrCode,
  Wallet,
  RefreshCw,
  UploadCloud,
  X, 
  Calendar,
  Clock,
  CheckCircle2,
  Server,
  Download,
  Trash2,
  AlertCircle,
  GraduationCap,
  ListChecks
} from '@lucide/vue';
import ExpedienteSolicitudDialog from '../../components/common/ExpedienteSolicitudDialog.vue';
import PagoQrModal from '../../components/pago/PagoQrModal.vue';
import logoStripe from '../../assets/stripe.svg';
import ModalMallaCurricular from '../../components/estudiante/ModalMallaCurricular.vue';
import ModalHistorialAcademico from '../../components/estudiante/ModalHistorialAcademico.vue';
import EtapasCronograma from '../../components/common/EtapasCronograma.vue';
import api from '../../services/api';

const props = defineProps({
  usuario: Object
});

const nombreAlumno = computed(() => [props.usuario?.nombre, props.usuario?.apellidos].filter(Boolean).join(' '));

const solicitudes = ref([]);
const actualizandoSolicitudes = ref(false);
const pausaActualizacion = ref(false);
const errorSolicitudes = ref('');
const ultimaActualizacion = ref('');
const consultasSolicitudes = new AbortController();
const horaActualizacion = new Intl.DateTimeFormat('es-BO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
let consultaSolicitudes;
let temporizadorPausa;
let intervaloSolicitudes;
const periodos = ref([]);
const materias = ref([]);
const historicoAcademico = ref([]);
const loading = ref(false);

const descargasCarta = ref(new Set());

async function descargarCarta(sol) {
  descargasCarta.value.add(sol.id);
  try {
    const { data } = await api.get(`/solicitudes/${sol.id}/carta-pdf`, {
      responseType: 'blob',
    });
    const url = URL.createObjectURL(data);
    const enlace = document.createElement('a');
    enlace.href = url;
    enlace.download = `Carta-${sol.codigo.replace(/[^a-zA-Z0-9_-]/g, '-')}.pdf`;
    document.body.append(enlace);
    enlace.click();
    enlace.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (error) {
    if (error.response?.status !== 401) alert('No se pudo descargar la carta. Vuelve a intentarlo.');
  } finally {
    descargasCarta.value.delete(sol.id);
  }
}

const modalMalla = ref(false);
const modalHistorial = ref(false);
const modalNueva = ref(false);
const tituloRegistro = ref(null);
const datosInicialesCargados = ref(false);
const route = useRoute();
const router = useRouter();
const modalRespaldo = ref(false);
const modalPago = ref(false);
const modalPagoQr = ref(false);
const selectorPago = ref(null);
const stripeDisponible = ref(null);
const stripeAbriendo = ref(null);
const estadoStripe = ref('');
const errorStripe = ref('');
const consultasStripe = new AbortController();
const etiquetaStripe = computed(() => {
  if (stripeAbriendo.value !== null) return 'Abriendo Stripe…';
  if (stripeDisponible.value === null) return 'Comprobando disponibilidad…';
  if (stripeDisponible.value === 'error') return 'No se pudo comprobar la disponibilidad';
  return stripeDisponible.value ? 'Paga en Stripe Checkout' : 'Stripe no está habilitado';
});
const expediente = ref(null);
const solicitudActiva = ref(null);

const archivoRespaldoInicial = ref(null);
const archivoRespaldo = ref(null);
const archivoComprobante = ref(null);

const formNueva = ref({ id_periodo: '', tipo_caso: 'CASO_ESPECIAL', motivo: '', id_grupo: '' });
const formRespaldo = ref({ tipo_archivo: 'RESPALDO' });

const materiasSeleccionadasForm = ref([]);
const grupoParaAgregar = ref('');

const limiteMateriasCaso = computed(() => {
  return periodoActivo.value?.max_materias_por_caso || 5;
});

const totalCasosCarrera = computed(() => {
  return solicitudes.value.filter(s => ['HABILITADA', 'APROBADA', 'APROBADA_DIRECCION', 'REMITIDA_A_CPD'].includes(s.estado)).length;
});

const limiteCarreraAlcanzado = computed(() => {
  const maxCarrera = periodoActivo.value?.max_casos_carrera || 5;
  return totalCasosCarrera.value >= maxCarrera;
});

const materiasAprobadasIds = computed(() => new Set(
  historicoAcademico.value
    .filter(h => h.estado_materia === 'APROBADA' || Number(h.nota_final) >= 51)
    .map(h => h.id_materia)
));

const obtenerMateriasInscritasIds = (gestion) => new Set(
  historicoAcademico.value
    .filter(h => h.gestion === gestion && ['INSCRITA', 'CURSANDO'].includes(h.estado_materia))
    .map(h => h.id_materia)
);

const materiasInscritasIds = computed(() => obtenerMateriasInscritasIds(periodoActivo.value?.gestion));
const materiasInscritasEnPeriodoForm = computed(() => {
  const periodo = periodos.value.find(p => p.id === formNueva.value.id_periodo);
  return obtenerMateriasInscritasIds(periodo?.gestion);
});

const materiasDisponiblesParaAgregar = computed(() => {
  const idsGruposElegidos = materiasSeleccionadasForm.value.map(m => m.id_grupo);

  return materias.value
    .filter(m => !materiasAprobadasIds.value.has(m.id) && !materiasInscritasEnPeriodoForm.value.has(m.id))
    .map(m => ({
      ...m,
      grupos: (m.grupos || []).filter(g => !idsGruposElegidos.includes(g.id))
    })).filter(m => m.grupos.length > 0);
});

const getNombreMateriaPorGrupo = (idGrupo) => {
  for (const m of materias.value) {
    const g = (m.grupos || []).find(gr => gr.id === idGrupo);
    if (g) return `${m.sigla} - ${m.nombre}`;
  }
  return 'Materia';
};

const getSiglaGrupo = (idGrupo) => {
  for (const m of materias.value) {
    const g = (m.grupos || []).find(gr => gr.id === idGrupo);
    if (g) return g.sigla_grupo;
  }
  return 'SA';
};

const getDetalleGrupoHorario = (grupo) => {
  if (!grupo) return '';
  const cuposTxt = grupo.cupos_disponibles === 0 ? '¡0 cupos - LLENO!' : `${grupo.cupos_disponibles} cupos libres`;

  if (!grupo.horarios || grupo.horarios.length === 0) {
    return `Grupo ${grupo.sigla_grupo} (${cuposTxt})`;
  }

  // Agrupar días y horas
  const diasAbrev = grupo.horarios.map(h => {
    const d = h.dia?.substring(0, 3) || '';
    const ini = h.hora_inicio?.substring(0, 5) || '';
    const fin = h.hora_fin?.substring(0, 5) || '';
    return `${d} ${ini}-${fin}`;
  });

  const aula = grupo.horarios[0]?.aula ? ` | Aula ${grupo.horarios[0].aula}` : '';
  const docente = grupo.docente ? ` | Doc: ${grupo.docente}` : '';

  return `Grupo ${grupo.sigla_grupo} [${diasAbrev.join(', ')}${aula}${docente}] - (${cuposTxt})`;
};

const getHorarioPorGrupoId = (idGrupo) => {
  for (const m of materias.value) {
    const g = (m.grupos || []).find(gr => gr.id === idGrupo);
    if (g && g.horarios && g.horarios.length > 0) {
      const dias = g.horarios.map(h => `${h.dia?.substring(0, 3)} ${h.hora_inicio?.substring(0, 5)}-${h.hora_fin?.substring(0, 5)}`).join(', ');
      const aula = g.horarios[0]?.aula ? ` (Aula ${g.horarios[0].aula})` : '';
      return `${dias}${aula}`;
    }
  }
  return 'Horario a confirmar';
};

const agregarMateriaAlForm = () => {
  if (!grupoParaAgregar.value) return;
  if (materiasSeleccionadasForm.value.length >= limiteMateriasCaso.value) {
    alert(`Solo puedes solicitar hasta un máximo de ${limiteMateriasCaso.value} asignaturas.`);
    return;
  }
  materiasSeleccionadasForm.value.push({
    id_grupo: grupoParaAgregar.value,
    motivo: 'Solicitud de inscripción por caso especial'
  });
  grupoParaAgregar.value = '';
};

const quitarMateriaForm = (idx) => {
  materiasSeleccionadasForm.value.splice(idx, 1);
};

const formatearGestion = (gestion) => {
  if (!gestion) return '';
  // Convertir formato semestral 1-YYYY a I-YYYY o semestral estándar
  const parts = gestion.split('-');
  if (parts.length === 2) {
    const sem = parts[0] === '1' ? 'I' : (parts[0] === '2' ? 'II' : parts[0]);
    return `${sem}-${parts[1]} (${parts[0]}-${parts[1]})`;
  }
  return gestion;
};

const formatearFecha = (f) => f ? new Date(f).toLocaleString('es-BO') : 'N/A';

const periodoActivo = computed(() => {
  return periodos.value.find(p => p.estado === 'ACTIVO') || periodos.value[0] || null;
});

const formatearPeriodoRecepcion = () => {
  const p = periodoActivo.value;
  if (!p) return '23–24 Marzo';
  if (p.fecha_inicio_solicitud && p.fecha_fin_solicitud) {
    const f1 = new Date(p.fecha_inicio_solicitud + 'T00:00:00').toLocaleDateString('es-BO', { day: 'numeric', month: 'short' });
    const f2 = new Date(p.fecha_fin_solicitud + 'T00:00:00').toLocaleDateString('es-BO', { day: 'numeric', month: 'short' });
    return `${f1} al ${f2}`;
  }
  return '23 al 24 de Marzo';
};

function actualizarSolicitudes(forzar = false) {
  const { signal } = consultasSolicitudes;
  if (consultaSolicitudes) return consultaSolicitudes;
  if (signal.aborted || (!forzar && pausaActualizacion.value)) return;

  clearTimeout(temporizadorPausa);
  actualizandoSolicitudes.value = true;
  errorSolicitudes.value = '';
  consultaSolicitudes = api.get('/solicitudes/mis-casos', { signal, timeout: 10000 })
    .then(({ data }) => {
      if (signal.aborted) return;
      solicitudes.value = data;
      ultimaActualizacion.value = horaActualizacion.format(new Date());
    })
    .catch(() => {
      if (!signal.aborted) errorSolicitudes.value = 'No se pudieron actualizar las solicitudes. Inténtalo de nuevo.';
    })
    .finally(() => {
      consultaSolicitudes = null;
      if (signal.aborted) return;
      actualizandoSolicitudes.value = false;
      pausaActualizacion.value = true;
      temporizadorPausa = setTimeout(() => { pausaActualizacion.value = false; }, 3000);
    });
  return consultaSolicitudes;
}

const actualizarSiVisible = () => {
  if (document.visibilityState === 'visible') actualizarSolicitudes();
};

const cargarDatos = async (trasCambio = false) => {
  const cargarSolicitudes = async () => {
    if (trasCambio && consultaSolicitudes) await consultaSolicitudes;
    await actualizarSolicitudes(trasCambio);
  };
  try {
    const carreraId = props.usuario?.estudiante?.carrera_id;
    const materiasParams = carreraId ? { carrera_id: carreraId } : {};

    const [resP, resM, resH] = await Promise.all([
      api.get('/catalogos/periodos'),
      api.get('/catalogos/materias', { params: materiasParams }),
      api.get('/solicitudes/mi-historico'),
      cargarSolicitudes(),
    ]);
    periodos.value = resP.data;
    materias.value = resM.data;
    historicoAcademico.value = resH.data || [];
    if (periodos.value.length && !formNueva.value.id_periodo) {
      formNueva.value.id_periodo = periodos.value[0].id;
    }
  } catch (e) {
    console.error('Error al cargar datos del estudiante:', e);
  }
};

watch(() => props.usuario?.estudiante?.carrera_id, (newVal) => {
  if (newVal) {
    cargarDatos();
  }
});

const materiasSolicitadasActivas = computed(() => {
  const ids = [];
  solicitudes.value.forEach(s => {
    if (s.grupos) {
      s.grupos.forEach(g => {
        if (g.grupo?.id_materia) ids.push(g.grupo.id_materia);
      });
    }
  });
  return ids;
});

const solicitarMateriaDesdeVisor = (materia) => {
  if (materiasAprobadasIds.value.has(materia.id)) {
    alert(`La materia ${materia.sigla} (${materia.nombre}) ya fue aprobada previamente. No es posible solicitarla en caso especial.`);
    return;
  }

  if (materiasInscritasIds.value.has(materia.id)) {
    alert(`Ya tienes inscrita la materia ${materia.sigla} en esta gestión.`);
    return;
  }

  if (!abrirModalRegistro()) return;

  modalMalla.value = false;
  modalHistorial.value = false;

  const grupo = materia.grupos?.[0];
  if (grupo && limiteMateriasCaso.value > 0) {
    materiasSeleccionadasForm.value.push({
      id_grupo: grupo.id,
      motivo: `Solicitud de levantamiento / caso especial para ${materia.sigla}`
    });
  }
};

const abrirModalRegistro = () => {
  if (limiteCarreraAlcanzado.value) {
    alert('Has alcanzado el límite institucional máximo de 5 casos especiales aprobados en toda tu carrera.');
    return false;
  }

  formNueva.value.id_periodo = periodoActivo.value?.id ?? '';
  archivoRespaldoInicial.value = null;
  materiasSeleccionadasForm.value = [];
  grupoParaAgregar.value = '';
  formNueva.value.motivo = '';
  modalNueva.value = true;
  return true;
};

const guardarSolicitud = async () => {
  if (materiasSeleccionadasForm.value.length === 0) {
    alert('Debes agregar al menos 1 materia para solicitar el caso especial.');
    return;
  }
  const materiaInscrita = materias.value.find(m => materiasInscritasEnPeriodoForm.value.has(m.id)
    && m.grupos?.some(g => materiasSeleccionadasForm.value.some(seleccion => seleccion.id_grupo === g.id)));
  if (materiaInscrita) {
    alert(`Quita ${materiaInscrita.sigla} de la solicitud. Ya la tienes inscrita en la gestión seleccionada.`);
    return;
  }

  loading.value = true;
  try {
    const fd = new FormData();
    fd.append('id_periodo', formNueva.value.id_periodo);
    fd.append('tipo_caso', 'CASO_ESPECIAL');
    fd.append('motivo', formNueva.value.motivo);

    // Enviar materias en formato array JSON y primer grupo para compatibilidad
    materiasSeleccionadasForm.value.forEach((mItem, index) => {
      fd.append(`grupos[${index}][id_grupo]`, mItem.id_grupo);
      fd.append(`grupos[${index}][motivo]`, mItem.motivo || '');
    });

    if (materiasSeleccionadasForm.value.length > 0) {
      fd.append('id_grupo', materiasSeleccionadasForm.value[0].id_grupo);
      fd.append('motivo_grupo', materiasSeleccionadasForm.value[0].motivo || '');
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
    materiasSeleccionadasForm.value = [];
    archivoRespaldoInicial.value = null;
    alert('Solicitud de Caso Especial presentada exitosamente.');
    cargarDatos(true);
  } catch (e) {
    alert(e.response?.data?.error || 'Error al crear solicitud.');
  } finally {
    loading.value = false;
  }
};

const verDetalle = (sol) => expediente.value?.abrir(sol);

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
    cargarDatos(true);
  } catch (e) {
    alert(e.response?.data?.error || 'Error al adjuntar archivo.');
  } finally {
    loading.value = false;
  }
};

const elegirPago = (sol) => {
  solicitudActiva.value = sol;
  errorStripe.value = '';
  selectorPago.value.showModal();
  consultarStripe();
};

async function consultarStripe() {
  stripeDisponible.value = null;
  try {
    const { data } = await api.get('/pagos/stripe/config', { signal: consultasStripe.signal, timeout: 8000 });
    stripeDisponible.value = data.disponible === true;
  } catch {
    if (!consultasStripe.signal.aborted) stripeDisponible.value = 'error';
  }
}

const abrirPagoQr = () => {
  selectorPago.value.close();
  modalPagoQr.value = true;
};

const cerrarPagoQr = async (volver) => {
  modalPagoQr.value = false;
  if (volver) {
    await nextTick();
    selectorPago.value?.showModal();
  }
};

async function pagarConTarjeta(sol) {
  stripeAbriendo.value = sol.id;
  errorStripe.value = '';
  try {
    const { data } = await api.post(`/solicitudes/${sol.id}/pagar-stripe`, {}, { signal: consultasStripe.signal, timeout: 30000 });
    if (data.estado === 'PAGADO') {
      selectorPago.value.close();
      estadoStripe.value = 'Pago del arancel confirmado.';
      await cargarDatos(true);
      return;
    }
    window.location.assign(data.url);
  } catch (error) {
    if (!consultasStripe.signal.aborted) {
      const mensaje = error.response?.data?.message || 'No se pudo abrir el pago con tarjeta.';
      errorStripe.value = mensaje;
      if (!selectorPago.value?.open) estadoStripe.value = mensaje;
    }
  } finally {
    stripeAbriendo.value = null;
  }
}

async function procesarRetornoStripe() {
  const { pago_tarjeta: id, resultado } = route.query;
  if (typeof id !== 'string' || !/^\d+$/.test(id) || !['confirmar', 'cancelar'].includes(resultado)) return;

  const rutaPago = `/pagos/stripe/${id}`;
  const opciones = { signal: consultasStripe.signal };
  estadoStripe.value = 'Consultando el estado del pago con tarjeta…';
  try {
    let { data } = resultado === 'cancelar'
      ? await api.delete(rutaPago, opciones)
      : await api.get(rutaPago, opciones);

    for (let intento = 0; intento < 5 && data.estado === 'PENDIENTE' && !consultasStripe.signal.aborted; intento++) {
      await new Promise(resolve => setTimeout(resolve, 2500));
      ({ data } = await api.get(rutaPago, opciones));
    }

    if (data.estado === 'PENDIENTE') {
      estadoStripe.value = 'Stripe todavía no ha confirmado el pago. Puedes volver a consultar el estado recargando la página.';
      return;
    }

    if (data.estado === 'PAGADO') {
      estadoStripe.value = 'Pago con tarjeta confirmado.';
      await cargarDatos(true);
    } else {
      estadoStripe.value = 'El pago con tarjeta se cerró. Puedes elegir un método de pago nuevamente.';
    }

    const query = { ...route.query };
    delete query.pago_tarjeta;
    delete query.resultado;
    await router.replace({ path: route.path, query, hash: route.hash });
  } catch (error) {
    if (!consultasStripe.signal.aborted) {
      estadoStripe.value = error.response?.data?.message || 'No se pudo consultar el pago con tarjeta.';
    }
  }
}

const onPagoQrCompletado = (pago) => {
  cargarDatos(true);
};

const abrirPago = () => {
  selectorPago.value.close();
  archivoComprobante.value = null;
  modalPago.value = true;
};

const subirComprobante = async () => {
  if (!archivoComprobante.value) return;
  loading.value = true;
  try {
    const fd = new FormData();
    fd.append('comprobante', archivoComprobante.value);
    const { data } = await api.post(`/solicitudes/${solicitudActiva.value.id}/pago`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });
    modalPago.value = false;
    alert(data.estado === 'PAGADO' ? 'El arancel ya fue confirmado.' : data.mensaje);
    cargarDatos(true);
  } catch (e) {
    alert(e.response?.data?.error || e.response?.data?.message || 'Error al adjuntar comprobante.');
  } finally {
    loading.value = false;
  }
};

watch(
  () => route.path === '/estudiante' ? route.query.solicitud : null,
  (id) => {
    if (typeof id !== 'string' || !/^\d+$/.test(id)) return;
    nextTick(() => verDetalle({ id }));
    const query = { ...route.query };
    delete query.solicitud;
    router.replace({ path: route.path, query, hash: route.hash });
  },
  { immediate: true }
);

watch(
  () => [route.path, route.query.accion, datosInicialesCargados.value, props.usuario],
  ([path, accion, cargados, usuario]) => {
    if (path !== '/estudiante' || !cargados || !usuario?.roles?.some(rol => rol.nombre === 'ESTUDIANTE')) return;
    if (accion !== 'crear' && accion !== 'malla' && accion !== 'historico') return;

    if (accion === 'crear') {
      if (!modalNueva.value) abrirModalRegistro();
      nextTick(() => tituloRegistro.value?.focus({ preventScroll: true }));
    }
    if (accion === 'malla') modalMalla.value = true;
    if (accion === 'historico') modalHistorial.value = true;
    router.replace({ path, query: { ...route.query, accion: undefined }, hash: route.hash });
  },
);

onMounted(async () => {
  intervaloSolicitudes = setInterval(actualizarSiVisible, 30000);
  document.addEventListener('visibilitychange', actualizarSiVisible);
  procesarRetornoStripe();
  await cargarDatos();
  datosInicialesCargados.value = true;
});
onUnmounted(() => {
  consultasStripe.abort();
  consultasSolicitudes.abort();
  clearInterval(intervaloSolicitudes);
  clearTimeout(temporizadorPausa);
  document.removeEventListener('visibilitychange', actualizarSiVisible);
});
</script>

<style scoped>
.bienvenida-alumno {
  padding: 1rem;
  margin-bottom: .75rem;
  background: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  overflow-wrap: anywhere;
}

.bienvenida-alumno h1 {
  margin: 0;
  font-size: 1.35rem;
}

.bienvenida-alumno p {
  margin: .25rem 0 0;
  color: var(--text-muted);
  font-size: .875rem;
}

.actualizacion-solicitudes {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .5rem .75rem;
  margin-bottom: .75rem;
}

.actualizacion-solicitudes p {
  margin: 0;
  color: var(--text-muted);
  font-size: .875rem;
}

.opciones-pago {
  display: grid;
  gap: .625rem;
  padding: 1rem;
}

.opciones-pago p {
  margin: 0;
}

.opcion-pago {
  justify-content: flex-start;
  padding: .625rem;
  border-radius: var(--radius-lg);
  gap: .75rem;
  text-align: start;
}

.opcion-pago small {
  display: block;
  margin-top: .125rem;
  color: var(--text-muted);
}

.opcion-pago:disabled {
  cursor: default;
  background: var(--surface-alt);
}

.marca-pago {
  flex: 0 0 3.25rem;
  min-height: 2.5rem;
  display: grid;
  place-items: center;
  border-radius: var(--radius-sm);
  background: var(--surface-alt);
}

.marca-pago:has(img) {
  background: #fff;
}

.marca-pago img {
  width: 2.5rem;
  max-width: 100%;
}

.opcion-boleta {
  justify-self: start;
  text-align: start;
}

.schedule-banner {
  background-color: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl, 16px);
  padding: 1rem 1.4rem;
  margin-bottom: 1.2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 1rem;
}
.schedule-info {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}
.schedule-info h4 {
  margin: 0;
  font-size: 0.98rem;
  font-weight: 700;
  color: var(--text);
}
.schedule-desc {
  font-size: 0.78rem;
  color: var(--text-muted);
}
.phases-tags {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
}
.phase-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.75rem;
  border-radius: var(--radius-pill, 100px);
  font-size: 0.78rem;
  background-color: var(--surface-alt);
  border: 1px solid var(--border);
  color: var(--text-muted);
}
.phase-pill.current {
  background-color: var(--primary);
  color: var(--on-primary);
  border-color: var(--primary);
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
.header-action-buttons {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
  flex-wrap: wrap;
}
.view-desc {
  color: var(--text-muted);
  font-size: 0.9rem;
  max-width: 800px;
  margin: 0.3rem 0 0 0;
}
.btn-malla {
  background: var(--primary-subtle);
  color: var(--text);
  border: 1px solid var(--primary-text);
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  white-space: nowrap;
  transition: all 0.2s ease;
}
.btn-malla:hover {
  background: var(--surface);
}
.btn-icon, .inline-icon {
  flex-shrink: 0;
  vertical-align: middle;
}
.btn-add { background: var(--primary-subtle); color: var(--text); }
.btn-file { background: var(--surface-alt); color: var(--text); }
.no-records {
  text-align: center;
  padding: 2rem;
  color: var(--text-muted);
}
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1.5rem;
  overflow-y: auto;
}
.modal-box {
  background: var(--surface);
  border-radius: 12px;
  width: 100%;
  max-width: 520px;
  padding: 1.5rem;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
.modal-container-dialog {
  max-height: 90vh;
  display: flex;
  flex-direction: column;
}
.modal-scrollable-form {
  overflow-y: auto;
  max-height: calc(90vh - 120px);
  padding-right: 0.6rem;
  padding-bottom: 0.5rem;
}
.modal-lg { max-width: 760px; }
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border);
  padding-bottom: 0.8rem;
}
.btn-close { background: transparent; border: none; cursor: pointer; color: var(--text-muted); }
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.3rem; }
.form-group select, .form-group input, .form-group textarea {
  width: 100%;
  padding: 0.65rem 0.8rem;
  border: 1px solid var(--border);
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
  background: var(--primary-subtle);
  border-left: 4px solid #3b82f6;
  padding: 0.8rem 1rem;
  font-size: 0.9rem;
  color: var(--text);
}
.banner-aviso-info {
  background: var(--primary-subtle);
  border: 1px solid var(--primary-text);
  color: var(--text);
  padding: 0.8rem 1.2rem;
  border-radius: 8px;
  margin-bottom: 1.2rem;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.9rem;
}
.banner-aviso-warn {
  background: #fffbeb;
  border: 1px solid #fde68a;
  color: #92400e;
  padding: 0.8rem 1.2rem;
  border-radius: 8px;
  margin-bottom: 1.2rem;
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.9rem;
}
.materias-builder-section {
  background: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 1rem;
  margin-bottom: 1rem;
}
.builder-header {
  margin-bottom: 0.6rem;
}
.add-materia-row {
  display: flex;
  gap: 0.8rem;
  align-items: center;
}
.col-select {
  flex: 1;
  margin-bottom: 0;
}
.btn-danger-action {
  background: #fee2e2;
  color: #b91c1c;
  border: none;
  padding: 0.35rem 0.6rem;
  border-radius: 4px;
  cursor: pointer;
}
.btn-danger-action:hover {
  background: #fecaca;
}
.documentos-card-box {
  background: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 1rem;
}
.documentos-card-box h4 {
  margin: 0 0 0.3rem 0;
  font-size: 0.95rem;
}
.text-sm {
  font-size: 0.82rem;
}
.form-control-sm {
  padding: 0.35rem 0.5rem;
  font-size: 0.85rem;
  border: 1px solid var(--border);
  border-radius: 4px;
  width: 100%;
}
.mt-3 { margin-top: 1rem; }
.mt-4 { margin-top: 1.5rem; }
</style>
