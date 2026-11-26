<template>
  <dialog ref="dialogo" class="modal-box registro-personal" aria-labelledby="titulo-registro-personal"
    @cancel="guardando && $event.preventDefault()" @close="limpiar">
    <form @submit.prevent="registrar">
      <header>
        <h3 id="titulo-registro-personal">{{ editando ? 'Editar usuario' : esAlumno ? 'Registrar alumno' : 'Registrar personal' }}</h3>
        <button type="button" class="btn-action" aria-label="Cerrar registro" :disabled="guardando" @click="dialogo.close()">
          <X :size="18" aria-hidden="true" />
        </button>
      </header>
      <div v-if="cargandoEdicion || (!edicionLista && editando)" class="campos">
        <p v-if="cargandoEdicion" role="status">Cargando datos del usuario…</p>
        <p v-else class="mensaje" role="alert">{{ error }}</p>
      </div>
      <div v-else class="campos">
        <p v-if="error" class="mensaje" role="alert">{{ error }}</p>
        <label v-if="!esAlumno && !editando">
          <span id="etiqueta-rol-personal">Rol</span>
          <select v-model="form.rol" aria-labelledby="etiqueta-rol-personal" required>
            <option value="CPD">CPD</option>
            <option value="PERSONAL_ACADEMICO">Personal Académico</option>
            <option value="DIRECTOR">Director de Carrera</option>
          </select>
        </label>
        <label v-if="requiereCarrera">
          <span id="etiqueta-carrera-personal">Carrera</span>
          <select v-model="form.id_carrera" aria-labelledby="etiqueta-carrera-personal" required>
            <option disabled value="">{{ cargandoCarreras ? 'Cargando carreras…' : 'Selecciona una carrera' }}</option>
            <option v-for="carrera in carreras" :key="carrera.id" :value="carrera.id">{{ carrera.nombre }}</option>
          </select>
        </label>
        <div v-if="requiereCarrera && errorCarreras" class="mensaje" role="status">
          {{ errorCarreras }}
          <button type="button" class="btn-secondary" @click="cargarCarreras">Reintentar</button>
        </div>
        <label v-if="form.rol === 'PERSONAL_ACADEMICO'" class="fila-completa">
          Cargo (opcional)
          <input v-model.trim="form.cargo" type="text" maxlength="100" />
        </label>
        <template v-if="esAlumno">
          <label>
            Registro universitario
            <input v-model.trim="form.registro_universitario" type="text" maxlength="30" :readonly="editando" required />
            <small v-if="editando">Identificador único del alumno</small>
          </label>
          <label>
            Semestre
            <input v-model.number="form.semestre" type="number" min="1" max="12" required />
          </label>
        </template>
        <label>
          Nombres
          <input v-model.trim="form.nombre" type="text" maxlength="100" required />
        </label>
        <label>
          Apellidos
          <input v-model.trim="form.apellidos" type="text" maxlength="100" required />
        </label>
        <label>
          Carnet de identidad
          <input v-model.trim="form.ci" type="text" maxlength="30" required />
        </label>
        <label>
          Fecha de nacimiento
          <input v-model="form.fecha_nacimiento" type="date" :max="fechaMaxima" :required="!editando" />
        </label>
        <label>
          Correo electrónico
          <input v-model.trim="form.email" type="email" maxlength="150" required />
        </label>
        <label>
          Teléfono
          <input :value="form.telefono" @input="actualizarTelefono" type="tel" inputmode="numeric"
            pattern="[0-9]+" maxlength="30" autocomplete="tel" :required="!editando" />
        </label>
        <label class="fila-completa">
          Dirección
          <input v-model.trim="form.direccion" type="text" maxlength="500" :required="!editando" />
        </label>
        <label v-if="!editando" class="fila-completa">
          Contraseña
          <input v-model="form.password" type="password" minlength="6" autocomplete="new-password" required />
        </label>
      </div>
      <footer>
        <button type="button" class="btn-secondary" :disabled="guardando" @click="dialogo.close()">Cancelar</button>
        <button v-if="!editando || edicionLista" type="submit" class="btn-primary"
          :disabled="guardando || (requiereCarrera && !form.id_carrera)">
          {{ guardando ? 'Guardando…' : editando ? 'Guardar cambios' : 'Registrar usuario' }}
        </button>
      </footer>
    </form>
  </dialog>
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref } from 'vue';
import { X } from '@lucide/vue';
import api from '../../services/api';

const emit = defineEmits(['registrado', 'actualizado']);
const dialogo = ref(null);
const form = ref({});
const carreras = ref([]);
const guardando = ref(false);
const cargandoCarreras = ref(false);
const error = ref('');
const errorCarreras = ref('');
const idEdicion = ref(null);
const cargandoEdicion = ref(false);
const edicionLista = ref(false);
const editando = computed(() => idEdicion.value !== null);
const requiereCarrera = computed(() => ['ESTUDIANTE', 'DIRECTOR', 'PERSONAL_ACADEMICO'].includes(form.value.rol));
const esAlumno = computed(() => form.value.rol === 'ESTUDIANTE');
const roles = { ESTUDIANTE: 'Alumno', CPD: 'CPD', PERSONAL_ACADEMICO: 'Personal Académico', DIRECTOR: 'Director de Carrera' };
const formatoFecha = new Intl.DateTimeFormat('sv-SE', { timeZone: 'America/La_Paz' });
const fechaMaxima = ref('');
let consultaEdicion;

function actualizarTelefono(evento) {
  const campo = evento.target;
  const posicion = campo.value.slice(0, campo.selectionStart ?? campo.value.length).replace(/[^0-9]/g, '').length;
  form.value.telefono = campo.value.replace(/[^0-9]/g, '');
  if (campo.value !== form.value.telefono) {
    campo.value = form.value.telefono;
    campo.setSelectionRange(posicion, posicion);
  }
}

function limpiar() {
  consultaEdicion?.abort();
  idEdicion.value = null;
  cargandoEdicion.value = false;
  edicionLista.value = false;
  form.value = {
    rol: 'CPD', id_carrera: '', cargo: '', registro_universitario: '', semestre: 1,
    nombre: '', apellidos: '', ci: '', email: '', telefono: '', direccion: '', fecha_nacimiento: '', password: '',
  };
  error.value = '';
}

async function cargarCarreras() {
  if (cargandoCarreras.value) return;
  cargandoCarreras.value = true;
  errorCarreras.value = '';
  try {
    const { data } = await api.get('/catalogos/carreras', { timeout: 10000 });
    carreras.value = data;
    if (!data.length) errorCarreras.value = 'No hay carreras disponibles.';
  } catch {
    errorCarreras.value = 'No se pudo cargar el catálogo de carreras.';
  } finally {
    cargandoCarreras.value = false;
  }
}

function abrir(rol) {
  limpiar();
  fechaMaxima.value = formatoFecha.format(new Date());
  form.value.rol = rol;
  dialogo.value.showModal();
  if (!carreras.value.length) cargarCarreras();
}

async function abrirEdicion(id) {
  limpiar();
  fechaMaxima.value = formatoFecha.format(new Date());
  idEdicion.value = id;
  cargandoEdicion.value = true;
  dialogo.value.showModal();
  consultaEdicion = new AbortController();
  const { signal } = consultaEdicion;
  try {
    const { data } = await api.get(`/cpd/usuarios/${id}`, { signal, timeout: 10000 });
    if (signal.aborted) return;
    form.value = { ...data, id_carrera: data.id_carrera ?? '', fecha_nacimiento: data.fecha_nacimiento?.slice(0, 10) ?? '',
      telefono: data.telefono ?? '', direccion: data.direccion ?? '', cargo: data.cargo ?? '' };
    edicionLista.value = true;
    if (requiereCarrera.value && !carreras.value.length) cargarCarreras();
  } catch (e) {
    if (!signal.aborted) error.value = e.response?.data?.message || 'No se pudo cargar el usuario.';
  } finally {
    if (!signal.aborted) cargandoEdicion.value = false;
  }
}

async function registrar() {
  if (editando.value && !edicionLista.value) return;
  if (guardando.value) return;
  guardando.value = true;
  error.value = '';
  const { cargo, id_carrera, rol, registro_universitario, semestre, ...datos } = form.value;
  if (requiereCarrera.value) datos.id_carrera = id_carrera;
  const recurso = esAlumno.value ? 'estudiante' : 'personal';
  if (esAlumno.value) datos.semestre = semestre;
  if (!editando.value && esAlumno.value) datos.registro_universitario = registro_universitario;
  if (!editando.value && !esAlumno.value) datos.rol = rol;
  if (rol === 'PERSONAL_ACADEMICO' && cargo) datos.cargo = cargo;
  try {
    if (editando.value) {
      await api.patch(`/cpd/usuarios/${idEdicion.value}`, datos, { timeout: 15000 });
      emit('actualizado', `${datos.nombre} ${datos.apellidos} · Cambios guardados.`);
    } else {
      await api.post(`/admin/usuarios/${recurso}`, datos, { timeout: 15000 });
      emit('registrado', `${datos.nombre} ${datos.apellidos} · ${roles[rol]}. Usuario registrado.`);
    }
    dialogo.value.close();
  } catch (e) {
    const respuesta = e.response?.data;
    error.value = Object.values(respuesta?.errors ?? {}).flat().join(' ')
      || respuesta?.message || 'No se pudo confirmar el guardado. Revisa la lista antes de reintentar.';
    await nextTick();
    dialogo.value?.querySelector('[role="alert"]')?.scrollIntoView({ block: 'nearest' });
  } finally {
    guardando.value = false;
  }
}

onUnmounted(() => consultaEdicion?.abort());
defineExpose({ abrir, abrirEdicion });
</script>

<style scoped>
dialog.modal-box.registro-personal {
  width: min(38rem, calc(100vw - 1.5rem));
  max-width: none;
  max-height: calc(100dvh - 1.5rem);
  padding: 0;
  margin: auto;
  overflow: hidden;
}

dialog.registro-personal:not([open]) {
  display: none;
}

.registro-personal::backdrop {
  background: rgb(10 19 23 / 65%);
}

.registro-personal form {
  display: flex;
  flex-direction: column;
  max-height: calc(100dvh - 1.5rem - 2px);
}

.registro-personal :is(header, footer) {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-items: center;
  gap: .5rem;
  padding: .75rem 1rem;
  border-bottom: 1px solid var(--border);
}

.registro-personal h3 {
  margin: 0;
}

.registro-personal footer {
  justify-content: flex-end;
  border-bottom: 0;
  border-top: 1px solid var(--border);
}

.campos {
  min-height: 0;
  overflow: auto;
  overscroll-behavior: contain;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 13rem), 1fr));
  gap: .75rem;
  padding: 1rem;
}

.campos label {
  display: grid;
  gap: .25rem;
  min-width: 0;
}

.campos :is(input, select) {
  width: 100%;
  min-width: 0;
  font: inherit;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: .5rem .625rem;
  background: var(--surface);
  color: var(--text);
}

.fila-completa,
.mensaje {
  grid-column: 1 / -1;
}

.mensaje {
  margin: 0;
  padding: .5rem .75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
}
</style>
