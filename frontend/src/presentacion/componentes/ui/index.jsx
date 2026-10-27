import { cn } from '@/lib/utils'
import leonMark from '@/assets/leon-mark.png'

/* ── Brand (logo del león) ────────────────────────────── */
/**
 * Silueta del león pintada con el degradado de marca.
 * variant: 'gradient' (verde) | 'solid' (color del texto) | 'contrast' (para fondos verdes)
 */
export function BrandMark({ className, variant = 'gradient' }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        'brand-mark inline-block shrink-0',
        variant === 'gradient' && 'grad-primary',
        variant === 'solid' && 'bg-foreground',
        variant === 'contrast' && 'bg-primary-foreground',
        className
      )}
      style={{ '--mark': `url(${leonMark})` }}
    />
  )
}

/** Logo completo: insignia + "LIFE FIT" */
export function BrandLogo({ size = 'md', sub, className }) {
  const s = {
    sm: { box: 'w-8 h-8 rounded-lg',   mark: 'w-7 h-7',   text: 'text-lg'  },
    md: { box: 'w-10 h-10 rounded-xl', mark: 'w-9 h-9',   text: 'text-2xl' },
    lg: { box: 'w-12 h-12 rounded-2xl', mark: 'w-11 h-11', text: 'text-3xl' },
  }[size]

  return (
    <div className={cn('flex items-center gap-3 min-w-0', className)}>
      <div className={cn(
        'relative flex items-center justify-center shrink-0 border border-primary/25 bg-primary/10 glow-sm overflow-hidden',
        s.box
      )}>
        <BrandMark className={s.mark} />
      </div>
      <div className="leading-none min-w-0">
        <span className={cn('font-display whitespace-nowrap text-foreground', s.text)} style={{ letterSpacing: '0.12em' }}>
          LIFE <span className="text-primary">FIT</span>
        </span>
        {sub && (
          <p className="text-[10px] text-muted-foreground uppercase tracking-[0.2em] mt-1">{sub}</p>
        )}
      </div>
    </div>
  )
}

/* ── Page Header ──────────────────────────────────────── */
export function PageHeader({ eyebrow, title, highlight, description, actions, className }) {
  return (
    <div className={cn('flex flex-wrap items-end justify-between gap-4', className)}>
      <div className="min-w-0">
        {eyebrow && (
          <p className="flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.2em] text-primary mb-2">
            <span className="w-1.5 h-1.5 rounded-full bg-primary animate-pulse" />
            {eyebrow}
          </p>
        )}
        <h1 className="font-display text-4xl md:text-5xl leading-[0.95] text-foreground">
          {title}{highlight && <> <span className="text-gradient">{highlight}</span></>}
        </h1>
        {description && <p className="text-sm text-muted-foreground mt-2 max-w-xl">{description}</p>}
      </div>
      {actions && <div className="flex items-center gap-2 shrink-0">{actions}</div>}
    </div>
  )
}

/* ── Card ─────────────────────────────────────────────── */
export function Card({ className, children, interactive = false, ...props }) {
  return (
    <div
      className={cn(
        'bg-card border border-border rounded-2xl p-5 relative overflow-hidden shadow-card',
        'transition-all duration-200',
        interactive && 'hover-lift hover:border-primary/25 cursor-pointer',
        className
      )}
      {...props}
    >
      {children}
    </div>
  )
}
export function CardHeader({ className, children }) {
  return <div className={cn('mb-4', className)}>{children}</div>
}
export function CardTitle({ className, children }) {
  return (
    <h3 className={cn('font-display text-xl uppercase leading-none', className)} style={{ letterSpacing: '0.06em' }}>
      {children}
    </h3>
  )
}
export function CardContent({ className, children }) {
  return <div className={cn('', className)}>{children}</div>
}

/* ── Button ───────────────────────────────────────────── */
const btnVariants = {
  default:     'bg-primary text-primary-foreground hover:brightness-110 glow font-bold tracking-wide btn-shine',
  secondary:   'bg-secondary text-secondary-foreground hover:bg-secondary/80 border border-border',
  ghost:       'hover:bg-secondary hover:text-foreground text-muted-foreground',
  destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
  outline:     'border border-border hover:border-primary/40 hover:bg-primary/5 text-foreground',
  fire:        'grad-fire text-white glow-fire font-bold tracking-wide btn-shine',
}
const btnSizes = {
  default: 'h-10 px-5 py-2 text-sm',
  sm:      'h-8 px-3 text-xs',
  lg:      'h-12 px-7 text-base',
  icon:    'h-10 w-10',
}

export function Button({ variant = 'default', size = 'default', className, disabled, children, ...props }) {
  return (
    <button
      className={cn(
        'inline-flex items-center justify-center gap-2 rounded-xl font-medium transition-all duration-150',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
        'disabled:opacity-50 disabled:pointer-events-none',
        'active:scale-[0.97]',
        btnVariants[variant],
        btnSizes[size],
        className
      )}
      disabled={disabled}
      {...props}
    >
      {children}
    </button>
  )
}

/* ── Badge ────────────────────────────────────────────── */
const badgeVariants = {
  default:   'bg-primary/15 text-primary border-primary/30',
  secondary: 'bg-secondary text-secondary-foreground border-border',
  success:   'bg-emerald-500/15 text-emerald-500 border-emerald-500/30',
  warning:   'bg-amber-500/15 text-amber-500 border-amber-500/30',
  danger:    'bg-red-500/15 text-red-500 border-red-500/30',
}
export function Badge({ variant = 'default', className, dot = false, children }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border uppercase tracking-wider',
        badgeVariants[variant],
        className
      )}
    >
      {dot && <span className="w-1.5 h-1.5 rounded-full bg-current" />}
      {children}
    </span>
  )
}

/* ── Input ────────────────────────────────────────────── */
export function Input({ className, ...props }) {
  return (
    <input
      className={cn(
        'flex h-11 w-full rounded-xl border border-input bg-secondary px-4 py-2',
        'text-sm text-foreground placeholder:text-muted-foreground',
        'hover:border-border',
        'focus:outline-none focus:ring-2 focus:ring-ring/60 focus:border-primary/50 focus:bg-card',
        'transition-all duration-150 disabled:opacity-50',
        className
      )}
      {...props}
    />
  )
}

/* ── Label ────────────────────────────────────────────── */
export function Label({ className, children, ...props }) {
  return (
    <label
      className={cn('text-xs font-semibold text-muted-foreground uppercase tracking-wider', className)}
      {...props}
    >
      {children}
    </label>
  )
}

/* ── Spinner ──────────────────────────────────────────── */
export function Spinner({ className }) {
  return (
    <div className={cn('w-5 h-5 border-2 border-primary/30 border-t-primary rounded-full animate-spin', className)} />
  )
}

/* ── Loader de página (con el león) ───────────────────── */
export function PageLoader({ label = 'Cargando...' }) {
  return (
    <div className="flex items-center justify-center h-full min-h-[70vh]">
      <div className="flex flex-col items-center gap-4">
        <div className="relative w-16 h-16 rounded-2xl bg-primary/10 border border-primary/25 flex items-center justify-center animate-pulse-glow">
          <BrandMark className="w-11 h-11" />
        </div>
        <p className="text-xs text-muted-foreground uppercase tracking-[0.25em]">{label}</p>
      </div>
    </div>
  )
}

/* ── Stat Card ────────────────────────────────────────── */
export function StatCard({ label, value, unit, sub, icon: Icon, className, accent = false }) {
  return (
    <Card
      className={cn(
        'group cursor-default hover-lift p-4 md:p-5',
        accent && 'border-primary/30 glow-sm',
        className
      )}
    >
      {/* Línea superior de acento */}
      <div className={cn(
        'absolute top-0 left-0 right-0 h-0.5 transition-all duration-300',
        accent ? 'grad-primary opacity-100' : 'grad-primary opacity-0 group-hover:opacity-100'
      )} />

      {/* Ícono gigante de fondo */}
      {Icon && (
        <Icon
          aria-hidden="true"
          className={cn(
            'absolute -right-3 -bottom-3 w-20 h-20 transition-all duration-300',
            accent ? 'text-primary/15' : 'text-foreground/[0.04] group-hover:text-primary/10'
          )}
          strokeWidth={1.5}
        />
      )}

      <div className="relative">
        <div className="flex items-center gap-2 mb-3">
          {Icon && (
            <div className={cn(
              'w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-all duration-200',
              accent ? 'grad-primary' : 'bg-primary/10 group-hover:bg-primary/20'
            )}>
              <Icon className={cn('w-4 h-4', accent ? 'text-primary-foreground' : 'text-primary')} />
            </div>
          )}
          <p className="text-[11px] text-muted-foreground uppercase tracking-wider font-semibold truncate">{label}</p>
        </div>
        <p className="font-display tabular text-4xl md:text-[2.75rem] leading-none animate-count-up whitespace-nowrap">
          {value}
          {unit && <span className="text-lg md:text-xl text-muted-foreground ml-1">{unit}</span>}
        </p>
        {sub && <p className="text-xs text-muted-foreground mt-1.5">{sub}</p>}
      </div>
    </Card>
  )
}

/* ── Progress Bar ─────────────────────────────────────── */
export function ProgressBar({ value = 0, max = 100, className, label, detail }) {
  const pct = max > 0 ? Math.min(100, Math.round((value / max) * 100)) : 0
  return (
    <div className={cn('space-y-1.5', className)}>
      {label && (
        <div className="flex items-center justify-between gap-2">
          <span className="text-xs text-muted-foreground uppercase tracking-wider">
            {label}
            {detail && <span className="normal-case tracking-normal text-foreground/70 ml-1.5 tabular">{detail}</span>}
          </span>
          <span className="text-xs font-bold text-primary tabular">{pct}%</span>
        </div>
      )}
      <div className="h-2 bg-secondary rounded-full overflow-hidden">
        <div
          className="h-full grad-primary rounded-full transition-all duration-700 ease-out relative"
          style={{ width: `${pct}%` }}
        >
          {pct > 0 && (
            <span className="absolute right-0 top-1/2 -translate-y-1/2 w-2 h-2 rounded-full bg-white/80 shadow-[0_0_8px_2px] shadow-primary/60" />
          )}
        </div>
      </div>
    </div>
  )
}

/* ── Progress Ring ────────────────────────────────────── */
export function ProgressRing({ value = 0, max = 100, size = 120, stroke = 10, children, className }) {
  const pct = max > 0 ? Math.min(1, value / max) : 0
  const r = (size - stroke) / 2
  const len = 2 * Math.PI * r
  return (
    <div className={cn('relative shrink-0', className)} style={{ width: size, height: size }}>
      <svg width={size} height={size} className="-rotate-90">
        <defs>
          <linearGradient id="lf-ring" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stopColor="var(--color-primary)" />
            <stop offset="100%" stopColor="hsl(180 100% 40%)" />
          </linearGradient>
        </defs>
        <circle cx={size / 2} cy={size / 2} r={r} fill="none" stroke="var(--color-secondary)" strokeWidth={stroke} />
        <circle
          cx={size / 2} cy={size / 2} r={r} fill="none"
          stroke="url(#lf-ring)" strokeWidth={stroke} strokeLinecap="round"
          strokeDasharray={len}
          strokeDashoffset={len * (1 - pct)}
          style={{
            '--ring-len': len,
            animation: 'ringFill 1.2s cubic-bezier(0.16, 1, 0.3, 1) both',
            filter: 'drop-shadow(0 0 6px color-mix(in srgb, var(--color-primary) 50%, transparent))',
          }}
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
        {children}
      </div>
    </div>
  )
}

/* ── Empty State ──────────────────────────────────────── */
export function EmptyState({ icon: Icon, title, description, action }) {
  return (
    <div className="flex flex-col items-center justify-center py-16 text-center gap-4">
      {Icon && (
        <div className="relative">
          <div className="absolute inset-0 rounded-3xl bg-primary/20 blur-2xl" />
          <div className="relative w-16 h-16 rounded-2xl bg-card border border-border shadow-card flex items-center justify-center">
            <Icon className="w-7 h-7 text-primary/70" />
          </div>
        </div>
      )}
      <div>
        <h3 className="font-display text-2xl text-foreground mb-1" style={{ letterSpacing: '0.05em' }}>{title}</h3>
        {description && <p className="text-sm text-muted-foreground max-w-xs">{description}</p>}
      </div>
      {action}
    </div>
  )
}

/* ── Select ───────────────────────────────────────────── */
export function Select({ className, children, ...props }) {
  return (
    <select
      className={cn(
        'flex h-11 w-full rounded-xl border border-input bg-secondary px-4 py-2',
        'text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-ring/60',
        'disabled:opacity-50 cursor-pointer',
        className
      )}
      {...props}
    >
      {children}
    </select>
  )
}

/* ── Divider ──────────────────────────────────────────── */
export function Divider({ label, className }) {
  return (
    <div className={cn('flex items-center gap-3', className)}>
      <div className="flex-1 h-px bg-gradient-to-r from-transparent to-border" />
      {label && <span className="text-xs text-muted-foreground uppercase tracking-widest">{label}</span>}
      <div className="flex-1 h-px bg-gradient-to-l from-transparent to-border" />
    </div>
  )
}
