<template>
  <div class="role-view">
    <!-- WIDGET DE ETAPAS DINÁMICAS PRO (En Curso / Próxima / Concluida) -->
    <EtapasCronograma :periodo="periodos[0] || null" />

    <div class="view-header">
      <div>
        <h2>Panel de Dirección de Carrera (CU1, CU3, CU4, CU5)</h2>
        <p class="view-desc">
          Gestión de usuarios/estudiantes, evaluación resolutiva de solicitudes, control transaccional de cupos y remisión de oficios a CPD.
        </p>
      </div>
      <div class="header-actions">
        <button @click="submodulo = 'usuarios'" :class="{ active: submodulo === 'usuarios' }" class="btn-sub">
          <Users :size="15" class="inline-icon" /> Alumnos y Usuarios (CU1)
        </button>
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

    <!-- SUBMODULO 0: GESTION DE ALUMNOS Y USUARIOS (CU1) -->
    <div v-if="submodulo === 'usuarios'">
      <div class="sub-header">
        <div>
          <h3>Directorio de Usuarios y Estudiantes Matriculados (CU1)</h3>
          <p class="text-muted">Registro administrativo de alumnos con los 11 campos estipulados y gestión de estado.</p>
        </div>
        <button @click="modalEstudiante = true" class="btn-primary">
          <UserPlus :size="15" class="inline-icon" /> Matricular Nuevo Alumno (CU1)
        </button>
      </div>

      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>C.I.</th>
              <th>Estudiante / Nombre</th>
              <th>Registro Univ.</th>
              <th>Carrera</th>
              <th>Semestre</th>
              <th>Correo Institucional</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in usuarios" :key="u.id_usuario">
              <td><strong>{{ u.ci }}</strong></td>
              <td>{{ u.nombre }} {{ u.apellidos }}</td>
              <td><span class="code-tag">{{ u.estudiante?.registro_universitario || 'N/A' }}</span></td>
              <td>{{ u.estudiante?.carrera?.nombre || 'Administración' }}</td>
              <td>{{ u.estudiante ? `Semestre ${u.estudiante.semestre_actual}` : '-' }}</td>
              <td>{{ u.email }}</td>
              <td><span :class="['status-badge', u.estado]">{{ u.estado }}</span></td>
            </tr>
            <tr v-if="usuarios.length === 0">
              <td colspan="7" class="no-records">No hay usuarios cargados en este momento.</td>
            </tr>
          </tbody>
        </table>
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
                <button v-if="sol.estado === 'ENVIADA' || sol.estado === 'OBSERVADA' || sol.estado === 'SUBSANADA'" @click="abrirEvaluar(sol)" class="btn-action btn-eval">
                  <Scale :size="14" class="inline-icon" /> Evaluar (CU3)
                </button>
                <button v-if="sol.estado === 'APROBADA_DIRECCION'" @click="validarPagoManual(sol)" :disabled="validandoPago" class="btn-action btn-eval">
                  Omitir y validar pago
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
        <div>
          <h3>Grupos y Reservas con Bloqueo Transaccional (FOR UPDATE)</h3>
          <p class="text-muted">Si un grupo tiene 0 cupos, se puede habilitar sobrecupo (+5, +10) para casos especiales o reservar cupos existentes.</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
          <button @click="abrirModalAmpliar" class="btn-secondary">
            <Plus :size="15" class="inline-icon" /> Habilitar Sobrecupo (+ Cupos)
          </button>
          <button @click="modalReserva = true" class="btn-primary">
            <Lock :size="15" class="inline-icon" /> Reservar / Bloquear Cupo
          </button>
        </div>
      </div>
      <div class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>Materia</th>
              <th>Grupo</th>
              <th>Horario y Días</th>
              <th>Cupo Máximo</th>
              <th>Cupos Libres</th>
              <th>Estado</th>
              <th>Acción Rápida</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in grupos" :key="g.id">
              <td><strong>{{ g.materia?.sigla }}</strong> - {{ g.materia?.nombre }}</td>
              <td><span class="code-tag">{{ g.sigla_grupo }}</span></td>
              <td>
                <div v-if="g.horarios && g.horarios.length > 0" style="font-size: 0.82rem;">
                  <span v-for="(h, hi) in g.horarios" :key="hi" style="display: block;">
                    {{ h.dia?.substring(0, 3) }} {{ h.hora_inicio?.substring(0, 5) }}-{{ h.hora_fin?.substring(0, 5) }} ({{ h.aula }})
                  </span>
                </div>
                <span v-else class="text-muted" style="font-size: 0.8rem;">Sin horario asignado</span>
              </td>
              <td>{{ g.cupo_maximo }}</td>
              <td>
                <span :style="{ color: g.cupos_disponibles === 0 ? '#dc2626' : '#16a34a', fontWeight: 'bold' }">
                  {{ g.cupos_disponibles }}
                </span>
                <span v-if="g.cupos_disponibles === 0" style="font-size: 0.75rem; background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; margin-left: 6px;">AGOTADO</span>
              </td>
              <td><span class="status-badge ABIERTO">{{ g.estado }}</span></td>
              <td>
                <button @click="seleccionarGrupoParaAmpliar(g)" class="btn-action">
                  + Habilitar Cupos
                </button>
              </td>
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

    <!-- MODAL MATRICULAR ALUMNO (CU1 - 11 CAMPOS) -->
    <div v-if="modalEstudiante" class="modal-backdrop">
      <div class="modal-box modal-lg">
        <div class="modal-header">
          <div>
            <h3>CU1: Matricular Nuevo Alumno</h3>
            <p class="modal-subtitle">Registro administrativo de estudiantes con validación de 11 campos oficiales</p>
          </div>
          <button @click="modalEstudiante = false" class="btn-close"><X :size="18" /></button>
        </div>
        <div class="modal-body-scroll">
          <form @submit.prevent="guardarEstudiante" class="modal-form">
            <div class="row">
              <div class="form-group col">
                <label>Nombres (*):</label>
                <input v-model="formEstudiante.nombre" required placeholder="Carlos Eduardo" />
              </div>
              <div class="form-group col">
                <label>Apellidos (*):</label>
                <input v-model="formEstudiante.apellidos" required placeholder="Méndez Vargas" />
              </div>
            </div>

            <div class="row">
              <div class="form-group col">
                <label>Cédula de Identidad (CI) (*):</label>
                <input v-model="formEstudiante.ci" required placeholder="7890123" />
              </div>
              <div class="form-group col">
                <label>Registro Universitario (RU) (*):</label>
                <input v-model="formEstudiante.registro_universitario" required placeholder="221098451" />
              </div>
            </div>

            <div class="row">
              <div class="form-group col">
                <label>Correo Electrónico (*):</label>
                <input v-model="formEstudiante.email" type="email" required placeholder="carlos.mendez@ficct.uagrm.edu.bo" />
              </div>
              <div class="form-group col">
                <label>Teléfono (*):</label>
                <input v-model="formEstudiante.telefono" required placeholder="78945612" />
              </div>
            </div>

            <div class="form-group">
              <label>Dirección de Residencia (*):</label>
              <input v-model="formEstudiante.direccion" required placeholder="Barrio Las Palmas Calle 3 #45" />
            </div>

            <div class="row">
              <div class="form-group col">
                <label>Fecha de Nacimiento (*):</label>
                <input v-model="formEstudiante.fecha_nacimiento" type="date" required />
              </div>
              <div class="form-group col">
                <label>Carrera Asignada (*):</label>
                <select v-model="formEstudiante.id_carrera" required>
                  <option value="" disabled>Seleccione Carrera...</option>
                  <option v-for="c in carreras" :key="c.id" :value="c.id">{{ c.nombre }} ({{ c.codigo }})</option>
                </select>
              </div>
              <div class="form-group col">
                <label>Semestre (1-12) (*):</label>
                <input v-model.number="formEstudiante.semestre" type="number" min="1" max="12" required />
              </div>
            </div>

            <div class="form-group">
              <label>Contraseña Inicial (*):</label>
              <input v-model="formEstudiante.password" type="password" required minlength="6" placeholder="Mínimo 6 caracteres" />
            </div>

            <div class="modal-footer">
              <button type="button" @click="modalEstudiante = false" class="btn-secondary">Cancelar</button>
              <button type="submit" class="btn-primary" :disabled="loading">
                <UserPlus :size="15" class="inline-icon" /> Registrar y Matricular Alumno
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL EVALUAR (CU3) -->
    <div v-if="modalEvaluar && solicitudActiva" class="modal-backdrop">
      <div class="modal-box modal-lg">
        <div class="modal-header">
          <div>
            <h3>CU3: Evaluar Solicitud {{ solicitudActiva.codigo }}</h3>
            <p class="modal-subtitle">Dictamen formal por materia y resolución de Dirección de Carrera</p>
          </div>
          <button @click="modalEvaluar = false" class="btn-close"><X :size="18" /></button>
        </div>
        <div class="modal-body-scroll">
          <form @submit.prevent="ejecutarEvaluacion" class="modal-form">
            <div class="form-group">
              <label>Decisión Formal (*):</label>
              <select v-model="formEval.decision" @change="cambiarDecisionGeneral" required>
                <option value="APROBADA">APROBADA (Habilita pago de arancel)</option>
                <option value="RECHAZADA">RECHAZADA</option>
                <option value="OBSERVADA">OBSERVADA (Exigir subsanación de requisitos)</option>
              </select>
            </div>

            <!-- Evaluación individual por materia solicitada -->
            <div v-if="solicitudActiva.grupos && solicitudActiva.grupos.length > 0" class="form-group">
              <label>Dictamen por Asignatura Solicitada:</label>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Materia / Grupo</th>
                      <th>Resolución</th>
                      <th>Observación</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="sg in formEvalMaterias" :key="sg.id">
                      <td><strong>{{ sg.materia_sigla }}</strong> (Gr. {{ sg.sigla_grupo }})</td>
                      <td>
                        <select v-model="sg.estado" @change="verificarEstadoMaterias" class="form-control-sm">
                          <option value="APROBADA">APROBADA</option>
                          <option value="RECHAZADA">RECHAZADA</option>
                          <option value="PENDIENTE">PENDIENTE</option>
                        </select>
                      </td>
                      <td>
                        <input v-model="sg.observacion" placeholder="Motivo o condición..." class="form-control-sm" />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <small class="text-muted">Si todas las materias quedan rechazadas, el caso especial pasa automáticamente a RECHAZADO.</small>
            </div>
            
            <div v-if="formEval.decision === 'RECHAZADA'" class="form-group">
              <label>Motivo de Rechazo (*):</label>
              <textarea v-model="formEval.motivo_rechazo" rows="3" required placeholder="Detalla el motivo del rechazo para notificar al estudiante..."></textarea>
            </div>
            <div class="form-group">
              <label>Justificación / Observaciones:</label>
              <textarea v-model="formEval.justificacion" rows="3" placeholder="Observaciones institucionales o indicaciones para el alumno..."></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" @click="modalEvaluar = false" class="btn-secondary">Cancelar</button>
              <button type="submit" class="btn-primary" :disabled="loading">Guardar Resolución</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL RESERVAR CUPO (CU4) -->
    <div v-if="modalReserva" class="modal-backdrop">
      <div class="modal-box">
        <div class="modal-header">
          <div>
            <h3>CU4: Reservar Cupo Transaccional</h3>
            <p class="modal-subtitle">Bloqueo de cupos existentes con SELECT FOR UPDATE</p>
          </div>
          <button @click="modalReserva = false" class="btn-close"><X :size="18" /></button>
        </div>
        <div class="modal-body-scroll">
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
    </div>

    <!-- MODAL HABILITAR SOBRECUPO (AMPLIACIÓN DE CUPOS) -->
    <div v-if="modalAmpliarCupos" class="modal-backdrop">
      <div class="modal-box">
        <div class="modal-header">
          <div>
            <h3>Habilitar Cupos Extra / Sobrecupo</h3>
            <p class="modal-subtitle">Aumento de cupos para grupos con demanda de casos especiales</p>
          </div>
          <button @click="modalAmpliarCupos = false" class="btn-close"><X :size="18" /></button>
        </div>
        <div class="modal-body-scroll">
          <form @submit.prevent="guardarAmpliacionCupos" class="modal-form">
            <div class="alert-box-info mb-3">
              Aumenta el cupo máximo y disponible para materias solicitadas por casos especiales (ej: añadir 5 o 10 cupos más).
            </div>
            <div class="form-group">
              <label>Grupo a Ampliar (*):</label>
              <select v-model="formAmpliar.id_grupo" required>
                <option value="" disabled>Seleccione grupo...</option>
                <option v-for="g in grupos" :key="g.id" :value="g.id">
                  {{ g.materia?.sigla }} - Gr. {{ g.sigla_grupo }} (Cupo actual: {{ g.cupo_maximo }}, Libres: {{ g.cupos_disponibles }})
                </option>
              </select>
            </div>
            <div class="form-group">
              <label>Cantidad de Cupos a Adicionar (*):</label>
              <input v-model.number="formAmpliar.cupos_extra" type="number" min="1" max="50" required />
            </div>
            <div class="form-group">
              <label>Motivo / Resolución de Sobrecupo (*):</label>
              <input v-model="formAmpliar.motivo" required placeholder="Habilitación por demanda de casos especiales..." />
            </div>
            <div class="modal-footer">
              <button type="button" @click="modalAmpliarCupos = false" class="btn-secondary">Cancelar</button>
              <button type="submit" class="btn-primary" :disabled="loading">Habilitar Cupos</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- MODAL GENERAR OFICIO A CPD (CU5) -->
    <div v-if="modalOficio" class="modal-backdrop">
      <div class="modal-box modal-lg">
        <div class="modal-header">
          <div>
            <h3>CU5: Emisión de Oficio Consolidado a CPD</h3>
            <p class="modal-subtitle">Genera el archivo CSV oficial y remite el lote de casos aprobados</p>
          </div>
          <button @click="modalOficio = false" class="btn-close"><X :size="18" /></button>
        </div>
        <div class="modal-body-scroll">
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
            <h4 class="section-title">Seleccione los casos APROBADOS a consolidar en este lote:</h4>
            <div class="sol-check-list">
              <div v-for="sol in solicitudesAprobadas" :key="sol.id" class="sol-check-item">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                  <input type="checkbox" :value="sol.id" v-model="formOficio.solicitudes_ids" />
                  <span><strong>{{ sol.codigo }}</strong> - {{ sol.estudiante?.usuario?.nombre }} {{ sol.estudiante?.usuario?.apellidos }} ({{ sol.tipo_caso }})</span>
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
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { 
  Users,
  UserPlus,
  FileCheck, 
  Lock, 
  Send, 
  FileSearch, 
  Scale, 
  Plus, 
  X 
} from '@lucide/vue';
import api from '../../services/api';
import EtapasCronograma from '../../components/common/EtapasCronograma.vue';

const submodulo = ref('usuarios');
const usuarios = ref([]);
const solicitudes = ref([]);
const grupos = ref([]);
const oficios = ref([]);
const periodos = ref([]);
const carreras = ref([]);
const loading = ref(false);
const validandoPago = ref(false);

const modalEstudiante = ref(false);
const modalEvaluar = ref(false);
const modalReserva = ref(false);
const modalAmpliarCupos = ref(false);
const modalOficio = ref(false);
const solicitudActiva = ref(null);

const formEstudiante = ref({
  nombre: '',
  apellidos: '',
  ci: '',
  registro_universitario: '',
  email: '',
  telefono: '',
  direccion: '',
  fecha_nacimiento: '',
  id_carrera: '',
  semestre: 1,
  password: 'password123',
});

const formEval = ref({ decision: 'APROBADA', justificacion: '', motivo_rechazo: '' });
const formEvalMaterias = ref([]);
const formReserva = ref({ id_grupo: '', cantidad: 1, motivo: '' });
const formAmpliar = ref({ id_grupo: '', cupos_extra: 5, motivo: '' });
const formOficio = ref({ id_periodo: '', destinatario: 'Director CPD - UAGRM', referencia: 'Remisión de Casos Especiales Aprobados', solicitudes_ids: [] });

const formatearFecha = (f) => f ? new Date(f).toLocaleString('es-BO') : 'N/A';

const solicitudesAprobadas = computed(() => {
  return solicitudes.value.filter(s => s.estado === 'APROBADA');
});

const cargarDatos = async () => {
  try {
    const [resU, resS, resG, resO, resP, resC] = await Promise.all([
      api.get('/admin/usuarios?rol=ESTUDIANTE'),
      api.get('/solicitudes'),
      api.get('/cupos/grupos'),
      api.get('/oficios-cpd'),
      api.get('/catalogos/periodos'),
      api.get('/catalogos/carreras')
    ]);
    usuarios.value = resU.data.data || resU.data;
    solicitudes.value = resS.data.data || resS.data;
    grupos.value = resG.data;
    oficios.value = resO.data;
    periodos.value = resP.data;
    carreras.value = resC.data;
    if (periodos.value.length) formOficio.value.id_periodo = periodos.value[0].id;
    if (carreras.value.length) formEstudiante.value.id_carrera = carreras.value[0].id;
  } catch (e) {
    console.error(e);
  }
};

const validarPagoManual = async (solicitud) => {
  if (!window.confirm(`¿Validar manualmente el arancel del trámite ${solicitud.codigo} y dejarlo listo para remitir a CPD?`)) return;

  validandoPago.value = true;
  try {
    await api.post(`/solicitudes/${solicitud.id}/validar-pago-manual`);
    await cargarDatos();
  } catch (e) {
    alert(e.response?.data?.error || e.response?.data?.message || 'Error al validar el pago.');
  } finally {
    validandoPago.value = false;
  }
};

const guardarEstudiante = async () => {
  loading.value = true;
  try {
    await api.post('/admin/usuarios/estudiante', formEstudiante.value);
    modalEstudiante.value = false;
    formEstudiante.value = {
      nombre: '',
      apellidos: '',
      ci: '',
      registro_universitario: '',
      email: '',
      telefono: '',
      direccion: '',
      fecha_nacimiento: '',
      id_carrera: carreras.value[0]?.id || '',
      semestre: 1,
      password: 'password123',
    };
    alert('Alumno registrado y matriculado con éxito en la base de datos.');
    cargarDatos();
  } catch (e) {
    alert(e.response?.data?.message || 'Error al registrar alumno.');
  } finally {
    loading.value = false;
  }
};

const abrirEvaluar = (sol) => {
  solicitudActiva.value = sol;
  formEval.value.decision = 'APROBADA';
  formEval.value.justificacion = '';
  formEval.value.motivo_rechazo = '';
  formEvalMaterias.value = (sol.grupos || []).map(sg => ({
    id: sg.id,
    materia_sigla: sg.grupo?.materia?.sigla || 'MAT',
    sigla_grupo: sg.grupo?.sigla_grupo || 'SA',
    estado: sg.estado === 'RECHAZADA' ? 'RECHAZADA' : 'APROBADA',
    observacion: sg.observacion_resolucion || ''
  }));
  verificarEstadoMaterias();
  modalEvaluar.value = true;
};

const verificarEstadoMaterias = () => {
  if (!formEvalMaterias.value.length) return;
  const tieneAprobadas = formEvalMaterias.value.some(m => m.estado === 'APROBADA');
  if (!tieneAprobadas) {
    formEval.value.decision = 'RECHAZADA';
    if (!formEval.value.motivo_rechazo) {
      formEval.value.motivo_rechazo = 'Todas las materias solicitadas fueron rechazadas.';
    }
  }
};

const cambiarDecisionGeneral = () => {
  if (formEval.value.decision === 'APROBADA') {
    const tieneAprobadas = formEvalMaterias.value.some(m => m.estado === 'APROBADA');
    if (!tieneAprobadas && formEvalMaterias.value.length > 0) {
      // Si el usuario pone APROBADA general pero todas las materias están rechazadas, forzamos al menos la primera a APROBADA
      formEvalMaterias.value[0].estado = 'APROBADA';
    }
  } else if (formEval.value.decision === 'RECHAZADA') {
    formEvalMaterias.value.forEach(m => m.estado = 'RECHAZADA');
  }
};

const ejecutarEvaluacion = async () => {
  verificarEstadoMaterias();
  loading.value = true;
  try {
    await api.post(`/solicitudes/${solicitudActiva.value.id}/evaluar`, {
      ...formEval.value,
      materias_resolucion: formEvalMaterias.value.map(m => ({
        id: m.id,
        estado: m.estado,
        observacion: m.observacion
      }))
    });
    modalEvaluar.value = false;
    alert('Evaluación guardada con éxito.');
    cargarDatos();
  } catch (e) {
    alert(e.response?.data?.error || 'Error al evaluar.');
  } finally {
    loading.value = false;
  }
};

const abrirModalAmpliar = () => {
  formAmpliar.value = {
    id_grupo: grupos.value[0]?.id || '',
    cupos_extra: 5,
    motivo: 'Ampliación de sobrecupo para atender casos especiales'
  };
  modalAmpliarCupos.value = true;
};

const seleccionarGrupoParaAmpliar = (g) => {
  formAmpliar.value = {
    id_grupo: g.id,
    cupos_extra: 5,
    motivo: `Sobrecupo habilitado para grupo ${g.sigla_grupo} de ${g.materia?.sigla}`
  };
  modalAmpliarCupos.value = true;
};

const guardarAmpliacionCupos = async () => {
  loading.value = true;
  try {
    const res = await api.post('/cupos/ampliar', formAmpliar.value);
    modalAmpliarCupos.value = false;
    alert(res.data.mensaje || 'Cupos adicionales habilitados exitosamente.');
    cargarDatos();
  } catch (e) {
    alert(e.response?.data?.error || 'Error al ampliar cupos.');
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
  flex-wrap: wrap;
  gap: 1rem;
}
.header-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
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
.status-badge.OBSERVADA { background: #ffedd5; color: #c2410c; }
.status-badge.SUBSANADA { background: #e0e7ff; color: #3730a3; }
.status-badge.ACTIVO { background: #dcfce7; color: #15803d; }
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
  padding: 1rem;
}
.modal-box {
  background: white;
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
  max-width: 760px; 
}
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 0.8rem;
  margin-bottom: 1rem;
}
.modal-header h3 {
  margin: 0;
  font-size: 1.25rem;
  color: #1e293b;
}
.modal-subtitle {
  margin: 0.2rem 0 0 0;
  font-size: 0.85rem;
  color: #64748b;
}
.btn-close {
  background: transparent;
  border: none;
  cursor: pointer;
  color: #64748b;
  padding: 0.3rem;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-close:hover {
  background: #f1f5f9;
  color: #0f172a;
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
  border-top: 1px solid #e2e8f0;
  padding-top: 1rem;
}
.section-title {
  font-size: 0.98rem;
  font-weight: 700;
  color: #1e293b;
  margin: 1.2rem 0 0.6rem 0;
  padding-bottom: 0.3rem;
  border-bottom: 1px solid #f1f5f9;
}
.alert-box-info {
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  color: #1e40af;
  padding: 0.75rem 1rem;
  border-radius: 6px;
  font-size: 0.85rem;
}
.form-group { margin-bottom: 1rem; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.3rem; color: #334155; }
.form-group select, .form-group input, .form-group textarea {
  width: 100%;
  padding: 0.65rem 0.8rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  box-sizing: border-box;
  font-size: 0.9rem;
}
.form-group select:focus, .form-group input:focus, .form-group textarea:focus {
  outline: none;
  border-color: #0064e0;
  box-shadow: 0 0 0 2px rgba(0, 100, 224, 0.15);
}
.form-control-sm {
  padding: 0.35rem 0.5rem !important;
  font-size: 0.85rem !important;
}
.row { display: flex; gap: 1rem; }
.col { flex: 1; }
.sol-check-list {
  max-height: 220px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  padding: 0.5rem;
  border-radius: 6px;
}
.sol-check-item {
  padding: 0.4rem;
  border-bottom: 1px solid #f1f5f9;
}
.auth-desc {
  color: #64748b;
  font-size: 0.85rem;
  margin-bottom: 1rem;
}
</style>
