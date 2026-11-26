<template>
  <section v-if="esCpd" class="usuarios-cpd" aria-labelledby="titulo-usuarios-cpd">
    <h1 id="titulo-usuarios-cpd">Registro de usuarios</h1>
    <p v-if="confirmacion" role="status">{{ confirmacion }}</p>
    <div class="opciones">
      <section>
        <h2>Alumnos</h2>
        <p>Alta con registro universitario, carrera y semestre.</p>
        <button type="button" class="btn-primary" @click="abrir('ESTUDIANTE')">
          <GraduationCap :size="16" aria-hidden="true" /> Registrar alumno
        </button>
      </section>
      <section>
        <h2>Personal</h2>
        <p>Altas de CPD, Personal Académico y Dirección de Carrera.</p>
        <button type="button" class="btn-primary" @click="abrir('CPD')">
          <UserPlus :size="16" aria-hidden="true" /> Registrar personal
        </button>
      </section>
    </div>
    <section class="lista-usuarios" aria-labelledby="titulo-usuarios-registrados">
      <h2 id="titulo-usuarios-registrados">Usuarios registrados</h2>
      <form class="filtros" @submit.prevent="cargarUsuarios()">
        <label>
          Buscar
          <input v-model.trim="buscar" type="search" maxlength="100" placeholder="Nombre, correo o registro" />
        </label>
        <label>
          <span id="filtro-rol-cpd">Rol</span>
          <select v-model="rol" aria-labelledby="filtro-rol-cpd">
            <option value="">Todos los roles</option>
            <option v-for="(nombre, codigo) in roles" :key="codigo" :value="codigo">{{ nombre }}</option>
          </select>
        </label>
        <button type="submit" class="btn-primary" :disabled="cargando">Buscar</button>
      </form>
      <p v-if="error" role="alert">{{ error }}</p>
      <p v-else-if="cargando" role="status">Cargando usuarios…</p>
      <p v-else-if="usuarios && !usuarios.data.length">No se encontraron usuarios.</p>
      <template v-else-if="usuarios">
        <p class="total-usuarios">{{ usuarios.total }} {{ usuarios.total === 1 ? 'usuario' : 'usuarios' }}</p>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Usuarios registrados">
          <table class="data-table">
            <thead>
              <tr>
                <th scope="col">Usuario</th><th scope="col">Rol</th><th scope="col">Registro universitario</th>
                <th scope="col">Carrera</th><th scope="col">Estado</th><th scope="col">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="cuenta in usuarios.data" :key="cuenta.id">
                <th scope="row">{{ cuenta.nombre }}<small class="correo">{{ cuenta.email }}</small></th>
                <td>{{ cuenta.roles.map(codigo => roles[codigo] ?? codigo).join(', ') || '—' }}</td>
                <td>{{ cuenta.registro_universitario || '—' }}</td>
                <td>{{ cuenta.carrera || '—' }}</td>
                <td><span :class="['status-badge', cuenta.estado]">{{ cuenta.estado }}</span></td>
                <td>
                  <button v-if="cuenta.editable" type="button" class="btn-action"
                    :aria-label="`Editar a ${cuenta.nombre}`" @click="abrirEdicion(cuenta.id)">Editar</button>
                  <small v-else class="cuenta-propia">Tu cuenta</small>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <nav v-if="usuarios.last_page > 1" class="paginas" aria-label="Páginas de usuarios">
          <button type="button" class="btn-action" :disabled="usuarios.current_page <= 1" @click="cambiarPagina(-1)">Anterior</button>
          <span>Página {{ usuarios.current_page }} de {{ usuarios.last_page }}</span>
          <button type="button" class="btn-action" :disabled="!usuarios.next_page_url" @click="cambiarPagina(1)">Siguiente</button>
        </nav>
      </template>
    </section>
    <RegistroUsuarioDialog ref="registro" @registrado="registrado" @actualizado="actualizado" />
  </section>
  <p v-else-if="usuario" role="status">Esta vista está disponible para el personal de CPD.</p>
</template>

<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { GraduationCap, UserPlus } from '@lucide/vue';
import RegistroUsuarioDialog from '../../components/common/RegistroUsuarioDialog.vue';
import api from '../../services/api';

const props = defineProps({ usuario: Object });
const esCpd = computed(() => props.usuario?.roles?.some(rol => rol.nombre === 'CPD'));
const registro = ref(null);
const confirmacion = ref('');
const usuarios = ref(null);
const buscar = ref('');
const rol = ref('');
const cargando = ref(false);
const error = ref('');
const roles = { ESTUDIANTE: 'Alumno', CPD: 'CPD', PERSONAL_ACADEMICO: 'Personal Académico', DIRECTOR: 'Director de Carrera' };
let consulta;
let filtrosAplicados = {};

function abrir(rol) {
  confirmacion.value = '';
  registro.value.abrir(rol);
}

function abrirEdicion(id) {
  confirmacion.value = '';
  registro.value.abrirEdicion(id);
}

function actualizado(mensaje) {
  confirmacion.value = mensaje;
  consultarPagina(1);
}

function registrado(mensaje) {
  confirmacion.value = mensaje;
  buscar.value = '';
  rol.value = '';
  cargarUsuarios();
}

function cargarUsuarios() {
  filtrosAplicados = {
    ...(buscar.value ? { buscar: buscar.value } : {}),
    ...(rol.value ? { rol: rol.value } : {}),
  };
  consultarPagina(1);
}

function cambiarPagina(desplazamiento) {
  consultarPagina(usuarios.value.current_page + desplazamiento);
}

async function consultarPagina(page) {
  consulta?.abort();
  consulta = new AbortController();
  const { signal } = consulta;
  cargando.value = true;
  error.value = '';
  try {
    const { data } = await api.get('/cpd/usuarios', { params: { ...filtrosAplicados, page }, signal, timeout: 10000 });
    if (!signal.aborted) usuarios.value = data;
  } catch {
    if (!signal.aborted) error.value = 'No se pudo cargar la lista. Pulsa Buscar para reintentar.';
  } finally {
    if (!signal.aborted) cargando.value = false;
  }
}

watch(esCpd, (permitido) => {
  if (permitido) cargarUsuarios();
  else {
    consulta?.abort();
    usuarios.value = null;
  }
}, { immediate: true });

onUnmounted(() => consulta?.abort());
</script>

<style scoped>
.usuarios-cpd h1 {
  margin: 0 0 1rem;
  font-size: 1.5rem;
}

.opciones {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 17rem), 1fr));
  gap: 1rem;
}

.opciones section {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: .5rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}

.opciones h2,
.opciones p {
  margin: 0;
}

.lista-usuarios {
  margin-top: 1rem;
  padding: 1rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}

.lista-usuarios h2 {
  margin: 0 0 .75rem;
  font-size: 1.125rem;
}

.filtros,
.paginas {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: .625rem;
}

.filtros label {
  display: grid;
  gap: .25rem;
  min-width: 0;
  font-size: .875rem;
}

.filtros input {
  width: 100%;
  min-width: 0;
  padding: .65rem .9rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  font: inherit;
  background: var(--surface);
  color: var(--text);
}

.correo {
  display: block;
  margin-top: .125rem;
  font-weight: 400;
}

.correo,
.cuenta-propia,
.total-usuarios {
  color: var(--text-muted);
}

.paginas {
  justify-content: flex-end;
  align-items: center;
  margin-top: .75rem;
}

.opciones h2 {
  font-size: 1.125rem;
}

.opciones p {
  flex: 1;
  color: var(--text-muted);
}
</style>
