// src/lib/imc.js
// IMC con la clasificación correcta según la edad:
//  - 18 años o más: cortes de la OMS para adultos (18.5 / 25 / 30).
//  - 5 a 17 años: percentiles de IMC para la edad y sexo (CDC 2000):
//      < P5 bajo peso · P5–P85 normal · P85–P95 sobrepeso · ≥ P95 obesidad.
// Tablas: P5, P85 y P95 a mitad de cada año de edad (CDC, bmiage.csv), interpoladas por mes.

// [edad, P5, P85, P95]
const CDC = {
  masculino: [[5, 13.8, 16.8, 17.9], [6, 13.7, 17.0, 18.4], [7, 13.7, 17.4, 19.2], [8, 13.8, 18.0, 20.1], [9, 14.0, 18.6, 21.1], [10, 14.2, 19.4, 22.2], [11, 14.6, 20.2, 23.2], [12, 15.0, 21.0, 24.2], [13, 15.5, 21.9, 25.2], [14, 16.0, 22.7, 26.0], [15, 16.6, 23.5, 26.8], [16, 17.1, 24.2, 27.6], [17, 17.7, 24.9, 28.3], [18, 18.2, 25.7, 29.0]],
  femenino:  [[5, 13.5, 16.8, 18.3], [6, 13.4, 17.1, 18.8], [7, 13.4, 17.6, 19.7], [8, 13.5, 18.3, 20.7], [9, 13.7, 19.1, 21.8], [10, 14.0, 20.0, 23.0], [11, 14.4, 20.9, 24.1], [12, 14.8, 21.7, 25.3], [13, 15.3, 22.6, 26.3], [14, 15.8, 23.3, 27.3], [15, 16.3, 24.0, 28.1], [16, 16.8, 24.7, 28.9], [17, 17.2, 25.2, 29.6], [18, 17.6, 25.7, 30.3]],
}

const NIVELES = {
  bajo:      { label: 'Bajo peso', color: '#60a5fa' },
  normal:    { label: 'Normal',    color: '#22c55e' },
  sobrepeso: { label: 'Sobrepeso', color: '#eab308' },
  obesidad:  { label: 'Obesidad',  color: '#ef4444' },
}

/** Edad en años cumplidos (con decimales si se pide) a partir de 'YYYY-MM-DD'. */
export function edadDesde(fecha, { decimales = false } = {}) {
  if (!fecha) return null
  const n = new Date(fecha + 'T00:00:00')
  if (Number.isNaN(n.getTime())) return null
  const hoy = new Date()
  let meses = (hoy.getFullYear() - n.getFullYear()) * 12 + (hoy.getMonth() - n.getMonth())
  if (hoy.getDate() < n.getDate()) meses -= 1
  return decimales ? meses / 12 : Math.floor(meses / 12)
}

function cortesMenor(edad, sexo) {
  const t = CDC[sexo]
  if (!t) return null
  const e = Math.min(Math.max(edad, 5), 18)
  const i = Math.min(Math.floor(e) - 5, t.length - 2)
  const [a0, ...c0] = t[i]
  const [, ...c1] = t[i + 1]
  const f = Math.min(Math.max(e - a0, 0), 1)
  return c0.map((v, k) => v + (c1[k] - v) * f)
}

/**
 * peso en kg, altura en cm. edad (años, puede tener decimales) y sexo opcionales.
 * Devuelve { value, label, color, nivel, metodo } o null.
 *   metodo: 'adulto' | 'percentil' | 'sin_referencia' (menor sin sexo masculino/femenino)
 */
export function calcIMC(peso, altura, { edad = null, sexo = null } = {}) {
  peso = Number(peso)
  altura = Number(altura)
  if (!peso || !altura || altura < 100) return null
  const imc = peso / (altura / 100) ** 2
  const value = imc.toFixed(1)

  let nivel, metodo
  if (edad == null || edad >= 18) {
    metodo = 'adulto'
    nivel = imc < 18.5 ? 'bajo' : imc < 25 ? 'normal' : imc < 30 ? 'sobrepeso' : 'obesidad'
  } else {
    const c = cortesMenor(edad, sexo)
    if (!c) return { value, label: 'Consulta con tu médico', color: '#94a3b8', nivel: null, metodo: 'sin_referencia' }
    const [p5, p85, p95] = c
    metodo = 'percentil'
    nivel = imc < p5 ? 'bajo' : imc < p85 ? 'normal' : imc < p95 ? 'sobrepeso' : 'obesidad'
  }
  return { value, ...NIVELES[nivel], nivel, metodo }
}
