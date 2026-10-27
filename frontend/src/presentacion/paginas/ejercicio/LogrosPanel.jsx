// Logros y misiones reales (M5) + opción de ocultarse del ranking.
// Los datos vienen de Api/Social/Logros.php, que los calcula con tus entrenamientos.

import { useEffect, useState } from 'react'
import api from '@/lib/api'

const ICONOS = { icon_bolt: '⚡', icon_fire: '🔥', icon_crown: '👑', icon_weight: '🏋️', icon_rocket: '🚀' }

function Barra({ valor }) {
  return (
    <div className="h-1.5 rounded-full bg-secondary overflow-hidden" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={Math.round(valor * 100)}>
      <div className="h-full rounded-full" style={{ width: `${Math.round(valor * 100)}%`, background: '#C6FF00' }} />
    </div>
  )
}

export function PrivacidadRanking({ onCambio }) {
  const [oculto, setOculto] = useState(null)
  useEffect(() => { api.get('/Social/Privacidad.php').then(r => setOculto(r.data.ocultar_ranking)).catch(() => {}) }, [])
  if (oculto === null) return null

  async function cambiar() {
    const nuevo = !oculto
    setOculto(nuevo)
    try {
      await api.post('/Social/Privacidad.php', { ocultar_ranking: nuevo })
      onCambio?.(nuevo)
    } catch {
      setOculto(!nuevo)
    }
  }

  return (
    <label className="flex items-center gap-2 text-xs text-muted-foreground cursor-pointer select-none">
      <input type="checkbox" checked={oculto} onChange={cambiar} className="w-4 h-4 accent-lime-400" />
      Ocultarme del ranking y la actividad
    </label>
  )
}

export default function LogrosPanel({ onNuevos }) {
  const [datos, setDatos] = useState(null)
  const [error, setError] = useState('')

  useEffect(() => {
    api.get('/Social/Logros.php')
      .then(r => { setDatos(r.data); if (r.data.nuevos?.length) onNuevos?.(r.data.nuevos) })
      .catch(() => setError('No se pudieron cargar tus logros.'))
  }, [onNuevos])

  if (error) return <p role="alert" className="text-sm text-red-500 p-4">⚠️ {error}</p>
  if (!datos) return <p role="status" className="text-sm text-muted-foreground p-4">Cargando logros…</p>

  const hechos = datos.logros.filter(l => l.obtenido).length

  return (
    <div className="space-y-6 p-1">
      <p className="text-sm text-muted-foreground">
        <span className="text-foreground font-semibold">{datos.xp} XP</span> · {hechos} de {datos.logros.length} logros
      </p>

      <section aria-labelledby="misiones-t">
        <h3 id="misiones-t" className="text-sm font-semibold mb-3">Misiones</h3>
        <ul className="grid sm:grid-cols-2 gap-3">
          {datos.misiones.map(m => (
            <li key={m.id} className={`p-3 rounded-xl border ${m.completada ? 'border-lime-400/40 bg-lime-400/5' : 'border-border bg-secondary/30'}`}>
              <div className="flex items-center gap-2 mb-1">
                <span className="text-[10px] uppercase tracking-wider text-muted-foreground">{m.tipo}</span>
                <span className="ml-auto text-[10px] text-muted-foreground">+{m.xp} XP</span>
              </div>
              <p className="text-sm font-medium">{m.completada ? '✅ ' : ''}{m.nombre}</p>
              <p className="text-xs text-muted-foreground mb-2">{m.descripcion}</p>
              {m.progreso != null && (
                <>
                  <Barra valor={Math.min(1, m.progreso / m.meta)} />
                  <p className="text-[10px] text-muted-foreground mt-1">{Math.min(m.progreso, m.meta).toLocaleString('es-MX')} / {m.meta.toLocaleString('es-MX')}</p>
                </>
              )}
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="logros-t">
        <h3 id="logros-t" className="text-sm font-semibold mb-3">Logros</h3>
        <ul className="grid sm:grid-cols-2 gap-3">
          {datos.logros.map(l => (
            <li key={l.id} className={`p-3 rounded-xl border flex gap-3 ${l.obtenido ? 'border-lime-400/40 bg-lime-400/5' : 'border-border bg-secondary/30 opacity-80'}`}>
              <span className="text-2xl" aria-hidden="true" style={{ filter: l.obtenido ? 'none' : 'grayscale(1)' }}>{ICONOS[l.icono] ?? '🏆'}</span>
              <div className="flex-1 min-w-0">
                <p className="text-sm font-medium">{l.nombre} {l.obtenido && <span className="sr-only">(obtenido)</span>}</p>
                <p className="text-xs text-muted-foreground mb-2">{l.descripcion}</p>
                {l.obtenido
                  ? <p className="text-[10px] text-lime-500">Obtenido el {new Date(l.fecha.replace(' ', 'T')).toLocaleDateString('es-MX')} · +{l.xp} XP</p>
                  : l.progreso != null && <Barra valor={l.progreso} />}
              </div>
            </li>
          ))}
        </ul>
      </section>
    </div>
  )
}
