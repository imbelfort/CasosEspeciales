<template>
  <div class="meta-auth-page">
    <div class="meta-auth-card">
      <!-- Encabezado de la Tarjeta estilo Meta -->
      <div class="card-header">
        <div class="meta-logo-box">FICCT</div>
        <h2 class="card-title">Acceso al Sistema</h2>
        <p class="card-subtitle">
          Sistema de Trámite de Casos Especiales &bull; Facultad de Ingeniería en Ciencias de la Computación y Telecomunicaciones
        </p>
      </div>

      <div class="card-body">
        <!-- 1. Botones Rápidos de Autocompletado por Rol para Pruebas -->
        <div class="role-selector-block">
          <span class="block-label">
            <Zap :size="13" class="inline-icon text-accent" /> Acceso Rápido por Rol (Autocompletar):
          </span>
          <div class="role-pills-grid">
            <button 
              type="button" 
              @click="seleccionarRol('estudiante')" 
              class="role-pill-btn"
              :class="{ active: form.email === 'estudiante@ficct.uagrm.edu.bo' }"
              title="Estudiante de prueba regular"
            >
              <GraduationCap :size="14" />
              <span>Estudiante</span>
            </button>

            <button 
              type="button" 
              @click="seleccionarRol('director')" 
              class="role-pill-btn"
              :class="{ active: form.email === 'director@ficct.uagrm.edu.bo' }"
              title="Director de Carrera (Evaluación de Casos CU3)"
            >
              <Briefcase :size="14" />
              <span>Director</span>
            </button>

            <button 
              type="button" 
              @click="seleccionarRol('academico')" 
              class="role-pill-btn"
              :class="{ active: form.email === 'academico@ficct.uagrm.edu.bo' }"
              title="Personal Académico Administrativo"
            >
              <Users :size="14" />
              <span>Académico</span>
            </button>

            <button 
              type="button" 
              @click="seleccionarRol('cpd')" 
              class="role-pill-btn"
              :class="{ active: form.email === 'cpd@uagrm.edu.bo' }"
              title="Centro de Procesamiento de Datos (CU6)"
            >
              <Server :size="14" />
              <span>CPD</span>
            </button>
          </div>
        </div>

        <!-- Formulario de Acceso -->
        <form @submit.prevent="handleLogin" class="meta-form">
          <div class="form-group">
            <label>Correo Electrónico Institucional:</label>
            <div class="input-container">
              <Mail :size="16" class="input-icon" />
              <input 
                v-model="form.email" 
                type="email" 
                required 
                placeholder="ejemplo@ficct.uagrm.edu.bo" 
                class="meta-input"
              />
            </div>
          </div>

          <div class="form-group">
            <label>Contraseña:</label>
            <div class="input-container">
              <Lock :size="16" class="input-icon" />
              <input 
                v-model="form.password" 
                type="password" 
                required 
                placeholder="••••••••" 
                class="meta-input"
              />
            </div>
          </div>

          <!-- Mensaje de Error si falla la autenticación -->
          <div v-if="errorLogin" class="error-banner">
            <AlertCircle :size="16" class="inline-icon text-danger" />
            <span>{{ errorLogin }}</span>
          </div>

          <!-- Botón de Inicio de Sesión Pill Primario Meta -->
          <button type="submit" class="meta-btn-primary" :disabled="loading">
            <LogIn :size="16" class="inline-icon" />
            {{ loading ? 'Autenticando...' : 'Iniciar Sesión' }}
          </button>
        </form>

        <!-- Información de Respaldo y Ayuda -->
        <div class="footer-help">
          <div class="help-item">
            <CheckCircle2 :size="14" class="text-success inline-icon" />
            <span>Registro y altas de estudiantes gestionados por Dirección de Carrera.</span>
          </div>
          <div class="help-item">
            <KeyRound :size="14" class="text-muted inline-icon" />
            <span>Contraseña común de prueba: <code>password123</code></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { 
  Mail, 
  Lock, 
  LogIn, 
  GraduationCap, 
  Briefcase, 
  Users, 
  Server, 
  Zap, 
  CheckCircle2, 
  KeyRound, 
  AlertCircle 
} from '@lucide/vue';
import api from '../../services/api';

const emit = defineEmits(['auth-success']);
const router = useRouter();
const route = useRoute();

const loading = ref(false);
const errorLogin = ref('');
const form = ref({ 
  email: 'estudiante@ficct.uagrm.edu.bo', 
  password: 'password123' 
});

const seleccionarRol = (rol) => {
  errorLogin.value = '';
  form.value.password = 'password123';

  if (rol === 'estudiante') {
    form.value.email = 'estudiante@ficct.uagrm.edu.bo';
  } else if (rol === 'director') {
    form.value.email = 'director@ficct.uagrm.edu.bo';
  } else if (rol === 'academico') {
    form.value.email = 'academico@ficct.uagrm.edu.bo';
  } else if (rol === 'cpd') {
    form.value.email = 'cpd@uagrm.edu.bo';
  }
};

const handleLogin = async () => {
  loading.value = true;
  errorLogin.value = '';

  try {
    const res = await api.post('/auth/login', form.value);
    localStorage.setItem('token', res.data.token);
    emit('auth-success', res.data.usuario);

    const roles = res.data.usuario.roles.map(r => r.nombre);
    if (roles.includes('DIRECTOR') || roles.includes('PERSONAL_ACADEMICO')) {
      router.push('/direccion');
    } else if (roles.includes('CPD')) {
      router.push('/cpd');
    } else {
      router.push({ path: '/estudiante', query: route.query.avisos === '1' ? { avisos: '1' } : {} });
    }
  } catch (e) {
    errorLogin.value = e.response?.data?.message || 'Credenciales incorrectas o usuario deshabilitado.';
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.meta-auth-page {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 2.5rem 1rem;
}

.meta-auth-card {
  background-color: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl, 16px);
  width: 100%;
  max-width: 490px;
  box-shadow: 0 1px 3px var(--shadow-color);
  overflow: hidden;
  transition: background-color 0.2s ease, border-color 0.2s ease;
}

/* Encabezado */
.card-header {
  text-align: center;
  padding: 2rem 2rem 1.4rem;
  border-bottom: 1px solid var(--border);
  background-color: var(--surface-alt);
}
.meta-logo-box {
  width: 52px;
  height: 52px;
  background-color: var(--primary);
  color: var(--on-primary);
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-circle, 50%);
  margin: 0 auto 0.75rem auto;
  font-size: 0.95rem;
  letter-spacing: 1px;
}
.card-title {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--text);
  margin: 0 0 0.35rem 0;
  letter-spacing: -0.2px;
}
.card-subtitle {
  color: var(--text-muted);
  font-size: 0.82rem;
  margin: 0;
  line-height: 1.4;
}

/* Cuerpo */
.card-body {
  padding: 1.8rem 2rem 2.2rem;
}

/* Bloque de Acceso Rápido por Rol */
.role-selector-block {
  margin-bottom: 1.4rem;
  padding-bottom: 1.2rem;
  border-bottom: 1px dashed var(--border);
}
.block-label {
  display: flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--text-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 0.6rem;
}
.role-pills-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.45rem;
}
.role-pill-btn {
  background-color: var(--surface-alt);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: var(--radius-pill, 100px);
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  transition: all 0.15s ease;
}
.role-pill-btn:hover {
  background-color: var(--border);
}
.role-pill-btn.active {
  background-color: var(--primary);
  color: var(--on-primary);
  border-color: var(--primary);
}

/* Formulario */
.meta-form {
  display: flex;
  flex-direction: column;
  gap: 1.1rem;
}
.form-group label {
  display: block;
  font-size: 0.84rem;
  font-weight: 600;
  margin-bottom: 0.35rem;
  color: var(--text);
}
.input-container {
  position: relative;
  display: flex;
  align-items: center;
}
.input-icon {
  position: absolute;
  left: 0.9rem;
  color: var(--text-muted);
  pointer-events: none;
}
.meta-input {
  width: 100%;
  padding: 0.72rem 0.9rem 0.72rem 2.5rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg, 8px);
  font-size: 0.9rem;
  background-color: var(--surface);
  color: var(--text);
  outline: none;
  transition: border-color 0.15s ease;
}
.meta-input:focus {
  border-color: var(--border-focus);
  box-shadow: 0 0 0 1px var(--border-focus);
}

.error-banner {
  background-color: rgba(228, 30, 63, 0.1);
  border: 1px solid var(--danger);
  border-radius: var(--radius-md, 6px);
  padding: 0.5rem 0.8rem;
  font-size: 0.82rem;
  color: var(--danger);
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

/* Botón Primario estilo Pill */
.meta-btn-primary {
  background-color: var(--primary);
  color: var(--on-primary);
  border: none;
  border-radius: var(--radius-pill, 100px);
  padding: 0.78rem 1.4rem;
  font-size: 0.95rem;
  font-weight: 700;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  margin-top: 0.4rem;
  transition: background-color 0.15s ease;
  width: 100%;
}
.meta-btn-primary:hover {
  background-color: var(--primary-hover);
}
.meta-btn-primary:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

/* Footer informativo */
.footer-help {
  margin-top: 1.4rem;
  padding-top: 1.1rem;
  border-top: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  font-size: 0.78rem;
  color: var(--text-muted);
}
.help-item {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}
.help-item code {
  background-color: var(--surface-alt);
  border: 1px solid var(--border);
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
  color: var(--text);
  font-family: monospace;
}
</style>
