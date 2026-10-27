// Consentimiento informado (M2).
// <ConConsentimiento> envuelve el onboarding y los cuestionarios: si el usuario
// no ha aceptado la versión vigente, muestra el texto y no deja pasar.
// El backend también lo exige (403 en Guardar*.php), esto es solo la cara visible.

import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import api from '@/lib/api'
import { useAuth } from '@/context/AuthContext'
import { LINEA_DE_LA_VIDA } from '@/modulos/cuestionarios/instrumentos'

const PUNTOS = [
  ['¿Qué es LIFE FIT?', 'Una herramienta de orientación sobre ejercicio, alimentación, salud y bienestar emocional. No es un servicio médico ni psicológico y no sustituye una consulta profesional.'],
  ['¿Qué te vamos a preguntar?', 'Cuestionarios avalados por profesionales (PAR-Q+, IPAQ, AUDIT-C, FINDRISC, PHQ-8, GAD-7, entre otros) sobre actividad física, hábitos, alimentación, ánimo y ansiedad. Algunas preguntas son personales; puedes dejar la app cuando quieras.'],
  ['¿Para qué se usan tus respuestas?', 'Solo para calcular tus resultados y personalizar tu rutina, tu dieta y las recomendaciones. Una IA que corre en un equipo del propio proyecto (LM Studio, sin servicios externos) genera parte del contenido a partir de un resumen de tu perfil.'],
  ['¿Quién puede verlas?', 'Solo tú. Tus resultados de ánimo, ansiedad y consumo de alcohol nunca aparecen en el ranking, el feed social ni en los PDF que descargues.'],
  ['Los resultados no son un diagnóstico', 'Son tamizajes: indican si conviene platicar con un profesional, no qué tienes.'],
  ['Si estás en crisis', `Si piensas en hacerte daño, llama a la ${LINEA_DE_LA_VIDA.nombre}: ${LINEA_DE_LA_VIDA.tel} (${LINEA_DE_LA_VIDA.horario}) o al 911.`],
  ['Tus derechos', 'Puedes pedir que se corrijan o borren tus datos y retirar este consentimiento en cualquier momento escribiendo al equipo de LIFE FIT. Retirarlo implica dejar de usar los cuestionarios.'],
]

export function ConConsentimiento({ children }) {
  const [estado, setEstado] = useState({ cargando: true, aceptado: false, version: null, error: '' })

  useEffect(() => {
    let vivo = true
    api.get('/Auth/Consentimiento.php')
      .then(({ data }) => vivo && setEstado({ cargando: false, aceptado: data.aceptado, version: data.version, error: '' }))
      .catch(() => vivo && setEstado({ cargando: false, aceptado: false, version: null, error: 'No se pudo cargar el consentimiento. Revisa tu conexión y recarga.' }))
    return () => { vivo = false }
  }, [])

  if (estado.cargando) {
    return <div role="status" className="min-h-screen grid place-items-center text-sm text-muted-foreground">Cargando…</div>
  }
  if (estado.aceptado) return children
  return <PantallaConsentimiento version={estado.version} errorCarga={estado.error} onAceptado={() => setEstado(e => ({ ...e, aceptado: true }))} />
}

function PantallaConsentimiento({ version, errorCarga, onAceptado }) {
  const navigate = useNavigate()
  const { logout } = useAuth()
  const [leido, setLeido] = useState(false)
  const [enviando, setEnviando] = useState(false)
  const [error, setError] = useState(errorCarga)

  async function aceptar() {
    setEnviando(true)
    setError('')
    try {
      await api.post('/Auth/Consentimiento.php', { acepto: true, version })
      onAceptado()
    } catch (e) {
      setError(e?.response?.data?.error ?? 'No se pudo guardar. Intenta de nuevo.')
    } finally {
      setEnviando(false)
    }
  }

  function noAcepto() {
    logout?.()
    navigate('/login', { replace: true })
  }

  return (
    <main className="min-h-screen bg-background text-foreground px-4 py-10">
      <div className="max-w-2xl mx-auto">
        <p className="text-[11px] font-mono tracking-[0.18em] uppercase text-primary mb-2">Antes de empezar</p>
        <h1 className="text-3xl font-bold mb-2">Consentimiento informado</h1>
        <p className="text-sm text-muted-foreground mb-6">Léelo con calma. Versión {version ?? '—'}.</p>

        <div className="rounded-2xl border border-border bg-secondary/40 divide-y divide-border">
          {PUNTOS.map(([titulo, texto]) => (
            <section key={titulo} className="p-4">
              <h2 className="text-sm font-semibold mb-1">{titulo}</h2>
              <p className="text-sm text-muted-foreground leading-relaxed">{texto}</p>
            </section>
          ))}
        </div>

        <p className="text-xs text-muted-foreground mt-4">
          Si eres menor de 18 años, comenta con tu mamá, papá o tutor que usarás LIFE FIT antes de continuar.
        </p>

        <label className="flex items-start gap-3 mt-6 cursor-pointer select-none">
          <input type="checkbox" checked={leido} onChange={e => setLeido(e.target.checked)} className="mt-1 w-4 h-4 accent-primary" />
          <span className="text-sm">Leí la información, entiendo que LIFE FIT no da diagnósticos y acepto que mis respuestas se usen para personalizar mi plan.</span>
        </label>

        {error && <p role="alert" className="text-sm text-red-500 mt-4">⚠️ {error}</p>}

        <div className="flex flex-wrap gap-3 mt-6">
          <button
            onClick={aceptar}
            disabled={!leido || enviando || !version}
            className="px-6 py-3 rounded-xl font-semibold bg-primary text-primary-foreground disabled:opacity-40"
          >
            {enviando ? 'Guardando…' : 'Acepto y continúo'}
          </button>
          <button onClick={noAcepto} className="px-6 py-3 rounded-xl font-semibold border border-border text-muted-foreground hover:text-foreground">
            No acepto
          </button>
        </div>
      </div>
    </main>
  )
}
