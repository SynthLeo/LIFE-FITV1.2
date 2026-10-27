import { createContext, useContext, useState, useCallback } from 'react'
import api from '@/lib/api'

const AuthContext = createContext(null)

/**
 * Lee el payload del JWT. Ojo: solo es para MOSTRAR datos (nombre, onboarding);
 * quien decide si el token es válido es el backend, que verifica la firma.
 * Maneja base64url y UTF-8 (nombres con acentos o ñ).
 */
function parseToken(token) {
  try {
    const b64 = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')
    const bin = atob(b64.padEnd(b64.length + (4 - (b64.length % 4)) % 4, '='))
    const bytes = Uint8Array.from(bin, c => c.charCodeAt(0))
    return JSON.parse(new TextDecoder().decode(bytes))
  } catch {
    return null
  }
}

// La sesión vive en una cookie httpOnly que pone el backend: el JavaScript no la
// puede leer (así un XSS no la roba). Aquí solo se guardan los datos para MOSTRAR
// (nombre, si ya hizo el onboarding y cuándo vence), que vienen dentro del token.
const CLAVE_USUARIO = 'lifefit_usuario'

function leerUsuarioGuardado() {
  try {
    const u = JSON.parse(localStorage.getItem(CLAVE_USUARIO))
    return u && (!u.exp || u.exp * 1000 > Date.now()) ? u : null
  } catch {
    return null
  }
}

function guardarUsuario(token) {
  const u = parseToken(token)
  if (!u) return null
  const datos = { sub: u.sub, nombre: u.nombre, onboarding_completo: u.onboarding_completo, exp: u.exp }
  try { localStorage.setItem(CLAVE_USUARIO, JSON.stringify(datos)) } catch { /* sin almacenamiento */ }
  return datos
}

export function AuthProvider({ children }) {
  const [user, setUser] = useState(leerUsuarioGuardado)

  const login = useCallback(async (email, password) => {
    const { data } = await api.post('/Auth/Login.php', { email, password })
    const u = guardarUsuario(data.token)
    setUser(u)
    return u
  }, [])

  const register = useCallback(async (nombre, email, password, password2) => {
    const { data } = await api.post('/Auth/Registro.php', { nombre, email, password, password2 })
    const u = guardarUsuario(data.token)
    setUser(u)
    return u
  }, [])

  const logout = useCallback(() => {
    try { localStorage.removeItem(CLAVE_USUARIO) } catch { /* nada */ }
    setUser(null)
    api.post('/Auth/Logout.php').catch(() => {})   // borra la cookie en el servidor
  }, [])

  // Al terminar el onboarding el backend marca onboarding_completo = 1 y manda una
  // cookie nueva con ese dato. Se actualiza para que al recargar no regrese a /onboarding.
  const completeOnboarding = useCallback(async () => {
    try {
      const { data } = await api.post('/Auth/CompletarOnboarding.php')
      if (data?.token) {
        setUser(guardarUsuario(data.token))
        return true
      }
    } catch (e) {
      console.error('No se pudo marcar el onboarding como completo', e)
    }
    // Si falla el servidor, al menos deja continuar en esta sesión
    setUser(prev => prev ? { ...prev, onboarding_completo: 1 } : prev)
    return false
  }, [])

  const isAuthenticated = !!user
  const needsOnboarding = isAuthenticated && user?.onboarding_completo === 0

  return (
    <AuthContext.Provider value={{
      user, isAuthenticated, needsOnboarding,
      login, register, logout, completeOnboarding,
    }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be inside AuthProvider')
  return ctx
}
