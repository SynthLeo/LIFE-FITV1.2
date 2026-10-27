import { BrandLogo, BrandMark } from '@/presentacion/componentes/ui'

/**
 * Panel izquierdo de Login / Registro.
 * Muestra el león de marca, el titular y lo que se pase como children.
 */
export default function AuthShowcase({ title, highlight, description, children }) {
  return (
    <div className="hidden lg:flex flex-col justify-between w-[48%] relative overflow-hidden p-12 border-r border-border">
      {/* Fondo */}
      <div className="absolute inset-0 bg-card" />
      <div className="absolute inset-0 diagonal-lines" />
      <div
        className="absolute -top-40 -left-40 w-[520px] h-[520px] rounded-full pointer-events-none"
        style={{ background: 'radial-gradient(circle, color-mix(in srgb, var(--color-primary) 16%, transparent) 0%, transparent 70%)' }}
      />
      <div
        className="absolute -bottom-48 -right-24 w-[440px] h-[440px] rounded-full pointer-events-none"
        style={{ background: 'radial-gradient(circle, hsl(185 100% 40% / 0.10) 0%, transparent 70%)' }}
      />

      {/* León gigante de fondo */}
      <div className="absolute -right-24 top-1/2 -translate-y-1/2 pointer-events-none select-none animate-float">
        <div className="absolute inset-10 rounded-full bg-primary/20 blur-[90px]" />
        <BrandMark className="relative w-[440px] h-[440px] opacity-[0.12]" />
      </div>

      {/* Logo */}
      <BrandLogo size="lg" className="relative" />

      {/* Contenido */}
      <div className="relative space-y-8 max-w-md">
        <div className="space-y-4">
          <span className="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-primary/25 bg-primary/10 text-[11px] font-semibold uppercase tracking-[0.2em] text-primary">
            <span className="w-1.5 h-1.5 rounded-full bg-primary animate-pulse" />
            Bienestar integral
          </span>
          <p className="font-display text-7xl leading-[0.9] text-foreground">
            {title}<br />
            <span className="text-gradient animate-shimmer-text">{highlight}</span>
          </p>
          <p className="text-muted-foreground text-base max-w-sm leading-relaxed">{description}</p>
        </div>
        {children}
      </div>

      <p className="relative text-xs text-muted-foreground/50">
        © {new Date().getFullYear()} Life Fit. Todos los derechos reservados.
      </p>
    </div>
  )
}
