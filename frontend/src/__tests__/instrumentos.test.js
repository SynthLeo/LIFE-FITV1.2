import { describe, it, expect } from 'vitest'
import {
  EJERCICIO, PSICOLOGIA, evaluarEjercicio, evaluarPsicologia, corteAuditC,
} from '@/modulos/cuestionarios/instrumentos'

// Arma respuestas eligiendo la opción N (0 = primera, -1 = última) de cada pregunta
const responder = (preguntas, filtro, idx) => Object.fromEntries(
  preguntas.filter(p => filtro(p.id)).map(p => [p.id, p.opts.at(idx).val]),
)
const psico = idx => responder(PSICOLOGIA, id => id.startsWith('phq_') || id.startsWith('gad_'), idx)

describe('PHQ-8 y GAD-7', () => {
  it('todo en "ningún día" → mínimo, sin alertas', () => {
    const r = evaluarPsicologia(psico(0))
    expect(r).toMatchObject({ phq8: 0, gad7: 0, score: 100, nivel: 'resiliente', sugerirConsulta: false, mostrarLinea: false })
  })

  it('todo en "casi todos los días" → máximos 24 y 21, muestra la Línea de la Vida', () => {
    const r = evaluarPsicologia(psico(-1))
    expect(r).toMatchObject({ phq8: 24, gad7: 21, score: 0, nivel: 'critico', mostrarLinea: true })
  })

  it('corte de 10 sugiere consulta pero todavía no muestra la línea', () => {
    const a = { ...psico(0), phq_interes: '3', phq_animo: '3', phq_sueno: '3', phq_energia: '1' } // PHQ-8 = 10
    const r = evaluarPsicologia(a)
    expect(r.phq8).toBe(10)
    expect(r.sugerirConsulta).toBe(true)
    expect(r.mostrarLinea).toBe(false)
  })
})

describe('AUDIT-C: punto de corte', () => {
  it('menores de 18 → 1', () => expect(corteAuditC({ sexo: 'masculino', edad: 16 })).toBe(1))
  it('hombres → 4', () => expect(corteAuditC({ sexo: 'masculino', edad: 20 })).toBe(4))
  it('mujeres → 3', () => expect(corteAuditC({ sexo: 'femenino', edad: 20 })).toBe(3))
})

describe('IPAQ + PAR-Q+', () => {
  const ipaqMax = responder(EJERCICIO, id => id.startsWith('ipaq_'), -1)
  const parqNo  = responder(EJERCICIO, id => id.startsWith('parq_'), 0)

  it('sin actividad → bajo y principiante', () => {
    const r = evaluarEjercicio({ ...parqNo, ...responder(EJERCICIO, id => id.startsWith('ipaq_'), 0) })
    expect(r).toMatchObject({ met: 0, categoria: 'bajo', nivel: 'principiante', parqResultado: 'apto' })
  })

  it('actividad máxima → alto y avanzado', () => {
    const r = evaluarEjercicio({ ...parqNo, ...ipaqMax })
    expect(r.categoria).toBe('alto')
    expect(r.nivel).toBe('avanzado')
  })

  it('un "sí" en el PAR-Q+ → consultar y baja a principiante aunque entrene mucho', () => {
    const primeraParq = EJERCICIO.find(p => p.id.startsWith('parq_')).id
    const r = evaluarEjercicio({ ...parqNo, ...ipaqMax, [primeraParq]: 'si' })
    expect(r.parqResultado).toBe('consultar')
    expect(r.nivel).toBe('principiante')
  })
})

// ── Casos límite pedidos en TAREAS (Q1) ──
import { ALIMENTACION, CLINICO, visibles, evaluarAlimentacion, evaluarClinico, payloadClinico, payloadEjercicio } from '@/modulos/cuestionarios/instrumentos'

// Arma respuestas PHQ-8 con un total exacto (reparte puntos de 3 en 3)
function phqCon(total) {
  const ids = PSICOLOGIA.filter(p => p.id.startsWith('phq_')).map(p => p.id)
  const a = { ...psico(0) }
  let resto = total
  for (const id of ids) { const v = Math.min(3, resto); a[id] = String(v); resto -= v }
  return a
}

describe('PHQ-8: bordes de severidad', () => {
  it.each([
    [4, 'Mínimo'], [5, 'Leve'], [9, 'Leve'], [10, 'Moderado'], [14, 'Moderado'], [15, 'Moderadamente severo'], [19, 'Moderadamente severo'], [20, 'Severo'],
  ])('PHQ-8 = %i → %s', (total, banda) => {
    const r = evaluarPsicologia(phqCon(total))
    expect(r.phq8).toBe(total)
    expect(r.phqBanda.label).toBe(banda)
  })
})

describe('IPAQ: días sin minutos no suman', () => {
  it('días de actividad vigorosa sin decir minutos → 0 MET de vigorosa', () => {
    const vig = EJERCICIO.find(p => p.id === 'ipaq_vig_dias').opts.at(-1).val
    const r = evaluarEjercicio({ ipaq_vig_dias: vig })
    expect(r.detalle.metVig).toBe(0)
  })
})

describe('Alimentación (CDMX)', () => {
  const peor  = Object.fromEntries(ALIMENTACION.map(p => [p.id, [...p.opts].sort((a, b) => (a.pts ?? 0) - (b.pts ?? 0))[0].val]))
  const mejor = Object.fromEntries(ALIMENTACION.map(p => [p.id, [...p.opts].sort((a, b) => (b.pts ?? 0) - (a.pts ?? 0))[0].val]))
  it('peores respuestas → deficiente / inadecuada', () => {
    expect(evaluarAlimentacion(peor)).toMatchObject({ nivel: 'deficiente', clasificacion: 'Inadecuada' })
  })
  it('mejores respuestas → óptimo con el máximo de puntos', () => {
    const r = evaluarAlimentacion(mejor)
    expect(r.puntos).toBe(r.max)
    expect(r.nivel).toBe('optimo')
  })
})

describe('Preguntas condicionales', () => {
  it('visibles() oculta los minutos si dijo 0 días', () => {
    const ids = visibles(EJERCICIO, { ipaq_vig_dias: '0' }).map(p => p.id)
    expect(ids).not.toContain('ipaq_vig_min')
  })

  it('payload manda null en las preguntas que ya no aplican', () => {
    // Contestó minutos y luego cambió a 0 días: la respuesta vieja no se debe mandar
    const vigMin = EJERCICIO.find(p => p.id === 'ipaq_vig_min').opts.at(-1).val
    const p = payloadEjercicio({ ipaq_vig_dias: '0', ipaq_vig_min: vigMin })
    expect(p.ipaq_vig_min ?? null).toBeNull()
  })

  it('payload clínico: preguntas ocultas de alcohol van en null', () => {
    const ocultas = CLINICO.filter(q => q.showIf && !q.showIf({}))
    expect(ocultas.length).toBeGreaterThan(0)
    const resp = Object.fromEntries(ocultas.map(q => [q.id, q.opts.at(-1).val]))
    const p = payloadClinico({ ...resp, audit_frecuencia: CLINICO.find(q => q.id === 'audit_frecuencia')?.opts[0].val })
    for (const q of ocultas) if (!q.showIf({ audit_frecuencia: CLINICO.find(x => x.id === 'audit_frecuencia')?.opts[0].val })) expect(p[q.id] ?? null).toBeNull()
  })

  it('AUDIT-C usa el corte de menores aunque el puntaje sea bajo', () => {
    const r = evaluarClinico({}, { sexo: 'femenino', edad: 16 })
    expect(r.auditC).toBe(0)
  })
})
