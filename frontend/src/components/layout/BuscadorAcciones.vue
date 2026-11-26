<template>
  <div class="buscador-acciones" @focusout="alPerderFoco">
    <div class="campo-busqueda">
      <Search :size="17" aria-hidden="true" />
      <input
        ref="campo"
        v-model="consulta"
        type="search"
        role="combobox"
        aria-label="Buscar acciones y páginas"
        aria-autocomplete="list"
        :aria-expanded="listaVisible"
        :aria-controls="listaVisible ? 'resultados-acciones' : undefined"
        :aria-activedescendant="listaVisible && indice >= 0 ? 'accion-' + indice : undefined"
        autocomplete="off"
        placeholder="Buscar acciones y páginas"
        @focus="abierto = true"
        @input="alEscribir"
        @keydown.down.prevent="mover(1)"
        @keydown.up.prevent="mover(-1)"
        @keydown.enter.prevent="elegir(resultados[indice])"
        @keydown.esc.stop.prevent="cerrar"
      />
    </div>
    <ul v-if="listaVisible" id="resultados-acciones" role="listbox" aria-label="Resultados de búsqueda">
      <li
        v-for="(accion, posicion) in resultados"
        :id="'accion-' + posicion"
        :key="accion.nombre"
        role="option"
        :aria-selected="posicion === indice"
        @mouseenter="indice = posicion"
        @mousedown.prevent
        @click="elegir(accion)"
      >
        <component :is="accion.icono" :size="16" aria-hidden="true" />
        <span>{{ accion.nombre }}</span>
      </li>
    </ul>
    <p v-else-if="abierto && consulta.trim()" class="sin-resultados" role="status">
      No hay acciones disponibles para esta búsqueda.
    </p>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { BarChart3, Contrast, FileText, Palette, Search, Settings } from '@lucide/vue';
import { useTheme } from '../../services/useTheme';

const props = defineProps({ usuario: Object });
const router = useRouter();
const route = useRoute();
const { setTema, setModoOscuro, altoContraste, toggleAltoContraste } = useTheme();
const consulta = ref('');
const campo = ref(null);
const abierto = ref(false);
const indice = ref(-1);

const paginas = [
  { nombre: 'Crear caso especial', palabras: 'registrar nueva solicitud inscripcion', destino: { path: '/estudiante', query: { accion: 'crear' } } },
  { nombre: 'Mis solicitudes', palabras: 'mis casos especiales', destino: '/estudiante' },
  { nombre: 'Historial de casos', palabras: 'solicitudes anteriores tramites', destino: '/estudiante/historial' },
  { nombre: 'Malla curricular e historial académico', palabras: 'avance materias plan estudios', destino: { path: '/estudiante', query: { accion: 'malla' } } },
  { nombre: 'Notificaciones', palabras: 'avisos mensajes', destino: { path: '/estudiante', query: { avisos: '1' } } },
  { nombre: 'Gestión de usuarios', palabras: 'registrar estudiantes personal administrativo', destino: '/direccion' },
  { nombre: 'Evaluar solicitudes', palabras: 'casos especiales evaluacion direccion', destino: { path: '/direccion', query: { seccion: 'solicitudes' } } },
  { nombre: 'Control de cupos', palabras: 'grupos reservas ampliar disponibilidad', destino: { path: '/direccion', query: { seccion: 'cupos' } } },
  { nombre: 'Oficios a CPD', palabras: 'remitir generar oficio csv', destino: { path: '/direccion', query: { seccion: 'oficios' } } },
  { nombre: 'Procesamiento CPD', palabras: 'pendientes habilitar solicitudes oficios remitidos', destino: '/cpd' },
  { nombre: 'Registro de usuarios', palabras: 'registrar alumnos personal académico director cuentas CPD', destino: '/cpd/usuarios' },
  { nombre: 'Bitácora CPD', palabras: 'logs auditoria accesos inicio sesion intentos fallidos direcciones ip', destino: '/cpd/bitacora' },
  { nombre: 'Reportes y estadísticas', palabras: 'graficos barra torta dona datos', destino: '/reportes', icono: BarChart3 },
];

const apariencia = computed(() => [
  { nombre: 'Activar tema Jóvenes', palabras: 'meta cobalto cobalt azul', icono: Palette, ejecutar: () => setTema('jovenes') },
  { nombre: 'Activar tema Niños', palabras: 'calido ambar naranja', icono: Palette, ejecutar: () => setTema('ninos') },
  { nombre: 'Activar tema Adultos', palabras: 'sobrio neutro ink', icono: Palette, ejecutar: () => setTema('adultos') },
  { nombre: 'Activar modo claro', icono: Settings, ejecutar: () => setModoOscuro('light') },
  { nombre: 'Activar modo oscuro', palabras: 'dark noche', icono: Settings, ejecutar: () => setModoOscuro('dark') },
  { nombre: 'Activar modo automático', palabras: 'auto iluminacion', icono: Settings, ejecutar: () => setModoOscuro('auto') },
  { nombre: altoContraste.value ? 'Desactivar alto contraste' : 'Activar alto contraste', icono: Contrast, ejecutar: toggleAltoContraste },
]);

const normalizar = texto => texto.toLocaleLowerCase('es').normalize('NFD').replace(/\p{Diacritic}/gu, '').trim();
const paginasPermitidas = computed(() => {
  const roles = new Set(props.usuario?.roles?.map(rol => rol.nombre));
  return paginas.filter(({ destino }) => {
    const { meta } = router.resolve(destino);
    return (meta.roles ?? [meta.role]).some(rol => roles.has(rol));
  });
});

const resultados = computed(() => {
  const texto = normalizar(consulta.value);
  if (!texto) return [];

  const terminos = texto.split(/\s+/);
  return [...apariencia.value, ...paginasPermitidas.value.map(pagina => ({ icono: FileText, ...pagina }))]
    .filter(accion => {
      const contenido = normalizar(accion.nombre + ' ' + (accion.palabras ?? ''));
      return terminos.every(termino => contenido.includes(termino));
    });
});
const listaVisible = computed(() => abierto.value && resultados.value.length > 0);

function alEscribir() {
  abierto.value = true;
  indice.value = resultados.value.length ? 0 : -1;
}

function mover(paso) {
  const cantidad = resultados.value.length;
  if (!cantidad) return;

  abierto.value = true;
  const actual = indice.value < 0 ? (paso > 0 ? -1 : 0) : indice.value;
  indice.value = (actual + paso + cantidad) % cantidad;
  nextTick(() => document.getElementById('accion-' + indice.value)?.scrollIntoView({ block: 'nearest' }));
}

function cerrar() {
  abierto.value = false;
  indice.value = -1;
}

function alPerderFoco(evento) {
  if (!evento.currentTarget.contains(evento.relatedTarget)) cerrar();
}

function elegir(accion) {
  if (!listaVisible.value || !accion) return;

  cerrar();
  consulta.value = '';
  if (accion.ejecutar) accion.ejecutar();
  else {
    campo.value?.blur();
    router.push(accion.destino);
  }
}

watch(() => route.path, cerrar);
watch(paginasPermitidas, () => {
  indice.value = -1;
});
</script>

<style scoped>
.buscador-acciones { position: relative; min-width: 0; }
.campo-busqueda {
  display: flex;
  align-items: center;
  gap: .5rem;
  padding: 0 .8rem;
  color: var(--text-muted);
  background: var(--surface-alt);
  border: 1px solid var(--text-muted);
  border-radius: var(--radius-pill);
}
.campo-busqueda:focus-within { outline: 2px solid var(--text); outline-offset: 2px; }
.campo-busqueda svg, li svg { flex-shrink: 0; }
.campo-busqueda input {
  width: 100%;
  min-width: 0;
  padding: .45rem 0;
  border: 0;
  outline: none;
  background: transparent;
  color: var(--text);
  box-shadow: none;
  font: inherit;
  font-size: .875rem;
}
.campo-busqueda input::placeholder { color: var(--text-muted); opacity: 1; }
.buscador-acciones :is(ul, .sin-resultados) {
  position: absolute;
  z-index: 1;
  inset-inline: 0;
  top: calc(100% + .35rem);
  margin: 0;
  padding: .3rem;
  max-height: min(20rem, 45dvh);
  overflow-y: auto;
  border: 1px solid var(--text-muted);
  border-radius: var(--radius-xl);
  background: var(--surface);
  color: var(--text);
  list-style: none;
}
.buscador-acciones li {
  display: flex;
  align-items: center;
  gap: .55rem;
  padding: .45rem .6rem;
  border-radius: var(--radius-lg);
  cursor: pointer;
}
.buscador-acciones li[aria-selected="true"] {
  color: var(--on-primary);
  background: var(--primary);
}
.buscador-acciones .sin-resultados { padding: .65rem .8rem; }
</style>
