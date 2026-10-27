import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
  // La sesión va en una cookie httpOnly que pone el backend; el navegador la manda sola
  withCredentials: true,
})

// ── Response interceptor: manejar 401 global ────────────────
api.interceptors.response.use(
  (res) => res,
  (err) => {
    // 401 = sesión vencida o inválida. En el login un 401 es "contraseña incorrecta": ahí no se redirige.
    if (err.response?.status === 401 && !err.config?.url?.includes('/Auth/Login.php')) {
      try { localStorage.removeItem('lifefit_usuario') } catch { /* nada */ }
      if (window.location.pathname !== '/login') window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api
