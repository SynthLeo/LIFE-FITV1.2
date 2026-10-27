import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  HeartPulse, Droplets, Brain, Dumbbell, ArrowRight, Activity,
  Thermometer, Wind, Moon, Apple, Sun, Footprints, Smartphone,
  PersonStanding, CalendarPlus, Smile, Utensils, ClipboardCheck,
  Sparkles, ShieldCheck,
} from 'lucide-react'
import { useAuth } from '@/context/AuthContext'
import { HeroCarousel, CardCarousel } from '@/presentacion/componentes/Carousel'
import { PageHeader, ProgressRing, Badge } from '@/presentacion/componentes/ui'
import { useSignosVitales } from '@/aplicacion/clinico/Clinico'
import { useAnimos } from '@/aplicacion/psiclogia/Psicologia'
import { useRegistroDiario } from '@/aplicacion/alimetacion/Alimentacion'
import { SignosVitales } from '@/modulos/clinico/Clinico'
import api from '@/lib/api'
import { descargarTodoPDF } from '@/lib/exportar'

/** Junta la rutina activa y la dieta guardada en un solo PDF (sin datos sensibles). */
function BotonDescargarTodo() {
  const [estado, setEstado] = useState('')
  async function descargar() {
    setEstado('Preparando…')
    try {
      const [rutinas, dieta] = await Promise.all([
        api.get('/Rutinas/Rutinas.php').then(r => r.data).catch(() => null),
        api.get('/Cuestionario/ObtenerPerfilAlimentacion.php').then(r => r.data).catch(() => null),
      ])
      const lista  = Array.isArray(rutinas) ? rutinas : (rutinas?.rutinas ?? rutinas?.data ?? [])
      const rutina = lista.find(r => Number(r.activa) === 1 && r.ejercicios?.length) ?? lista.find(r => r.ejercicios?.length)
      const perfil = dieta?.data ?? dieta?.perfil ?? dieta
      let plan = perfil?.plan_semanal ?? null
      if (typeof plan === 'string') { try { plan = JSON.parse(plan) } catch { plan = null } }
      await descargarTodoPDF({ rutina, plan, parqConsultar: /PAR-Q/i.test(rutina?.descripcion ?? '') })
      setEstado('')
    } catch (e) {
      setEstado(e.message)
    }
  }
  return (
    <div className="flex flex-col items-end gap-1">
      <button onClick={descargar} disabled={estado === 'Preparando…'} className="px-4 py-2 rounded-xl text-sm font-semibold border border-border hover:border-primary/50 disabled:opacity-50">
        ⬇️ Descargar mi plan (PDF)
      </button>
      {estado && estado !== 'Preparando…' && <span role="alert" className="text-xs text-red-500">{estado}</span>}
    </div>
  )
}

/* Colores de cada área (los mismos que ya usan los módulos) */
const C = {
  corazon:    '#0ea5e9',
  agua:       '#22c55e',
  mente:      '#a78bfa',
  movimiento: 'hsl(158 100% 45%)',
}

const META_AGUA     = 8
const META_SESIONES = 5

/* ── Consejos de salud (se rotan según el día) ─────────────── */
const CONSEJOS = [
  { icon: Droplets,       color: '#38bdf8', tag: 'Hidratación', title: 'Un vaso al despertar',   text: 'Tu cuerpo pasa horas sin agua mientras duermes. Empieza el día con un vaso antes del café.' },
  { icon: Moon,           color: '#818cf8', tag: 'Sueño',       title: 'Duerme 7 a 9 horas',     text: 'Dormir bien mejora la memoria, el ánimo y la recuperación muscular. Intenta acostarte a la misma hora.' },
  { icon: Wind,           color: '#a78bfa', tag: 'Mente',       title: 'Respiración 4-7-8',      text: 'Inhala 4 segundos, sostén 7 y exhala 8. Repite 4 veces cuando sientas estrés antes de un examen.' },
  { icon: Apple,          color: '#22c55e', tag: 'Nutrición',   title: 'Medio plato de verduras', text: 'Llena la mitad de tu plato con verduras, un cuarto con proteína y un cuarto con cereales.' },
  { icon: PersonStanding, color: '#f59e0b', tag: 'Postura',     title: 'Pausa cada 45 minutos',  text: 'Si estudias sentado mucho tiempo, levántate, estírate y camina un par de minutos.' },
  { icon: Sun,            color: '#facc15', tag: 'Bienestar',   title: 'Luz de día',             text: 'Unos minutos de sol por la mañana ayudan a regular tu reloj interno y a dormir mejor de noche.' },
  { icon: Footprints,     color: '#2dd4bf', tag: 'Movimiento',  title: 'Camina más',             text: 'Bajarte una parada antes o usar escaleras suma pasos sin que te cueste tiempo extra.' },
  { icon: Smartphone,     color: '#f472b6', tag: 'Descanso',    title: 'Pantallas fuera',        text: 'Deja el celular 30 minutos antes de dormir. La luz de la pantalla retrasa el sueño.' },
]

/** Rota los consejos para que cada día empiece con uno distinto */
function consejosDelDia() {
  const dia = Math.floor(new Date().getTime() / 86_400_000)
  const k = dia % CONSEJOS.length
  return [...CONSEJOS.slice(k), ...CONSEJOS.slice(0, k)]
}

const ACCIONES = [
  { to: '/clinico',    icon: CalendarPlus,   color: C.corazon,    label: 'Agendar consulta', sub: 'Médico general'   },
  { to: '/psicologia', icon: Smile,          color: C.mente,      label: 'Registrar ánimo',  sub: '¿Cómo te sientes?' },
  { to: '/dieta',      icon: Utensils,       color: C.agua,       label: 'Registrar comida', sub: 'Y vasos de agua'   },
  { to: '/psicologia', icon: ClipboardCheck, color: '#f59e0b',    label: 'Test de estrés',   sub: 'PSS-10, 3 minutos' },
]

const DIAS = ['D', 'L', 'M', 'M', 'J', 'V', 'S']
// Fecha local AAAA-MM-DD (toISOString usa UTC y en México de noche ya sería "mañana")
const isoLocal = (d = new Date()) =>
  `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
const hoyISO = () => isoLocal()
const fechaBonita = (iso) => {
  if (!iso) return 'reciente'
  const [y, m, d] = iso.split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString('es-MX', { day: 'numeric', month: 'long' })
}

export default function SaludPage() {
  const { user } = useAuth()
  const signos    = useSignosVitales()
  const animos    = useAnimos(7)
  const registros = useRegistroDiario()
  const [panel, setPanel] = useState(null)

  useEffect(() => {
    signos.cargar(30)
    animos.cargar()
    registros.cargar(7)
    api.get('/PanelControl/ObtenerPanel.php').then(r => setPanel(r.data)).catch(() => {})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const nombre = user?.nombre?.split(' ')[0] ?? 'Atleta'

  /* ── Datos derivados ── */
  const ultimo = signos.ultimo
  const registroHoy = registros.registros.find(r => r.fecha === hoyISO()) ?? null
  const vasos = registroHoy?.aguaVasos ?? 0
  const ultimoAnimo = animos.animos[0] ?? null
  const sesionesSemana = panel?.stats?.sesiones_semana ?? 0
  const minutosSemana  = panel?.stats?.minutos_semana ?? 0

  const semana = useMemo(() => {
    // Últimos 7 días, de más viejo a hoy
    return Array.from({ length: 7 }, (_, k) => {
      const d = new Date()
      d.setDate(d.getDate() - (6 - k))
      const iso = isoLocal(d)
      const reg = animos.animos.find(a => a.fecha === iso)
      return { dia: DIAS[d.getDay()], hoy: k === 6, info: reg ? reg.info : null }
    })
  }, [animos.animos])


  /* ── Slides del carrusel ── */
  const slides = [
    {
      id: 'corazon',
      color: C.corazon,
      content: (
        <Slide
          color={C.corazon}
          icon={HeartPulse}
          eyebrow="Signos vitales"
          title="Tu"
          highlight="corazón"
          description={ultimo
            ? `Último registro: ${fechaBonita(ultimo.fecha)}. Mantener tus signos al día ayuda a detectar cambios a tiempo.`
            : 'Aún no registras tus signos vitales. Toma 2 minutos para medir pulso, presión y temperatura.'}
          cta={{ to: '/clinico', label: ultimo ? 'Ver historial' : 'Registrar signos' }}
          badge={ultimo ? <BadgeSemaforo nivel={ultimo.nivelMaximo} /> : null}
          visual={<VisualCorazon ultimo={ultimo} />}
        />
      ),
    },
    {
      id: 'agua',
      color: C.agua,
      content: (
        <Slide
          color={C.agua}
          icon={Droplets}
          eyebrow="Nutrición"
          title="Hidratación"
          highlight="de hoy"
          description={vasos >= META_AGUA
            ? '¡Meta cumplida! Tu cuerpo te lo agradece. Sigue así el resto del día.'
            : `Llevas ${vasos} de ${META_AGUA} vasos. Tener una botella cerca hace la diferencia.`}
          cta={{ to: '/dieta', label: 'Registrar agua' }}
          visual={<VisualAgua vasos={vasos} />}
        />
      ),
    },
    {
      id: 'mente',
      color: C.mente,
      content: (
        <Slide
          color={C.mente}
          icon={Brain}
          eyebrow="Psicología"
          title="Tu"
          highlight="mente"
          description={ultimoAnimo
            ? `Tu último registro fue "${ultimoAnimo.info.label}". Anotar cómo te sientes te ayuda a notar patrones.`
            : 'Registra cómo te sientes cada día. Toma 10 segundos y te ayuda a entenderte mejor.'}
          cta={{ to: '/psicologia', label: 'Registrar ánimo' }}
          visual={<VisualMente semana={semana} ultimo={ultimoAnimo} />}
        />
      ),
    },
    {
      id: 'movimiento',
      color: C.movimiento,
      content: (
        <Slide
          color={C.movimiento}
          icon={Activity}
          eyebrow="Ejercicio"
          title="Movimiento"
          highlight="semanal"
          description={sesionesSemana >= META_SESIONES
            ? '¡Cumpliste tu meta de la semana! Recuerda también descansar para recuperarte.'
            : `Llevas ${sesionesSemana} de ${META_SESIONES} sesiones y ${minutosSemana} minutos activos esta semana.`}
          cta={{ to: '/entrena', label: 'Entrenar ahora' }}
          visual={<VisualMovimiento sesiones={sesionesSemana} minutos={minutosSemana} />}
        />
      ),
    },
  ]

  return (
    <div className="p-5 md:p-7 max-w-5xl mx-auto space-y-8 animate-fade-up">

      <PageHeader
        eyebrow={new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })}
        title="Mi"
        highlight="salud"
        description={`Hola ${nombre} 👋 Este es el resumen de tu bienestar: cuerpo, alimentación y mente.`}
        actions={<BotonDescargarTodo />}
      />

      {/* ── Carrusel principal ── */}
      <HeroCarousel slides={slides} label="Resumen de salud" />

      {/* ── Consejos (carrusel de tarjetas) ── */}
      <CardCarousel title={<SectionTitle icon={Sparkles}>Consejos de salud</SectionTitle>}>
        {consejosDelDia().map((c, i) => (
          <TipCard key={c.title} {...c} destacado={i === 0} />
        ))}
      </CardCarousel>

      {/* ── Accesos rápidos ── */}
      <div>
        <SectionTitle icon={ArrowRight}>Cuida tu salud hoy</SectionTitle>
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
          {ACCIONES.map(({ to, icon: Icon, color, label, sub }) => (
            <Link key={label} to={to} className="group">
              <div
                className="relative h-full bg-card border border-border rounded-2xl p-4 shadow-card hover-lift overflow-hidden"
                style={{ '--c': color }}
              >
                <div className="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300"
                  style={{ background: `radial-gradient(ellipse at top left, ${color}1a, transparent 70%)` }} />
                <div className="relative">
                  <div className="w-10 h-10 rounded-xl flex items-center justify-center mb-3" style={{ background: `${color}1f` }}>
                    <Icon className="w-5 h-5" style={{ color }} />
                  </div>
                  <p className="font-semibold text-sm text-foreground">{label}</p>
                  <p className="text-xs text-muted-foreground mt-0.5">{sub}</p>
                </div>
                <ArrowRight className="absolute right-3 bottom-3 w-3.5 h-3.5 text-muted-foreground/40 group-hover:translate-x-0.5 transition-all" style={{ color }} />
              </div>
            </Link>
          ))}
        </div>
      </div>

      <p className="flex items-start gap-2 text-xs text-muted-foreground border-t border-border pt-4">
        <ShieldCheck className="w-4 h-4 shrink-0 text-primary" />
        Esta información es orientativa y no sustituye la consulta con un profesional de la salud.
        Si tienes síntomas que te preocupan, agenda una cita en el módulo clínico.
      </p>
    </div>
  )
}

/* ════════════════════════════════════════════════════════════
   Piezas del carrusel
   ════════════════════════════════════════════════════════════ */

function Slide({ color, icon: Icon, eyebrow, title, highlight, description, cta, badge, visual }) {
  return (
    <div className="grid md:grid-cols-[1.1fr_1fr] gap-6 md:gap-8 items-center p-6 md:p-8 min-h-[22rem]">
      <div className="space-y-4 min-w-0">
        <div className="flex items-center gap-3 flex-wrap">
          <div
            className="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0"
            style={{ background: `color-mix(in srgb, ${color} 15%, transparent)`, boxShadow: `0 0 24px -6px ${color}` }}
          >
            <Icon className="w-5 h-5" style={{ color }} />
          </div>
          <span className="text-[11px] font-semibold uppercase tracking-[0.2em]" style={{ color }}>{eyebrow}</span>
          {badge}
        </div>
        <h2 className="font-display text-5xl md:text-6xl leading-[0.92] text-foreground">
          {title}<br />
          <span style={{ color }}>{highlight}</span>
        </h2>
        <p className="text-sm text-muted-foreground max-w-sm leading-relaxed">{description}</p>
        <Link
          to={cta.to}
          draggable={false}
          className="btn-shine inline-flex items-center gap-2 h-11 px-5 rounded-xl text-sm font-bold active:scale-[0.97] transition-transform"
          style={{ background: color, color: '#06110d', boxShadow: `0 8px 24px -10px ${color}` }}
        >
          {cta.label} <ArrowRight className="w-4 h-4" />
        </Link>
      </div>
      <div className="flex items-center justify-center">{visual}</div>
    </div>
  )
}

function BadgeSemaforo({ nivel }) {
  const s = SignosVitales.SEMAFORO[nivel] ?? SignosVitales.SEMAFORO.verde
  return (
    <span className="text-[11px] font-semibold px-2.5 py-1 rounded-full border"
      style={{ color: s.color, borderColor: `${s.color}55`, background: `${s.color}14` }}>
      {s.label}
    </span>
  )
}

/* ── Visual: corazón + signos ── */
function VisualCorazon({ ultimo }) {
  const datos = [
    { icon: HeartPulse,  label: 'Pulso',      valor: ultimo?.frecuenciaCardiaca, unidad: 'bpm' },
    { icon: Activity,    label: 'Presión',    valor: ultimo ? `${ultimo.presionSistolica}/${ultimo.presionDiastolica}` : null, unidad: 'mmHg' },
    { icon: Wind,        label: 'Oxígeno',    valor: ultimo?.saturacionO2, unidad: '%' },
    { icon: Thermometer, label: 'Temp.',      valor: ultimo?.temperatura, unidad: '°C' },
  ]
  return (
    <div className="w-full max-w-sm space-y-3">
      {/* Electrocardiograma */}
      <div className="relative h-20 rounded-2xl border border-border bg-background/50 overflow-hidden">
        <svg viewBox="0 0 300 80" className="absolute inset-0 w-full h-full" preserveAspectRatio="none">
          <path
            className="animate-ecg"
            d="M0 40 H70 L80 40 L88 22 L96 58 L104 10 L112 70 L120 40 H180 L188 34 L196 46 L204 40 H300"
            fill="none" stroke={C.corazon} strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"
            style={{ filter: `drop-shadow(0 0 6px ${C.corazon})` }}
          />
        </svg>
        <HeartPulse className="absolute right-3 top-3 w-5 h-5 animate-heartbeat" style={{ color: C.corazon }} />
      </div>
      <div className="grid grid-cols-2 gap-2.5">
        {datos.map(({ icon: Icon, label, valor, unidad }) => (
          <div key={label} className="rounded-2xl border border-border bg-background/50 p-3">
            <p className="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-muted-foreground font-semibold">
              <Icon className="w-3 h-3" style={{ color: C.corazon }} /> {label}
            </p>
            <p className="font-display text-3xl leading-none mt-1.5 tabular">
              {valor ?? '—'}
              {valor != null && <span className="text-sm text-muted-foreground ml-1">{unidad}</span>}
            </p>
          </div>
        ))}
      </div>
    </div>
  )
}

/* ── Visual: vaso de agua ── */
function VisualAgua({ vasos }) {
  const pct = Math.min(100, Math.round((vasos / META_AGUA) * 100))
  return (
    <div className="flex items-center gap-6">
      {/* Vaso grande */}
      <div className="relative w-32 h-44 rounded-b-[2.5rem] rounded-t-xl border-2 overflow-hidden bg-background/50"
        style={{ borderColor: `${C.agua}66` }}>
        <div
          className="absolute inset-x-0 bottom-0 transition-[height] duration-1000 ease-out"
          style={{ height: `${Math.max(pct, 4)}%`, background: `linear-gradient(180deg, #38bdf8cc, ${C.agua}cc)` }}
        >
          {/* Ola */}
          <svg viewBox="0 0 240 20" className="absolute -top-3 left-0 w-[200%] h-4 animate-wave" preserveAspectRatio="none">
            <path d="M0 10 Q30 0 60 10 T120 10 T180 10 T240 10 V20 H0 Z" fill="#38bdf8cc" />
          </svg>
        </div>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="font-display text-5xl leading-none text-foreground drop-shadow tabular">{vasos}</span>
          <span className="text-[10px] uppercase tracking-widest text-foreground/80 font-semibold">de {META_AGUA} vasos</span>
        </div>
      </div>

      {/* Vasitos */}
      <div className="grid grid-cols-2 gap-2">
        {Array.from({ length: META_AGUA }, (_, i) => (
          <div
            key={i}
            className="w-9 h-11 rounded-b-xl rounded-t-sm border flex items-end overflow-hidden transition-all duration-500"
            style={{
              borderColor: i < vasos ? `${C.agua}88` : 'var(--color-border)',
              transitionDelay: `${i * 60}ms`,
            }}
          >
            <div className="w-full transition-all duration-500" style={{
              height: i < vasos ? '75%' : '0%',
              background: 'linear-gradient(180deg, #38bdf8, #22c55e)',
              transitionDelay: `${i * 60}ms`,
            }} />
          </div>
        ))}
      </div>
    </div>
  )
}

/* ── Visual: ánimo de la semana ── */
function VisualMente({ semana, ultimo }) {
  return (
    <div className="w-full max-w-sm space-y-4">
      <div className="flex items-center gap-4">
        <div
          className="w-24 h-24 rounded-3xl flex items-center justify-center text-5xl border animate-float"
          style={{ borderColor: `${C.mente}55`, background: `${C.mente}14`, boxShadow: `0 0 40px -10px ${C.mente}` }}
        >
          {ultimo ? ultimo.info.emoji : '🙂'}
        </div>
        <div>
          <p className="text-[10px] uppercase tracking-widest text-muted-foreground font-semibold">Último registro</p>
          <p className="font-display text-3xl leading-none mt-1" style={{ color: ultimo ? ultimo.info.color : undefined }}>
            {ultimo ? ultimo.info.label : 'Sin registro'}
          </p>
        </div>
      </div>
      <div className="rounded-2xl border border-border bg-background/50 p-3">
        <p className="text-[10px] uppercase tracking-widest text-muted-foreground font-semibold mb-2">Últimos 7 días</p>
        <div className="grid grid-cols-7 gap-1.5">
          {semana.map((d, i) => (
            <div key={i} className="flex flex-col items-center gap-1.5">
              <div
                className="w-full aspect-square rounded-xl flex items-center justify-center text-lg border"
                style={{
                  borderColor: d.info ? `${d.info.color}55` : 'var(--color-border)',
                  background: d.info ? `${d.info.color}1a` : 'transparent',
                }}
              >
                {d.info ? d.info.emoji : <span className="w-1.5 h-1.5 rounded-full bg-muted-foreground/30" />}
              </div>
              <span className={`text-[10px] font-semibold ${d.hoy ? 'text-foreground' : 'text-muted-foreground'}`}>{d.dia}</span>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

/* ── Visual: movimiento ── */
function VisualMovimiento({ sesiones, minutos }) {
  return (
    <div className="flex items-center gap-6">
      <ProgressRing value={sesiones} max={META_SESIONES} size={168} stroke={14}>
        <span className="font-display text-5xl leading-none tabular">
          {sesiones}<span className="text-muted-foreground text-3xl">/{META_SESIONES}</span>
        </span>
        <span className="text-[10px] text-muted-foreground uppercase tracking-widest mt-1">sesiones</span>
      </ProgressRing>
      <div className="space-y-3">
        <div className="rounded-2xl border border-border bg-background/50 p-3 min-w-[7.5rem]">
          <p className="flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-muted-foreground font-semibold">
            <Dumbbell className="w-3 h-3 text-primary" /> Minutos
          </p>
          <p className="font-display text-3xl leading-none mt-1.5 tabular">{minutos}</p>
        </div>
        <Badge dot>{sesiones >= META_SESIONES ? 'Meta cumplida' : `Faltan ${META_SESIONES - sesiones}`}</Badge>
      </div>
    </div>
  )
}

/* ════════════════════════════════════════════════════════════ */

function TipCard({ icon: Icon, color, tag, title, text, destacado }) {
  return (
    <article
      className="snap-start shrink-0 w-[78%] sm:w-[46%] lg:w-[31%] relative rounded-2xl border bg-card p-5 shadow-card hover-lift overflow-hidden"
      style={{ borderColor: destacado ? `${color}66` : 'var(--color-border)' }}
    >
      <div className="absolute inset-0 pointer-events-none" style={{ background: `radial-gradient(ellipse at top right, ${color}1c, transparent 65%)` }} />
      <Icon aria-hidden="true" className="absolute -right-4 -bottom-4 w-24 h-24 opacity-[0.08]" style={{ color }} strokeWidth={1.5} />
      <div className="relative">
        <div className="flex items-center justify-between mb-4">
          <div className="w-11 h-11 rounded-xl flex items-center justify-center" style={{ background: `${color}1f` }}>
            <Icon className="w-5 h-5" style={{ color }} />
          </div>
          {destacado
            ? <span className="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full" style={{ color, background: `${color}1f` }}>Tip del día</span>
            : <span className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">{tag}</span>}
        </div>
        <h3 className="font-display text-2xl leading-none text-foreground">{title}</h3>
        <p className="text-sm text-muted-foreground mt-2 leading-relaxed">{text}</p>
      </div>
    </article>
  )
}

function SectionTitle({ icon: Icon, children }) {
  return (
    <h2 className="flex items-center gap-2.5 font-display text-xl uppercase text-foreground" style={{ letterSpacing: '0.1em' }}>
      {Icon && <Icon className="w-4 h-4 text-primary" />}
      {children}
    </h2>
  )
}
