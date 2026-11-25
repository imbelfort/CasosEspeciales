<template>
  <header ref="cabecera" class="cabecera" @keydown.esc="cerrarNavegacion">
    <router-link to="/" class="marca">
      <span class="marca-icono" aria-hidden="true"><GraduationCap :size="20" /></span>
      <span>Casos Especiales <small>FICCT · UAGRM</small></span>
    </router-link>

    <nav v-if="$slots.navegacion" id="navegacion-principal" class="navegacion" :class="{ abierta: navegacionAbierta }"
      aria-label="Navegación principal" @click="alNavegar">
      <slot name="navegacion" />
    </nav>

    <BuscadorAcciones :usuario="usuario" />

    <div ref="controles" class="controles" @keydown.esc="cerrarConEscape" @focusout="alSalir">
      <button v-if="$slots.navegacion" ref="botonMenu" type="button" class="icono boton-menu"
        :aria-expanded="navegacionAbierta" aria-controls="navegacion-principal"
        :aria-label="navegacionAbierta ? 'Cerrar menú' : 'Abrir menú'" @click="alternarNavegacion">
        <X v-if="navegacionAbierta" :size="20" aria-hidden="true" />
        <Menu v-else :size="20" aria-hidden="true" />
      </button>
      <NotificacionesMenu v-if="usuario?.roles?.[0]?.nombre === 'ESTUDIANTE'"
        :key="usuario.id_usuario" :usuario="usuario" />
      <details name="panel-cabecera">
        <summary class="icono" title="Apariencia y accesibilidad" aria-label="Apariencia y accesibilidad">
          <SlidersHorizontal :size="19" aria-hidden="true" />
        </summary>
        <div class="panel apariencia">
          <h2>Apariencia</h2>
          <label class="ajuste">
            <span><Palette :size="16" aria-hidden="true" /> Tema</span>
            <select :value="tema" @change="setTema($event.target.value)">
              <option value="jovenes">Jóvenes · Cobalto Meta</option>
              <option value="ninos">Niños · Ámbar</option>
              <option value="adultos">Adultos · Sobrio</option>
            </select>
          </label>
          <label class="ajuste">
            <span><SunMoon :size="16" aria-hidden="true" /> Modo</span>
            <select :value="modoOscuro" @change="setModoOscuro($event.target.value)">
              <option value="light">Claro</option>
              <option value="dark">Oscuro</option>
              <option value="auto">Automático</option>
            </select>
          </label>
          <label class="ajuste">
            <span><Type :size="16" aria-hidden="true" /> Tamaño del texto</span>
            <span class="escala">
              <input type="range" min="80" max="140" step="5" :value="escalaFuente"
                :aria-valuetext="escalaFuente + '%'" @input="setEscalaFuente($event.target.value)" />
              <output>{{ escalaFuente }}%</output>
            </span>
          </label>
          <label class="ajuste contraste">
            <span><Contrast :size="16" aria-hidden="true" /> Alto contraste</span>
            <input type="checkbox" :checked="altoContraste" @change="toggleAltoContraste" />
          </label>
        </div>
      </details>

      <details v-if="usuario" name="panel-cabecera">
        <summary class="icono icono-cuenta" title="Mi cuenta" aria-label="Mi cuenta">
          <UserRound :size="19" aria-hidden="true" />
        </summary>
        <div class="panel cuenta">
          <div>
            <strong>{{ usuario.nombre }} {{ usuario.apellidos }}</strong>
            <div class="rol">{{ usuario.roles?.[0]?.nombre?.replaceAll('_', ' ') }}</div>
          </div>
          <button type="button" class="btn btn-secondary" @click="logout">
            <LogOut :size="16" aria-hidden="true" /> Cerrar sesión
          </button>
        </div>
      </details>
    </div>
  </header>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { Contrast, GraduationCap, LogOut, Menu, Palette, SlidersHorizontal, SunMoon, Type, UserRound, X } from '@lucide/vue';
import { useTheme } from '../../services/useTheme';
import BuscadorAcciones from './BuscadorAcciones.vue';
import NotificacionesMenu from './NotificacionesMenu.vue';
import api from '../../services/api';

defineProps({ usuario: Object });
const emit = defineEmits(['logout']);
const router = useRouter();
const cabecera = ref(null);
const controles = ref(null);
const botonMenu = ref(null);
const navegacionAbierta = ref(false);
const {
  tema, modoOscuro, altoContraste, escalaFuente, setTema, setModoOscuro,
  toggleAltoContraste, setEscalaFuente,
} = useTheme();

function cerrarPanel() {
  const abierto = controles.value?.querySelector('details[open]');
  if (abierto) abierto.open = false;
}

function cerrarConEscape(evento) {
  const abierto = controles.value?.querySelector('details[open]');
  if (!abierto) return;

  evento.preventDefault();
  evento.stopPropagation();
  abierto.open = false;
  abierto.querySelector('summary').focus();
}

function alternarNavegacion() {
  navegacionAbierta.value = !navegacionAbierta.value;
  if (navegacionAbierta.value) {
    nextTick(() => cabecera.value?.querySelector('.navegacion a')?.focus());
  }
}

function cerrarNavegacion(evento) {
  if (!navegacionAbierta.value) return;
  evento.preventDefault();
  navegacionAbierta.value = false;
  botonMenu.value?.focus();
}

function alNavegar(evento) {
  if (evento.target.closest('a')) navegacionAbierta.value = false;
}

function alSalir(evento) {
  if (evento.relatedTarget && !controles.value?.contains(evento.relatedTarget)) cerrarPanel();
}

function alPulsarFuera(evento) {
  if (!controles.value?.contains(evento.target)) cerrarPanel();
  if (!evento.target.closest('.navegacion, .boton-menu')) navegacionAbierta.value = false;
}

watch(() => router.currentRoute.value.path, () => { navegacionAbierta.value = false; });
onMounted(() => document.addEventListener('pointerdown', alPulsarFuera));
onUnmounted(() => document.removeEventListener('pointerdown', alPulsarFuera));

async function logout() {
  try {
    await api.post('/auth/logout');
  } catch (e) {}
  localStorage.removeItem('token');
  cerrarPanel();
  emit('logout');
  router.push('/login');
}
</script>

<style scoped>
.cabecera {
  position: sticky;
  top: 0;
  z-index: 50;
  display: grid;
  grid-template-columns: max-content minmax(0, 1fr) minmax(10rem, 17rem) auto;
  grid-template-areas: "marca navegacion buscador controles";
  align-items: center;
  gap: .75rem clamp(.65rem, 1.7vw, 1.5rem);
  padding: .75rem clamp(.75rem, 3vw, 2.5rem);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
}
.marca { grid-area: marca; display: inline-flex; align-items: center; justify-self: start; gap: .55rem; color: var(--text); text-decoration: none; font-weight: 700; line-height: 1.2; }
.marca-icono, .controles :deep(.icono) { display: grid; place-items: center; inline-size: 2.5rem; block-size: 2.5rem; flex-shrink: 0; }
.marca-icono { color: var(--primary-text); }
.marca small { display: block; margin-top: .15rem; color: var(--text-muted); font-size: .72rem; font-weight: 500; }
.navegacion { grid-area: navegacion; }
.buscador-acciones { grid-area: buscador; }
.buscador-acciones :deep(.campo-busqueda) { min-height: 2.5rem; border-color: var(--border); }
.controles { grid-area: controles; position: relative; z-index: 2; justify-self: end; display: flex; gap: .35rem; }
.controles :deep(.icono) {
  color: var(--text);
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-circle);
  cursor: pointer;
  list-style: none;
}
.controles :deep(.icono::-webkit-details-marker) { display: none; }
.controles :deep(details[open] > .icono) { color: var(--primary-text); background: var(--primary-subtle); border-color: var(--primary-text); }
.controles .boton-menu { display: none; padding: 0; }
.controles :deep(.icono:hover) { border-color: var(--primary-text); }
.cabecera :deep(:is(a, summary, button, input, select):focus-visible) { outline: 2px solid var(--text); outline-offset: 2px; }
.controles :deep(.panel) {
  position: absolute;
  inset-inline-end: 0;
  top: calc(100% + .5rem);
  width: min(var(--ancho-panel, 17rem), calc(100vw - 1.5rem));
  max-height: calc(100dvh - 5rem);
  overflow: auto;
  display: grid;
  gap: .75rem;
  padding: .85rem;
  background: var(--surface);
  color: var(--text);
  border: 1px solid var(--text-muted);
  border-radius: var(--radius-xl);
  box-shadow: 0 4px 12px var(--shadow-color);
}
.controles :deep(.panel h2) { margin: 0; font-size: .9rem; }
.ajuste { display: grid; gap: .35rem; font-size: .83rem; }
.ajuste > span:first-child, .escala { display: flex; align-items: center; gap: .45rem; }
.ajuste select { padding: .35rem .45rem; font-size: inherit; }
.escala input { flex: 1; min-width: 0; margin-inline: 0; }
.escala output { min-width: 3.3em; text-align: end; font-variant-numeric: tabular-nums; }
.ajuste input { accent-color: var(--primary); }
.contraste { grid-template-columns: 1fr auto; align-items: center; cursor: pointer; }
.cuenta { overflow-wrap: anywhere; }
.cuenta button { justify-self: start; }
.rol { font-size: .75rem; color: var(--text-muted); }

@media (max-width: 48rem) {
  .cabecera {
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas: "marca controles" "buscador buscador";
    gap: .55rem .65rem;
  }
  .controles .boton-menu { display: grid; }
  .navegacion {
    display: none;
    position: absolute;
    inset: 100% 0 auto;
    z-index: 3;
    max-height: 65dvh;
    overflow: auto;
    padding: .75rem;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    box-shadow: 0 8px 16px var(--shadow-color);
  }
  .navegacion.abierta { display: block; }
  .marca { min-width: 0; font-size: .88rem; }
  .marca-icono { inline-size: 1.5rem; }
}
@media (max-width: 22rem) {
  .controles { gap: .2rem; }
  .controles :deep(.icono) { inline-size: 2.25rem; block-size: 2.25rem; }
}
</style>
