<template>
  <header class="meta-header-wrapper">
    <!-- 1. Promo Banner Oficial Meta (Arriba de la barra de navegación) -->
    <div class="meta-promo-strip">
      <div class="promo-content">
        <span class="promo-tag">FICCT UAGRM</span>
        <span class="promo-text">Facultad de Ciencias de la Computación y Telecomunicaciones &bull; Sistema Académico de Casos Especiales</span>
      </div>
    </div>

    <!-- 2. Barra Principal Top Navigation (64px, fondo canvas, bordes hairline planos) -->
    <nav class="meta-navbar">
      <!-- Izquierda: Brand Logo Wordmark plano -->
      <div class="navbar-left">
        <router-link to="/" class="brand-link">
          <div class="logo-badge">FICCT</div>
          <div class="brand-names">
            <span class="brand-main">Casos Especiales</span>
            <span class="brand-sub">UAGRM Oficial</span>
          </div>
        </router-link>
      </div>

      <!-- Centro / Derecha: Controles de Apariencia y Accesibilidad (Pills) -->
      <div class="navbar-center-controls">
        <!-- Selector de Audiencia / Tema (Pill Tab) -->
        <div class="pill-control-group" title="Seleccionar tema por público">
          <Palette :size="14" class="ctrl-icon" />
          <select :value="tema" @change="e => setTema(e.target.value)" class="pill-select">
            <option value="jovenes">Jóvenes (Cobalt Meta)</option>
            <option value="ninos">Niños (Cálido Ámbar)</option>
            <option value="adultos">Adultos (Ink Sobrio)</option>
          </select>
        </div>

        <!-- Selector de Iluminación: Claro, Oscuro o Automático -->
        <div class="pill-control-group" title="Modo de iluminación">
          <select :value="modoOscuro" @change="e => setModoOscuro(e.target.value)" class="pill-select">
            <option value="light">Modo Claro</option>
            <option value="dark">Modo Oscuro</option>
            <option value="auto">Auto (Noche)</option>
          </select>
          <button 
            @click="toggleModoOscuroRapido" 
            class="circle-icon-btn" 
            :title="`Modo: ${modoOscuro}. Clic para alternar rápido`"
          >
            <Sun v-if="modoOscuro === 'light'" :size="15" class="icon-sun" />
            <Moon v-else-if="modoOscuro === 'dark'" :size="15" class="icon-moon" />
            <Clock v-else :size="15" class="icon-clock" />
          </button>
        </div>

        <!-- Ajuste de Tipografía (Pill con steppers) -->
        <div class="pill-control-group" title="Escala de texto accesible">
          <Type :size="14" class="ctrl-icon" />
          <button @click="disminuirFuente" class="circle-icon-btn micro" title="Disminuir texto (-5%)">
            <AArrowDown :size="13" />
          </button>
          <select :value="escalaFuente" @change="e => setEscalaFuente(e.target.value)" class="pill-select font-size-select">
            <option :value="80">80%</option>
            <option :value="90">90%</option>
            <option :value="100">100%</option>
            <option :value="110">110%</option>
            <option :value="120">120%</option>
            <option :value="130">130%</option>
            <option :value="140">140%</option>
          </select>
          <button @click="aumentarFuente" class="circle-icon-btn micro" title="Aumentar texto (+5%)">
            <AArrowUp :size="13" />
          </button>
        </div>

        <!-- Botón Alto Contraste -->
        <button 
          @click="toggleAltoContraste" 
          class="pill-tab-btn" 
          :class="{ active: altoContraste }"
          :title="altoContraste ? 'Desactivar Alto Contraste' : 'Activar Alto Contraste'"
        >
          <Contrast :size="14" />
          <span>Contraste</span>
        </button>
      </div>

      <!-- Derecha: Perfil de Usuario Logueado (Pill de Cuenta) -->
      <div class="navbar-right">
        <div v-if="usuario" class="user-account-pill">
          <div class="user-meta">
            <span class="user-name">
              <User :size="13" class="inline-icon" /> {{ usuario.nombre }} {{ usuario.apellidos }}
            </span>
            <span class="user-role-badge">{{ rolActual }}</span>
          </div>
          <button @click="logout" class="circle-icon-btn logout-btn" title="Cerrar sesión">
            <LogOut :size="15" />
          </button>
        </div>

        <div v-else class="login-prompt-pill">
          <ShieldCheck :size="15" class="inline-icon text-primary" />
          <span>Portal Seguro</span>
        </div>
      </div>
    </nav>
  </header>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { 
  User, 
  LogOut, 
  Sun, 
  Moon, 
  Clock,
  Palette, 
  AArrowUp, 
  AArrowDown,
  Type,
  Contrast,
  ShieldCheck
} from '@lucide/vue';
import { useTheme } from '../../services/useTheme';
import api from '../../services/api';

const props = defineProps({
  usuario: Object
});
const emit = defineEmits(['logout']);
const router = useRouter();

const {
  tema,
  modoOscuro,
  altoContraste,
  escalaFuente,
  setTema,
  setModoOscuro,
  toggleAltoContraste,
  setEscalaFuente,
  aumentarFuente,
  disminuirFuente
} = useTheme();

const toggleModoOscuroRapido = () => {
  if (modoOscuro.value === 'light') {
    setModoOscuro('dark');
  } else if (modoOscuro.value === 'dark') {
    setModoOscuro('auto');
  } else {
    setModoOscuro('light');
  }
};

const rolActual = computed(() => {
  if (!props.usuario || !props.usuario.roles || props.usuario.roles.length === 0) return 'ESTUDIANTE';
  return props.usuario.roles[0].nombre;
});

const logout = async () => {
  try {
    await api.post('/auth/logout');
  } catch (e) {}
  localStorage.removeItem('token');
  emit('logout');
  router.push('/login');
};
</script>

<style scoped>
.meta-header-wrapper {
  width: 100%;
  position: sticky;
  top: 0;
  z-index: 50;
  background-color: var(--surface);
  box-shadow: 0 1px 2px var(--shadow-color);
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

/* 1. Promo Banner Strip (Estilo Meta: Ink-deep con texto blanco o sutil) */
.meta-promo-strip {
  background-color: var(--primary);
  color: #ffffff;
  padding: 0.35rem 1.8rem;
  font-size: 0.76rem;
  font-weight: 600;
  letter-spacing: -0.14px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.promo-content {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.promo-tag {
  background: rgba(255, 255, 255, 0.2);
  padding: 0.1rem 0.45rem;
  border-radius: var(--radius-pill, 100px);
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.5px;
}
.promo-text {
  opacity: 0.95;
}

/* 2. Top Navigation Bar (64px de alto, bordes hairline planos) */
.meta-navbar {
  height: 64px;
  padding: 0 1.8rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid var(--border);
  gap: 1rem;
}

/* Izquierda: Wordmark Logo */
.navbar-left {
  display: flex;
  align-items: center;
}
.brand-link {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  text-decoration: none;
  color: inherit;
}
.logo-badge {
  background-color: var(--primary);
  color: #ffffff;
  font-weight: 800;
  font-size: 0.95rem;
  letter-spacing: 1px;
  padding: 0.35rem 0.65rem;
  border-radius: var(--radius-lg, 8px);
}
.brand-names {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
}
.brand-main {
  font-size: 0.98rem;
  font-weight: 700;
  color: var(--text);
  letter-spacing: -0.2px;
}
.brand-sub {
  font-size: 0.74rem;
  color: var(--text-muted);
  font-weight: 500;
}

/* Centro: Controles de Apariencia con estética Pill */
.navbar-center-controls {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  flex-wrap: wrap;
}

.pill-control-group {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  background-color: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill, 100px);
  padding: 0.2rem 0.55rem;
  font-size: 0.8rem;
}
.ctrl-icon {
  color: var(--text-muted);
  margin-left: 0.1rem;
}

.pill-select {
  background: transparent;
  color: var(--text);
  border: none;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0.15rem 0.3rem;
  outline: none;
  font-family: inherit;
}
.pill-select option {
  background: var(--surface);
  color: var(--text);
}
.font-size-select {
  min-width: 48px;
  text-align: center;
}

/* Botones circulares para iconos según DESIGN-meta.md (button-icon-circular) */
.circle-icon-btn {
  background-color: var(--surface);
  color: var(--text);
  border: 1px solid var(--border);
  width: 28px;
  height: 28px;
  border-radius: var(--radius-circle, 50%);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  transition: all 0.15s ease;
}
.circle-icon-btn:hover {
  background-color: var(--surface-alt);
}
.circle-icon-btn.micro {
  width: 20px;
  height: 20px;
}

.pill-tab-btn {
  background-color: var(--surface-alt);
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill, 100px);
  padding: 0.35rem 0.75rem;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  transition: all 0.15s ease;
}
.pill-tab-btn:hover {
  background-color: var(--border);
}
.pill-tab-btn.active {
  background-color: var(--text);
  color: var(--surface);
  border-color: var(--text);
}

/* Derecha: Pill de cuenta */
.navbar-right {
  display: flex;
  align-items: center;
}
.user-account-pill {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  background-color: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill, 100px);
  padding: 0.3rem 0.8rem 0.3rem 1rem;
}
.user-meta {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
  text-align: right;
}
.user-name {
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--text);
  display: flex;
  align-items: center;
  gap: 0.3rem;
  justify-content: flex-end;
}
.user-role-badge {
  font-size: 0.68rem;
  font-weight: 800;
  color: var(--primary);
  letter-spacing: 0.5px;
}
.logout-btn {
  color: var(--danger);
  border-color: var(--border);
}
.logout-btn:hover {
  background-color: var(--danger);
  color: #ffffff;
  border-color: var(--danger);
}

.login-prompt-pill {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  background-color: var(--surface-alt);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill, 100px);
  padding: 0.35rem 0.85rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text);
}

.icon-sun { color: #f59e0b; }
.icon-moon { color: #3b82f6; }
.icon-clock { color: var(--text-muted); }
</style>
