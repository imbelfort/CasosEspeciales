<template>
  <section class="bitacora" :aria-busy="cargando">
    <h1>Bitácora CPD</h1>

    <form class="filtros" @submit.prevent="aplicarFiltros">
      <label>Evento
        <select v-model="filtros.evento">
          <option value="">Todos</option>
          <option v-for="(nombre, codigo) in eventos" :key="codigo" :value="codigo">{{ nombre }}</option>
        </select>
      </label>
      <label>Cuenta <input v-model.trim="filtros.cuenta" type="email" maxlength="254" /></label>
      <label>Dirección IP <input v-model.trim="filtros.ip" maxlength="45" /></label>
      <label>Desde <input v-model="filtros.desde" type="date" /></label>
      <label>Hasta <input v-model="filtros.hasta" type="date" :min="filtros.desde || undefined" /></label>
      <button class="btn accion-principal" type="submit" :disabled="cargando">Consultar</button>
      <button class="btn accion-secundaria" type="button" :disabled="cargando" @click="limpiar">Limpiar</button>
    </form>

    <p v-if="error" role="alert">{{ error }}</p>
    <p v-else-if="cargando" role="status">Cargando bitácora…</p>

    <template v-if="datos && !error">
      <section>
        <h2>Índice de acceso por recurso</h2>
        <div v-if="datos.recursos.length" class="tabla">
          <table>
            <thead><tr><th>Recurso</th><th>Total</th><th>Exitosos</th><th>Fallidos</th><th>IP distintas</th><th>Último acceso</th></tr></thead>
            <tbody>
              <tr v-for="item in datos.recursos" :key="item.metodo + item.recurso">
                <td>
                  <button class="btn recurso" type="button" :disabled="cargando" @click="filtrarRecurso(item)">
                    {{ item.metodo }} {{ item.recurso }}
                  </button>
                </td>
                <td>{{ item.total }}</td><td>{{ item.exitosos }}</td><td>{{ item.fallidos }}</td>
                <td>{{ item.ips }}</td><td>{{ fechaLocal(item.ultimo_acceso) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else>Sin accesos para los filtros seleccionados.</p>
      </section>

      <section>
        <h2>Registro de eventos</h2>
        <p v-if="aplicados.recurso">
          {{ aplicados.metodo }} {{ aplicados.recurso }}
          <button class="btn accion-secundaria" type="button" :disabled="cargando" @click="filtrarRecurso({})">Quitar filtro de recurso</button>
        </p>
        <div v-if="datos.eventos.data.length" class="tabla">
          <table>
            <thead><tr><th>Fecha y hora</th><th>Evento</th><th>Cuenta</th><th>IP</th><th>Recurso</th><th>Referencia</th><th>HTTP</th></tr></thead>
            <tbody>
              <tr v-for="item in datos.eventos.data" :key="item.id">
                <td>{{ fechaLocal(item.fecha) }}</td><td>{{ eventos[item.evento] }}</td>
                <td>{{ item.cuenta || '—' }}</td><td>{{ item.ip || '—' }}</td>
                <td>{{ item.metodo }} {{ item.recurso }}</td>
                <td>{{ item.referencia || '—' }}</td><td>{{ item.estado_http }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-else>Sin eventos para los filtros seleccionados.</p>
        <div v-if="datos.eventos.last_page > 1" class="paginas">
          <button class="btn accion-secundaria" type="button" :disabled="cargando || datos.eventos.current_page <= 1" @click="cargar(datos.eventos.current_page - 1, datos.hasta_id)">Anterior</button>
          <span>Página {{ datos.eventos.current_page }} de {{ datos.eventos.last_page }}</span>
          <button class="btn accion-secundaria" type="button" :disabled="cargando || datos.eventos.current_page >= datos.eventos.last_page" @click="cargar(datos.eventos.current_page + 1, datos.hasta_id)">Siguiente</button>
        </div>
      </section>
    </template>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../services/api';

const eventos = {
  INICIO_SESION: 'Inicio de sesión',
  INTENTO_FALLIDO: 'Intento de inicio fallido',
  CIERRE_SESION: 'Cierre de sesión',
  ACCESO: 'Acceso al recurso',
  ACCESO_DENEGADO: 'Acceso denegado',
  ERROR: 'Error de solicitud',
};

const filtros = ref({});
const aplicados = ref({});
const datos = ref(null);
const cargando = ref(false);
const error = ref('');
const formatoFecha = new Intl.DateTimeFormat('es-BO', {
  dateStyle: 'short', timeStyle: 'medium', timeZone: 'America/La_Paz',
});
const fechaLocal = (fecha) => formatoFecha.format(new Date(fecha));

async function cargar(page = 1, hastaId) {
  cargando.value = true;
  error.value = '';

  try {
    const { data } = await api.get('/cpd/bitacora', {
      params: { ...aplicados.value, page, hasta_id: hastaId },
    });
    datos.value = data;
  } catch (e) {
    const status = e.response?.status;
    error.value = status === 403
      ? 'La bitácora está reservada al personal de CPD.'
      : e.response?.data?.message || 'No se pudo cargar la bitácora.';
  } finally {
    cargando.value = false;
  }
}

function aplicarFiltros() {
  aplicados.value = { ...filtros.value };
  cargar();
}

function limpiar() {
  filtros.value = {};
  aplicarFiltros();
}

function filtrarRecurso({ metodo, recurso }) {
  aplicados.value = { ...aplicados.value, metodo, recurso };
  cargar();
}

onMounted(() => cargar());
</script>

<style scoped>
.bitacora { display: grid; gap: 1.25rem; }
.bitacora > section { min-width: 0; }
.bitacora h1, .bitacora h2 { margin: 0 0 .75rem; }
.filtros, .paginas { display: flex; flex-wrap: wrap; gap: .75rem; align-items: end; }
.filtros label { display: grid; gap: .35rem; min-width: 0; max-width: 100%; }
.filtros input, .filtros select {
  min-height: 44px;
  max-width: 100%;
  padding: .5rem .65rem;
  border: 1px solid var(--text-muted);
  border-radius: var(--radius-lg);
  color: var(--text);
  background: var(--surface);
  font: inherit;
}
.bitacora .btn { min-height: 44px; }
.accion-principal, .accion-principal:hover {
  background: var(--ink-button);
  color: var(--ink-button-text);
  border-color: var(--ink-button);
}
.accion-principal:hover:enabled { text-decoration: underline; }
.accion-secundaria {
  background: var(--surface);
  color: var(--text);
  border-color: var(--text-muted);
}
.accion-secundaria:hover:enabled, .recurso:hover:enabled { background: var(--surface-alt); }
.bitacora :is(button, input, select):focus-visible {
  outline: 2px solid var(--text);
  outline-offset: 2px;
}
.bitacora .btn:disabled {
  background: var(--surface-alt);
  border-color: var(--border);
  color: var(--text-muted);
  cursor: default;
}
.tabla { overflow-x: auto; border: 1px solid var(--border); border-radius: var(--radius-lg); background: var(--surface); }
table { width: 100%; border-collapse: collapse; }
th, td { padding: .65rem .75rem; text-align: left; white-space: nowrap; }
th, tr + tr td { border-bottom: 1px solid var(--border); }
.recurso { padding-inline: .6rem; background: transparent; color: var(--text); text-decoration: underline; }
.paginas { margin-top: .75rem; align-items: center; }
[role="alert"] { padding: .75rem; border: 1px solid currentColor; color: var(--text); }
</style>
