// src/lib/ia.js
// Llamadas de IA de la app. TODAS pasan por el backend PHP, que habla con LM Studio:
// así el modelo, los prompts, la detección de crisis y el límite de peticiones
// viven en el servidor y el navegador nunca llama directo a LM Studio.

import api from '@/lib/api'

// Un modelo local de 4B en una laptop puede tardar: se da margen.
const TIMEOUT_IA = 240_000

function mensajeError(e, porDefecto) {
  if (e?.code === 'ECONNABORTED') return 'La IA tardó demasiado en responder. Intenta de nuevo.'
  return e?.response?.data?.error ?? porDefecto
}

/**
 * Genera el plan semanal de comidas.
 * - respuestas: respuestas del cuestionario de alimentación (si aún no se guardan). Sin ellas usa el perfil guardado.
 * - guardar: true para que el servidor lo guarde en el perfil.
 * Devuelve { plan, guardado, kcal_objetivo }.
 */
export async function generarDieta({ respuestas, guardar = false } = {}) {
  try {
    const r = await api.post('/IA/GenerarDieta.php', { respuestas, guardar }, { timeout: TIMEOUT_IA })
    return r.data
  } catch (e) {
    throw new Error(mensajeError(e, 'No se pudo generar el plan. Intenta de nuevo.'), { cause: e })
  }
}

/** Diagnóstico del onboarding a partir de los cuestionarios ya guardados. */
export async function generarDiagnostico() {
  try {
    const r = await api.post('/IA/Diagnostico.php', {}, { timeout: TIMEOUT_IA })
    return r.data.diagnostico
  } catch (e) {
    throw new Error(mensajeError(e, 'No se pudo generar el diagnóstico.'), { cause: e })
  }
}

/** Genera la rutina de ejercicio con el perfil guardado. */
export async function generarRutina() {
  try {
    const r = await api.post('/IA/GenerarRutina.php', {}, { timeout: TIMEOUT_IA })
    return r.data
  } catch (e) {
    throw new Error(mensajeError(e, 'No se pudo generar la rutina.'), { cause: e })
  }
}
