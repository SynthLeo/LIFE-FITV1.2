// application/ia/hooks/useIA.js
// Hook compartido — cada módulo lo instancia con su propio `modulo`

import { useState, useCallback, useRef } from 'react'
import apiClient from '../../infraestructura/api/Api'

export function useIA(modulo) {
  const [mensajes,  setMensajes]  = useState([])
  const [cargando,  setCargando]  = useState(false)   // esperando el primer pedazo
  const [escribiendo, setEscribiendo] = useState(false) // la respuesta va llegando
  const [esCrisis,  setEsCrisis]  = useState(false)
  const [error,     setError]     = useState(null)
  const bottomRef = useRef(null)

  const enviar = useCallback(async (texto) => {
    if (!texto.trim() || cargando || escribiendo) return

    // Agregar mensaje del usuario optimísticamente
    const msgUsuario = { rol: 'user', contenido: texto, ts: Date.now() }
    setMensajes(prev => [...prev, msgUsuario])
    setCargando(true)
    setError(null)

    // La respuesta llega en pedazos (streaming): se va escribiendo en el último mensaje
    const ts = Date.now()
    let empezo = false
    const agregar = (trozo) => {
      if (!empezo) {
        empezo = true
        setCargando(false)
        setEscribiendo(true)
        setMensajes(prev => [...prev, { rol: 'assistant', contenido: trozo, ts }])
      } else {
        setMensajes(prev => prev.map(m => m.ts === ts && m.rol === 'assistant' ? { ...m, contenido: m.contenido + trozo } : m))
      }
      bottomRef.current?.scrollIntoView({ block: 'end' })
    }

    try {
      const res = await fetch('/api/ia/chat.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ modulo, mensaje: texto, stream: true }),
      })
      if (res.status === 401) { window.location.href = '/login'; return }
      if (!res.ok || !res.body) {
        const datos = await res.json().catch(() => ({}))
        throw new Error(datos.error ?? 'No pude conectarme con el asistente.')
      }

      const lector = res.body.getReader()
      const decodificador = new TextDecoder()
      let pendiente = ''
      for (;;) {
        const { value, done } = await lector.read()
        if (done) break
        pendiente += decodificador.decode(value, { stream: true })
        let salto
        while ((salto = pendiente.indexOf('\n')) >= 0) {
          const linea = pendiente.slice(0, salto).trim()
          pendiente = pendiente.slice(salto + 1)
          if (!linea) continue
          const ev = JSON.parse(linea)
          if (ev.delta) agregar(ev.delta)
          if (ev.error) throw new Error(ev.error)
          if (ev.fin && ev.es_crisis) {
            setEsCrisis(true)
            setMensajes(prev => prev.map(m => m.ts === ts && m.rol === 'assistant' ? { ...m, esCrisis: true } : m))
          }
        }
      }
      if (!empezo) throw new Error('El asistente no respondió.')
    } catch (e) {
      setError(e.message)
      setMensajes(prev => [...prev, {
        rol: 'assistant',
        contenido: `⚠️ ${e.message || 'No pude conectarme con el asistente.'} Intenta de nuevo.`,
        ts: Date.now() + 1,
        esError: true,
      }])
    } finally {
      setCargando(false)
      setEscribiendo(false)
      // Scroll al último mensaje
      setTimeout(() => bottomRef.current?.scrollIntoView({ behavior: 'smooth' }), 80)
    }
  }, [modulo, cargando, escribiendo])

  const limpiar = useCallback(async () => {
    try {
      await apiClient.post('/ia/limpiar.php', { modulo })
    } catch (_) { /* no crítico */ }
    setMensajes([])
    setEsCrisis(false)
    setError(null)
  }, [modulo])

  return { mensajes, cargando, escribiendo, esCrisis, error, enviar, limpiar, bottomRef }
}
