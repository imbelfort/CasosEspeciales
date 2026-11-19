import { createApp } from 'vue'
import './style.css'
import App from './App.vue'
import router from './router'
import { useTheme } from './services/useTheme'

// Inicializar y aplicar inmediatamente el tema guardado, modo oscuro y tamaño de letra
const { aplicarConfiguracion } = useTheme()
aplicarConfiguracion()

const app = createApp(App)
app.use(router)
app.mount('#app')
