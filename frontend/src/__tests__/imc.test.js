import { describe, it, expect } from 'vitest'
import { calcIMC, edadDesde } from '@/lib/imc'

describe('IMC adultos (OMS)', () => {
  it.each([
    [50, 170, 'bajo'], [70, 170, 'normal'], [80, 170, 'sobrepeso'], [95, 170, 'obesidad'],
  ])('%i kg / %i cm → %s', (peso, altura, nivel) => {
    expect(calcIMC(peso, altura, { edad: 25 }).nivel).toBe(nivel)
  })

  it('datos incompletos → null', () => {
    expect(calcIMC('', 170)).toBeNull()
    expect(calcIMC(70, 50)).toBeNull()
  })
})

describe('IMC menores (percentiles CDC)', () => {
  it('IMC 25 es obesidad en adulto pero sobrepeso a los 15 años (hombre)', () => {
    // 72.25 kg / 170 cm = IMC 25.0
    expect(calcIMC(72.25, 170, { edad: 30 }).nivel).toBe('sobrepeso')
    const r = calcIMC(72.25, 170, { edad: 15.5, sexo: 'masculino' })
    expect(r.metodo).toBe('percentil')
    expect(r.nivel).toBe('sobrepeso')
  })

  it('IMC 17 es bajo peso en adulto pero normal a los 12 años (mujer)', () => {
    expect(calcIMC(49.13, 170, { edad: 30 }).nivel).toBe('bajo')
    expect(calcIMC(49.13, 170, { edad: 12, sexo: 'femenino' }).nivel).toBe('normal')
  })

  it('menor sin sexo masculino/femenino → sin referencia, no inventa un nivel', () => {
    expect(calcIMC(60, 160, { edad: 14, sexo: 'otro' })).toMatchObject({ metodo: 'sin_referencia', nivel: null })
  })
})

describe('edadDesde', () => {
  it('cuenta años cumplidos', () => {
    const hoy = new Date()
    const d = new Date(hoy.getFullYear() - 15, hoy.getMonth(), hoy.getDate() + 1)
    const f = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
    expect(edadDesde(f)).toBe(14) // el cumpleaños es mañana
  })
  it('fecha vacía o inválida → null', () => {
    expect(edadDesde('')).toBeNull()
    expect(edadDesde('no-es-fecha')).toBeNull()
  })
})
