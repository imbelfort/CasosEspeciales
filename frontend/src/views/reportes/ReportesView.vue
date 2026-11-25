<template>
  <section class="reportes" :aria-busy="cargando">
    <header class="encabezado">
      <h1>Reportes y estadísticas</h1>
      <button type="button" class="btn btn-secondary" :disabled="cargando" @click="cargarReportes">
        <RefreshCw :size="16" aria-hidden="true" /> Actualizar datos
      </button>
    </header>

    <p v-if="cargando" role="status">Cargando reportes…</p>
    <p v-if="error" role="alert">{{ error }}</p>

    <dl v-if="reporteGeneral" class="metricas">
      <div v-for="[campo, etiqueta] in metricas" :key="campo">
        <dt>{{ etiqueta }}</dt>
        <dd>{{ entero.format(reporteGeneral[campo]) }}</dd>
      </div>
    </dl>

    <section v-if="reporteEstados !== null" class="distribucion" aria-labelledby="titulo-distribucion">
      <h2 id="titulo-distribucion">Solicitudes por estado</h2>

      <template v-if="reporteEstados.length">
        <fieldset class="opciones">
          <legend>Tipo de gráfico</legend>
          <div class="controles">
            <button v-for="[valor, etiqueta] in tipos" :key="valor" type="button" class="btn btn-secondary"
              :aria-pressed="tipo === valor" @click="tipo = valor">
              {{ etiqueta }}
            </button>
          </div>
        </fieldset>

        <details>
          <summary>Filtrar estados ({{ estadosVisibles.length }} de {{ reporteEstados.length }})</summary>
          <fieldset class="opciones">
            <legend>Estados incluidos</legend>
            <div class="controles">
              <label v-for="estado in reporteEstados" :key="estado.codigo" class="opcion-estado">
                <input v-model="seleccionados" type="checkbox" :value="estado.codigo" />
                {{ estado.nombre }}
              </label>
            </div>
            <button type="button" class="btn btn-secondary" @click="seleccionarTodos">Seleccionar todos</button>
          </fieldset>
        </details>

        <template v-if="estadosVisibles.length">
          <div v-if="tipo === 'barras'" class="barras" aria-hidden="true">
            <div v-for="estado in estadosVisibles" :key="estado.codigo" class="barra">
              <span>{{ estado.nombre }}</span>
              <div class="pista">
                <div class="relleno" :style="{ width: estado.cantidad / maximo * 100 + '%', background: estado.color }"></div>
              </div>
              <strong>{{ entero.format(estado.cantidad) }}</strong>
            </div>
          </div>

          <div v-else class="circular" :class="{ dona: tipo === 'dona' }" :style="{ background: gradiente }"
            aria-hidden="true">
            <strong v-if="tipo === 'dona'">{{ entero.format(totalVisible) }}</strong>
          </div>

          <div class="tabla">
            <table>
              <caption>Distribución de {{ entero.format(totalVisible) }} solicitudes en los estados seleccionados</caption>
              <thead><tr><th scope="col">Estado</th><th scope="col">Cantidad</th><th scope="col">Porcentaje</th></tr></thead>
              <tbody>
                <tr v-for="estado in estadosVisibles" :key="estado.codigo">
                  <th scope="row">
                    <span class="muestra" :style="{ background: estado.color }" aria-hidden="true"></span>
                    {{ estado.nombre }}
                  </th>
                  <td>{{ entero.format(estado.cantidad) }}</td>
                  <td>{{ porcentaje.format(estado.cantidad / totalVisible) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
        <p v-else role="status">Selecciona al menos un estado para ver el gráfico.</p>
      </template>
      <p v-else>No hay solicitudes registradas.</p>
    </section>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RefreshCw } from '@lucide/vue';
import api from '../../services/api';

const metricas = [
  ['total_solicitudes', 'Total de solicitudes'],
  ['pendientes_evaluacion', 'Pendientes de evaluación'],
  ['aprobadas_direccion', 'Aprobadas por Dirección'],
  ['aprobadas_con_pago', 'Listas con pago'],
  ['remitidas_cpd', 'Remitidas a CPD'],
  ['habilitadas_cpd', 'Habilitadas por CPD'],
];
const tipos = [['barras', 'Barras'], ['torta', 'Torta'], ['dona', 'Dona']];
const colores = ['var(--primary-text)', '#a34c88', '#008477', '#be5800', '#7862bd', '#bf4354',
  '#457e22', '#217ca3', '#9e6e00', '#73523e', '#747984', '#9362a3'];
const entero = new Intl.NumberFormat('es-BO');
const porcentaje = new Intl.NumberFormat('es-BO', { style: 'percent', maximumFractionDigits: 2 });

const reporteGeneral = ref(null);
const reporteEstados = ref(null);
const seleccionados = ref([]);
const tipo = ref('barras');
const cargando = ref(false);
const error = ref('');

const estadosVisibles = computed(() => (reporteEstados.value ?? []).filter(estado => seleccionados.value.includes(estado.codigo)));
const totalVisible = computed(() => estadosVisibles.value.reduce((suma, estado) => suma + estado.cantidad, 0));
const maximo = computed(() => Math.max(1, ...estadosVisibles.value.map(estado => estado.cantidad)));
const gradiente = computed(() => {
  let acumulado = 0;
  const secciones = estadosVisibles.value.map(({ cantidad, color }) => {
    const inicio = acumulado;
    acumulado += cantidad;

    return `${color} ${inicio / totalVisible.value * 100}% ${acumulado / totalVisible.value * 100}%`;
  });

  return `conic-gradient(${secciones.join(', ')})`;
});

function seleccionarTodos() {
  seleccionados.value = reporteEstados.value.map(estado => estado.codigo);
}

async function cargarReportes() {
  cargando.value = true;
  error.value = '';

  try {
    const [general, porEstado] = await Promise.all([
      api.get('/reportes/general'),
      api.get('/reportes/por-estado'),
    ]);
    const anteriores = reporteEstados.value ?? [];
    const omitidos = new Set(anteriores.filter(estado => !seleccionados.value.includes(estado.codigo)).map(estado => estado.codigo));

    reporteGeneral.value = general.data;
    reporteEstados.value = [...porEstado.data.estados]
      .sort((a, b) => a.estado.localeCompare(b.estado, 'es'))
      .map(({ estado, cantidad }, indice) => ({
        codigo: estado,
        nombre: estado.replaceAll('_', ' ').toLocaleLowerCase('es')
          .replace(/\bcpd\b/g, 'CPD').replace(/^./, letra => letra.toUpperCase()),
        cantidad: Number(cantidad),
        color: colores[indice % colores.length],
      }));
    seleccionados.value = reporteEstados.value.filter(estado => !omitidos.has(estado.codigo)).map(estado => estado.codigo);
  } catch (e) {
    if (e.response?.status === 403) {
      reporteGeneral.value = null;
      reporteEstados.value = null;
      error.value = 'Tu cuenta no tiene acceso a Reportes.';
    } else {
      error.value = 'No se pudieron cargar los reportes. Intenta actualizar los datos.';
    }
  } finally {
    cargando.value = false;
  }
}

onMounted(cargarReportes);
</script>

<style scoped>
.reportes { display: grid; gap: 1.25rem; }
.reportes h1, .reportes h2, .reportes p, .metricas { margin: 0; }
.encabezado, .controles { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }
.encabezado { justify-content: space-between; }
.metricas { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 10rem), 1fr)); gap: .75rem; }
.metricas > div, .distribucion { border: 1px solid var(--border); border-radius: var(--radius-xl); background: var(--surface); }
.metricas > div { padding: 1rem; }
.metricas dt { color: var(--text-muted); }
.metricas dd { margin: 0; font-size: 1.65rem; font-weight: 700; }
.distribucion { min-width: 0; padding: clamp(.75rem, 3vw, 1.5rem); display: grid; gap: 1rem; }
.opciones { padding: 0; border: 0; margin: 0; min-width: 0; }
.opciones legend { padding: 0; margin-bottom: .5rem; font-weight: 600; }
details .opciones { margin-top: .75rem; }
details button { margin-top: .75rem; }
summary { cursor: pointer; min-height: 44px; align-content: center; }
.opcion-estado { display: inline-flex; align-items: center; gap: .5rem; min-height: 44px; cursor: pointer; }
.opcion-estado input { accent-color: var(--ink-button); width: 1rem; height: 1rem; }
.reportes .btn { min-height: 44px; background: var(--surface); color: var(--text); border-color: var(--text-muted); }
.reportes .btn:hover:enabled { background: var(--surface-alt); }
.reportes .btn[aria-pressed="true"] { background: var(--ink-button); color: var(--ink-button-text); border-color: var(--ink-button); }
.reportes .btn:disabled { cursor: default; background: var(--surface-alt); color: var(--text-muted); }
.reportes :is(button, input, summary):focus-visible { outline: 2px solid var(--text); outline-offset: 2px; }
.barras { display: grid; gap: .65rem; }
.barra { display: grid; grid-template-columns: minmax(7rem, 12rem) minmax(2rem, 1fr) auto; align-items: center; gap: .65rem; overflow-wrap: anywhere; }
.pista { height: 1.25rem; background: var(--surface-alt); border: 1px solid var(--border); border-radius: var(--radius-pill); }
.relleno { height: 100%; border-radius: inherit; }
.circular { width: min(100%, 16rem); aspect-ratio: 1; border-radius: 50%; margin-inline: auto; display: grid; place-items: center; }
.dona strong { display: grid; place-items: center; width: 65%; aspect-ratio: 1; border-radius: 50%; background: var(--surface); font-size: 1.5rem; }
.tabla { overflow-x: auto; }
table caption { text-align: start; color: var(--text-muted); padding: .5rem 0; }
table :is(th, td) { padding: .65rem .5rem; text-align: start; border-bottom: 1px solid var(--border); }
tbody th { font-weight: 500; }
.muestra { display: inline-block; inline-size: .85rem; block-size: .85rem; margin-inline-end: .35rem; border-radius: var(--radius-circle); border: 1px solid var(--text-muted); }
@media (forced-colors: active) {
  .circular, .relleno, .muestra { forced-color-adjust: none; }
  .dona strong { background: Canvas; color: CanvasText; }
}
</style>
