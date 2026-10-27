import { useCallback, useEffect, useRef, useState } from 'react'
import { ChevronLeft, ChevronRight, Pause, Play } from 'lucide-react'
import { cn } from '@/lib/utils'

const prefiereMenosMovimiento = () =>
  typeof window !== 'undefined' &&
  window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

/**
 * Carrusel de slides completos.
 * - Autoplay (se pausa al pasar el mouse, al enfocar o con el botón)
 * - Swipe en celular, flechas, puntitos y teclado (← →)
 *
 * slides: [{ id, color, content: <JSX/> }]
 */
export function HeroCarousel({ slides, interval = 6500, className, label = 'Carrusel' }) {
  const [index, setIndex]   = useState(0)
  const [paused, setPaused] = useState(prefiereMenosMovimiento)
  const [hover, setHover]   = useState(false)
  const [drag, setDrag]     = useState(0)
  const startX = useRef(null)
  const total  = slides.length

  const go   = useCallback((i) => setIndex(((i % total) + total) % total), [total])
  const next = useCallback(() => go(index + 1), [go, index])
  const prev = useCallback(() => go(index - 1), [go, index])

  // Autoplay
  const corriendo = !paused && !hover && total > 1
  useEffect(() => {
    if (!corriendo) return
    const t = setTimeout(next, interval)
    return () => clearTimeout(t)
  }, [corriendo, next, interval, index])

  // Swipe
  const onPointerDown = (e) => { startX.current = e.clientX }
  const onPointerMove = (e) => {
    if (startX.current === null) return
    setDrag(e.clientX - startX.current)
  }
  const onPointerUp = () => {
    if (startX.current === null) return
    if (drag < -50) next()
    else if (drag > 50) prev()
    startX.current = null
    setDrag(0)
  }

  const onKeyDown = (e) => {
    if (e.key === 'ArrowRight') { e.preventDefault(); next() }
    if (e.key === 'ArrowLeft')  { e.preventDefault(); prev() }
  }

  const color = slides[index]?.color ?? 'var(--color-primary)'

  return (
    <section
      aria-roledescription="carrusel"
      aria-label={label}
      tabIndex={0}
      onKeyDown={onKeyDown}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => { setHover(false); onPointerUp() }}
      className={cn(
        'relative rounded-3xl border border-border bg-card shadow-card overflow-hidden select-none',
        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
        className
      )}
      style={{ borderColor: `color-mix(in srgb, ${color} 30%, var(--color-border))`, transition: 'border-color 500ms ease' }}
    >
      {/* Luz del color del slide actual */}
      <div
        className="absolute inset-0 pointer-events-none transition-[background] duration-700"
        style={{ background: `radial-gradient(ellipse at 85% 20%, color-mix(in srgb, ${color} 20%, transparent), transparent 60%)` }}
      />
      <div className="absolute inset-0 diagonal-lines pointer-events-none opacity-60" />

      {/* Pista */}
      <div
        className="relative flex touch-pan-y"
        onPointerDown={onPointerDown}
        onPointerMove={onPointerMove}
        onPointerUp={onPointerUp}
        onPointerCancel={onPointerUp}
        style={{
          transform: `translateX(calc(${-index * 100}% + ${drag}px))`,
          transition: drag ? 'none' : 'transform 650ms cubic-bezier(0.16, 1, 0.3, 1)',
        }}
      >
        {slides.map((s, i) => (
          <div
            key={s.id}
            role="group"
            aria-roledescription="slide"
            aria-label={`${i + 1} de ${total}`}
            aria-hidden={i !== index}
            inert={i !== index}
            className="w-full shrink-0"
            style={{ '--slide': s.color }}
          >
            <div className={cn('transition-opacity duration-500', i === index ? 'opacity-100' : 'opacity-0')}>
              {s.content}
            </div>
          </div>
        ))}
      </div>

      {/* Controles */}
      <div className="relative flex items-center justify-between gap-3 px-5 md:px-8 pb-5 md:pb-6">
        {/* Puntitos con barra de progreso */}
        <div className="flex items-center gap-2" role="tablist" aria-label="Elegir slide">
          {slides.map((s, i) => (
            <button
              key={s.id}
              role="tab"
              aria-selected={i === index}
              aria-label={`Ir al slide ${i + 1}`}
              onClick={() => go(i)}
              className={cn(
                'relative h-2 rounded-full overflow-hidden transition-all duration-500 bg-foreground/15 hover:bg-foreground/25',
                i === index ? 'w-10' : 'w-2'
              )}
            >
              {i === index && (
                <span
                  key={`${index}-${corriendo}`}
                  className="absolute inset-y-0 left-0 rounded-full"
                  style={{
                    background: s.color,
                    width: corriendo ? undefined : '100%',
                    animation: corriendo ? `carouselProgress ${interval}ms linear forwards` : 'none',
                  }}
                />
              )}
            </button>
          ))}
        </div>

        <div className="flex items-center gap-1.5">
          <button
            onClick={() => setPaused(p => !p)}
            aria-label={paused ? 'Reanudar carrusel' : 'Pausar carrusel'}
            className="w-9 h-9 rounded-xl flex items-center justify-center text-muted-foreground hover:text-foreground hover:bg-secondary transition-colors"
          >
            {paused ? <Play className="w-3.5 h-3.5" /> : <Pause className="w-3.5 h-3.5" />}
          </button>
          <ArrowBtn onClick={prev} label="Anterior"><ChevronLeft className="w-4 h-4" /></ArrowBtn>
          <ArrowBtn onClick={next} label="Siguiente"><ChevronRight className="w-4 h-4" /></ArrowBtn>
        </div>
      </div>
    </section>
  )
}

function ArrowBtn({ onClick, label, children }) {
  return (
    <button
      onClick={onClick}
      aria-label={label}
      className="w-9 h-9 rounded-xl border border-border bg-secondary/70 flex items-center justify-center text-foreground hover:border-primary/40 hover:text-primary active:scale-95 transition-all"
    >
      {children}
    </button>
  )
}

/**
 * Carrusel horizontal de tarjetas (scroll-snap).
 * Se desliza con el dedo / trackpad y tiene flechas en escritorio.
 */
export function CardCarousel({ title, children, className }) {
  const ref = useRef(null)
  const [edges, setEdges] = useState({ start: true, end: false })

  const update = useCallback(() => {
    const el = ref.current
    if (!el) return
    setEdges({
      start: el.scrollLeft <= 4,
      end:   el.scrollLeft + el.clientWidth >= el.scrollWidth - 4,
    })
  }, [])

  useEffect(() => {
    update()
    window.addEventListener('resize', update)
    return () => window.removeEventListener('resize', update)
  }, [update])

  const scroll = (dir) => {
    const el = ref.current
    if (!el) return
    el.scrollBy({ left: dir * el.clientWidth * 0.8, behavior: 'smooth' })
  }

  return (
    <div className={cn('space-y-3', className)}>
      <div className="flex items-center justify-between gap-3">
        {title}
        <div className="hidden sm:flex items-center gap-1.5">
          <ArrowBtnSmall disabled={edges.start} onClick={() => scroll(-1)} label="Anterior"><ChevronLeft className="w-4 h-4" /></ArrowBtnSmall>
          <ArrowBtnSmall disabled={edges.end}   onClick={() => scroll(1)}  label="Siguiente"><ChevronRight className="w-4 h-4" /></ArrowBtnSmall>
        </div>
      </div>

      <div className="relative">
        {/* Desvanecidos en las orillas */}
        <div className={cn('pointer-events-none absolute left-0 top-0 bottom-0 w-10 z-10 bg-gradient-to-r from-background to-transparent transition-opacity', edges.start && 'opacity-0')} />
        <div className={cn('pointer-events-none absolute right-0 top-0 bottom-0 w-10 z-10 bg-gradient-to-l from-background to-transparent transition-opacity', edges.end && 'opacity-0')} />

        <div
          ref={ref}
          onScroll={update}
          className="flex gap-3 overflow-x-auto snap-x snap-mandatory scroll-smooth pb-2 -mx-1 px-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          {children}
        </div>
      </div>
    </div>
  )
}

function ArrowBtnSmall({ onClick, label, disabled, children }) {
  return (
    <button
      onClick={onClick}
      aria-label={label}
      disabled={disabled}
      className="w-8 h-8 rounded-lg border border-border bg-card flex items-center justify-center text-foreground hover:border-primary/40 hover:text-primary disabled:opacity-30 disabled:pointer-events-none transition-all"
    >
      {children}
    </button>
  )
}
