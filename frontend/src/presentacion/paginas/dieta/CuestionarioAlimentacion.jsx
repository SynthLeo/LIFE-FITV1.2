import { useState, useRef } from 'react'
import api from '@/lib/api'
import { generarDieta as generarDietaIA } from '@/lib/ia'
import { descargarDietaPDF } from '@/lib/exportar'
import { ALIMENTACION, evaluarAlimentacion, payloadAlimentacion, habitosAMejorar } from '@/modulos/cuestionarios/instrumentos'
import Cuestionario from '@/presentacion/componentes/cuestionario/Cuestionario'

/* ─────────────────────────────────────────────
   SCORING
───────────────────────────────────────────── */
function calcNutricionScore(ans) {
  return evaluarAlimentacion(ans).score
}

// Cortes del cuestionario de la CDMX (50 % y 75 % del máximo); el último se divide en dos
function getNivelNutricion(score) {
  return score < 50 ? 'deficiente' : score < 75 ? 'mejorable' : score < 90 ? 'adecuado' : 'optimo'
}

const COLOR = '#16A34A'

/* ─────────────────────────────────────────────
   DIETA SEMANAL (con IA)
   onDietaLista: avisa a la página cuando el plan ya está listo,
   para guardarlo junto con el perfil.
───────────────────────────────────────────── */
function DietaSemanal({ answers, onDietaLista }) {
  const [dieta,     setDieta]     = useState(null)
  const [generando, setGenerando] = useState(false)
  const [error,     setError]     = useState(null)
  const [openDia,   setOpenDia]   = useState('Lunes')
  const generadoRef = useRef(false)

  async function generarDieta() {
    if (generadoRef.current) return
    generadoRef.current = true
    setGenerando(true)
    setError(null)
    try {
      // El backend arma el prompt, llama a LM Studio y valida el plan (7 días, kcal seguras).
      // Aquí no se guarda: el plan se guarda junto con el perfil al pulsar "Guardar perfil".
      const { plan } = await generarDietaIA({ respuestas: answers })
      setDieta(plan)
      onDietaLista?.(plan)
    } catch (e) {
      generadoRef.current = false
      setError(e.message)
    } finally {
      setGenerando(false)
    }
  }

  const caja = 'border border-green-600/20 bg-card mb-2 overflow-hidden'
  const botonSec = 'mt-3 border border-green-600/30 text-green-600 text-[11px] uppercase tracking-wider px-4 py-2 hover:border-green-600/60'

  if (generando) {
    return (
      <div className={`${caja} flex items-center justify-center gap-3 py-10`} role="status">
        <span className="w-7 h-7 border-2 border-green-600/20 border-t-green-600 rounded-full animate-spin" aria-hidden="true" />
        <span className="text-xs uppercase tracking-wider text-muted-foreground">Nutriólogo IA creando tu plan semanal...</span>
      </div>
    )
  }
  if (error) {
    return (
      <div className={`${caja} p-6 text-center`}>
        <p role="alert" className="text-sm text-red-500">{error}</p>
        <button className={botonSec} onClick={() => { generadoRef.current = false; generarDieta() }}>Reintentar</button>
      </div>
    )
  }
  if (!dieta) {
    return (
      <div className={`${caja} flex flex-col items-center gap-3 py-8 px-6 text-center`}>
        <span className="text-3xl" aria-hidden="true">🥗</span>
        <p className="text-sm text-muted-foreground">Tu dieta personalizada para toda la semana está lista para generarse.</p>
        <button onClick={generarDieta} className="text-white font-extrabold uppercase tracking-widest text-sm px-6 py-3" style={{ background: COLOR }}>
          🤖 Generar mi dieta semanal con IA →
        </button>
      </div>
    )
  }

  return (
    <div className={caja}>
      <div className="flex items-center gap-2 px-5 py-4 bg-green-600/5 border-b border-green-600/10">
        <span aria-hidden="true">🥗</span>
        <span className="flex-1 font-bold uppercase tracking-wider text-green-600">Plan alimentario semanal</span>
        <span className="text-[10px] uppercase px-2 py-1 bg-green-600/10 text-green-600 border border-green-600/20">~{dieta.calorias_dia} kcal/día</span>
        <button className="border border-green-600/30 text-green-600 text-[11px] px-3 py-1" onClick={() => descargarDietaPDF(dieta).catch(e => setError(e.message))}>⬇️ PDF</button>
      </div>
      {dieta.nota && <p className="px-5 py-3 text-xs text-muted-foreground border-b border-green-600/10">💡 {dieta.nota}</p>}
      {dieta.dias.map(({ dia, comidas }) => {
        const abierto = openDia === dia
        return (
          <div key={dia} className="border-b border-border last:border-0">
            <button className="w-full flex items-center px-5 py-3 text-left hover:bg-secondary/40" aria-expanded={abierto} onClick={() => setOpenDia(abierto ? null : dia)}>
              <span className="flex-1 font-bold uppercase tracking-wider text-sm">{dia}</span>
              <span className="text-xs text-muted-foreground" style={{ transform: abierto ? 'rotate(180deg)' : 'none' }} aria-hidden="true">▾</span>
            </button>
            {abierto && (
              <div className="px-5 pb-4">
                {comidas.map((c, i) => (
                  <div key={i} className="flex gap-3 py-1.5 border-b border-border/40 last:border-0">
                    <span className="text-[10px] uppercase tracking-wider text-muted-foreground min-w-[64px] pt-0.5">{c.tiempo}</span>
                    <span className="text-sm text-secondary-foreground flex-1">{c.descripcion}</span>
                    {c.kcal && <span className="text-[11px] text-muted-foreground whitespace-nowrap">~{c.kcal} kcal</span>}
                  </div>
                ))}
              </div>
            )}
          </div>
        )
      })}
    </div>
  )
}

/* ─────────────────────────────────────────────
   PÁGINA
───────────────────────────────────────────── */
const ICONOS    = { deficiente: '🚨', mejorable: '⚠️', adecuado: '🔵', optimo: '🌟' }
const ETIQUETAS = { deficiente: 'Dieta Deficiente', mejorable: 'Hay Margen de Mejora', adecuado: 'Alimentación Adecuada', optimo: 'Nutrición Óptima' }
const ESTADO    = { deficiente: 'Deficiente', mejorable: 'Mejorable', adecuado: 'Adecuado', optimo: 'Óptimo' }

export default function CuestionarioAlimentacion() {
  const [dietaGenerada, setDietaGenerada] = useState(null)   // plan generado por IA, se guarda con el perfil

  function resultados(answers) {
    const score   = calcNutricionScore(answers)
    const nivel   = getNivelNutricion(score)
    const ev      = evaluarAlimentacion(answers)
    const mejorar = habitosAMejorar(answers)
    return {
      score,
      etiquetaScore: 'Score nutricional',
      icono: ICONOS[nivel],
      nivel: ETIQUETAS[nivel],
      metricas: [
        { val: `${score}/100`, lbl: 'Score nutricional' },
        { val: ESTADO[nivel], lbl: 'Calidad de dieta' },
        { val: `${ev.puntos}/${ev.max}`, lbl: 'Puntos de hábitos' },
        { val: ev.clasificacion, lbl: 'Clasificación' },
      ],
      extra: (
        <>
          {mejorar.length > 0 && (
            <>
              <h2 className="cq-sec-title">Hábitos que puedes mejorar</h2>
              <ul className="cq-lista">{mejorar.map(m => <li key={m}>{m}</li>)}</ul>
            </>
          )}
          <h2 className="cq-sec-title">Tu dieta semanal personalizada — IA</h2>
          <DietaSemanal answers={answers} onDietaLista={setDietaGenerada} />
        </>
      ),
    }
  }

  async function guardar(answers) {
    await api.post('/Cuestionario/GuardarAlimentacion.php', {
      ...payloadAlimentacion(answers),
      plan_semanal: dietaGenerada ?? null,   // incluye el plan si ya se generó
    })
    return { mensaje: 'Perfil nutricional guardado exitosamente.', ruta: '/dieta' }
  }

  return (
    <Cuestionario
      titulo="Cuestionario Nutricional"
      tituloResultados="Perfil Nutricional"
      color={COLOR} colorRgb="22,163,74"
      preguntas={ALIMENTACION}
      resultados={resultados}
      guardar={guardar}
      textoGuardar={dietaGenerada ? 'Guardar perfil y dieta →' : 'Guardar perfil →'}
    />
  )
}
