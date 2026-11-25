<template>
  <details ref="menu" name="panel-cabecera" @toggle="alAlternar">
    <summary class="icono" title="Notificaciones" :aria-label="'Notificaciones, ' + noLeidas + ' sin leer'">
      <Bell :size="19" aria-hidden="true" />
      <span v-if="noLeidas" class="contador" aria-hidden="true">{{ noLeidas > 99 ? '99+' : noLeidas }}</span>
    </summary>
    <section class="panel avisos" aria-label="Notificaciones">
      <header>
        <h2>Notificaciones</h2>
        <button v-if="registro && clavePublica" type="button" class="btn btn-secondary"
          :disabled="guardandoPush || permiso === 'denied'" @click="alternarPush">
          {{ pushActivo ? 'Desactivar avisos' : 'Activar avisos del navegador' }}
        </button>
      </header>
      <p v-if="permiso === 'denied'" class="estado">Permite las notificaciones en la configuración del navegador.</p>
      <p v-if="errorPush" class="estado" role="status">{{ errorPush }}</p>
      <p v-if="errorCarga" class="estado" role="status">No se pudieron actualizar los avisos.</p>
      <p v-else-if="!cargado" class="estado" role="status">Cargando avisos…</p>
      <p v-else-if="!avisos.length" class="estado">Todavía no tienes notificaciones.</p>
      <ul v-if="avisos.length" class="lista">
        <li v-for="aviso in avisos" :key="aviso.id">
          <details class="aviso" :class="{ pendiente: !aviso.fecha_lectura }"
            @toggle="$event.target.open && marcarLeida(aviso)">
            <summary>
              <strong>{{ aviso.asunto }}</strong>
              <small>{{ fechaFormateada(aviso.fecha_creacion) }}</small>
            </summary>
            <p>{{ aviso.mensaje }}</p>
            <button v-if="aviso.id_solicitud" type="button" class="btn btn-secondary" @click="verCaso(aviso)">
              Ver solicitud
            </button>
          </details>
        </li>
      </ul>
    </section>
  </details>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, shallowRef, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { Bell } from '@lucide/vue';
import api from '../../services/api';

const props = defineProps({ usuario: { type: Object, required: true } });
const route = useRoute();
const router = useRouter();
const menu = ref(null);
const avisos = ref([]);
const cargado = ref(false);
const errorCarga = ref(false);
const noLeidas = computed(() => avisos.value.filter(aviso => !aviso.fecha_lectura).length);
const admitePush = window.isSecureContext && 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window;
const permiso = ref(admitePush ? Notification.permission : '');
const registro = shallowRef(null);
const clavePublica = ref(null);
const pushActivo = ref(false);
const guardandoPush = ref(false);
const errorPush = ref('');
const controlador = new AbortController();
const lecturasPendientes = new Set();
const preferenciaPush = 'avisos-push:' + props.usuario.id_usuario;
const formatoFecha = new Intl.DateTimeFormat('es-BO', { dateStyle: 'medium', timeStyle: 'short' });
let cargando = false;
let intervalo;

function fechaFormateada(valor) {
  const fecha = new Date(valor?.replace(' ', 'T'));
  return Number.isNaN(fecha.getTime()) ? '' : formatoFecha.format(fecha);
}

async function actualizar() {
  if (cargando || controlador.signal.aborted) return;
  cargando = true;
  try {
    const { data } = await api.get('/notificaciones', { signal: controlador.signal });
    const leidas = new Map(avisos.value.map(aviso => [aviso.id, aviso.fecha_lectura]));
    avisos.value = data.map(aviso => ({ ...aviso, fecha_lectura: aviso.fecha_lectura || leidas.get(aviso.id) }));
    cargado.value = true;
    errorCarga.value = false;
  } catch {
    errorCarga.value = !controlador.signal.aborted;
  } finally {
    cargando = false;
  }
}

async function marcarLeida(aviso) {
  if (aviso.fecha_lectura || lecturasPendientes.has(aviso.id)) return;
  lecturasPendientes.add(aviso.id);
  try {
    const { data } = await api.post(`/notificaciones/${aviso.id}/leer`, {}, { signal: controlador.signal });
    const actual = avisos.value.find(item => item.id === aviso.id);
    if (actual) actual.fecha_lectura = data.notificacion.fecha_lectura;
  } catch {
    errorCarga.value = !controlador.signal.aborted;
  } finally {
    lecturasPendientes.delete(aviso.id);
  }
}

function verCaso(aviso) {
  menu.value.open = false;
  router.push({ path: '/estudiante', query: { solicitud: aviso.id_solicitud } });
}

function alAlternar(evento) {
  if (evento.target === menu.value && menu.value.open) actualizar();
}

function abrirDesdeEnlace() {
  if (route.query.avisos !== '1' || !menu.value) return;
  menu.value.open = true;
  const query = { ...route.query };
  delete query.avisos;
  router.replace({ path: route.path, query, hash: route.hash });
}

async function prepararPush() {
  if (!admitePush) return;
  try {
    const { data } = await api.get('/notificaciones/push', { signal: controlador.signal });
    if (!data.clavePublica) return;
    await navigator.serviceWorker.register(import.meta.env.BASE_URL + 'avisos-sw.js');
    registro.value = await navigator.serviceWorker.ready;
    clavePublica.value = data.clavePublica;
    if (permiso.value !== 'granted' || localStorage.getItem(preferenciaPush) !== '1') return;
    const suscripcion = await registro.value.pushManager.getSubscription();
    if (suscripcion) {
      await api.put('/notificaciones/push', suscripcion.toJSON(), { signal: controlador.signal });
      pushActivo.value = true;
    }
  } catch {
    if (!controlador.signal.aborted && clavePublica.value) errorPush.value = 'No se pudieron reactivar los avisos del navegador.';
  }
}

async function alternarPush() {
  guardandoPush.value = true;
  errorPush.value = '';
  try {
    if (pushActivo.value) {
      const suscripcion = await registro.value.pushManager.getSubscription();
      if (suscripcion) {
        await api.delete('/notificaciones/push', { data: { endpoint: suscripcion.endpoint }, signal: controlador.signal });
        await suscripcion.unsubscribe();
      }
      localStorage.removeItem(preferenciaPush);
      pushActivo.value = false;
      return;
    }

    permiso.value = await Notification.requestPermission();
    if (permiso.value !== 'granted' || controlador.signal.aborted) return;
    const suscripcion = await registro.value.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: clavePublica.value,
    });
    await api.put('/notificaciones/push', suscripcion.toJSON(), { signal: controlador.signal });
    localStorage.setItem(preferenciaPush, '1');
    pushActivo.value = true;
  } catch {
    if (!controlador.signal.aborted) errorPush.value = 'No se pudo cambiar la configuración de avisos.';
  } finally {
    guardandoPush.value = false;
  }
}

function alVolver() {
  if (admitePush) permiso.value = Notification.permission;
  if (!document.hidden) actualizar();
}

function alRecibirPush(evento) {
  if (evento.data?.tipo === 'aviso-caso') actualizar();
}

watch(() => route.query.avisos, abrirDesdeEnlace);
onMounted(() => {
  abrirDesdeEnlace();
  actualizar();
  prepararPush();
  intervalo = setInterval(alVolver, 15000);
  document.addEventListener('visibilitychange', alVolver);
  navigator.serviceWorker?.addEventListener('message', alRecibirPush);
});
onUnmounted(() => {
  controlador.abort();
  clearInterval(intervalo);
  document.removeEventListener('visibilitychange', alVolver);
  navigator.serviceWorker?.removeEventListener('message', alRecibirPush);
});
</script>

<style scoped>
.icono { position: relative; }
.contador {
  position: absolute;
  inset-block-start: -.3rem;
  inset-inline-end: -.35rem;
  min-width: 1.15rem;
  padding: .1rem .22rem;
  border-radius: 1rem;
  background: var(--danger);
  color: white;
  text-align: center;
  font-size: .66rem;
  font-weight: 700;
}
.avisos { --ancho-panel: 23rem; }
header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .5rem; }
.estado, .aviso p { margin: 0; font-size: .83rem; color: var(--text-muted); }
.lista { list-style: none; padding: 0; margin: 0; display: grid; gap: .4rem; }
.aviso { padding: .65rem; border: 1px solid var(--border); border-radius: var(--radius-md); overflow-wrap: anywhere; }
.aviso.pendiente { background: var(--primary-subtle); border-inline-start: .2rem solid var(--primary-text); }
.aviso summary { cursor: pointer; font-size: .85rem; }
.aviso small { display: block; color: var(--text-muted); }
.aviso p { margin-top: .5rem; white-space: pre-line; }
.aviso button { margin-top: .5rem; }
</style>
