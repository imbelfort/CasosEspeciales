<template>
  <section class="reportes" :aria-busy="cargando">
    <header class="encabezado">
      <div>
        <h1>Reportes y estadísticas</h1>
        <p>Seguimiento de solicitudes de casos especiales</p>
      </div>
      <button type="button" class="btn btn-secondary actualizar" :disabled="cargando" @click="cargarReportes">
        <RefreshCw :size="16" aria-hidden="true" /> {{ cargando ? 'Actualizando…' : 'Actualizar' }}
      </button>
    </header>

    <p v-if="cargando && !reporteGeneral" class="mensaje" role="status">Cargando reportes…</p>
    <p v-if="error" class="mensaje error" role="alert">{{ error }}</p>

    <dl v-if="reporteGeneral" class="metricas">
      <div v-for="[campo, etiqueta, icono, tono] in metricas" :key="campo" class="metrica" :class="tono">
        <component :is="icono" class="metrica-icono" :size="36" :stroke-width="1.8" aria-hidden="true" />
        <dt>{{ etiqueta }}</dt>
        <dd>{{ entero.format(reporteGeneral[campo]) }}</dd>
      </div>
    </dl>

    <section v-if="reporteEstados !== null" class="distribucion" aria-labelledby="titulo-distribucion">
      <div class="distribucion-cabecera">
        <div>
          <h2 id="titulo-distribucion">Solicitudes por estado</h2>
          <p v-if="reporteEstados.length">{{ entero.format(totalVisible) }} solicitudes en la selección</p>
        </div>

        <div v-if="reporteEstados.length" class="segmentos" role="group" aria-label="Tipo de gráfico">
          <button v-for="[valor, etiqueta] in tipos" :key="valor" type="button" class="segmento"
            :aria-pressed="tipo === valor" @click="tipo = valor">
            {{ etiqueta }}
          </button>
        </div>
      </div>

      <template v-if="reporteEstados.length">
        <details class="filtros">
          <summary>
            <ListFilter :size="16" aria-hidden="true" /> Filtrar estados
            <span>{{ estadosVisibles.length }}/{{ reporteEstados.length }}</span>
            <ChevronDown :size="15" class="flecha" aria-hidden="true" />
          </summary>
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

        <div v-if="estadosVisibles.length" class="desglose">
          <div class="grafico">
            <div v-if="tipo === 'barras'" class="barras" aria-hidden="true">
              <div v-for="estado in estadosVisibles" :key="estado.codigo" class="barra">
                <span>{{ estado.nombre }}</span>
                <strong>{{ entero.format(estado.cantidad) }}</strong>
                <div class="pista">
                  <div class="relleno" :style="{ width: estado.cantidad / maximo * 100 + '%', background: estado.color }"></div>
                </div>
              </div>
            </div>

            <div v-else class="circular" :style="{ background: gradiente }" aria-hidden="true">
              <div v-if="tipo === 'dona'" class="dona-centro">
                <strong>{{ entero.format(totalVisible) }}</strong><small>Solicitudes</small>
              </div>
            </div>
          </div>

          <div class="table-responsive" tabindex="0" role="region" aria-label="Desglose por estado">
            <table class="data-table">
              <caption>Detalle por estado</caption>
              <thead><tr><th scope="col">Estado</th><th scope="col">Cantidad</th><th scope="col">Porcentaje</th></tr></thead>
              <tbody>
                <tr v-for="estado in estadosVisibles" :key="estado.codigo">
                  <th scope="row">
                    <span class="muestra" :style="{ background: estado.color }" aria-hidden="true"></span>
                    {{ estado.nombre }}
                  </th>
                  <td>{{ entero.format(estado.cantidad) }}</td>
                  <td>{{ porcentaje.format(totalVisible ? estado.cantidad / totalVisible : 0) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <p v-else class="mensaje" role="status">Selecciona al menos un estado para ver el gráfico.</p>
      </template>
      <p v-else>No hay solicitudes registradas.</p>
    </section>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { CheckCircle2, ChevronDown, CircleCheck, Clock3, FileText, ListFilter, RefreshCw, Send, Wallet } from '@lucide/vue';
import api from '../../services/api';

const metricas = [
  ['total_solicitudes', 'Total de solicitudes', FileText, 'principal'],
  ['pendientes_evaluacion', 'Pendientes de evaluación', Clock3, 'pendiente'],
  ['aprobadas_direccion', 'Aprobadas por Dirección', CircleCheck, 'resuelta'],
  ['aprobadas_con_pago', 'Listas con pago', Wallet, 'resuelta'],
  ['remitidas_cpd', 'Remitidas a CPD', Send, 'principal'],
  ['habilitadas_cpd', 'Habilitadas por CPD', CheckCircle2, 'resuelta'],
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
  if (!totalVisible.value) return 'var(--border)';
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
.reportes { display: grid; gap: 1.25rem; color: var(--text); }
.reportes :is(h1, h2, p, dl) { margin: 0; }
.encabezado, .distribucion-cabecera { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .85rem; }
.encabezado h1 { font-size: clamp(1.55rem, 3.3vw, 2.15rem); letter-spacing: -.025em; line-height: 1.1; }
.encabezado p, .distribucion-cabecera p { margin-top: .35rem; color: var(--text-muted); font-size: .88rem; }
.actualizar { min-height: 2.65rem; border-radius: var(--radius-pill); }
.metricas { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .75rem; }
.metrica, .distribucion { border: 1px solid var(--border); background: var(--surface); box-shadow: 0 2px 8px var(--shadow-color); }
.metrica { display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: center; gap: .1rem .85rem; padding: .85rem 1rem; border-radius: var(--radius-lg); }
.metrica-icono { grid-row: span 2; padding: .55rem; box-sizing: content-box; width: 1.15rem; height: 1.15rem; border-radius: 50%; color: var(--primary-text); background: var(--primary-subtle); }
.pendiente .metrica-icono { color: var(--text); background: var(--surface-alt); }
.resuelta .metrica-icono { color: var(--success); background: var(--surface-alt); }
.metrica dt { font-size: .84rem; color: var(--text-muted); line-height: 1.3; overflow-wrap: anywhere; }
.metrica dd { grid-column: 2; margin: 0; font-size: 1.55rem; font-weight: 700; line-height: 1.2; font-variant-numeric: tabular-nums; }
.distribucion { min-width: 0; padding: clamp(.85rem, 2.5vw, 1.35rem); border-radius: var(--radius-xl); display: grid; gap: .85rem; }
.distribucion h2 { font-size: 1.2rem; letter-spacing: -.02em; }
.segmentos { display: inline-flex; gap: .2rem; padding: .22rem; background: var(--surface-alt); border-radius: var(--radius-pill); }
.segmento { padding: .45rem .8rem; min-height: 2.5rem; border: 1px solid transparent; border-radius: var(--radius-pill); background: transparent; color: var(--text-muted); font: inherit; font-size: .83rem; font-weight: 650; cursor: pointer; }
.segmento[aria-pressed="true"] { background: var(--surface); color: var(--primary-text); border-color: var(--border); box-shadow: 0 1px 3px var(--shadow-color); }
.segmento:hover { color: var(--primary-text); }
.filtros summary { display: inline-flex; align-items: center; min-height: 2.5rem; gap: .5rem; cursor: pointer; font-size: .85rem; font-weight: 600; list-style: none; }
.filtros summary::-webkit-details-marker { display: none; }
.filtros summary span { border-radius: var(--radius-pill); padding: .12rem .5rem; color: var(--primary-text); background: var(--primary-subtle); }
.filtros[open] .flecha { transform: rotate(180deg); }
.opciones { margin: .35rem 0 0; padding: .65rem .8rem .8rem; border: 1px solid var(--border); border-radius: var(--radius-lg); }
.opciones legend { padding: 0 .3rem; font-weight: 600; font-size: .82rem; }
.controles { display: flex; flex-wrap: wrap; align-items: center; gap: .15rem .9rem; }
.opcion-estado { display: inline-flex; align-items: center; gap: .45rem; min-height: 2.5rem; font-size: .84rem; cursor: pointer; }
.opcion-estado input { accent-color: var(--primary); width: 1rem; height: 1rem; }
.reportes :is(button, input, summary):focus-visible { outline: 2px solid var(--primary-text); outline-offset: 2px; }
.desglose { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 1rem; }
.grafico { display: grid; align-items: center; background: var(--surface-alt); border-radius: var(--radius-lg); padding: clamp(.8rem, 2.3vw, 1.25rem); min-height: 15rem; }
.barras { display: grid; gap: .65rem; }
.barra { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; gap: .25rem .6rem; font-size: .83rem; overflow-wrap: anywhere; }
.barra strong { font-variant-numeric: tabular-nums; }
.pista { grid-column: 1 / -1; height: .45rem; background: var(--border); border-radius: var(--radius-pill); }
.relleno { height: 100%; border-radius: inherit; }
.circular { width: min(100%, 14rem); aspect-ratio: 1; border-radius: 50%; margin-inline: auto; display: grid; place-items: center; }
.dona-centro { display: grid; place-content: center; text-align: center; width: 68%; aspect-ratio: 1; border-radius: 50%; background: var(--surface); }
.dona-centro strong { font-size: 1.65rem; line-height: 1.15; }
.dona-centro small { color: var(--text-muted); }
table caption { text-align: start; font-size: .9rem; font-weight: 700; padding: .35rem .5rem .65rem; }
table td { text-align: end; font-variant-numeric: tabular-nums; }
thead th:not(:first-child) { text-align: end; }
.muestra { display: inline-block; inline-size: .6rem; block-size: .6rem; margin-inline-end: .3rem; border-radius: 50%; }
.mensaje { padding: .8rem 1rem; border-radius: var(--radius-lg); background: var(--surface-alt); color: var(--text); }
.mensaje.error { border-inline-start: 3px solid var(--danger); }
@media (max-width: 45rem) {
  .metricas { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .desglose { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 28rem) {
  .metrica { padding: .65rem; gap: .15rem .4rem; }
  .metrica-icono { padding: .32rem; width: .9rem; height: .9rem; }
  .metrica dt { font-size: .76rem; }
  table :is(th, td) { padding-inline: .35rem; }
}
@media (forced-colors: active) {
  .circular, .relleno, .muestra { forced-color-adjust: none; }
  .dona-centro { background: Canvas; color: CanvasText; }
  .segmento[aria-pressed="true"] { border-color: Highlight; }
}
</style>
