<template>
  <div class="role-view">
    <!-- WIDGET DE ETAPAS DINÁMICAS PRO (En Curso / Próxima / Concluida) -->
    <EtapasCronograma :periodo="periodoActivo" />

    <!-- BARRA RESUMEN LIMPIA CON ACCIONES PRINCIPALES -->
    <div class="view-header">
      <div class="header-text">
        <h2>Mis Solicitudes de Casos Especiales (CU2)</h2>
        <p class="view-desc">
          Plan de estudios verificado automáticamente en el sistema central. Puedes solicitar de 1 hasta {{ limiteMateriasCaso }} asignaturas por caso (habitualmente 2). Límite institucional: 1 solicitud por temporada y máx. 5 casos en toda la carrera.
        </p>
      </div>
      <div class="header-action-buttons">
        <!-- BOTÓN PARA ABRIR EL VISOR EN MODAL LIMPIO -->
        <button 
          @click="modalAvance = true" 
          class="btn-malla"
          title="Ver Malla Curricular interactiva e Historial Académico"
        >
          <GraduationCap :size="16" class="btn-icon" /> Ver Malla e Historial Académico
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
            <div class="table-responsive mb-2">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Materia</th>
                    <th>Grupo Solicitado</th>
                    <th>Motivo de la Materia</th>
                    <th>Quitar</th>
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

          <h4 class="mt-4">Materias, Grupos y Horarios Asignados</h4>
          <ul class="clean-list">
            <li v-for="sg in solicitudActiva.grupos" :key="sg.id" style="margin-bottom: 0.5rem;">
              <BookOpen :size="15" class="inline-icon text-primary" />
              <strong>{{ sg.grupo?.materia?.sigla }}</strong> - {{ sg.grupo?.materia?.nombre }} 
              <span class="code-tag" style="margin-left: 0.4rem;">Grupo {{ sg.grupo?.sigla_grupo }}</span>
              <div v-if="sg.grupo?.horarios && sg.grupo.horarios.length > 0" class="text-muted text-sm" style="margin-left: 1.5rem; font-size: 0.8rem;">
                <Clock :size="12" class="inline-icon" /> 
                <span v-for="(h, hi) in sg.grupo.horarios" :key="hi" style="margin-right: 0.6rem;">
                  {{ h.dia }} {{ h.hora_inicio?.substring(0, 5) }}-{{ h.hora_fin?.substring(0, 5) }} (Aula {{ h.aula }})
                </span>
              </div>
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

    <!-- MODAL VISOR INDEPENDIENTE DE MALLA E HISTORIAL ACADÉMICO -->
    <ModalAvanceAcademico
      :visible="modalAvance"
      :materias="materias"
      :historico-lista="historicoAcademico"
      :historico-map="historicoMap"
      :materias-solicitadas-ids="materiasSolicitadasActivas"
      :materia-seleccionada="materiaSeleccionadaMalla"
      :semestre-actual="usuario?.estudiante?.semestre_actual || 5"
      @close="modalAvance = false"
      @select-materia="seleccionarMateriaDesdeMalla"
    />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
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
  ArrowRight,
  Calendar,
  Clock,
  CheckCircle2,
  Server,
  Download,
  Trash2,
  AlertCircle,
  GraduationCap
} from '@lucide/vue';
import PagoQrModal from '../../components/pago/PagoQrModal.vue';
import ModalAvanceAcademico from '../../components/estudiante/ModalAvanceAcademico.vue';
import EtapasCronograma from '../../components/common/EtapasCronograma.vue';
import api from '../../services/api';

const props = defineProps({
  usuario: Object
});

const solicitudes = ref([]);
const periodos = ref([]);
const materias = ref([]);
const historicoAcademico = ref([]);
const loading = ref(false);

const historicoMap = computed(() => {
  const map = {};
  historicoAcademico.value.forEach(h => {
    map[h.id_materia] = h;
  });
  return map;
});

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

const materiaSeleccionadaMalla = ref(null);

const modalAvance = ref(false);
const modalNueva = ref(false);
const tituloRegistro = ref(null);
const datosInicialesCargados = ref(false);
const route = useRoute();
const router = useRouter();
const modalRespaldo = ref(false);
const modalPago = ref(false);
const modalPagoQr = ref(false);
const modalDetalle = ref(false);
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

const materiasDisponiblesParaAgregar = computed(() => {
  const idsGruposElegidos = materiasSeleccionadasForm.value.map(m => m.id_grupo);
  
  // Obtener IDs de materias ya aprobadas o cursadas satisfactoriamente
  const idsMateriasAprobadas = historicoAcademico.value
    .filter(h => h.estado_materia === 'APROBADA' || (h.nota_final && h.nota_final >= 51))
    .map(h => h.id_materia);

  return materias.value
    .filter(m => !idsMateriasAprobadas.includes(m.id)) // Excluir materias aprobadas
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

const cargarDatos = async () => {
  try {
    const carreraId = props.usuario?.estudiante?.carrera_id;
    const materiasParams = carreraId ? { carrera_id: carreraId } : {};

    const [resS, resP, resM, resH] = await Promise.all([
      api.get('/solicitudes/mis-casos'),
      api.get('/catalogos/periodos'),
      api.get('/catalogos/materias', { params: materiasParams }),
      api.get('/solicitudes/mi-historico')
    ]);
    solicitudes.value = resS.data;
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

const seleccionarMateriaDesdeMalla = (materia) => {
  // Validar si la materia ya fue aprobada
  const yaAprobada = historicoAcademico.value.some(
    h => h.id_materia === materia.id && (h.estado_materia === 'APROBADA' || (h.nota_final && h.nota_final >= 51))
  );
  if (yaAprobada) {
    alert(`La materia ${materia.sigla} (${materia.nombre}) ya fue aprobada previamente. No es posible solicitarla en caso especial.`);
    return;
  }

  if (materiaSeleccionadaMalla.value?.id === materia.id) {
    materiaSeleccionadaMalla.value = null;
    return;
  }
  materiaSeleccionadaMalla.value = materia;

  // Cerrar el modal del visor de avance si estaba abierto
  modalAvance.value = false;

  abrirModalRegistro();

  // Si tiene grupos disponibles y no ha alcanzado límite, añadirlo automáticamente
  if (materia.grupos && materia.grupos.length > 0) {
    const gId = materia.grupos[0].id;
    if (!materiasSeleccionadasForm.value.some(m => m.id_grupo === gId)) {
      if (materiasSeleccionadasForm.value.length < limiteMateriasCaso.value) {
        materiasSeleccionadasForm.value.push({
          id_grupo: gId,
          motivo: `Solicitud de levantamiento / caso especial para ${materia.sigla}`
        });
      }
    }
  }
};

const abrirModalRegistro = () => {
  if (limiteCarreraAlcanzado.value) {
    alert('Has alcanzado el límite institucional máximo de 5 casos especiales aprobados en toda tu carrera.');
    return;
  }

  if (periodos.value.length) formNueva.value.id_periodo = periodos.value[0].id;
  archivoRespaldoInicial.value = null;
  materiasSeleccionadasForm.value = [];
  grupoParaAgregar.value = '';
  formNueva.value.motivo = '';
  modalNueva.value = true;
};

const guardarSolicitud = async () => {
  if (materiasSeleccionadasForm.value.length === 0) {
    alert('Debes agregar al menos 1 materia para solicitar el caso especial.');
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

watch(
  () => route.path === '/estudiante' ? route.query.solicitud : null,
  (id) => {
    if (typeof id !== 'string' || !/^\d+$/.test(id)) return;
    verDetalle({ id });
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
    if (accion !== 'crear' && accion !== 'malla') return;

    if (accion === 'crear') {
      if (!modalNueva.value) abrirModalRegistro();
      nextTick(() => tituloRegistro.value?.focus({ preventScroll: true }));
    }
    if (accion === 'malla') modalAvance.value = true;
    router.replace({ path, query: { ...route.query, accion: undefined }, hash: route.hash });
  },
);

onMounted(async () => {
  await cargarDatos();
  datosInicialesCargados.value = true;
});
</script>

<style scoped>
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
.header-action-buttons {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 0.75rem;
  flex-shrink: 0;
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
.table-responsive {
  overflow-x: auto;
}
.data-table {
  width: 100%;
  border-collapse: collapse;
}
.data-table th, .data-table td {
  padding: 0.85rem 1rem;
  border-bottom: 1px solid var(--border);
  text-align: left;
  font-size: 0.9rem;
}
.data-table th {
  background: var(--surface-alt);
  color: #475569;
  font-weight: 600;
}
.code-tag {
  background: var(--surface-alt);
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
.status-badge.SUBSANADA { background: #e0e7ff; color: #3730a3; }
.actions-cell {
  display: flex;
  gap: 0.4rem;
}
.btn-action {
  padding: 0.35rem 0.7rem;
  border: 1px solid var(--border);
  background: var(--surface);
  border-radius: 4px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.btn-add { background: var(--primary-subtle); color: var(--text); border-color: #bfdbfe; }
.btn-pdf { background: #fef2f2; color: #dc2626; border-color: #fecaca; text-decoration: none; }
.btn-file { background: var(--surface-alt); color: var(--text); }
.btn-pay { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
.pay-actions-group {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
}
.btn-pay-qr {
  background: var(--primary);
  color: var(--on-primary);
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
.clean-list { list-style: none; padding: 0; }
.clean-list li { padding: 0.4rem 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 0.4rem; }
.timeline { border-left: 2px solid var(--border); padding-left: 1rem; margin-top: 1rem; }
.timeline-item { position: relative; margin-bottom: 1rem; }
.timeline-dot { position: absolute; left: -1.35rem; top: 0.2rem; width: 10px; height: 10px; background: #1e3a8a; border-radius: 50%; }
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
.modal-body-scroll {
  max-height: 70vh;
  overflow-y: auto;
  padding-right: 0.5rem;
}
.mt-3 { margin-top: 1rem; }
.mt-4 { margin-top: 1.5rem; }
</style>
