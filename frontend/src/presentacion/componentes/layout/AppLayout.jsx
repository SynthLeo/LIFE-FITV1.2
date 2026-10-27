import { NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '@/context/AuthContext'
import {
  LayoutDashboard, Dumbbell, BookOpen, BarChart2,
  Users, ListChecks, LogOut,
  ClipboardList, Brain, Salad, Stethoscope, ChevronRight, HeartPulse
} from 'lucide-react'
import { cn } from '@/lib/utils'
import ThemeToggle from '@/presentacion/componentes/ThemeToggle'
import { BrandLogo } from '@/presentacion/componentes/ui'

const navGroups = [
  {
    label: 'Entrenamiento',
    items: [
      { to: '/dashboard',    label: 'Panel',        icon: LayoutDashboard },
      { to: '/catalogo',     label: 'Catálogo',     icon: BookOpen        },
      { to: '/rutinas',      label: 'Rutinas',      icon: ListChecks      },
      { to: '/entrena',      label: 'Entrenar',     icon: Dumbbell        },
      { to: '/estadisticas', label: 'Estadísticas', icon: BarChart2       },
      { to: '/social',       label: 'Social',       icon: Users           },
      { to: '/cuestionario', label: 'Cuestionario', icon: ClipboardList   },
    ],
  },
  {
    label: 'Salud Integral',
    items: [
      { to: '/salud',      label: 'Mi salud',   icon: HeartPulse,  color: '#f43f5e' },
      { to: '/clinico',    label: 'Clínico',    icon: Stethoscope, color: '#0ea5e9' },
      { to: '/dieta',      label: 'Nutrición',  icon: Salad,       color: '#22c55e' },
      { to: '/psicologia', label: 'Psicología', icon: Brain,       color: '#a78bfa' },
    ],
  },
]

const mobileNav = [
  { to: '/dashboard',  icon: LayoutDashboard, label: 'Panel'   },
  { to: '/entrena',    icon: Dumbbell,        label: 'Entrena' },
  { to: '/rutinas',    icon: ListChecks,      label: 'Rutinas' },
  { to: '/salud',      icon: HeartPulse,      label: 'Salud'   },
  { to: '/psicologia', icon: Brain,           label: 'Mente'   },
]

export default function AppLayout({ children }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const handleLogout = () => { logout(); navigate('/login') }

  const initials = user?.nombre
    ?.split(' ')
    .map(n => n[0])
    .slice(0, 2)
    .join('')
    .toUpperCase() ?? 'LF'

  return (
    <div className="flex h-screen overflow-hidden bg-background app-ambient">

      {/* ── Sidebar desktop ───────────────────────────── */}
      <aside className="hidden md:flex flex-col w-64 border-r border-border shrink-0 relative bg-card/80 backdrop-blur-xl">
        {/* Luz de marca arriba */}
        <div
          className="absolute -top-24 -left-24 w-72 h-72 rounded-full pointer-events-none"
          style={{ background: 'radial-gradient(circle, color-mix(in srgb, var(--color-primary) 14%, transparent) 0%, transparent 70%)' }}
        />

        {/* Logo */}
        <div className="relative flex items-center justify-between gap-2 px-5 py-5 border-b border-border">
          <BrandLogo size="md" sub="Pro" />
          <ThemeToggle />
        </div>

        {/* Nav groups */}
        <nav className="relative flex-1 px-3 py-5 overflow-y-auto space-y-6">
          {navGroups.map(group => (
            <div key={group.label}>
              <p className="flex items-center gap-2 text-[10px] font-bold text-muted-foreground/60 uppercase tracking-[0.2em] px-3 mb-2">
                {group.label}
                <span className="flex-1 h-px bg-border" />
              </p>
              <div className="space-y-0.5">
                {group.items.map(({ to, label, icon: Icon, color }) => (
                  <NavLink
                    key={to}
                    to={to}
                    className={({ isActive }) => cn(
                      'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm transition-all duration-150 group relative',
                      isActive
                        ? 'bg-primary/10 text-primary font-semibold border border-primary/20 shadow-[0_0_20px_-8px] shadow-primary/50'
                        : 'text-muted-foreground hover:text-foreground hover:bg-secondary/80 hover:translate-x-0.5 border border-transparent'
                    )}
                  >
                    {({ isActive }) => (
                      <>
                        {isActive && (
                          <div className="absolute -left-3 top-1/2 -translate-y-1/2 w-1 h-6 grad-primary rounded-r-full glow-sm" />
                        )}
                        <div className={cn(
                          'w-7 h-7 rounded-lg flex items-center justify-center shrink-0 transition-all duration-150',
                          isActive ? 'grad-primary' : 'bg-secondary group-hover:bg-primary/10'
                        )}>
                          <Icon
                            className={cn(
                              'w-3.5 h-3.5 transition-colors',
                              isActive ? 'text-primary-foreground' : 'text-muted-foreground group-hover:text-primary'
                            )}
                            style={!isActive && color ? { color } : undefined}
                          />
                        </div>
                        <span className="flex-1">{label}</span>
                        {isActive && <ChevronRight className="w-3.5 h-3.5 opacity-60" />}
                      </>
                    )}
                  </NavLink>
                ))}
              </div>
            </div>
          ))}
        </nav>

        {/* User footer */}
        <div className="relative px-3 pb-4 border-t border-border pt-3 space-y-1">
          <div className="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-secondary/60 border border-border">
            <div className="relative">
              <div className="w-9 h-9 rounded-xl grad-primary flex items-center justify-center text-primary-foreground font-bold text-xs shrink-0">
                {initials}
              </div>
              <span className="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-primary border-2 border-card" />
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-semibold truncate">{user?.nombre ?? 'Usuario'}</p>
              <p className="text-[10px] text-muted-foreground uppercase tracking-wider">En línea</p>
            </div>
          </div>
          <button
            onClick={handleLogout}
            className="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm text-muted-foreground hover:text-destructive hover:bg-destructive/10 transition-all duration-150"
          >
            <LogOut className="w-4 h-4" />
            Cerrar sesión
          </button>
        </div>
      </aside>

      {/* ── Mobile top bar ────────────────────────────── */}
      <div className="md:hidden fixed top-0 left-0 right-0 z-50 glass border-b border-border px-4 h-14 flex items-center justify-between">
        <BrandLogo size="sm" />
        <div className="flex items-center gap-2.5">
          <ThemeToggle />
          <div className="w-8 h-8 rounded-lg grad-primary flex items-center justify-center text-primary-foreground font-bold text-[10px]">
            {initials}
          </div>
        </div>
      </div>

      {/* ── Main content ──────────────────────────────── */}
      <main className="flex-1 overflow-y-auto md:pt-0 pt-14 pb-24 md:pb-0">
        {children}
      </main>

      {/* ── Mobile bottom nav (flotante) ──────────────── */}
      <nav className="md:hidden fixed bottom-3 left-3 right-3 glass border border-border rounded-2xl z-50 shadow-card">
        <div className="grid grid-cols-5 h-16">
          {mobileNav.map(({ to, icon: Icon, label }) => (
            <NavLink
              key={to}
              to={to}
              className={({ isActive }) => cn(
                'flex flex-col items-center justify-center gap-1 transition-all duration-150',
                isActive ? 'text-primary' : 'text-muted-foreground'
              )}
            >
              {({ isActive }) => (
                <>
                  <div className={cn(
                    'w-10 h-7 rounded-xl flex items-center justify-center transition-all duration-200',
                    isActive ? 'grad-primary glow-sm scale-105' : ''
                  )}>
                    <Icon className={cn('w-4 h-4', isActive ? 'text-primary-foreground' : '')} />
                  </div>
                  <span className="text-[9px] uppercase tracking-wider font-semibold">{label}</span>
                </>
              )}
            </NavLink>
          ))}
        </div>
      </nav>
    </div>
  )
}
