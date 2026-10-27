import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import { Button, Input, Label, Spinner } from '@/presentacion/componentes/ui'
import { Eye, EyeOff, ArrowRight } from 'lucide-react'
import { BrandLogo } from '@/presentacion/componentes/ui'
import AuthShowcase from '@/presentacion/componentes/layout/AuthShowcase'

export default function LoginPage() {
  const { login } = useAuth()
  const navigate  = useNavigate()

  const [form, setForm]       = useState({ email: '', password: '' })
  const [error, setError]     = useState('')
  const [loading, setLoading] = useState(false)
  const [showPw, setShowPw]   = useState(false)

  const handle = (e) => setForm(f => ({ ...f, [e.target.name]: e.target.value }))

  const submit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const user = await login(form.email, form.password)
      navigate(user.onboarding_completo ? '/dashboard' : '/onboarding', { replace: true })
    } catch (err) {
      setError(err.response?.data?.error ?? 'Error al iniciar sesión.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen flex bg-background app-ambient">

      {/* ── Panel izquierdo visual ─── */}
      <AuthShowcase
        title="TRANSFORMA"
        highlight="TU CUERPO"
        description="Rutinas inteligentes, seguimiento real y un plan que se adapta a ti."
      >
        <div className="grid grid-cols-3 gap-3">
          {[
            { val: '500+', label: 'Ejercicios' },
            { val: '4',    label: 'Módulos' },
            { val: 'IA',   label: 'Asistente 24/7' },
          ].map(({ val, label }) => (
            <div key={label} className="glass border border-border rounded-2xl p-4 shadow-card">
              <p className="font-display text-3xl text-primary leading-none">{val}</p>
              <p className="text-[10px] text-muted-foreground uppercase tracking-wider mt-1.5">{label}</p>
            </div>
          ))}
        </div>
      </AuthShowcase>

      {/* ── Panel derecho — formulario ─── */}
      <div className="flex-1 flex items-center justify-center p-6 relative">
        {/* Glow muy sutil */}
        <div
          className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 rounded-full pointer-events-none"
          style={{ background: 'radial-gradient(circle, hsl(158 100% 45% / 0.07) 0%, transparent 70%)' }}
        />

        <div className="w-full max-w-sm animate-fade-up relative">

          {/* Logo mobile */}
          <BrandLogo size="md" className="mb-10 lg:hidden" />

          {/* Encabezado */}
          <div className="mb-8">
            <h1
              className="font-display text-5xl mb-2 text-foreground"
            >
              BIENVENIDO
            </h1>
            <p className="text-sm text-muted-foreground">Ingresa para continuar tu progreso.</p>
          </div>

          {/* Formulario */}
          <form onSubmit={submit} className="space-y-5">
            <div className="space-y-2">
              <Label htmlFor="email">Correo electrónico</Label>
              <Input
                id="email" name="email" type="email"
                placeholder="tu@correo.com"
                value={form.email} onChange={handle}
                required autoComplete="email"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="password">Contraseña</Label>
              <div className="relative">
                <Input
                  id="password" name="password"
                  type={showPw ? 'text' : 'password'}
                  placeholder="••••••••"
                  value={form.password} onChange={handle}
                  required autoComplete="current-password"
                  className="pr-11"
                />
                <button
                  type="button"
                  onClick={() => setShowPw(v => !v)}
                  className="absolute right-3.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                >
                  {showPw ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                </button>
              </div>
            </div>

            {error && (
              <div className="flex items-start gap-2.5 text-sm text-destructive bg-destructive/10 border border-destructive/20 rounded-xl px-4 py-3">
                <span className="shrink-0 mt-0.5">⚠</span>
                <p>{error}</p>
              </div>
            )}

            <Button type="submit" className="w-full" size="lg" disabled={loading}>
              {loading
                ? <Spinner className="w-4 h-4 border-primary-foreground/30 border-t-primary-foreground" />
                : <>Iniciar sesión <ArrowRight className="w-4 h-4" /></>
              }
            </Button>
          </form>

          {/* Divisor */}
          <div className="flex items-center gap-3 my-6">
            <div className="flex-1 h-px bg-border" />
            <span className="text-xs text-muted-foreground">o</span>
            <div className="flex-1 h-px bg-border" />
          </div>

          <p className="text-center text-sm text-muted-foreground">
            ¿No tienes cuenta?{' '}
            <Link to="/registro" className="text-primary font-semibold hover:underline underline-offset-2">
              Regístrate gratis
            </Link>
          </p>
        </div>
      </div>
    </div>
  )
}
