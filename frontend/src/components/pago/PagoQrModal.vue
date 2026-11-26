<template>
  <div v-if="visible" class="modal-backdrop">
    <div class="modal-box modal-qr">
      <div class="modal-header">
        <div class="title-with-icon">
          <QrCode :size="20" class="header-icon" />
          <div>
            <h3>Pago de Arancel por QR - PagoFácil</h3>
            <span class="sub-code">Trámite Caso Especial: {{ solicitud?.codigo }}</span>
          </div>
        </div>
        <button @click="cerrar" class="btn-close" :disabled="procesando">
          <X :size="18" />
        </button>
      </div>

      <div class="modal-body-pago">
        <!-- Banner Informativo -->
        <div class="pago-banner">
          <div v-if="pagoActual?.monto != null || solicitud?.periodo?.costo_arancel_caso != null" class="banner-amount">
            <span class="currency">BOB</span>
            <span class="amount">{{ pagoActual?.monto ?? solicitud?.periodo?.costo_arancel_caso }}</span>
          </div>
          <div class="banner-desc">
            <strong>Arancel Académico - FICCT UAGRM</strong>
            <p>Habilita la remisión de tu caso a Centro de Procesamiento de Datos (CPD).</p>
          </div>
        </div>

        <!-- Indicador de Carga al generar QR -->
        <div v-if="cargando" class="loading-state">
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
            <ol class="steps-list">
              <li>Abre tu aplicación bancaria móvil (Banco Unión, BCP, BNB, etc.).</li>
              <li>Selecciona la opción <strong>Simple / Cobro QR</strong>.</li>
              <li>Apunta la cámara de tu celular y confirma el monto.</li>
            </ol>
            
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
                {{ verificando ? 'Comprobando pago...' : 'Verificar Pago' }}
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
          <button @click="finalizar" class="btn-primary mt-3">Finalizar y Continuar</button>
        </div>

        <!-- Estado de Error al generar -->
        <div v-else-if="errorMensaje" class="error-state">
          <AlertCircle :size="40" class="text-danger" />
          <p class="text-danger font-bold">{{ errorMensaje }}</p>
          <button v-if="stripePendiente" @click="generarCobro(true)" class="btn-secondary mt-2">Cerrar Stripe y generar QR</button>
          <button v-else @click="generarCobro()" class="btn-secondary mt-2">Reintentar generar QR</button>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" @click="cerrar" class="btn-secondary" :disabled="procesando">Cerrar</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onUnmounted } from 'vue';
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

const cargando = ref(false);
const verificando = ref(false);
const procesando = ref(false);
const errorMensaje = ref('');
const stripePendiente = ref(false);
const pagoActual = ref(null);
const modoSandbox = ref(false);
let intervaloPolling = null;

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
  if (!props.solicitud) return;
  cargando.value = true;
  errorMensaje.value = '';
  stripePendiente.value = false;
  pagoActual.value = null;
  
  try {
    const res = await api.post(`/solicitudes/${props.solicitud.id}/pagar-qr`, { cerrar_stripe: cerrarStripe });
    pagoActual.value = res.data.pago;
    modoSandbox.value = !!res.data.modo_sandbox;

    // Si no está pagado aún, iniciar sondeo automático cada 7 segundos
    if (pagoActual.value?.estado === 'PAGADO') {
      emit('pago-completado', pagoActual.value);
    } else if (pagoActual.value?.estado === 'PENDIENTE') {
      iniciarPolling();
    }
  } catch (e) {
    stripePendiente.value = e.response?.data?.codigo === 'STRIPE_PENDIENTE';
    errorMensaje.value = e.response?.data?.error || e.response?.data?.message || 'Error al conectar con PagoFácil QR.';
  } finally {
    cargando.value = false;
  }
};

const verificarEstado = async () => {
  if (!pagoActual.value?.id || verificando.value) return;
  verificando.value = true;
  try {
    const res = await api.get(`/pagos/${pagoActual.value.id}/consultar`);
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
    console.error('Error al verificar estado de pago:', e);
  } finally {
    verificando.value = false;
  }
};

const simularPago = async () => {
  if (!pagoActual.value?.id) return;
  procesando.value = true;
  try {
    const res = await api.post(`/pagos/${pagoActual.value.id}/simular`);
    pagoActual.value = res.data.pago;
    detenerPolling();
    emit('pago-completado', pagoActual.value);
  } catch (e) {
    alert(e.response?.data?.error || 'Error al simular pago.');
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
  detenerPolling();
  emit('close');
};

const finalizar = () => {
  detenerPolling();
  emit('pago-completado', pagoActual.value);
  emit('close');
};

watch(() => props.visible, (val) => {
  if (val) {
    generarCobro();
  } else {
    detenerPolling();
  }
});

onUnmounted(() => {
  detenerPolling();
});
</script>

<style scoped>
.modal-qr {
  max-width: 520px;
  width: 95%;
}

.title-with-icon {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.header-icon {
  color: var(--primary);
}
.sub-code {
  font-size: 0.78rem;
  color: var(--text-muted);
  display: block;
}

.modal-body-pago {
  padding: 1.2rem 1.4rem;
}

.pago-banner {
  background: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm, 8px);
  padding: 0.85rem 1rem;
  display: flex;
  align-items: center;
  gap: 1.2rem;
  margin-bottom: 1.2rem;
}
.banner-amount {
  display: flex;
  align-items: baseline;
  gap: 0.25rem;
  background: var(--primary);
  color: var(--on-primary);
  padding: 0.4rem 0.8rem;
  border-radius: 6px;
  font-weight: 900;
}
.currency {
  font-size: 0.75rem;
  opacity: 0.9;
}
.amount {
  font-size: 1.3rem;
}
.banner-desc strong {
  font-size: 0.9rem;
  display: block;
}
.banner-desc p {
  margin: 0.2rem 0 0;
  font-size: 0.78rem;
  color: var(--text-muted);
}

.loading-state, .error-state, .success-state {
  text-align: center;
  padding: 2rem 1rem;
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
  gap: 1rem;
}

.qr-box {
  background: #ffffff;
  padding: 0.8rem;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  display: flex;
  flex-direction: column;
  align-items: center;
  border: 1px solid #e2e8f0;
}
.qr-img {
  width: 220px;
  height: 220px;
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
.steps-list {
  padding-left: 1.2rem;
  font-size: 0.84rem;
  color: var(--text);
  margin-bottom: 0.8rem;
  line-height: 1.4;
}
.steps-list li {
  margin-bottom: 0.3rem;
}

.meta-info {
  background: var(--surface-alt);
  border: 1px dashed var(--border);
  padding: 0.6rem 0.8rem;
  border-radius: 6px;
  font-size: 0.8rem;
  margin-bottom: 1rem;
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
  border: 1px solid var(--accent, #8b5cf6);
  color: var(--accent, #8b5cf6);
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
  background: var(--accent, #8b5cf6);
  color: #ffffff;
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
