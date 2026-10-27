// Componente único para los 4 cuestionarios (ejercicio, clínico, nutrición, psicología).
// Cada página solo le pasa sus preguntas, su color, cómo armar los resultados y cómo guardar.
//
// Props:
//   titulo, tituloResultados   textos del encabezado
//   color, colorRgb            p. ej. '#0284C7' y '2,132,199'
//   preguntas                  arreglo de instrumentos.js (se filtran con visibles())
//   resultados(answers, n)     → { score, etiquetaScore, icono, nivel, metricas:[{val,lbl}],
//                                  aviso?: { titulo, texto, alerta?, telefono? },
//                                  tituloLista, lista:[{icon,titulo,desc,badge,badgeClass}],
//                                  tips:[{icon,text}], extra?: ReactNode }
//   guardar(answers, avisar)   async; avisar(texto) cambia el texto del botón mientras guarda.
//                              Devuelve { mensaje, ruta } para el aviso final y a dónde ir.
//   textoGuardar               texto del botón de guardar

import { useState, useRef } from 'react'
import { useNavigate } from 'react-router-dom'
import { visibles } from '@/modulos/cuestionarios/instrumentos'
import './Cuestionario.css'

const BADGE = { high: 'cq-badge-high', med: 'cq-badge-med', '': 'cq-badge-def' }

function AnilloScore({ score }) {
  const R = 68
  const C = 2 * Math.PI * R
  const offset = C - (C * score) / 100
  return (
    <svg width="170" height="170" viewBox="0 0 170 170" role="img" aria-label={`Puntaje ${score} de 100`}>
      <circle cx="85" cy="85" r={R} fill="none" stroke="currentColor" strokeOpacity="0.08" strokeWidth="8" />
      <circle cx="85" cy="85" r={R} fill="none" stroke="var(--cq-color)" strokeWidth="8"
        strokeDasharray={C} strokeDashoffset={offset} transform="rotate(-90 85 85)"
        className="cq-ring-fg" style={{ '--cq-ring-offset': offset }} />
      <text x="85" y="80" textAnchor="middle" fill="currentColor" fontWeight="900" fontSize="40">{score}</text>
      <text x="85" y="98" textAnchor="middle" fill="currentColor" opacity="0.45" fontSize="10" letterSpacing="2">/100</text>
    </svg>
  )
}

export default function Cuestionario({ titulo, tituloResultados, color, colorRgb, preguntas, resultados, guardar, textoGuardar }) {
  const navigate = useNavigate()
  const [current, setCurrent] = useState(0)
  const [answers, setAnswers] = useState({})
  const [error, setError] = useState('')
  const [exiting, setExiting] = useState(false)
  const [showResults, setShowResults] = useState(false)
  const [saving, setSaving] = useState(false)
  const [textoGuardando, setTextoGuardando] = useState('')
  const [toast, setToast] = useState({ show: false, msg: '', type: 'error' })
  const slideRef = useRef(null)

  const qs  = visibles(preguntas, answers)
  const q   = qs[Math.min(current, qs.length - 1)]
  const pct = Math.round(((current + 1) / qs.length) * 100)
  const estilo = { '--cq-color': color, '--cq-rgb': colorRgb }

  const showToast = (msg, type = 'error') => {
    setToast({ show: true, msg, type })
    setTimeout(() => setToast(t => ({ ...t, show: false })), 4000)
  }

  function select(val) { setAnswers(a => ({ ...a, [q.id]: val })); setError('') }

  function mover(delta) {
    setExiting(true)
    setTimeout(() => { setCurrent(c => c + delta); setExiting(false); setError('') }, 240)
  }

  function nextQ() {
    if (!answers[q.id]) {
      setError('Selecciona una opción para continuar.')
      slideRef.current?.animate([
        { transform: 'translateX(0)' }, { transform: 'translateX(-8px)' },
        { transform: 'translateX(8px)' }, { transform: 'translateX(0)' },
      ], { duration: 300 })
      return
    }
    if (current < qs.length - 1) mover(1)
    else { setShowResults(true); window.scrollTo({ top: 0 }) }
  }

  function reset() { setAnswers({}); setCurrent(0); setShowResults(false); setError(''); window.scrollTo({ top: 0, behavior: 'smooth' }) }

  async function onGuardar() {
    setSaving(true)
    setTextoGuardando('Guardando perfil...')
    try {
      const { mensaje, ruta } = await guardar(answers, setTextoGuardando)
      showToast(mensaje ?? 'Guardado.', 'success')
      setTimeout(() => navigate(ruta), 1400)
    } catch (e) {
      showToast(e.response?.data?.error ?? e.message ?? 'Error al guardar el perfil.')
      setSaving(false)
    }
  }

  const Toast = <div role="status" aria-live="polite" className={`cq-toast ${toast.show ? 'show' : ''} ${toast.type}`}>{toast.msg}</div>

  /* ── Resultados ── */
  if (showResults) {
    const r = resultados(answers, qs.length)
    return (
      <div className="cq-wrap" style={estilo}>
        <header className="cq-header">
          <button className="cq-back" onClick={reset}>← Volver</button>
          <h1 className="cq-header-title">{tituloResultados}</h1>
          <div className="cq-counter">Análisis</div>
        </header>

        <main className="cq-results cq-fade">
          <div className="cq-hero">
            <AnilloScore score={r.score} />
            <div className="cq-score-label">{r.etiquetaScore}</div>
            <span className="cq-nivel-icon" aria-hidden="true">{r.icono}</span>
            <div className="cq-hero-nivel">{r.nivel}</div>
            <div className="cq-hero-sub">Basado en tus {qs.length} respuestas</div>
          </div>

          <div className="cq-metrics">
            {r.metricas.map((m, i) => (
              <div className="cq-metric" key={i}>
                <div className="cq-metric-val">{m.val}</div>
                <div className="cq-metric-lbl">{m.lbl}</div>
              </div>
            ))}
          </div>

          {r.aviso && (
            <div className={`cq-aviso${r.aviso.alerta ? ' alerta' : ''}`} role={r.aviso.alerta ? 'alert' : undefined}>
              <div className="cq-aviso-titulo">{r.aviso.titulo}</div>
              <p className="cq-aviso-texto">{r.aviso.texto}</p>
              {r.aviso.telefono && <a className="cq-aviso-tel" href={`tel:${r.aviso.telefono.replace(/\s/g, '')}`}>📞 {r.aviso.telefono}</a>}
            </div>
          )}

          {r.lista?.length > 0 && (
            <>
              <h2 className="cq-sec-title">{r.tituloLista}</h2>
              {r.lista.map((x, i) => (
                <div className="cq-rec" key={i}>
                  <div className="cq-rec-top">
                    <span className="cq-rec-icon" aria-hidden="true">{x.icon}</span>
                    <span className="cq-rec-name">{x.titulo}</span>
                    <span className={`cq-badge ${BADGE[x.badgeClass ?? '']}`}>{x.badge}</span>
                  </div>
                  <p className="cq-rec-desc">{x.desc}</p>
                </div>
              ))}
            </>
          )}

          {r.extra}

          {r.tips?.length > 0 && (
            <>
              <h2 className="cq-sec-title">Consejos personalizados</h2>
              {r.tips.map((t, i) => (
                <div className="cq-tip" key={i}>
                  <span className="cq-tip-icon" aria-hidden="true">{t.icon}</span>
                  <span className="cq-tip-text">{t.text}</span>
                </div>
              ))}
            </>
          )}

          <div className="cq-cta">
            <button className="cq-btn-primary full" onClick={onGuardar} disabled={saving}>
              {saving ? <><span className="cq-spinner" aria-hidden="true" /> {textoGuardando}</> : textoGuardar}
            </button>
            <button className="cq-btn-ghost full" onClick={reset} disabled={saving}>Volver a hacer el cuestionario</button>
          </div>
        </main>
        {Toast}
      </div>
    )
  }

  /* ── Preguntas ── */
  return (
    <div className="cq-wrap" style={estilo}>
      <header className="cq-header">
        <button className="cq-back" onClick={() => navigate(-1)}>← Volver</button>
        <h1 className="cq-header-title">{titulo}</h1>
        <div className="cq-counter" aria-label={`Pregunta ${current + 1} de ${qs.length}`}>{current + 1} / {qs.length}</div>
      </header>

      <div className="cq-progress">
        <div className="cq-progress-meta">
          <span className="cq-cat-tag">{q.category}</span>
          <span className="cq-pct-label">{pct}%</span>
        </div>
        <div className="cq-track" role="progressbar" aria-valuemin={0} aria-valuemax={100} aria-valuenow={pct}>
          <div className="cq-fill" style={{ width: `${pct}%` }} />
        </div>
      </div>

      <main className="cq-main">
        {/* fieldset + radios reales: se puede contestar con teclado (flechas, espacio) y lo leen los lectores de pantalla */}
        <fieldset key={current} ref={slideRef} className={`cq-q-wrap ${exiting ? 'cq-out' : 'cq-in'}`}>
          <div className="cq-ghost-num" aria-hidden="true">{String(current + 1).padStart(2, '0')}</div>
          <legend className="cq-q-title">{q.title}</legend>
          {q.help && <p className="cq-q-help" id={`ayuda-${q.id}`}>{q.help}</p>}
          <div className="cq-opts" role="radiogroup" aria-describedby={q.help ? `ayuda-${q.id}` : undefined}>
            {q.opts.map((opt, i) => {
              const sel = answers[q.id] === opt.val
              return (
                <label key={opt.val} className={`cq-opt${sel ? ' active' : ''}`}>
                  <input
                    type="radio"
                    name={q.id}
                    value={opt.val}
                    checked={sel}
                    autoFocus={i === 0 && !answers[q.id]}
                    onChange={() => select(opt.val)}
                    onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); nextQ() } }}
                  />
                  <span className="cq-radio" aria-hidden="true" />
                  <span>
                    <span className="cq-opt-label" style={{ display: 'block' }}>{opt.label}</span>
                    {opt.desc && <span className="cq-opt-desc" style={{ display: 'block' }}>{opt.desc}</span>}
                  </span>
                </label>
              )
            })}
          </div>
          {error && <div className="cq-error" role="alert">⚠ {error}</div>}
        </fieldset>
      </main>

      <div className="cq-nav">
        <div className="cq-nav-inner">
          {current > 0 && <button className="cq-btn-ghost" onClick={() => mover(-1)}>← Atrás</button>}
          <div className="cq-spacer" />
          <button className="cq-btn-primary" onClick={nextQ}>
            {current === qs.length - 1 ? 'Ver resultados →' : 'Continuar →'}
          </button>
        </div>
      </div>
      {Toast}
    </div>
  )
}
