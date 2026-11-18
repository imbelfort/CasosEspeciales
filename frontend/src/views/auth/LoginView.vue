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
  background: white;
  border-radius: 12px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
  width: 100%;
  max-width: 480px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}
.auth-header {
  text-align: center;
  padding: 2rem 2rem 1rem 2rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}
.logo-circle {
  width: 50px;
  height: 50px;
  background: #1e3a8a;
  color: white;
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
  color: #0f172a;
  margin: 0 0 0.5rem 0;
}
.auth-desc {
  color: #64748b;
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
  color: #334155;
}
.input-with-icon {
  position: relative;
  display: flex;
  align-items: center;
}
.field-icon {
  position: absolute;
  left: 0.8rem;
  color: #94a3b8;
  pointer-events: none;
}
.input-with-icon input {
  width: 100%;
  padding: 0.7rem 0.8rem 0.7rem 2.4rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-size: 0.95rem;
}
.btn-full {
  width: 100%;
  background: #1e3a8a;
  color: white;
  padding: 0.8rem;
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
}
.btn-icon {
  flex-shrink: 0;
}
.demo-accounts {
  margin-top: 1.5rem;
  padding-top: 1.2rem;
  border-top: 1px solid #e2e8f0;
}
.demo-accounts h4 {
  font-size: 0.8rem;
  color: #475569;
  margin: 0 0 0.5rem 0;
}
.demo-accounts ul {
  margin: 0;
  padding-left: 1.2rem;
  font-size: 0.8rem;
  color: #64748b;
}
.demo-accounts li {
  margin-bottom: 0.2rem;
}
.demo-accounts code {
  background: #f1f5f9;
  padding: 0.1rem 0.3rem;
  border-radius: 4px;
}
</style>
