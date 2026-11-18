<template>
  <header class="navbar">
    <div class="brand">
      <div class="logo-box">FICCT</div>
      <div>
        <h1 class="brand-title">Sistema de Casos Especiales</h1>
        <p class="brand-subtitle">Facultad de Ciencias de la Computación y Telecomunicaciones - UAGRM</p>
      </div>
    </div>
    <div v-if="usuario" class="user-pill">
      <div class="user-info">
        <span class="user-name">
          <User :size="15" class="inline-icon" /> {{ usuario.nombre }} {{ usuario.apellidos }}
        </span>
        <span class="user-role-tag">{{ rolActual }}</span>
      </div>
      <button @click="logout" class="btn-logout">
        <LogOut :size="14" class="inline-icon" /> Salir
      </button>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { User, LogOut } from '@lucide/vue';
import api from '../../services/api';

const props = defineProps({
  usuario: Object
});
const emit = defineEmits(['logout']);
const router = useRouter();

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
  background: #1e3a8a;
  color: white;
  padding: 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.brand {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.logo-box {
  background: #2563eb;
  padding: 0.5rem 0.8rem;
  border-radius: 6px;
  font-weight: 800;
  letter-spacing: 1px;
}
.brand-title {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 700;
}
.brand-subtitle {
  margin: 0;
  font-size: 0.75rem;
  color: #93c5fd;
}
.user-pill {
  display: flex;
  align-items: center;
  gap: 1rem;
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
}
.user-role-tag {
  background: rgba(255,255,255,0.2);
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: bold;
}
.btn-logout {
  background: #ef4444;
  color: white;
  border: none;
  padding: 0.45rem 0.8rem;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}
.inline-icon {
  vertical-align: middle;
}
</style>
