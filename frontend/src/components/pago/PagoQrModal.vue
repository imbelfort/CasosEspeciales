<template>
  <dialog ref="dialogo" class="modal-box payment-dialog modal-qr" aria-labelledby="titulo-pago-qr" @cancel.prevent="cerrar">
      <header>
      <div class="title-with-icon">
        <QrCode :size="20" class="header-icon" />
        <div>
          <h3 id="titulo-pago-qr">Pagar con QR</h3>
          <small>{{ solicitud?.codigo }}</small>
        </div>
      </div>
      <button type="button" @click="cerrar" class="btn-action" aria-label="Cerrar pago QR" :disabled="procesando">
        <X :size="18" aria-hidden="true" />
      </button>
    </header>

    <div class="modal-body-pago">
      <p v-if="pagoActual?.estado !== 'PAGADO' && monto != null" class="monto-qr">
        Arancel <strong>{{ monto }} Bs</strong>
      </p>

      <!-- Indicador de Carga al generar QR -->
      <div v-if="cargando" class="loading-state" role="status">
        <Loader2 :size="36" class="spin-icon" />
        <p>Conectando con la pasarela PagoFácil QR...</p>
      </div>

      <!-- Pantalla de QR Generado -->
      <div v-else-if="pagoActual && pagoActual.estado !== 'PAGADO'" class="qr-content-wrapper">
        <div class="qr-box">
          <img
            v-if="pagoActual.qr_base64"
            :src="getQrImageSrc(pagoActual.qr_base64)"
            alt="Código QR de PagoFácil"
            class="qr-img"
          />
          <div v-else class="qr-placeholder">
            <QrCode :size="80" class="text-muted" />
            <span>Código QR no disponible</span>
          </div>

          <div v-if="modoSandbox" class="sandbox-tag">
            <Sparkles :size="12" /> Modo Simulación / Sandbox Activo
          </div>
        </div>

        <div class="qr-instructions">
          <p class="paso-qr">Escanea el código con tu aplicación bancaria y confirma el importe.</p>

          <div class="meta-info">
            <div><strong>Código de Pago:</strong> <code>{{ pagoActual.numero_pago }}</code></div>
            <div v-if="pagoActual.fecha_expiracion">
              <strong>Vence:</strong> {{ formatearFecha(pagoActual.fecha_expiracion) }}
            </div>
          </div>

          <!-- Botones de Acción -->
          <div class="qr-actions">
            <button @click="verificarEstado" class="btn-primary btn-check" :disabled="verificando">
              <RefreshCw :size="16" :class="{ 'spin-icon': verificando }" />
              {{ verificando ? 'Comprobando…' : 'Verificar pago' }}
            </button>

            <button
              v-if="modoSandbox"
              @click="simularPago"
              class="btn-demo"
              :disabled="procesando"
              title="Simula un pago exitoso instantáneo para pruebas de desarrollo"
            >
              <CheckCircle2 :size="15" /> Simular Pago Exitoso (Demo)
            </button>
          </div>
          <p v-if="errorVerificacion" class="paso-qr" role="status">{{ errorVerificacion }}</p>
        </div>
      </div>

      <!-- Estado de Pago Confirmado -->
      <div v-else-if="pagoActual && pagoActual.estado === 'PAGADO'" class="success-state">
        <CheckCircle2 :size="50" class="text-success" />
        <h4>¡Pago Confirmado Exitosamente!</h4>
        <p>Tu arancel está pagado. Puedes consultar el estado actualizado de tu caso en la lista de solicitudes.</p>
        <div class="receipt-box">
          <div><strong>Referencia:</strong> {{ pagoActual.numero_pago }}</div>
          <div><strong>Monto:</strong> {{ pagoActual.monto }} BOB</div>
          <div><strong>Fecha:</strong> {{ formatearFecha(pagoActual.fecha_pago) }}</div>
        </div>
      </div>

      <!-- Estado de Error al generar -->
      <div v-else-if="errorMensaje" class="error-state" role="status">
        <AlertCircle :size="40" class="text-danger" />
        <p class="text-danger font-bold">{{ errorMensaje }}</p>
        <button v-if="stripePendiente" @click="generarCobro(true)" class="btn-secondary mt-2">Cerrar Stripe y generar QR</button>
        <button v-else @click="generarCobro()" class="btn-secondary mt-2">Reintentar generar QR</button>
      </div>
    </div>

    <footer>
      <button type="button" @click="cerrar" class="btn-secondary" :disabled="procesando">
        {{ pagoActual?.estado === 'PAGADO' ? 'Listo' : 'Volver a métodos de pago' }}
      </button>
    </footer>
  </dialog>
</template>

<script setup>
import { computed, ref, watch, onUnmounted } from 'vue';
import { 
  QrCode, 
  X, 
  RefreshCw, 
  CheckCircle2, 
  AlertCircle, 
  Loader2, 
  Sparkles 
} from '@lucide/vue';
import api from '../../services/api';

const props = defineProps({
  visible: Boolean,
  solicitud: Object
});

const emit = defineEmits(['close', 'pago-completado']);
const dialogo = ref(null);

const cargando = ref(false);
const verificando = ref(false);
const procesando = ref(false);
const errorMensaje = ref('');
const errorVerificacion = ref('');
const stripePendiente = ref(false);
const pagoActual = ref(null);
const modoSandbox = ref(false);
const monto = computed(() => pagoActual.value?.monto ?? props.solicitud?.periodo?.costo_arancel_caso);
let intervaloPolling = null;
let consultas;

const getQrImageSrc = (base64Str) => {
  if (!base64Str) return '';
  if (base64Str.startsWith('data:image/')) return base64Str;
  // Detectar si es SVG o PNG
  const isSvg = base64Str.startsWith('PHN2Zw') || base64Str.includes('svg');
  const mime = isSvg ? 'image/svg+xml' : 'image/png';
  return `data:${mime};base64,${base64Str}`;
};

const formatearFecha = (f) => {
  if (!f) return '';
  const d = new Date(f);
  return d.toLocaleString('es-BO', { 
    dateStyle: 'medium', 
    timeStyle: 'short' 
  });
};

const generarCobro = async (cerrarStripe = false) => {
  if (!props.solicitud || !consultas) return;
  const { signal } = consultas;
  cargando.value = true;
  errorMensaje.value = '';
  errorVerificacion.value = '';
  stripePendiente.value = false;
  pagoActual.value = null;
  
  try {
    const res = await api.post(`/solicitudes/${props.solicitud.id}/pagar-qr`, { cerrar_stripe: cerrarStripe }, { signal, timeout: 30000 });
    if (signal.aborted) return;
    pagoActual.value = res.data.pago;
    modoSandbox.value = !!res.data.modo_sandbox;

    // Si no está pagado aún, iniciar sondeo automático cada 7 segundos
    if (pagoActual.value?.estado === 'PAGADO') {
      emit('pago-completado', pagoActual.value);
    } else if (pagoActual.value?.estado === 'PENDIENTE') {
      iniciarPolling();
    }
  } catch (e) {
    if (signal.aborted) return;
    stripePendiente.value = e.response?.data?.codigo === 'STRIPE_PENDIENTE';
    errorMensaje.value = e.response?.data?.error || e.response?.data?.message || 'Error al conectar con PagoFácil QR.';
  } finally {
    if (!signal.aborted) cargando.value = false;
  }
};

const verificarEstado = async () => {
  if (!pagoActual.value?.id || verificando.value || !consultas) return;
  const { signal } = consultas;
  verificando.value = true;
  try {
    const res = await api.get(`/pagos/${pagoActual.value.id}/consultar`, { signal, timeout: 10000 });
    if (signal.aborted) return;
    errorVerificacion.value = '';
    pagoActual.value = res.data.pago;
    if (res.data.estado === 'PAGADO') {
      detenerPolling();
      emit('pago-completado', pagoActual.value);
    } else if (res.data.estado === 'EXPIRADO') {
      detenerPolling();
      pagoActual.value = null;
      errorMensaje.value = 'El QR venció. Puedes generar otro o cerrar esta ventana para elegir Stripe.';
    }
  } catch (e) {
    if (!signal.aborted) errorVerificacion.value = e.response?.data?.error || 'No se pudo verificar el pago. Inténtalo de nuevo.';
  } finally {
    if (!signal.aborted) verificando.value = false;
  }
};

const simularPago = async () => {
  if (!pagoActual.value?.id || !consultas) return;
  const { signal } = consultas;
  procesando.value = true;
  try {
    const res = await api.post(`/pagos/${pagoActual.value.id}/simular`, {}, { signal, timeout: 10000 });
    if (signal.aborted) return;
    pagoActual.value = res.data.pago;
    detenerPolling();
    emit('pago-completado', pagoActual.value);
  } catch (e) {
    if (!signal.aborted) errorVerificacion.value = e.response?.data?.error || 'No se pudo simular el pago.';
  } finally {
    procesando.value = false;
  }
};

const iniciarPolling = () => {
  detenerPolling();
  intervaloPolling = setInterval(() => {
    if (props.visible && pagoActual.value && pagoActual.value.estado === 'PENDIENTE') {
      verificarEstado();
    } else {
      detenerPolling();
    }
  }, 7000);
};

const detenerPolling = () => {
  if (intervaloPolling) {
    clearInterval(intervaloPolling);
    intervaloPolling = null;
  }
};

const cerrar = () => {
  if (!procesando.value) emit('close', pagoActual.value?.estado !== 'PAGADO');
};

watch(() => props.visible, (visible) => {
  if (visible) {
    consultas = new AbortController();
    verificando.value = false;
    dialogo.value.showModal();
    generarCobro();
  } else {
    consultas?.abort();
    dialogo.value.close();
    detenerPolling();
  }
});

onUnmounted(() => {
  consultas?.abort();
  detenerPolling();
});
</script>

<style scoped>
.modal-qr {
  overflow: hidden;
}

.modal-qr[open] {
  display: flex;
  flex-direction: column;
}

.modal-qr > :is(header, footer) {
  flex-shrink: 0;
}

.modal-qr > footer {
  justify-content: flex-end;
  border-top: 1px solid var(--border);
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.header-icon {
  color: var(--primary);
}
.modal-body-pago {
  min-height: 0;
  overflow-y: auto;
  overflow-wrap: anywhere;
  overscroll-behavior: contain;
  padding: .75rem 1rem;
}

.monto-qr {
  margin: 0 0 .625rem;
  text-align: center;
  color: var(--text-muted);
}

.monto-qr strong {
  color: var(--text);
}

.loading-state, .error-state, .success-state {
  text-align: center;
  padding: .75rem 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
}

.spin-icon {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  100% { transform: rotate(360deg); }
}

.qr-content-wrapper {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: .625rem;
}

.qr-box {
  background: #ffffff;
  padding: .5rem;
  max-width: 100%;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
  border: 1px solid #e2e8f0;
}
.qr-img {
  width: clamp(9rem, 33dvh, 12rem);
  max-width: 100%;
  height: auto;
  aspect-ratio: 1;
  object-fit: contain;
}

.sandbox-tag {
  background: #fef08a;
  color: #854d0e;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  margin-top: 0.4rem;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

.qr-instructions {
  width: 100%;
}
.paso-qr {
  margin: 0 0 .625rem;
  font-size: .875rem;
  text-align: center;
  color: var(--text-muted);
}

.loading-state p,
.error-state p,
.success-state :is(h4, p) {
  margin: 0;
}

.meta-info {
  background: var(--surface-alt);
  border: 1px dashed var(--border);
  padding: 0.6rem 0.8rem;
  border-radius: 6px;
  font-size: 0.8rem;
  margin-bottom: .625rem;
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.qr-actions {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
.btn-check {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  width: 100%;
  padding: 0.65rem;
  font-weight: 700;
}
.btn-demo {
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 0.5rem;
  border-radius: var(--radius-sm, 6px);
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  transition: all 0.2s;
}
.btn-demo:hover {
  background: var(--surface-alt);
}

.receipt-box {
  background: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 0.8rem 1.2rem;
  font-size: 0.85rem;
  text-align: left;
  width: 100%;
  margin-top: 0.5rem;
}
</style>
