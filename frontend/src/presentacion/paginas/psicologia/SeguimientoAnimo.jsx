// Seguimiento de PHQ-8 y GAD-7 cada 2 semanas (M3).
// Gráfica privada: estos datos solo los ve el usuario, nunca van a social ni ranking.

import { useEffect, useState } from 'react'
import api from '@/lib/api'
import { PSICOLOGIA, LINEA_DE_LA_VIDA } from '@/modulos/cuestionarios/instrumentos'

const COLOR_PHQ = '#a78bfa'
const COLOR_GAD = '#38bdf8'
const PREGUNTAS = PSICOLOGIA.filter(p => p.id.startsWith('phq_') || p.id.startsWith('gad_'))
const fechaCorta = f => new Date(f.replace(' ', 'T')).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' })

function Grafica({ mediciones }) {
  const W = 640, H = 140, P = 24
  const x = i => mediciones.length === 1 ? W / 2 : P + (i * (W - 2 * P)) / (mediciones.length - 1)
  const y = v => H - P - (v / 24) * (H - 2 * P)
  const linea = k => mediciones.map((m, i) => `${x(i)},${y(m[k])}`).join(' ')
  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="w-full h-32" role="img" aria-label="Evolución de tus puntajes de ánimo y ansiedad">
      {[5, 10, 15].map(c => (
        <g key={c}>
          <line x1={P} x2={W - P} y1={y(c)} y2={y(c)} stroke="currentColor" strokeOpacity="0.12" strokeDasharray="3 3" />
          <text x={W - P + 2} y={y(c) + 3} fontSize="8" fill="currentColor" opacity="0.5">{c}</text>
        </g>
      ))}
      {[['phq8', COLOR_PHQ], ['gad7', COLOR_GAD]].map(([k, c]) => (
        <g key={k}>
          <polyline points={linea(k)} fill="none" stroke={c} strokeWidth="2" />
          {mediciones.map((m, i) => <circle key={i} cx={x(i)} cy={y(m[k])} r="3" fill={c}><title>{`${fechaCorta(m.fecha)}: ${m[k]}`}</title></circle>)}
        </g>
      ))}
      {mediciones.map((m, i) => <text key={i} x={x(i)} y={H - 4} fontSize="8" textAnchor="middle" fill="currentColor" opacity="0.6">{fechaCorta(m.fecha)}</text>)}
    </svg>
  )
}

export default function SeguimientoAnimo({ onAgendar }) {
  const [datos, setDatos] = useState(null)
  const [abierto, setAbierto] = useState(false)
  const [resp, setResp] = useState({})
  const [enviando, setEnviando] = useState(false)
  const [resultado, setResultado] = useState(null)
  const [error, setError] = useState('')

  const cargar = () => api.get('/Cuestionario/Seguimiento.php').then(r => setDatos(r.data)).catch(() => setDatos({ mediciones: [] }))
  useEffect(() => { cargar() }, [])

  if (!datos || !datos.mediciones.length) return null
  const completo = PREGUNTAS.every(p => resp[p.id] != null)

  async function enviar() {
    setEnviando(true); setError('')
    try {
      const { data } = await api.post('/Cuestionario/Seguimiento.php', { respuestas: resp })
      setResultado(data); setAbierto(false); setResp({})
      cargar()
    } catch (e) {
      setError(e?.response?.data?.error ?? 'No se pudo guardar. Intenta de nuevo.')
    } finally {
      setEnviando(false)
    }
  }

  return (
    <section className="mt-5 p-4 bg-secondary/50 border border-purple-500/15 rounded-2xl" aria-labelledby="seg-titulo">
      <div className="flex items-center gap-3 flex-wrap mb-2">
        <h2 id="seg-titulo" className="text-sm font-semibold">📈 Tu evolución (privada)</h2>
        <span className="text-[10px] text-muted-foreground flex gap-3">
          <span style={{ color: COLOR_PHQ }}>● Ánimo PHQ-8</span>
          <span style={{ color: COLOR_GAD }}>● Ansiedad GAD-7</span>
        </span>
        <span className="ml-auto text-[11px] text-muted-foreground">
          {datos.toca ? '¡Ya te toca tu chequeo!' : `Siguiente chequeo en ${datos.dias_para_siguiente} día${datos.dias_para_siguiente === 1 ? '' : 's'}`}
        </span>
      </div>

      <Grafica mediciones={datos.mediciones} />
      <p className="text-[10px] text-muted-foreground">Más bajo es mejor. Las líneas punteadas marcan 5, 10 y 15 (leve, moderado, alto).</p>

      {resultado && (
        <div role="status" className="mt-3 p-3 rounded-xl text-xs leading-relaxed" style={{ background: resultado.mostrar_linea ? 'rgba(239,68,68,0.08)' : 'rgba(167,139,250,0.08)' }}>
          Guardado: ánimo {resultado.phq8}/24 · ansiedad {resultado.gad7}/21.
          {resultado.empeoro && ' Tus puntajes subieron desde la última vez.'}
          {resultado.sugerir_consulta && (
            <> Te recomendamos platicar con un profesional. <button onClick={onAgendar} className="underline font-semibold">Agendar cita</button></>
          )}
          {resultado.mostrar_linea && (
            <> Si te sientes muy mal, llama a la {LINEA_DE_LA_VIDA.nombre}: <a className="font-bold text-red-500" href={`tel:${LINEA_DE_LA_VIDA.tel.replace(/\s/g, '')}`}>{LINEA_DE_LA_VIDA.tel}</a>.</>
          )}
        </div>
      )}

      {datos.toca && !abierto && (
        <button onClick={() => { setAbierto(true); setResultado(null) }} className="mt-3 text-xs px-4 py-2 rounded-xl font-semibold text-white" style={{ background: '#7c3aed' }}>
          Hacer chequeo de 2 semanas (15 preguntas)
        </button>
      )}

      {abierto && (
        <div className="mt-4 space-y-4">
          {PREGUNTAS.map((p, n) => (
            <fieldset key={p.id} className="border-b border-border/50 pb-3">
              <legend className="text-sm mb-2">{n + 1}. {p.title}</legend>
              <div className="flex flex-wrap gap-2">
                {p.opts.map(o => (
                  <label key={o.val} className={`text-xs px-3 py-1.5 rounded-lg border cursor-pointer ${resp[p.id] === o.val ? 'border-purple-500 bg-purple-500/10 text-foreground' : 'border-border text-muted-foreground'}`}>
                    <input type="radio" name={p.id} value={o.val} className="sr-only" checked={resp[p.id] === o.val} onChange={() => setResp(r => ({ ...r, [p.id]: o.val }))} />
                    {o.label}
                  </label>
                ))}
              </div>
            </fieldset>
          ))}
          {error && <p role="alert" className="text-xs text-red-500">⚠️ {error}</p>}
          <div className="flex gap-2">
            <button onClick={enviar} disabled={!completo || enviando} className="text-xs px-4 py-2 rounded-xl font-semibold text-white disabled:opacity-40" style={{ background: '#7c3aed' }}>
              {enviando ? 'Guardando…' : 'Guardar chequeo'}
            </button>
            <button onClick={() => setAbierto(false)} className="text-xs px-4 py-2 rounded-xl border border-border text-muted-foreground">Cancelar</button>
          </div>
        </div>
      )}
    </section>
  )
}
