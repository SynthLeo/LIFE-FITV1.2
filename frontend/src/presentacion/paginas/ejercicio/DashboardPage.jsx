import { useEffect, useState } from 'react'
import { useAuth } from '@/context/AuthContext'
import {
  Card, CardHeader, CardTitle, CardContent,
  StatCard, Badge, ProgressBar, ProgressRing, PageLoader, BrandMark, Button
} from '@/presentacion/componentes/ui'
import {
  Flame, Dumbbell, Clock, TrendingUp,
  ChevronRight, Calendar, Target, AlertTriangle,
  Play, BarChart2, BookOpen, ClipboardList,
  Stethoscope, Salad, Brain, ArrowUpRight
} from 'lucide-react'
import { Link } from 'react-router-dom'
import api from '@/lib/api'

const fmt = (n) => Number(n ?? 0).toLocaleString('es-MX')

// Metas semanales (las mismas que ya usaba el panel)
const META_SESIONES = 5
const META_MINUTOS  = 200

const GREET = () => {
  const h = new Date().getHours()
  if (h < 12) return '¡Buenos días'
  if (h < 19) return '¡Buenas tardes'
  return '¡Buenas noches'
}

export default function DashboardPage() {
  const { user } = useAuth()
  const [data, setData]       = useState(null)
  const [loading, setLoading] = useState(true)
  const [error, setError]     = useState('')

  useEffect(() => {
    api.get('/PanelControl/ObtenerPanel.php')
      .then(r => setData(r.data))
      .catch(e => setError(e.response?.data?.error ?? 'Error al cargar panel.'))
      .finally(() => setLoading(false))
  }, [])

  if (loading) return <PageLoader label="Cargando tu panel" />

  if (error) return (
    <div className="p-5 md:p-7 max-w-5xl mx-auto">
      <Card className="border-destructive/30">
        <div className="flex items-start gap-4">
          <div className="w-11 h-11 rounded-xl bg-destructive/10 flex items-center justify-center shrink-0">
            <AlertTriangle className="w-5 h-5 text-destructive" />
          </div>
          <div className="flex-1">
            <p className="font-semibold">No pudimos cargar tu panel</p>
            <p className="text-sm text-muted-foreground mt-0.5">{error}</p>
          </div>
          <Button variant="outline" size="sm" onClick={() => window.location.reload()}>Reintentar</Button>
        </div>
      </Card>
    </div>
  )

  const { stats, ultima_sesion, usuario } = data ?? {}

  const nivelColor = {
    principiante: 'success',
    intermedio:   'warning',
    avanzado:     'danger',
  }[usuario?.nivel_entrenamiento] ?? 'secondary'

  const nombre = usuario?.nombre?.split(' ')[0] ?? user?.nombre?.split(' ')[0] ?? 'Atleta'
  const sesionesSemana = stats?.sesiones_semana ?? 0
  const minutosSemana  = stats?.minutos_semana ?? 0

  return (
    <div className="p-5 md:p-7 max-w-5xl mx-auto space-y-7 animate-fade-up">

      {/* ── Header hero ─────────────────────────────── */}
      <div className="relative rounded-3xl overflow-hidden border border-border shadow-card">
        {/* Fondo */}
        <div className="absolute inset-0 bg-card diagonal-lines" />
        <div
          className="absolute inset-0 pointer-events-none"
          style={{ background: 'radial-gradient(ellipse at 85% 30%, color-mix(in srgb, var(--color-primary) 16%, transparent) 0%, transparent 60%)' }}
        />
        <BrandMark className="absolute -right-10 -bottom-16 w-72 h-72 opacity-[0.07] pointer-events-none" />

        <div className="relative p-6 md:p-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div className="space-y-3 min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <p className="text-xs text-muted-foreground uppercase tracking-[0.2em] font-medium">
                {new Date().toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long' })}
              </p>
              <Badge variant={nivelColor} dot className="capitalize">
                {usuario?.nivel_entrenamiento ?? 'Sin nivel'}
              </Badge>
            </div>
            <h1 className="font-display text-5xl md:text-6xl leading-[0.92]">
              {GREET()},<br />
              <span className="text-gradient">{nombre}!</span>
            </h1>
            <p className="text-sm text-muted-foreground">
              {stats?.racha > 0
                ? <>🔥 Llevas <b className="text-foreground">{stats.racha} días</b> en racha. ¡No pares!</>
                : 'Aquí está tu resumen de hoy.'}
            </p>
            <div className="pt-2">
              <Link to="/entrena">
                <Button size="lg">
                  <Play className="w-4 h-4 fill-current" />
                  Iniciar entrenamiento
                </Button>
              </Link>
            </div>
          </div>

          {/* Anillo de la semana */}
          <div className="flex items-center gap-5 md:flex-col md:gap-3 md:pr-2">
            <ProgressRing value={sesionesSemana} max={META_SESIONES} size={132} stroke={11}>
              <span className="font-display text-4xl leading-none tabular">
                {sesionesSemana}<span className="text-muted-foreground text-2xl">/{META_SESIONES}</span>
              </span>
              <span className="text-[10px] text-muted-foreground uppercase tracking-widest mt-1">sesiones</span>
            </ProgressRing>
            <p className="text-xs text-muted-foreground md:text-center max-w-[10rem]">
              {sesionesSemana >= META_SESIONES
                ? '¡Meta semanal cumplida! 🏆'
                : `${META_SESIONES - sesionesSemana === 1 ? 'Te falta 1 sesión' : `Te faltan ${META_SESIONES - sesionesSemana} sesiones`} para tu meta semanal`}
            </p>
          </div>
        </div>
      </div>

      {/* ── KPI grid ─────────────────────────────────── */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
        <div className="animate-fade-up delay-100">
          <StatCard
            label="Sesiones"
            value={fmt(stats?.sesiones)}
            icon={Dumbbell}
          />
        </div>
        <div className="animate-fade-up delay-200">
          <StatCard
            label="Minutos"
            value={fmt(stats?.minutos)}
            unit="min"
            icon={Clock}
          />
        </div>
        <div className="animate-fade-up delay-300">
          <StatCard
            label="Volumen"
            value={fmt(stats?.volumen)}
            unit="kg"
            icon={TrendingUp}
          />
        </div>
        <div className="animate-fade-up delay-400">
          <StatCard
            label="Racha"
            value={stats?.racha ?? 0}
            unit="días"
            icon={Flame}
            accent={stats?.racha > 0}
          />
        </div>
      </div>

      {/* ── Última sesión + progreso ──────────────────── */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {/* Última sesión */}
        {ultima_sesion ? (
          <Card className="hover-lift hover:border-primary/25">
            <CardHeader>
              <CardTitle>Última sesión</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-4">
                  <div className="w-12 h-12 rounded-2xl bg-primary/10 flex items-center justify-center shrink-0">
                    <Dumbbell className="w-6 h-6 text-primary" />
                  </div>
                  <div>
                    <p className="font-bold">{ultima_sesion.ejercicio ?? 'Sesión de entrenamiento'}</p>
                    <div className="flex items-center gap-2 mt-1">
                      <Calendar className="w-3 h-3 text-muted-foreground" />
                      <p className="text-xs text-muted-foreground">{ultima_sesion.fecha}</p>
                      {ultima_sesion.duracion_minutos > 0 && (
                        <span className="text-xs text-muted-foreground">· {ultima_sesion.duracion_minutos} min</span>
                      )}
                    </div>
                  </div>
                </div>
                <Link
                  to="/entrena"
                  className="w-9 h-9 rounded-xl bg-primary/10 flex items-center justify-center text-primary hover:bg-primary/20 transition-colors"
                >
                  <ChevronRight className="w-4 h-4" />
                </Link>
              </div>
            </CardContent>
          </Card>
        ) : (
          <Card className="border-dashed animate-border-pulse">
            <CardContent>
              <div className="flex items-center justify-between py-2">
                <div className="flex items-center gap-4">
                  <div className="w-12 h-12 rounded-2xl bg-secondary flex items-center justify-center">
                    <Dumbbell className="w-6 h-6 text-muted-foreground" />
                  </div>
                  <div>
                    <p className="font-bold">Sin sesiones aún</p>
                    <p className="text-sm text-muted-foreground">¡Empieza tu primera sesión hoy!</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Objetivo semanal */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Target className="w-4 h-4 text-primary" />
              Objetivo semanal
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <ProgressBar
              label="Sesiones"
              detail={`${sesionesSemana}/${META_SESIONES}`}
              value={sesionesSemana}
              max={META_SESIONES}
            />
            <ProgressBar
              label="Minutos"
              detail={`${minutosSemana}/${META_MINUTOS}`}
              value={minutosSemana}
              max={META_MINUTOS}
            />
            <ProgressBar
              label="Racha personal"
              detail={`${stats?.racha ?? 0}/30 días`}
              value={stats?.racha ?? 0}
              max={30}
            />
          </CardContent>
        </Card>
      </div>

      {/* ── Módulos de salud ─────────────────────────── */}
      <div>
        <SectionTitle>Módulos de Salud Integral</SectionTitle>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          {[
            { to: '/clinico',    icon: Stethoscope, label: 'Clínico',    color: '#0ea5e9', desc: 'Signos vitales, síntomas y consultas' },
            { to: '/dieta',      icon: Salad,       label: 'Nutrición',  color: '#22c55e', desc: 'Plan semanal IA y registro diario'    },
            { to: '/psicologia', icon: Brain,       label: 'Psicología', color: '#a78bfa', desc: 'Bienestar emocional y apoyo'          },
          ].map(({ to, icon: Icon, label, color, desc }) => (
            <Link key={to} to={to} className="group">
              <div
                className="relative h-full bg-card border rounded-2xl p-5 shadow-card hover-lift active:scale-[0.98] overflow-hidden"
                style={{ borderColor: `${color}30` }}
              >
                <div className="absolute inset-0 opacity-60 group-hover:opacity-100 transition-opacity duration-300" style={{ background: `radial-gradient(ellipse at top left, ${color}18, transparent 65%)` }} />
                <Icon aria-hidden="true" className="absolute -right-4 -bottom-4 w-24 h-24 opacity-[0.07] group-hover:opacity-[0.14] group-hover:scale-110 transition-all duration-300" style={{ color }} strokeWidth={1.5} />
                <div className="relative">
                  <div className="flex items-center justify-between mb-4">
                    <div className="w-11 h-11 rounded-xl flex items-center justify-center shrink-0" style={{ background: `${color}1f`, boxShadow: `0 0 20px -6px ${color}` }}>
                      <Icon className="w-5 h-5" style={{ color }} />
                    </div>
                    <ArrowUpRight className="w-4 h-4 opacity-40 group-hover:opacity-100 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-all" style={{ color }} />
                  </div>
                  <p className="font-display text-2xl leading-none" style={{ color, letterSpacing: '0.05em' }}>{label}</p>
                  <p className="text-xs text-muted-foreground mt-1.5 leading-relaxed">{desc}</p>
                </div>
              </div>
            </Link>
          ))}
        </div>
      </div>

      {/* ── Quick links ──────────────────────────────── */}
      <div>
        <SectionTitle>Accesos Rápidos</SectionTitle>
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
          {[
            { to: '/catalogo',     icon: BookOpen,      label: 'Catálogo',      sub: 'Ejercicios',   color: 'from-emerald-500/10' },
            { to: '/rutinas',      icon: ClipboardList, label: 'Rutinas',       sub: 'Mis planes',   color: 'from-blue-500/10'    },
            { to: '/estadisticas', icon: BarChart2,     label: 'Estadísticas',  sub: 'Mi progreso',  color: 'from-purple-500/10'  },
            { to: '/cuestionario', icon: Target,        label: 'Perfil',        sub: 'Re-evaluar',   color: 'from-amber-500/10'   },
          ].map(({ to, icon: Icon, label, sub, color }) => (
            <Link key={to} to={to} className="group">
              <div className={`
                relative bg-card border border-border rounded-2xl p-4 shadow-card hover-lift
                hover:border-primary/30
                overflow-hidden cursor-pointer
                active:scale-[0.97]
              `}>
                {/* Fondo degradado sutil */}
                <div className={`absolute inset-0 bg-gradient-to-br ${color} to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-200`} />

                <div className="relative">
                  <div className="w-9 h-9 rounded-xl bg-secondary group-hover:bg-primary/10 flex items-center justify-center mb-3 transition-colors duration-200">
                    <Icon className="w-4 h-4 text-muted-foreground group-hover:text-primary transition-colors" />
                  </div>
                  <p className="font-display text-xl leading-none text-foreground" style={{ letterSpacing: '0.05em' }}>{label}</p>
                  <p className="text-xs text-muted-foreground mt-1">{sub}</p>
                </div>

                <ChevronRight className="absolute right-3 bottom-3 w-3.5 h-3.5 text-muted-foreground/30 group-hover:text-primary/50 transition-colors" />
              </div>
            </Link>
          ))}
        </div>
      </div>
    </div>
  )
}

function SectionTitle({ children }) {
  return (
    <h2 className="flex items-center gap-3 font-display text-lg uppercase text-muted-foreground mb-3" style={{ letterSpacing: '0.14em' }}>
      {children}
      <span className="flex-1 h-px bg-gradient-to-r from-border to-transparent" />
    </h2>
  )
}
