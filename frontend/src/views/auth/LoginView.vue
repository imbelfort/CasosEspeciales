<template>
  <div class="auth-wrapper">
    <div class="auth-card">
      <div class="auth-header">
        <div class="logo-circle">FICCT</div>
        <h2>Acceso al Sistema de Casos Especiales</h2>
        <p class="auth-desc">
          Ingrese con sus credenciales institucionales. El registro y habilitación de cuentas es administrado directamente por Dirección de Carrera y CPD.
        </p>
      </div>

      <div class="auth-body">
        <form @submit.prevent="handleLogin" class="form">
          <div class="form-group">
            <label>Correo Electrónico Institucional:</label>
            <div class="input-with-icon">
              <Mail :size="16" class="field-icon" />
              <input v-model="form.email" type="email" required placeholder="ejemplo@ficct.uagrm.edu.bo" />
            </div>
          </div>

          <div class="form-group">
            <label>Contraseña:</label>
            <div class="input-with-icon">
              <Lock :size="16" class="field-icon" />
              <input v-model="form.password" type="password" required placeholder="••••••••" />
            </div>
          </div>

          <button type="submit" class="btn-full" :disabled="loading">
            <LogIn :size="16" class="btn-icon" />
            {{ loading ? 'Verificando credenciales...' : 'Iniciar Sesión' }}
          </button>
        </form>

        <div class="demo-accounts">
          <h4>Cuentas de Acceso Predefinidas (Contraseña: password123)</h4>
          <ul>
            <li><strong>Estudiantes:</strong> <code>estudiante@ficct.uagrm.edu.bo</code>, <code>maria.gonzales@ficct.uagrm.edu.bo</code></li>
            <li><strong>Dirección:</strong> <code>director@ficct.uagrm.edu.bo</code>, <code>academico@ficct.uagrm.edu.bo</code></li>
            <li><strong>CPD:</strong> <code>cpd@uagrm.edu.bo</code></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { Mail, Lock, LogIn } from '@lucide/vue';
import api from '../../services/api';

const emit = defineEmits(['auth-success']);
const router = useRouter();
const loading = ref(false);
const form = ref({ email: '', password: '' });

const handleLogin = async () => {
  loading.value = true;
  try {
    const res = await api.post('/auth/login', form.value);
    localStorage.setItem('token', res.data.token);
    emit('auth-success', res.data.usuario);
    const rol = res.data.usuario.roles?.[0]?.nombre;
    if (rol === 'ESTUDIANTE') router.push('/estudiante');
    else if (rol === 'CPD') router.push('/cpd');
    else router.push('/direccion');
  } catch (err) {
    alert(err.response?.data?.message || 'Credenciales incorrectas o usuario no habilitado.');
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.auth-wrapper {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 3rem 1rem;
}
.auth-card {
  background: var(--surface);
  border-radius: 12px;
  box-shadow: 0 10px 25px -5px var(--shadow-color);
  width: 100%;
  max-width: 480px;
  overflow: hidden;
  border: 1px solid var(--border);
  color: var(--text);
}
.auth-header {
  text-align: center;
  padding: 2rem 2rem 1rem 2rem;
  background: var(--surface-alt);
  border-bottom: 1px solid var(--border);
}
.logo-circle {
  width: 50px;
  height: 50px;
  background: var(--primary);
  color: #ffffff;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  margin: 0 auto 0.8rem auto;
  font-size: 0.9rem;
  letter-spacing: 1px;
}
.auth-header h2 {
  font-size: 1.25rem;
  color: var(--text);
  margin: 0 0 0.5rem 0;
}
.auth-desc {
  color: var(--text-muted);
  font-size: 0.85rem;
  margin: 0;
  line-height: 1.4;
}
.auth-body {
  padding: 2rem;
}
.form-group {
  margin-bottom: 1.2rem;
}
.form-group label {
  display: block;
  font-size: 0.85rem;
  font-weight: 600;
  margin-bottom: 0.4rem;
  color: var(--text);
}
.input-with-icon {
  position: relative;
  display: flex;
  align-items: center;
}
.field-icon {
  position: absolute;
  left: 0.8rem;
  color: var(--text-muted);
  pointer-events: none;
}
.input-with-icon input {
  width: 100%;
  padding: 0.75rem 0.8rem 0.75rem 2.4rem;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 0.95rem;
  background-color: var(--surface);
  color: var(--text);
}
.input-with-icon input:focus {
  outline: none;
  border-color: var(--border-focus);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
}
.btn-full {
  width: 100%;
  background: var(--primary);
  color: #ffffff;
  padding: 0.85rem;
  border: none;
  border-radius: 6px;
  font-size: 0.95rem;
  font-weight: 600;
  cursor: pointer;
  margin-top: 0.8rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  transition: background-color 0.2s;
}
.btn-full:hover {
  background: var(--primary-hover);
}
.btn-icon {
  flex-shrink: 0;
}
.demo-accounts {
  margin-top: 1.5rem;
  padding-top: 1.2rem;
  border-top: 1px solid var(--border);
  background: transparent;
}
.demo-accounts h4 {
  font-size: 0.8rem;
  color: var(--text);
  margin: 0 0 0.5rem 0;
}
.demo-accounts ul {
  margin: 0;
  padding-left: 1.2rem;
  font-size: 0.8rem;
  color: var(--text-muted);
}
.demo-accounts li {
  margin-bottom: 0.2rem;
}
.demo-accounts code {
  background: var(--surface-alt);
  color: var(--text);
  border: 1px solid var(--border);
  padding: 0.1rem 0.3rem;
  border-radius: 4px;
}
</style>
