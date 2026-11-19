<template>
  <header class="navbar">
    <div class="brand">
      <div class="logo-box">FICCT</div>
      <div>
        <h1 class="brand-title">Sistema de Casos Especiales</h1>
        <p class="brand-subtitle">Facultad de Ciencias de la Computación y Telecomunicaciones - UAGRM</p>
      </div>
    </div>

    <!-- Barra de Configuración de Accesibilidad y Apariencia -->
    <div class="navbar-controls">
      <!-- 1. Ajuste de Tamaño de Fuente -->
      <div class="control-group" title="Ajustar tamaño de letra">
        <span class="control-label">Texto:</span>
        <button @click="disminuirFuente" class="btn-ctrl" title="Disminuir texto (A-)">
          <AArrowDown :size="15" />
        </button>
        <span class="font-indicator" @click="reiniciarFuente" title="Restablecer tamaño (100%)">
          {{ escalaFuente }}%
        </span>
        <button @click="aumentarFuente" class="btn-ctrl" title="Aumentar texto (A+)">
          <AArrowUp :size="15" />
        </button>
      </div>

      <!-- 2. Selector de 3 Temas de Color -->
      <div class="control-group" title="Seleccionar tema de color">
        <Palette :size="15" class="ctrl-icon" />
        <select :value="tema" @change="e => setTema(e.target.value)" class="theme-select">
          <option value="institucional">Azul UAGRM</option>
          <option value="esmeralda">Verde Esmeralda</option>
          <option value="purpura">Púrpura Moderno</option>
        </select>
      </div>

      <!-- 3. Alternador Modo Oscuro / Claro -->
      <button 
        @click="toggleModoOscuro" 
        class="btn-ctrl btn-mode" 
        :title="modoOscuro ? 'Cambiar a Modo Claro' : 'Cambiar a Modo Oscuro'"
      >
        <Sun v-if="modoOscuro" :size="16" class="text-warning" />
        <Moon v-else :size="16" />
      </button>

      <!-- 4. Información de Usuario si está autenticado -->
      <div v-if="usuario" class="user-pill">
        <div class="user-info">
          <span class="user-name">
            <User :size="14" class="inline-icon" /> {{ usuario.nombre }} {{ usuario.apellidos }}
          </span>
          <span class="user-role-tag">{{ rolActual }}</span>
        </div>
        <button @click="logout" class="btn-logout" title="Cerrar sesión">
          <LogOut :size="14" class="inline-icon" />
        </button>
      </div>
    </div>
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
  Palette, 
  AArrowUp, 
  AArrowDown 
} from '@lucide/vue';
import { useTheme } from '../../services/useTheme';
import api from '../../services/api';

const props = defineProps({
  usuario: Object
});
const emit = defineEmits(['logout']);
const router = useRouter();

// Utilidad global de temas y accesibilidad
const {
  tema,
  modoOscuro,
  escalaFuente,
  setTema,
  toggleModoOscuro,
  aumentarFuente,
  disminuirFuente,
  reiniciarFuente
} = useTheme();

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
.navbar {
  background: var(--primary);
  color: white;
  padding: 0.8rem 1.8rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 6px rgba(0,0,0,0.15);
  transition: background-color 0.25s ease;
  flex-wrap: wrap;
  gap: 1rem;
}
.brand {
  display: flex;
  align-items: center;
  gap: 0.8rem;
}
.logo-box {
  background: rgba(255,255,255,0.25);
  padding: 0.4rem 0.7rem;
  border-radius: 6px;
  font-weight: 800;
  letter-spacing: 1px;
  font-size: 0.95rem;
}
.brand-title {
  margin: 0;
  font-size: 1.15rem;
  font-weight: 700;
}
.brand-subtitle {
  margin: 0;
  font-size: 0.75rem;
  opacity: 0.85;
}
.navbar-controls {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  flex-wrap: wrap;
}
.control-group {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  background: rgba(0, 0, 0, 0.2);
  padding: 0.25rem 0.5rem;
  border-radius: 6px;
  border: 1px solid rgba(255,255,255,0.15);
}
.control-label {
  font-size: 0.75rem;
  font-weight: 600;
  opacity: 0.85;
}
.font-indicator {
  font-size: 0.75rem;
  font-weight: bold;
  cursor: pointer;
  padding: 0 0.2rem;
}
.ctrl-icon {
  opacity: 0.85;
}
.btn-ctrl {
  background: rgba(255,255,255,0.15);
  color: white;
  border: none;
  padding: 0.25rem 0.4rem;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.2s;
}
.btn-ctrl:hover {
  background: rgba(255,255,255,0.3);
}
.btn-mode {
  padding: 0.4rem 0.55rem;
}
.theme-select {
  background: rgba(255,255,255,0.15) !important;
  color: white !important;
  border: 1px solid rgba(255,255,255,0.2) !important;
  padding: 0.25rem 0.4rem;
  border-radius: 4px;
  font-size: 0.75rem;
  cursor: pointer;
}
.theme-select option {
  background: #1e293b;
  color: white;
}
.user-pill {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  border-left: 1px solid rgba(255,255,255,0.2);
  padding-left: 0.8rem;
}
.user-info {
  text-align: right;
}
.user-name {
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.3rem;
  justify-content: flex-end;
  font-size: 0.85rem;
}
.user-role-tag {
  background: rgba(255,255,255,0.25);
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: bold;
}
.btn-logout {
  background: #ef4444;
  color: white;
  border: none;
  padding: 0.4rem 0.5rem;
  border-radius: 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
}
.inline-icon {
  vertical-align: middle;
}
.text-warning {
  color: #facc15;
}
</style>
