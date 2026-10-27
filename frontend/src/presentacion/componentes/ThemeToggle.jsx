import { Moon, Sun } from 'lucide-react'
import { useTheme } from '@/context/ThemeContext'
import { cn } from '@/lib/utils'

export default function ThemeToggle({ className = '' }) {
  const { theme, toggle } = useTheme()
  const isDark = theme === 'dark'

  return (
    <button
      onClick={toggle}
      aria-label={isDark ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'}
      title={isDark ? 'Tema claro' : 'Tema oscuro'}
      className={cn(
        'relative w-14 h-7 rounded-full border border-border bg-secondary shrink-0',
        'transition-colors duration-300 cursor-pointer hover:border-primary/40',
        className
      )}
    >
      {/* Íconos del riel */}
      <Moon className="absolute left-1.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground pointer-events-none" />
      <Sun  className="absolute right-1.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground pointer-events-none" />

      {/* Perilla */}
      <span
        className={cn(
          'absolute top-0.5 w-6 h-6 rounded-full flex items-center justify-center',
          'transition-all duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] shadow-md',
          isDark ? 'left-0.5 grad-primary' : 'left-[calc(100%-26px)] bg-card border border-border'
        )}
      >
        {isDark
          ? <Moon className="w-3.5 h-3.5 text-primary-foreground" />
          : <Sun  className="w-3.5 h-3.5 text-amber-500" />}
      </span>
    </button>
  )
}
