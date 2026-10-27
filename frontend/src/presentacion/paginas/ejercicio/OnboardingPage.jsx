import { useState, useRef, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import api from '@/lib/api'
import { generarDieta, generarDiagnostico, generarRutina } from '@/lib/ia'
import { calcIMC, edadDesde } from '@/lib/imc'
import {
  EJERCICIO, CLINICO, ALIMENTACION, PSICOLOGIA, visibles, LINEA_DE_LA_VIDA,
  evaluarEjercicio, evaluarPsicologia,
  payloadEjercicio, payloadClinico, payloadAlimentacion, payloadPsicologia,
} from '@/modulos/cuestionarios/instrumentos'

/* ══════════════════════════════════════════════════════════
   MÓDULOS Y PREGUNTAS — instrumentos validados (ver src/modulos/cuestionarios/instrumentos.js)
   1 paso de biometría + ejercicio + clínico + nutrición + psicología.
   Algunas preguntas solo aparecen si aplican, así que el total cambia.
══════════════════════════════════════════════════════════ */

const MODULES = [
  { key: 'bio',        icon: '📋', label: 'PERFIL',      color: '#00e5ff' },
  { key: 'ejercicio',  icon: '⚡', label: 'EJERCICIO',   color: '#00e5ff' },
  { key: 'clinico',    icon: '🩺', label: 'CLÍNICO',     color: '#0ea5e9' },
  { key: 'nutricion',  icon: '🥗', label: 'NUTRICIÓN',   color: '#22c55e' },
  { key: 'psicologia', icon: '🧠', label: 'PSICOLOGÍA',  color: '#a78bfa' },
]

// ── Biometría (1 paso especial) ─────────────────────────
const BIO_STEP = { module: 'bio', type: 'bio' }
const EDAD_MIN = 12
const SEG_POR_PREGUNTA = 10   // promedio para estimar el tiempo restante
const edadValida = (e) => e != null && e >= EDAD_MIN && e <= 100
const hace = (anios) => { const d = new Date(); d.setFullYear(d.getFullYear() - anios); return d.toISOString().slice(0, 10) }
const FECHA_MIN = hace(100)
const FECHA_MAX = hace(EDAD_MIN)

const Q_EJERCICIO  = EJERCICIO.map(q => ({ ...q, module: 'ejercicio' }))
const Q_CLINICO    = CLINICO.map(q => ({ ...q, module: 'clinico' }))
const Q_NUTRICION  = ALIMENTACION.map(q => ({ ...q, module: 'nutricion' }))
const Q_PSICOLOGIA = PSICOLOGIA.map(q => ({ ...q, module: 'psicologia' }))

// Pasos visibles según lo que ya contestó (p. ej. "¿cuántos minutos?" solo si hubo días)
// Ejercicio es obligatorio (de ahí salen la rutina y el PAR-Q+). Los demás se pueden dejar para después.
const MODULOS_OPCIONALES = { clinico: Q_CLINICO, nutricion: Q_NUTRICION, psicologia: Q_PSICOLOGIA }

function buildSteps(answers, omitidos = []) {
  const preguntas = [...Q_EJERCICIO]
  for (const [mod, qs] of Object.entries(MODULOS_OPCIONALES)) if (!omitidos.includes(mod)) preguntas.push(...qs)
  return [BIO_STEP, ...visibles(preguntas, answers, { onboarding: true })]
}

/* ══════════════════════════════════════════════════════════
   SCORING
══════════════════════════════════════════════════════════ */

/* ══════════════════════════════════════════════════════════
   ESTILOS INLINE
══════════════════════════════════════════════════════════ */
const S = {
  root: { position:'fixed', inset:0, background:'var(--color-background)', display:'flex', flexDirection:'column', zIndex:9000, overflow:'hidden', fontFamily:'Inter Variable, Inter, system-ui, sans-serif' },
  topBar: { padding:'12px 24px 8px', display:'flex', justifyContent:'space-between', alignItems:'center', fontSize:'0.6rem', fontFamily:'monospace', letterSpacing:'0.15em', textTransform:'uppercase', color:'#3d4451', flexShrink:0 },
  progressWrap: { margin:'0 24px', height:2, background:'var(--border-subtle)', borderRadius:2, flexShrink:0 },
  moduleRow: { display:'flex', gap:0, borderBottom:'1px solid rgba(255,255,255,0.05)', flexShrink:0 },
  slide: { flex:1, overflow:'hidden', position:'relative', display:'flex', alignItems:'center', justifyContent:'center' },
  content: { position:'absolute', inset:0, display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', padding:'24px', overflowY:'auto' },
  navBar: { padding:'16px 24px 28px', display:'flex', justifyContent:'space-between', alignItems:'center', gap:12, borderTop:'1px solid rgba(255,255,255,0.05)', flexShrink:0 },
  btnPrimary: { background:'linear-gradient(135deg,#00e5ff,#00b4cc)', color:'#000', border:'none', borderRadius:12, padding:'13px 28px', fontWeight:700, fontSize:'0.875rem', cursor:'pointer', letterSpacing:'0.02em' },
  btnGhost: { background:'transparent', color:'var(--color-muted-foreground)', border:'1px solid rgba(255,255,255,0.08)', borderRadius:12, padding:'13px 20px', fontWeight:600, fontSize:'0.875rem', cursor:'pointer' },
  optCard: (sel, color) => ({ display:'flex', alignItems:'center', gap:14, padding:'14px 16px', border:`1px solid ${sel ? color : 'var(--border-subtle)'}`, background: sel ? `${color}12` : 'rgba(255,255,255,0.03)', borderRadius:14, cursor:'pointer', transition:'all 160ms', marginBottom:10, width:'100%', maxWidth:560, textAlign:'left' }),
  radio: (sel, color) => ({ width:20, height:20, borderRadius:'50%', border:`2px solid ${sel ? color : '#333'}`, background: sel ? color : 'transparent', flexShrink:0, transition:'all 160ms', display:'flex', alignItems:'center', justifyContent:'center' }),
  optLabel: { fontWeight:600, fontSize:'0.9rem', color:'var(--color-foreground)' },
  optDesc: { fontSize:'0.78rem', color:'var(--color-muted-foreground)', marginTop:2 },
  qTitle: { fontSize:'1.6rem', fontWeight:800, color:'var(--color-foreground)', lineHeight:1.35, maxWidth:560, textAlign:'center', marginBottom:28 },
  qHelp: { fontSize:'0.85rem', color:'var(--color-muted-foreground)', lineHeight:1.5, maxWidth:520, textAlign:'center', margin:'-16px 0 24px' },
  catTag: (color) => ({ fontSize:'0.55rem', fontFamily:'monospace', letterSpacing:'0.18em', textTransform:'uppercase', color, background:`${color}15`, border:`1px solid ${color}30`, padding:'3px 10px', borderRadius:4, marginBottom:14, display:'inline-block' }),
  bioInput: { background:'var(--surface-2)', border:'1px solid rgba(255,255,255,0.1)', borderRadius:12, padding:'14px 18px', color:'var(--color-foreground)', fontSize:'1rem', fontFamily:'Inter Variable, Inter, sans-serif', width:'100%', outline:'none' },
}

/* ══════════════════════════════════════════════════════════
   COMPONENTE MÓDULO TAB
══════════════════════════════════════════════════════════ */
function ModuleTab({ mod, active, pct }) {
  return (
    <div style={{ flex:1, padding:'8px 4px 6px', display:'flex', flexDirection:'column', alignItems:'center', gap:3, opacity: active ? 1 : 0.35, transition:'opacity 300ms' }}>
      <div style={{ fontSize:'0.75rem' }}>{mod.icon}</div>
      <div style={{ fontSize:'0.45rem', fontFamily:'monospace', letterSpacing:'0.12em', color: active ? mod.color : '#3d4451', textTransform:'uppercase', fontWeight:700 }}>{mod.label}</div>
      <div style={{ width:'100%', height:2, background:'var(--border-subtle)', borderRadius:2, overflow:'hidden' }}>
        <div style={{ height:'100%', background: mod.color, width:`${pct}%`, transition:'width 400ms', boxShadow:`0 0 8px ${mod.color}` }} />
      </div>
    </div>
  )
}

/* ══════════════════════════════════════════════════════════
   DIAGNÓSTICO IA — usa Claude Sonnet
══════════════════════════════════════════════════════════ */
function DiagnosticoIA({ data, bio, onContinue }) {
  const [diag, setDiag]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState(null)

  // Solo se usa para el aviso de apoyo (el diagnóstico se calcula en el servidor)
  const ps = evaluarPsicologia(data)

  const generar = useCallback(async () => {
    setLoading(true); setError(null)
    try {
      // El servidor lee los cuestionarios ya guardados, arma un resumen (sin respuestas crudas)
      // y llama a LM Studio. Si la IA falla, responde un diagnóstico básico calculado por reglas.
      setDiag(await generarDiagnostico())
    } catch (e) {
      setError(e.message)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    let vivo = true
    // Se difiere un tick para no actualizar estado durante el render del efecto
    Promise.resolve().then(() => { if (vivo) generar() })
    return () => { vivo = false }
  }, [generar])

  const MOD_META = {
    ejercicio:  { color:'#00e5ff', icon:'⚡', label:'Ejercicio'  },
    clinico:    { color:'#0ea5e9', icon:'🩺', label:'Clínico'    },
    nutricion:  { color:'#22c55e', icon:'🥗', label:'Nutrición'  },
    psicologia: { color:'#a78bfa', icon:'🧠', label:'Psicología' },
  }
  const imc = calcIMC(bio.peso, bio.altura, { edad: edadDesde(bio.nacimiento, { decimales: true }), sexo: bio.sexo })

  return (
    <div style={{ position:'fixed', inset:0, background:'var(--color-background)', zIndex:9999, overflowY:'auto', fontFamily:'Inter Variable, Inter, system-ui, sans-serif' }}>
      <div style={{ maxWidth:560, margin:'0 auto', padding:'48px 24px 80px' }}>
        <div style={{ fontSize:'0.55rem', fontFamily:'monospace', letterSpacing:'0.18em', color:'#00e5ff', textTransform:'uppercase', marginBottom:10 }}>DIAGNÓSTICO INTEGRAL</div>
        <h2 style={{ fontSize:'2.75rem', lineHeight:1, color:'var(--color-foreground)', marginBottom:6 }}>Tu perfil de salud</h2>
        <p style={{ color:'#444', fontSize:'0.82rem', marginBottom:32 }}>Análisis personalizado de tus 4 módulos · Generado por IA</p>

        {/* Biometría rápida */}
        {imc && (
          <div style={{ background:'var(--surface-1)', border:'1px solid rgba(255,255,255,0.07)', borderRadius:16, padding:'16px 20px', marginBottom:24, display:'flex', gap:24, flexWrap:'wrap' }}>
            <div style={{ textAlign:'center' }}><div style={{ fontSize:'1.4rem', fontWeight:800, color: imc.color }}>{imc.value}</div><div style={{ fontSize:'0.65rem', color:'var(--color-muted-foreground)', textTransform:'uppercase', letterSpacing:'0.1em' }}>IMC · {imc.label}</div></div>
            <div style={{ textAlign:'center' }}><div style={{ fontSize:'1.4rem', fontWeight:800, color:'var(--color-foreground)' }}>{bio.peso}kg</div><div style={{ fontSize:'0.65rem', color:'var(--color-muted-foreground)', textTransform:'uppercase', letterSpacing:'0.1em' }}>Peso</div></div>
            <div style={{ textAlign:'center' }}><div style={{ fontSize:'1.4rem', fontWeight:800, color:'var(--color-foreground)' }}>{bio.altura}cm</div><div style={{ fontSize:'0.65rem', color:'var(--color-muted-foreground)', textTransform:'uppercase', letterSpacing:'0.1em' }}>Altura</div></div>
          </div>
        )}

        {ps.mostrarLinea && (
          <div style={{ background:'rgba(239,68,68,0.08)', border:'1px solid rgba(239,68,68,0.35)', borderRadius:16, padding:'16px 20px', marginBottom:24 }}>
            <div style={{ fontWeight:700, color:'var(--color-foreground)', marginBottom:4 }}>No tienes que pasar por esto solo/a 💜</div>
            <p style={{ color:'var(--color-muted-foreground)', fontSize:'0.85rem', lineHeight:1.55, margin:0 }}>
              Tus respuestas de ánimo o ansiedad indican que estás pasando por un momento difícil. Hablar con alguien hoy puede ayudarte.
              La {LINEA_DE_LA_VIDA.nombre} atiende {LINEA_DE_LA_VIDA.horario}.
            </p>
            <a href={`tel:${LINEA_DE_LA_VIDA.tel.replace(/\s/g, '')}`} style={{ display:'inline-block', marginTop:10, fontWeight:800, color:'#ef4444', textDecoration:'none' }}>📞 {LINEA_DE_LA_VIDA.tel}</a>
          </div>
        )}
        {!ps.mostrarLinea && ps.sugerirConsulta && (
          <div style={{ background:'rgba(167,139,250,0.08)', border:'1px solid rgba(167,139,250,0.3)', borderRadius:16, padding:'14px 20px', marginBottom:24, color:'var(--color-muted-foreground)', fontSize:'0.85rem', lineHeight:1.55 }}>
            💬 Tus respuestas sugieren que te vendría bien platicar con un profesional. Puedes agendar en el módulo de Psicología.
          </div>
        )}

        {loading && (
          <div style={{ textAlign:'center', padding:'64px 0' }}>
            <div style={{ width:40, height:40, border:'2px solid rgba(255,255,255,0.08)', borderTopColor:'#00e5ff', borderRadius:'50%', animation:'spin 700ms linear infinite', margin:'0 auto 16px' }} />
            <div style={{ fontSize:'0.6rem', fontFamily:'monospace', letterSpacing:'0.15em', color:'#444', textTransform:'uppercase' }}>Analizando perfil con IA...</div>
          </div>
        )}

        {error && (
          <div style={{ background:'rgba(239,68,68,0.07)', border:'1px solid rgba(239,68,68,0.2)', borderRadius:14, padding:'16px 20px', marginBottom:20, color:'#ef4444', fontSize:'0.82rem' }}>
            <div>{error}</div>
            <div style={{ display:'flex', gap:10, marginTop:12, flexWrap:'wrap' }}>
              <button onClick={generar} style={{ ...S.btnGhost, padding:'8px 14px', color:'#ef4444', borderColor:'rgba(239,68,68,0.35)' }}>Reintentar</button>
              <span style={{ color:'var(--color-muted-foreground)', alignSelf:'center' }}>Tu perfil ya quedó guardado; puedes continuar al panel.</span>
            </div>
          </div>
        )}

        {diag && (
          <>
            <div style={{ background:'linear-gradient(135deg, rgba(0,229,255,0.07), rgba(0,180,204,0.03))', border:'1px solid rgba(0,229,255,0.15)', borderRadius:16, padding:'20px 24px', marginBottom:24 }}>
              <p style={{ color:'#c8e6f0', fontSize:'0.95rem', lineHeight:1.6, margin:0 }}>{diag.resumen}</p>
              <p style={{ color:'#00e5ff', fontSize:'0.95rem', fontWeight:700, marginTop:14, marginBottom:0 }}>"{diag.frase_motivadora}"</p>
            </div>

            <div style={{ display:'grid', gap:14, marginBottom:28 }}>
              {Object.entries(diag.modulos).map(([key, mod]) => {
                const m = MOD_META[key]
                return (
                  <div key={key} style={{ background:'var(--surface-1)', border:`1px solid ${m.color}25`, borderRadius:16, padding:'16px 20px' }}>
                    <div style={{ display:'flex', alignItems:'center', gap:10, marginBottom:10 }}>
                      <span style={{ fontSize:'1.1rem' }}>{m.icon}</span>
                      <span style={{ fontWeight:700, color: m.color, fontSize:'0.8rem', textTransform:'uppercase', letterSpacing:'0.1em' }}>{m.label}</span>
                      <span style={{ marginLeft:'auto', background:`${m.color}18`, color: m.color, border:`1px solid ${m.color}35`, borderRadius:6, fontSize:'0.65rem', fontWeight:700, padding:'2px 8px', textTransform:'uppercase', letterSpacing:'0.08em' }}>{mod.nivel}</span>
                    </div>
                    <p style={{ color:'var(--color-secondary-foreground)', fontSize:'0.83rem', lineHeight:1.5, margin:'0 0 8px' }}>{mod.observacion}</p>
                    <p style={{ color:'#ccc', fontSize:'0.83rem', lineHeight:1.5, margin:0 }}>💡 {mod.recomendacion}</p>
                  </div>
                )
              })}
            </div>
          </>
        )}

        <button
          onClick={onContinue}
          style={{ ...S.btnPrimary, width:'100%', fontSize:'1rem', padding:'16px', borderRadius:14 }}
        >
          Ir al Dashboard →
        </button>
      </div>
      <style>{`@keyframes spin { to { transform: rotate(360deg) } }`}</style>
    </div>
  )
}

/* ══════════════════════════════════════════════════════════
   MAIN COMPONENT
══════════════════════════════════════════════════════════ */
export default function OnboardingPage() {
  const { completeOnboarding, user } = useAuth()
  const navigate = useNavigate()

  // Progreso guardado en este navegador para poder retomarlo (M4)
  const claveProgreso = `lifefit_onboarding_${user?.sub ?? 'anon'}`
  const [guardado] = useState(() => {
    try { return JSON.parse(localStorage.getItem(claveProgreso)) } catch { return null }
  })
  const [step, setStep]     = useState(guardado?.step ?? 0)
  const [answers, setAnswers] = useState(guardado?.answers ?? {})
  const [bio, setBio]       = useState(guardado?.bio ?? { sexo:'', peso:'', altura:'', nacimiento:'' })
  const [omitidos, setOmitidos] = useState(guardado?.omitidos ?? [])
  const [retomado, setRetomado] = useState(Boolean(guardado?.step))
  useEffect(() => {
    try { localStorage.setItem(claveProgreso, JSON.stringify({ step, answers, bio, omitidos })) } catch { /* sin almacenamiento: no se guarda el progreso */ }
  }, [claveProgreso, step, answers, bio, omitidos])
  const [error, setError]   = useState('')
  const [exiting, setExiting] = useState(false)
  const [saving, setSaving] = useState(false)
  const [saveStep, setSaveStep] = useState('')
  const [showDiag, setShowDiag] = useState(false)
  const [toast, setToast]   = useState({ show:false, msg:'', type:'error' })
  const slideRef = useRef(null)

  const STEPS = buildSteps(answers, omitidos)
  const TOTAL = STEPS.length
  const currentStep = STEPS[Math.min(step, TOTAL - 1)]
  const isBio = currentStep.type === 'bio'
  const modKey = currentStep.module
  const mod = MODULES.find(m => m.key === modKey)
  const pct = Math.round(((step + 1) / TOTAL) * 100)

  // Progreso por módulo
  const modProgress = MODULES.map(m => {
    const modSteps = STEPS.filter(s => s.module === m.key)
    const done = STEPS.slice(0, step + 1).filter(s => s.module === m.key).length
    return { ...m, pct: modSteps.length ? Math.round((done / modSteps.length) * 100) : 0 }
  })

  const showToast = (msg, type = 'error') => {
    setToast({ show:true, msg, type })
    setTimeout(() => setToast(t => ({ ...t, show:false })), 4000)
  }

  function canAdvance() {
    if (isBio) return bio.sexo && bio.peso && bio.altura && edadValida(edadDesde(bio.nacimiento))
    return !!answers[currentStep.id]
  }

  function next() {
    if (!canAdvance()) {
      setError(isBio ? 'Completa todos los campos para continuar.' : 'Selecciona una opción para continuar.')
      if (slideRef.current) {
        slideRef.current.animate([
          { transform:'translateX(0)' }, { transform:'translateX(-8px)' },
          { transform:'translateX(8px)' }, { transform:'translateX(-4px)' },
          { transform:'translateX(0)' }
        ], { duration:300 })
      }
      return
    }
    if (step < TOTAL - 1) {
      setExiting(true)
      setTimeout(() => { setStep(s => s + 1); setExiting(false); setError('') }, 240)
    } else {
      submitAll()
    }
  }

  function prev() {
    if (step > 0) {
      setExiting(true)
      setTimeout(() => { setStep(s => s - 1); setExiting(false); setError('') }, 240)
    }
  }

  /* ── Dejar un módulo opcional para después (M4) ── */
  function omitirModulo() {
    if (!MODULOS_OPCIONALES[modKey]) return
    const inicio = STEPS.findIndex(st => st.module === modKey)
    const nuevos = [...omitidos, modKey]
    const total  = buildSteps(answers, nuevos).length
    setOmitidos(nuevos)
    setError('')
    // Lo que sigue queda justo donde empezaba el módulo omitido
    setStep(Math.min(inicio, total - 1))
  }

  /* ── Submit: guarda en los endpoints y genera rutina + dieta ── */
  async function submitAll() {
    setSaving(true)

    const edad  = edadDesde(bio.nacimiento)
    const imc   = calcIMC(bio.peso, bio.altura, { edad: edadDesde(bio.nacimiento, { decimales: true }), sexo: bio.sexo })
    const ipaq  = evaluarEjercicio(answers)
    const pEjer = payloadEjercicio(answers)
    const pClin = payloadClinico(answers, { sexo: bio.sexo, edad })
    const pNutr = payloadAlimentacion(answers, { ipaq })
    const pPsic = payloadPsicologia(answers)

    try {
      // 1. Cuestionario ejercicio + biometría
      setSaveStep('ejercicio')
      await api.post('/Cuestionario/Guardar.php', {
        ...pEjer,
        sexo:   bio.sexo,
        peso:   parseFloat(bio.peso),
        altura: parseFloat(bio.altura),
        imc:    imc?.value,
        fecha_nacimiento: bio.nacimiento,
      })

      // 2-4. Módulos opcionales: solo los que no se dejaron para después
      if (!omitidos.includes('clinico')) {
        setSaveStep('clinico')
        await api.post('/Cuestionario/GuardarClinico.php', pClin)
      }
      if (!omitidos.includes('nutricion')) {
        setSaveStep('nutricion')
        await api.post('/Cuestionario/GuardarAlimentacion.php', pNutr)
      }
      if (!omitidos.includes('psicologia')) {
        setSaveStep('psicologia')
        await api.post('/Cuestionario/GuardarPsicologico.php', pPsic)
        try {
          await api.post('/Bienestar/GuardarPlanSemanal.php', { completados: {} })   // crea el registro de la semana
        } catch { /* no bloquear si falla */ }
      }

      // Ya se guardaron los 4 cuestionarios: el servidor marca el onboarding y da un token nuevo.
      // Se hace aquí (y no al final) para que recargar la página ya no regrese al onboarding.
      await completeOnboarding()
      try { localStorage.removeItem(claveProgreso) } catch { /* nada */ }

      // 5. Rutina y dieta con IA (vía backend). Si la IA falla, el perfil ya quedó guardado
      setSaveStep('rutina')
      await generarRutina().catch(e => console.warn('[onboarding] rutina:', e.message))

      if (!omitidos.includes('nutricion')) {
        setSaveStep('dieta')
        await generarDieta({ guardar: true }).catch(e => console.warn('[onboarding] dieta:', e.message))
      }

      // Mostrar diagnóstico
      setSaving(false)
      setShowDiag(true)
    } catch(e) {
      showToast(e.response?.data?.error ?? 'Error al guardar el perfil. Intenta de nuevo.')
      setSaving(false)
      setSaveStep('')
    }
  }

  function handleDiagContinue() {
    navigate('/dashboard', { replace:true })
  }

  /* ── Render diagnóstico ── */
  if (showDiag) return <DiagnosticoIA data={answers} bio={bio} onContinue={handleDiagContinue} />

  /* ── Render paso biometría ── */
  const edadBio = edadDesde(bio.nacimiento)
  const imc = calcIMC(bio.peso, bio.altura, { edad: edadDesde(bio.nacimiento, { decimales: true }), sexo: bio.sexo })

  return (
    <div style={S.root}>
      <style>{`
        @keyframes spin { to { transform:rotate(360deg) } }
        @keyframes slideIn { from { opacity:0; transform:translateY(18px) } to { opacity:1; transform:translateY(0) } }
        @keyframes slideOut { from { opacity:1; transform:translateY(0) } to { opacity:0; transform:translateY(-14px) } }
        ::-webkit-scrollbar { width:3px }
        ::-webkit-scrollbar-thumb { background:#222; border-radius:4px }
      `}</style>

      {/* Top bar */}
      <div style={S.topBar}>
        <span>PASO {step + 1} / {TOTAL} · ≈ {Math.max(1, Math.ceil(((TOTAL - step - 1) * SEG_POR_PREGUNTA) / 60))} min restantes</span>
        <span style={{ color: mod?.color ?? '#00e5ff' }}>{mod?.icon} {mod?.label}</span>
        <span style={{ color: mod?.color ?? '#00e5ff' }}>{pct}%</span>
      </div>

      {retomado && (
        <div role="status" style={{ display:'flex', gap:12, alignItems:'center', justifyContent:'center', flexWrap:'wrap', padding:'8px 16px', fontSize:'0.8rem', color:'var(--color-muted-foreground)', background:'rgba(0,229,255,0.06)' }}>
          Seguiste donde te quedaste 👌
          <button onClick={() => { setStep(0); setAnswers({}); setOmitidos([]); setBio({ sexo:'', peso:'', altura:'', nacimiento:'' }); setRetomado(false) }} style={{ background:'none', border:'none', color:'#00e5ff', cursor:'pointer', textDecoration:'underline', fontSize:'0.8rem' }}>Empezar de cero</button>
          <button onClick={() => setRetomado(false)} aria-label="Cerrar aviso" style={{ background:'none', border:'none', color:'var(--color-muted-foreground)', cursor:'pointer' }}>✕</button>
        </div>
      )}

      {/* Progress bar global */}
      <div style={S.progressWrap}>
        <div style={{ height:'100%', background:'linear-gradient(90deg,#00e5ff,#00b4cc)', width:`${pct}%`, transition:'width 400ms cubic-bezier(.4,0,.2,1)', boxShadow:'0 0 8px #00e5ff66', borderRadius:2 }} />
      </div>

      {/* Module tabs */}
      <div style={S.moduleRow}>
        {modProgress.filter(m => !omitidos.includes(m.key)).map(m => (
          <ModuleTab key={m.key} mod={m} active={m.key === modKey} pct={m.pct} />
        ))}
      </div>

      {/* Slide content */}
      <div style={S.slide}>
        <div
          ref={slideRef}
          key={step}
          style={{ ...S.content, animation: exiting ? 'slideOut 240ms ease forwards' : 'slideIn 260ms cubic-bezier(0.16,1,0.3,1) forwards' }}
        >
          {isBio ? (
            /* ── Paso biometría ── */
            <div style={{ width:'100%', maxWidth:480 }}>
              <div style={S.catTag('#00e5ff')}>PERFIL INICIAL</div>
              <div style={{ ...S.qTitle, textAlign:'left' }}>Cuéntanos un poco sobre ti</div>
              <div style={{ display:'flex', flexDirection:'column', gap:16 }}>
                {/* Sexo */}
                <div>
                  <div style={{ fontSize:'0.72rem', color:'var(--color-muted-foreground)', letterSpacing:'0.1em', textTransform:'uppercase', marginBottom:8 }}>Sexo biológico</div>
                  <div style={{ display:'flex', gap:10 }}>
                    {[{val:'masculino',label:'♂ Masculino'},{val:'femenino',label:'♀ Femenino'},{val:'otro',label:'◯ Otro'}].map(s => (
                      <button
                        key={s.val}
                        onClick={() => setBio(b => ({...b, sexo:s.val}))}
                        style={{ flex:1, padding:'12px', border:`1px solid ${bio.sexo===s.val ? '#00e5ff' : 'var(--border-medium)'}`, background: bio.sexo===s.val ? 'rgba(0,229,255,0.1)' : 'rgba(255,255,255,0.03)', color: bio.sexo===s.val ? '#00e5ff' : '#888', borderRadius:12, cursor:'pointer', fontWeight:600, fontSize:'0.85rem', transition:'all 160ms' }}
                      >{s.label}</button>
                    ))}
                  </div>
                </div>
                {/* Fecha de nacimiento */}
                <div>
                  <label htmlFor="ob-nacimiento" style={{ display:'block', fontSize:'0.72rem', color:'var(--color-muted-foreground)', letterSpacing:'0.1em', textTransform:'uppercase', marginBottom:8 }}>Fecha de nacimiento</label>
                  <input id="ob-nacimiento" type="date" min={FECHA_MIN} max={FECHA_MAX} value={bio.nacimiento} onChange={e => setBio(b => ({...b, nacimiento:e.target.value}))} style={S.bioInput} />
                  {bio.nacimiento && !edadValida(edadBio) && (
                    <div role="alert" style={{ marginTop:8, fontSize:'0.78rem', color:'#ef4444' }}>LIFE FIT es para personas de {EDAD_MIN} años o más. Revisa la fecha.</div>
                  )}
                  {edadValida(edadBio) && edadBio < 18 && (
                    <div role="note" style={{ marginTop:8, background:'rgba(234,179,8,0.08)', border:'1px solid rgba(234,179,8,0.35)', borderRadius:12, padding:'10px 14px', fontSize:'0.78rem', color:'var(--color-muted-foreground)', lineHeight:1.5 }}>
                      👋 Tienes {edadBio} años. Platica con tu mamá, papá o tutor sobre el uso de la app. Tus resultados son orientativos: tu IMC se compara con chicos y chicas de tu edad (percentiles), y la app no te propondrá dietas de pérdida de peso.
                    </div>
                  )}
                </div>
                {/* Peso */}
                <div>
                  <div style={{ fontSize:'0.72rem', color:'var(--color-muted-foreground)', letterSpacing:'0.1em', textTransform:'uppercase', marginBottom:8 }}>Peso (kg)</div>
                  <input type="number" min="30" max="250" placeholder="Ej: 70" value={bio.peso} onChange={e => setBio(b => ({...b, peso:e.target.value}))} style={S.bioInput} />
                </div>
                {/* Altura */}
                <div>
                  <div style={{ fontSize:'0.72rem', color:'var(--color-muted-foreground)', letterSpacing:'0.1em', textTransform:'uppercase', marginBottom:8 }}>Altura (cm)</div>
                  <input type="number" min="100" max="250" placeholder="Ej: 170" value={bio.altura} onChange={e => setBio(b => ({...b, altura:e.target.value}))} style={S.bioInput} />
                </div>
                {/* IMC preview */}
                {imc && (
                  <div style={{ background:`${imc.color}12`, border:`1px solid ${imc.color}30`, borderRadius:12, padding:'12px 16px', display:'flex', alignItems:'center', gap:12 }}>
                    <div style={{ fontSize:'1.6rem', fontWeight:800, color:imc.color }}>{imc.value}</div>
                    <div>
                      <div style={{ fontWeight:700, color:imc.color, fontSize:'0.85rem' }}>{imc.label}</div>
                      <div style={{ color:'var(--color-muted-foreground)', fontSize:'0.75rem' }}>{imc.metodo === 'percentil' ? 'IMC por percentil para tu edad y sexo (CDC)' : imc.metodo === 'sin_referencia' ? 'En menores el IMC se interpreta por edad y sexo' : 'Índice de Masa Corporal'}</div>
                    </div>
                  </div>
                )}
              </div>
            </div>
          ) : (
            /* ── Paso pregunta ── */
            <div style={{ width:'100%', maxWidth:560 }}>
              <div style={{ textAlign:'center' }}>
                <div style={S.catTag(mod?.color ?? '#00e5ff')}>{currentStep.cat}</div>
                <div style={S.qTitle}>{currentStep.title}</div>
                {currentStep.help && <div style={S.qHelp}>{currentStep.help}</div>}
              </div>
              <div style={{ display:'flex', flexDirection:'column', alignItems:'center' }}>
                {currentStep.opts.map(opt => {
                  const sel = answers[currentStep.id] === opt.val
                  return (
                    <div
                      key={opt.val}
                      onClick={() => { setAnswers(a => ({...a, [currentStep.id]: opt.val})); setError('') }}
                      style={S.optCard(sel, mod?.color ?? '#00e5ff')}
                    >
                      <div style={S.radio(sel, mod?.color ?? '#00e5ff')}>
                        {sel && <div style={{ width:8, height:8, background:'var(--color-background)', borderRadius:'50%' }} />}
                      </div>
                      <div>
                        <div style={S.optLabel}>{opt.label}</div>
                        {opt.desc && <div style={S.optDesc}>{opt.desc}</div>}
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>
          )}

          {error && (
            <div style={{ fontFamily:'monospace', fontSize:'0.58rem', color:'#ef4444', marginTop:12, letterSpacing:'0.05em' }}>{error}</div>
          )}
        </div>
      </div>

      {/* Nav */}
      <div style={S.navBar}>
        {step > 0
          ? <button style={S.btnGhost} onClick={prev}>← Atrás</button>
          : <div />
        }
        {MODULOS_OPCIONALES[modKey] && !saving && (
          <button style={{ ...S.btnGhost, marginLeft:'auto', marginRight:8 }} onClick={omitirModulo}>
            Hacer {mod?.label?.toLowerCase()} después
          </button>
        )}
        <button style={{ ...S.btnPrimary, minWidth:140 }} onClick={next} disabled={saving}>
          {step === TOTAL - 1 ? 'Generar diagnóstico ✨' : 'Continuar →'}
        </button>
      </div>

      {/* Toast */}
      {toast.show && (
        <div style={{ position:'fixed', left:'50%', transform:'translateX(-50%)', bottom:90, background: toast.type==='error' ? 'rgba(239,68,68,0.9)' : 'rgba(34,197,94,0.9)', color:'#fff', padding:'10px 20px', borderRadius:10, fontSize:'0.82rem', fontWeight:600, zIndex:9999, whiteSpace:'nowrap' }}>
          {toast.msg}
        </div>
      )}

      {/* Saving overlay */}
      {saving && (
        <div style={{ position:'fixed', inset:0, background:'var(--color-background)', zIndex:9999, display:'flex', flexDirection:'column', alignItems:'center', justifyContent:'center', gap:20 }}>
          <div style={{ width:44, height:44, border:'2px solid rgba(255,255,255,0.08)', borderTopColor:'#00e5ff', borderRadius:'50%', animation:'spin 700ms linear infinite' }} />
          <div style={{ textAlign:'center' }}>
            <div style={{ fontFamily:'monospace', fontSize:'0.6rem', letterSpacing:'0.15em', color:'#00e5ff', textTransform:'uppercase', marginBottom:6 }}>
              {saveStep === 'ejercicio'  && '⚡ Guardando perfil de ejercicio...'}
              {saveStep === 'clinico'    && '🩺 Guardando perfil clínico...'}
              {saveStep === 'nutricion'  && '🥗 Guardando perfil nutricional...'}
              {saveStep === 'psicologia' && '🧠 Guardando perfil psicológico...'}
              {saveStep === 'rutina'     && '⚡ Generando tu rutina de entrenamiento...'}
              {saveStep === 'dieta'      && '🥗 Generando tu plan de alimentación...'}
            </div>
            <div style={{ fontFamily:'monospace', fontSize:'0.5rem', color:'var(--border-medium)', letterSpacing:'0.1em', textTransform:'uppercase' }}>Creando tu perfil de salud integral</div>
          </div>
          {/* Steps indicator */}
          <div style={{ display:'flex', gap:8 }}>
            {['ejercicio','clinico','nutricion','psicologia','rutina','dieta'].map(s => (
              <div key={s} style={{ width:6, height:6, borderRadius:'50%', background: ['ejercicio','clinico','nutricion','psicologia','rutina','dieta'].indexOf(s) <= ['ejercicio','clinico','nutricion','psicologia','rutina','dieta'].indexOf(saveStep) ? '#00e5ff' : '#222', transition:'background 300ms', boxShadow: s===saveStep ? '0 0 8px #00e5ff' : 'none' }} />
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
