// src/modulos/cuestionarios/instrumentos.js
//
// Preguntas de los 4 cuestionarios de LIFE FIT, tomadas de instrumentos validados.
// Una sola fuente para CuestionarioPage, CuestionarioClinico, CuestionarioPsicologia,
// CuestionarioAlimentacion y OnboardingPage.
//
//  Ejercicio    → PAR-Q+ 2025 (PAR-Q+ Collaboration) + IPAQ versión corta (Craig et al., 2003)
//  Clínico      → Salud autopercibida (OMS) + STEPS OPS/OMS + AUDIT-C (Bush et al., 1998)
//                 + antecedente familiar FINDRISC (Lindström y Tuomilehto, 2003) + sueño (AASM)
//  Psicología   → PHQ-8 (Kroenke et al., 2009) + GAD-7 (Spitzer et al., 2006) — dominio público
//  Alimentación → Cuestionario de hábitos en adolescentes de la CDMX
//                 (Martínez Coronado et al., Healthcare 2023, CC BY)
//
// Todos son TAMIZAJE: orientan, no diagnostican.
//
// Forma de cada pregunta (compatible con las pantallas existentes):
//   { id, category, cat, title, help?, opts: [{ val, label, desc, pts?, v? }], showIf?(answers), soloSuelto? }
//   - pts: puntos de la opción en su escala
//   - v:   valor numérico que representa (días, minutos…)
//   - showIf: la pregunta solo aparece si devuelve true
//   - soloSuelto: solo aparece en el cuestionario suelto (en el onboarding se calcula)

/* ───────────────────────── helpers ───────────────────────── */

const q = (category, def) => ({ ...def, category, cat: category })

/** Devuelve solo las preguntas que aplican con las respuestas actuales */
export function visibles(preguntas, answers = {}, { onboarding = false } = {}) {
  return preguntas.filter(p =>
    (!p.showIf || p.showIf(answers)) && !(onboarding && p.soloSuelto)
  )
}

/** Opción elegida de una pregunta */
function opcion(preguntas, id, answers) {
  const p = preguntas.find(x => x.id === id)
  return p?.opts.find(o => o.val === answers[id]) ?? null
}
const pts = (preguntas, id, a, def = 0) => opcion(preguntas, id, a)?.pts ?? def
const val = (preguntas, id, a, def = 0) => opcion(preguntas, id, a)?.v ?? def

/* ═════════════════════════ EJERCICIO ═════════════════════════ */

const SI_NO = [
  { val: 'no', label: 'No', desc: '', pts: 0 },
  { val: 'si', label: 'Sí', desc: '', pts: 1 },
]

const DIAS = [
  { val: '0',   label: 'Ningún día', desc: '',            v: 0 },
  { val: '1_2', label: '1 o 2 días', desc: '',            v: 1 },
  { val: '3_4', label: '3 o 4 días', desc: '',            v: 3 },
  { val: '5_6', label: '5 o 6 días', desc: '',            v: 5 },
  { val: '7',   label: 'Los 7 días', desc: 'Todos los días', v: 7 },
]

// Se usa el mínimo de cada rango para no sobreestimar (protocolo IPAQ: bloques de 10 min o más)
const MINUTOS = [
  { val: '10_19',  label: '10 a 19 minutos',  desc: '', v: 10 },
  { val: '20_29',  label: '20 a 29 minutos',  desc: '', v: 20 },
  { val: '30_59',  label: '30 a 59 minutos',  desc: '', v: 30 },
  { val: '60_89',  label: '1 hora a 1 h 29',  desc: '', v: 60 },
  { val: '90_mas', label: '1 h 30 o más',     desc: '', v: 90 },
]

const PARQ = 'APTITUD FÍSICA · PAR-Q+'
const IPAQ = 'ÚLTIMOS 7 DÍAS · IPAQ'
const PREF = 'TUS PREFERENCIAS'

export const EJERCICIO = [
  q(PARQ, { id: 'parq_corazon', title: '¿Algún médico te ha dicho que tienes una condición del corazón o presión arterial alta?', opts: SI_NO }),
  q(PARQ, { id: 'parq_dolor_pecho', title: '¿Sientes dolor en el pecho en reposo, en tus actividades diarias o al hacer actividad física?', opts: SI_NO }),
  q(PARQ, { id: 'parq_mareo', title: '¿En los últimos 12 meses has perdido el equilibrio por un mareo o has perdido el conocimiento?', help: 'No cuenta si el mareo fue por respirar muy rápido, incluso al hacer ejercicio intenso.', opts: SI_NO }),
  q(PARQ, { id: 'parq_cronica', title: '¿Te han diagnosticado alguna otra condición médica crónica (que no sea del corazón o presión alta)?', opts: SI_NO }),
  q(PARQ, { id: 'parq_medicamento', title: '¿Tomas actualmente medicamentos recetados para una condición médica crónica?', opts: SI_NO }),
  q(PARQ, { id: 'parq_huesos', title: '¿Tienes, o tuviste en los últimos 12 meses, un problema de huesos, articulaciones o tejidos blandos (músculos, ligamentos, tendones) que podría empeorar si te vuelves más activo/a?', help: 'No cuenta si fue un problema pasado que ya no te limita.', opts: SI_NO }),
  q(PARQ, { id: 'parq_supervision', title: '¿Algún médico te ha dicho que solo debes hacer actividad física con supervisión médica?', opts: SI_NO }),

  q(IPAQ, { id: 'ipaq_vig_dias', title: 'En los últimos 7 días, ¿cuántos días hiciste actividades físicas VIGOROSAS por al menos 10 minutos seguidos?', help: 'Las que te hacen respirar mucho más fuerte de lo normal: correr, fútbol, básquet, pesas pesadas, bicicleta rápida.', opts: DIAS }),
  q(IPAQ, { id: 'ipaq_vig_min', title: 'En uno de esos días, ¿cuánto tiempo hiciste actividad vigorosa?', opts: MINUTOS, showIf: a => !!a.ipaq_vig_dias && a.ipaq_vig_dias !== '0' }),
  q(IPAQ, { id: 'ipaq_mod_dias', title: 'En los últimos 7 días, ¿cuántos días hiciste actividades físicas MODERADAS por al menos 10 minutos seguidos?', help: 'Las que te hacen respirar algo más fuerte de lo normal: bici a paso normal, cargar cosas ligeras, nadar tranquilo, bailar. No incluyas caminar.', opts: DIAS }),
  q(IPAQ, { id: 'ipaq_mod_min', title: 'En uno de esos días, ¿cuánto tiempo hiciste actividad moderada?', opts: MINUTOS, showIf: a => !!a.ipaq_mod_dias && a.ipaq_mod_dias !== '0' }),
  q(IPAQ, { id: 'ipaq_cam_dias', title: 'En los últimos 7 días, ¿cuántos días caminaste por al menos 10 minutos seguidos?', help: 'Cuenta caminar a la escuela, al trabajo, para trasladarte o por gusto.', opts: DIAS }),
  q(IPAQ, { id: 'ipaq_cam_min', title: 'En uno de esos días, ¿cuánto tiempo caminaste en total?', opts: MINUTOS, showIf: a => !!a.ipaq_cam_dias && a.ipaq_cam_dias !== '0' }),
  q(IPAQ, { id: 'ipaq_sentado', title: 'En un día normal entre semana, ¿cuánto tiempo pasas sentado/a?', help: 'En clase, estudiando, en el transporte, viendo el celular o la tele.', opts: [
    { val: 'menos_4', label: 'Menos de 4 horas', desc: '' },
    { val: '4_6',     label: '4 a 6 horas',      desc: '' },
    { val: '7_9',     label: '7 a 9 horas',      desc: '' },
    { val: '10_mas',  label: '10 horas o más',   desc: '' },
  ] }),

  q(PREF, { id: 'lugar_ejercicio', title: '¿Dónde prefieres hacer tu actividad física?', opts: [
    { val: 'casa',     label: '🏠 En casa',            desc: 'Rutinas con peso corporal o equipo básico' },
    { val: 'gimnasio', label: '🏋️ En un gimnasio',     desc: 'Acceso a máquinas y pesas' },
    { val: 'exterior', label: '🌳 Al aire libre',      desc: 'Parques, pistas, calle' },
    { val: 'ninguno',  label: '⚪ Todavía no sé',       desc: 'Quiero que me recomienden' },
  ] }),
  q(PREF, { id: 'equipo', title: '¿Con qué equipo cuentas para entrenar?', opts: [
    { val: 'gimnasio_completo', label: '🏋️ Gimnasio completo', desc: 'Máquinas, pesas y barras' },
    { val: 'mancuernas',        label: '💪 Mancuernas',        desc: 'Solo mancuernas en casa' },
    { val: 'peso_corporal',     label: '🤸 Peso corporal',     desc: 'Sin equipo extra' },
    { val: 'bandas',            label: '🪢 Bandas elásticas',  desc: 'Bandas de resistencia' },
  ] }),
  q(PREF, { id: 'objetivo_ejercicio', title: '¿Cuál es tu meta principal de entrenamiento?', opts: [
    { val: 'fuerza',        label: '⚡ Ganar fuerza',  desc: 'Aumentar mis marcas' },
    { val: 'masa_muscular', label: '🔥 Masa muscular', desc: 'Hipertrofia y volumen' },
    { val: 'perdida_grasa', label: '💧 Perder grasa',  desc: 'Definición y control de peso' },
    { val: 'resistencia',   label: '🏃 Resistencia',   desc: 'Rendimiento cardiovascular' },
  ] }),
  q(PREF, { id: 'motivacion', title: '¿Cuál es tu principal motivo para hacer actividad física?', opts: [
    { val: 'salud',       label: '❤️ Salud y bienestar',     desc: 'Sentirme mejor física y mentalmente' },
    { val: 'estetica',    label: '✨ Estética o peso',        desc: 'Mejorar mi apariencia' },
    { val: 'rendimiento', label: '🏆 Rendimiento deportivo', desc: 'Competir o mejorar en un deporte' },
    { val: 'medico',      label: '🩺 Recomendación médica',   desc: 'Me lo indicó un profesional' },
  ] }),
]

const PARQ_IDS = EJERCICIO.filter(p => p.id.startsWith('parq_')).map(p => p.id)

/** Calificación oficial IPAQ (protocolo 2005) + regla PAR-Q+ */
export function evaluarEjercicio(a = {}) {
  const dias = id => val(EJERCICIO, id, a, 0)
  const mins = (diasId, minId) => (dias(diasId) > 0 ? val(EJERCICIO, minId, a, 0) : 0)

  const vigD = dias('ipaq_vig_dias'), vigM = mins('ipaq_vig_dias', 'ipaq_vig_min')
  const modD = dias('ipaq_mod_dias'), modM = mins('ipaq_mod_dias', 'ipaq_mod_min')
  const camD = dias('ipaq_cam_dias'), camM = mins('ipaq_cam_dias', 'ipaq_cam_min')

  const metVig = 8.0 * vigM * vigD
  const metMod = 4.0 * modM * modD
  const metCam = 3.3 * camM * camD
  const met = Math.round(metVig + metMod + metCam)
  const diasTotales = Math.min(7, vigD + modD + camD)

  let categoria = 'bajo'
  if ((vigD >= 3 && metVig >= 1500) || (diasTotales >= 7 && met >= 3000)) categoria = 'alto'
  else if (
    (vigD >= 3 && vigM >= 20) ||
    ((modM >= 30 ? modD : 0) + (camM >= 30 ? camD : 0)) >= 5 ||
    (diasTotales >= 5 && met >= 600)
  ) categoria = 'moderado'

  const parqSi = PARQ_IDS.filter(id => a[id] === 'si')
  const parqResultado = parqSi.length ? 'consultar' : 'apto'

  let nivel = { bajo: 'principiante', moderado: 'intermedio', alto: 'avanzado' }[categoria]
  if (parqSi.length) nivel = 'principiante'

  const score = Math.min(100, Math.round(met / 30))
  const minutosSemana = vigM * vigD + modM * modD + camM * camD

  return {
    met, categoria, score, nivel, parqSi, parqResultado, minutosSemana,
    detalle: { vigD, vigM, modD, modM, camD, camM, metVig, metMod, metCam },
  }
}

/** Datos para Cuestionario/Guardar.php: campos nuevos + los viejos calculados */
export function payloadEjercicio(a = {}) {
  const r = evaluarEjercicio(a)
  const maxDias = Math.max(r.detalle.vigD, r.detalle.modD)
  return {
    ...soloCampos(EJERCICIO, a),
    parq_resultado:    r.parqResultado,
    ipaq_met_min:      r.met,
    ipaq_categoria:    r.categoria,
    fitness_score:     r.score,
    nivel_recomendado: r.nivel,
    // ── compatibilidad con columnas existentes ──
    nivel_entrenamiento: r.nivel,
    minutos_semana:    r.minutosSemana < 75 ? 'menos_75' : r.minutosSemana < 150 ? '75_150' : r.minutosSemana <= 300 ? '150_300' : 'mas_300',
    frecuencia_semana: maxDias === 0 ? 'nunca' : maxDias <= 2 ? '1_2' : maxDias <= 4 ? '3_4' : '5_mas',
    horas_sedentario:  { menos_4: 'menos_4', '4_6': '4_6', '7_9': '6_8', '10_mas': 'mas_8' }[a.ipaq_sentado] ?? null,
    condicion_salud:   r.parqSi.length ? 'moderada' : 'ninguna',
    condicion_fisica:  r.categoria === 'alto' ? 'muy_buena' : r.categoria === 'moderado' ? 'buena' : r.met > 0 ? 'regular' : 'mala',
  }
}

/* ═════════════════════════ CLÍNICO ═════════════════════════ */

const SALUD = 'SALUD GENERAL · OMS'
const TAB   = 'TABACO · STEPS OMS'
const ALC   = 'ALCOHOL · AUDIT-C'
const ANT   = 'ANTECEDENTES · STEPS OMS'
const HAB   = 'HÁBITOS Y PREVENCIÓN'

const DX = (alto = 'Sí, me dijeron que está alta') => [
  { val: 'nunca',  label: '❓ Nunca me la han medido', desc: 'O no lo recuerdo', pts: 4 },
  { val: 'normal', label: '🟢 Me la midieron y salió normal', desc: '', pts: 8 },
  { val: 'alta',   label: `🔴 ${alto}`, desc: '', pts: 0 },
]

const toma = a => !!a.audit_frecuencia && a.audit_frecuencia !== 'nunca'

export const CLINICO = [
  q(SALUD, { id: 'srh_salud', title: 'En general, ¿cómo calificarías tu salud hoy?', opts: [
    { val: 'muy_buena', label: '🟢 Muy buena', desc: '', pts: 20 },
    { val: 'buena',     label: '🔵 Buena',     desc: '', pts: 15 },
    { val: 'regular',   label: '🟡 Regular',   desc: '', pts: 10 },
    { val: 'mala',      label: '🟠 Mala',      desc: '', pts: 5 },
    { val: 'muy_mala',  label: '🔴 Muy mala',  desc: '', pts: 0 },
  ] }),
  q(TAB, { id: 'tabaco_actual', title: '¿Actualmente fumas cigarros, puros u otro producto de tabaco?', opts: [
    { val: 'no',        label: '✅ No',                   desc: '', pts: 15 },
    { val: 'no_diario', label: '⚠️ Sí, pero no a diario', desc: '', pts: 5 },
    { val: 'diario',    label: '🚬 Sí, a diario',         desc: '', pts: 0 },
  ] }),
  q(TAB, { id: 'vapeo_actual', title: '¿Usas vapeador o cigarro electrónico?', opts: [
    { val: 'no',         label: '✅ No',            desc: '', pts: 10 },
    { val: 'ocasional',  label: '⚠️ Ocasionalmente', desc: '', pts: 4 },
    { val: 'diario',     label: '💨 A diario',       desc: '', pts: 0 },
  ] }),
  q(ALC, { id: 'audit_frecuencia', title: '¿Con qué frecuencia tomas bebidas que contienen alcohol?', opts: [
    { val: 'nunca',          label: 'Nunca',                         desc: '', pts: 0 },
    { val: 'mensual',        label: '1 vez al mes o menos',          desc: '', pts: 1 },
    { val: '2_4_mes',        label: '2 a 4 veces al mes',            desc: '', pts: 2 },
    { val: '2_3_semana',     label: '2 a 3 veces por semana',        desc: '', pts: 3 },
    { val: '4_mas_semana',   label: '4 o más veces por semana',      desc: '', pts: 4 },
  ] }),
  q(ALC, { id: 'audit_cantidad', title: 'En un día normal en que tomas, ¿cuántas bebidas estándar tomas?', help: '1 bebida estándar = 1 cerveza de 355 ml, 1 copa de vino o 1 caballito de tequila u otro destilado.', showIf: toma, opts: [
    { val: '1_2',    label: '1 o 2',       desc: '', pts: 0 },
    { val: '3_4',    label: '3 o 4',       desc: '', pts: 1 },
    { val: '5_6',    label: '5 o 6',       desc: '', pts: 2 },
    { val: '7_9',    label: '7 a 9',       desc: '', pts: 3 },
    { val: '10_mas', label: '10 o más',    desc: '', pts: 4 },
  ] }),
  q(ALC, { id: 'audit_atracon', title: '¿Con qué frecuencia tomas 6 o más bebidas estándar en una sola ocasión?', showIf: toma, opts: [
    { val: 'nunca',          label: 'Nunca',                    desc: '', pts: 0 },
    { val: 'menos_mensual',  label: 'Menos de una vez al mes',  desc: '', pts: 1 },
    { val: 'mensual',        label: 'Una vez al mes',           desc: '', pts: 2 },
    { val: 'semanal',        label: 'Una vez por semana',       desc: '', pts: 3 },
    { val: 'diario',         label: 'Diario o casi diario',     desc: '', pts: 4 },
  ] }),
  q(ANT, { id: 'presion_dx', title: '¿Algún médico o profesional de salud te ha medido la presión y te dijo que la tienes alta?', opts: DX() }),
  q(ANT, { id: 'glucosa_dx', title: '¿Te han medido el azúcar (glucosa) en sangre y te dijeron que está alta o que tienes diabetes?', opts: [
    { val: 'nunca',  label: '❓ Nunca me la han medido', desc: 'O no lo recuerdo', pts: 4 },
    { val: 'normal', label: '🟢 Me la midieron y salió normal', desc: '', pts: 8 },
    { val: 'alta',   label: '🔴 Sí, me dijeron que está alta o que tengo diabetes', desc: '', pts: 0 },
  ] }),
  q(ANT, { id: 'colesterol_dx', title: '¿Te han medido el colesterol y te dijeron que lo tienes alto?', opts: [
    { val: 'nunca',  label: '❓ Nunca me lo han medido', desc: 'O no lo recuerdo', pts: 4 },
    { val: 'normal', label: '🟢 Me lo midieron y salió normal', desc: '', pts: 8 },
    { val: 'alta',   label: '🔴 Sí, me dijeron que está alto', desc: '', pts: 0 },
  ] }),
  q('ANTECEDENTES · FINDRISC', { id: 'antecedente_diabetes', title: '¿Alguno de tus familiares tiene o ha tenido diabetes (tipo 1 o tipo 2)?', opts: [
    { val: 'no',            label: '✅ No / no que yo sepa',            desc: '', findrisc: 0 },
    { val: 'segundo_grado', label: '👥 Abuelos, tíos o primos',          desc: '', findrisc: 3 },
    { val: 'primer_grado',  label: '👨‍👩‍👧 Padres, hermanos o hijos',      desc: '', findrisc: 5 },
  ] }),
  q(HAB, { id: 'sueno_horas', title: '¿Cuántas horas duermes en una noche normal?', help: 'La recomendación es de 8 a 10 horas entre los 13 y 18 años, y 7 horas o más en adultos.', opts: [
    { val: 'menos_6', label: '😵 Menos de 6 horas', desc: '', pts: 0 },
    { val: '6_7',     label: '😐 6 a 7 horas',      desc: '', pts: 3 },
    { val: '7_8',     label: '🙂 7 a 8 horas',      desc: '', pts: 6 },
    { val: '8_10',    label: '✅ 8 a 10 horas',     desc: '', pts: 8 },
    { val: 'mas_10',  label: '🛌 Más de 10 horas',  desc: '', pts: 4 },
  ] }),
  q(HAB, { id: 'vacunas', title: '¿Tu Cartilla Nacional de Salud (vacunas) está al día?', opts: [
    { val: 'completo',   label: '✅ Sí, completa',                         desc: '', pts: 8 },
    { val: 'incompleto', label: '⚠️ Creo que está incompleta',             desc: '', pts: 3 },
    { val: 'no_se',      label: '❓ No lo sé',                             desc: '', pts: 3 },
    { val: 'no',         label: '❌ No está al día o no tengo cartilla',    desc: '', pts: 0 },
  ] }),
  q(HAB, { id: 'chequeo_ultimo', title: '¿Cuándo fue tu último chequeo médico preventivo (no por enfermedad)?', opts: [
    { val: 'ultimo_anio', label: '📅 En el último año', desc: '' },
    { val: '1_2_anios',   label: '🔄 Hace 1 o 2 años',  desc: '' },
    { val: 'mas_2',       label: '⏳ Hace más de 2 años', desc: '' },
    { val: 'nunca',       label: '❌ Nunca',             desc: '' },
  ] }),
]

/**
 * AUDIT-C: positivo con 4+ en hombres y 3+ en mujeres (Bradley et al.).
 * Si no se conoce el sexo se usa 3 (más cuidadoso).
 * En menores de 18 años cualquier consumo cuenta como positivo (corte 1).
 */
export function corteAuditC({ sexo, edad } = {}) {
  if (edad != null && edad < 18) return 1
  return sexo === 'M' || sexo === 'masculino' ? 4 : 3
}

export function evaluarClinico(a = {}, { sexo, edad } = {}) {
  const auditC = toma(a)
    ? pts(CLINICO, 'audit_frecuencia', a) + pts(CLINICO, 'audit_cantidad', a) + pts(CLINICO, 'audit_atracon', a)
    : 0
  const corte = corteAuditC({ sexo, edad })
  const auditPositivo = auditC >= corte
  const ptsAlcohol = auditC === 0 ? 15 : auditPositivo ? 0 : 8

  const score = Math.min(100,
    pts(CLINICO, 'srh_salud', a) +
    pts(CLINICO, 'tabaco_actual', a) +
    pts(CLINICO, 'vapeo_actual', a) +
    ptsAlcohol +
    pts(CLINICO, 'presion_dx', a) +
    pts(CLINICO, 'glucosa_dx', a) +
    pts(CLINICO, 'colesterol_dx', a) +
    pts(CLINICO, 'sueno_horas', a) +
    pts(CLINICO, 'vacunas', a)
  )
  const nivel = score < 40 ? 'critico' : score < 60 ? 'moderado' : score < 80 ? 'bueno' : 'optimo'
  const findrisc = opcion(CLINICO, 'antecedente_diabetes', a)?.findrisc ?? 0

  return { score, nivel, auditC, auditPositivo, corte, findrisc }
}

export function payloadClinico(a = {}, opts = {}) {
  const r = evaluarClinico(a, opts)
  return {
    ...soloCampos(CLINICO, a),
    audit_c_total: r.auditC,
    salud_score:   r.score,
    nivel_salud:   r.nivel,
    // ── compatibilidad con columnas existentes ──
    tabaco:          { no: 'nunca', no_diario: 'ocasional', diario: 'regular' }[a.tabaco_actual] ?? null,
    alcohol:         { nunca: 'nunca', mensual: 'social', '2_4_mes': 'social', '2_3_semana': 'frecuente', '4_mas_semana': 'diario' }[a.audit_frecuencia] ?? null,
    sueno:           { menos_6: 'menos_5', '6_7': '5_6', '7_8': '7_8', '8_10': '7_8', mas_10: 'mas_9' }[a.sueno_horas] ?? null,
    estres_fisico:   { muy_buena: 'excelente', buena: 'bueno', regular: 'regular', mala: 'malo', muy_mala: 'malo' }[a.srh_salud] ?? null,
    presion_arterial:{ nunca: 'no_se', normal: 'normal', alta: 'alta' }[a.presion_dx] ?? null,
    visitas_medico:  { ultimo_anio: 'anual', '1_2_anios': 'rara_vez', mas_2: 'rara_vez', nunca: 'nunca' }[a.chequeo_ultimo] ?? null,
  }
}

/* ═════════════════════════ PSICOLOGÍA ═════════════════════════ */

const FRECUENCIA = [
  { val: '0', label: 'Ningún día',                  desc: '', pts: 0 },
  { val: '1', label: 'Varios días',                 desc: '', pts: 1 },
  { val: '2', label: 'Más de la mitad de los días', desc: '', pts: 2 },
  { val: '3', label: 'Casi todos los días',         desc: '', pts: 3 },
]
const ANIMO = 'ESTADO DE ÁNIMO · PHQ-8'
const ANSI  = 'ANSIEDAD · GAD-7'
// Cada pregunta ya trae el periodo: "En las últimas 2 semanas, ¿qué tan seguido…?" (mismo contenido que el PHQ-8 y el GAD-7)
const p2 = texto => `En las últimas 2 semanas, ¿qué tan seguido ${texto}?`

export const PSICOLOGIA = [
  q(ANIMO, { id: 'phq_interes',       title: p2('has tenido poco interés o placer en hacer las cosas'), opts: FRECUENCIA }),
  q(ANIMO, { id: 'phq_animo',         title: p2('te has sentido decaído/a, deprimido/a o sin esperanza'), opts: FRECUENCIA }),
  q(ANIMO, { id: 'phq_sueno',         title: p2('has tenido problemas para dormirte o para seguir dormido/a, o has dormido demasiado'), opts: FRECUENCIA }),
  q(ANIMO, { id: 'phq_energia',       title: p2('te has sentido cansado/a o con poca energía'), opts: FRECUENCIA }),
  q(ANIMO, { id: 'phq_apetito',       title: p2('has tenido poco apetito o has comido de más'), opts: FRECUENCIA }),
  q(ANIMO, { id: 'phq_autoestima',    title: p2('te has sentido mal contigo mismo/a, como si fueras un fracaso o le hubieras quedado mal a tu familia o a ti'), opts: FRECUENCIA }),
  q(ANIMO, { id: 'phq_concentracion', title: p2('te ha costado concentrarte en cosas como leer, estudiar o ver la tele'), opts: FRECUENCIA }),
  q(ANIMO, { id: 'phq_movimiento',    title: p2('te has movido o hablado tan lento que otras personas lo notaron, o al contrario, has estado tan inquieto/a que te movías mucho más de lo normal'), opts: FRECUENCIA }),

  q(ANSI, { id: 'gad_nervios',      title: p2('te has sentido nervioso/a, ansioso/a o con los nervios de punta'), opts: FRECUENCIA }),
  q(ANSI, { id: 'gad_control',      title: p2('no has podido dejar de preocuparte o controlar tu preocupación'), opts: FRECUENCIA }),
  q(ANSI, { id: 'gad_preocupacion', title: p2('te has preocupado demasiado por diferentes cosas'), opts: FRECUENCIA }),
  q(ANSI, { id: 'gad_relajarse',    title: p2('te ha costado relajarte'), opts: FRECUENCIA }),
  q(ANSI, { id: 'gad_inquietud',    title: p2('te has sentido tan inquieto/a que no podías quedarte quieto/a'), opts: FRECUENCIA }),
  q(ANSI, { id: 'gad_irritable',    title: p2('te has molestado o irritado fácilmente'), opts: FRECUENCIA }),
  q(ANSI, { id: 'gad_miedo',        title: p2('has sentido miedo, como si algo terrible fuera a pasar'), opts: FRECUENCIA }),

  q('TU OBJETIVO', { id: 'metas_bienestar', title: '¿Qué aspecto de tu bienestar mental quieres trabajar más?', opts: [
    { val: 'estres',     label: '🧘 Manejo del estrés',       desc: 'Técnicas de relajación y regulación emocional' },
    { val: 'ansiedad',   label: '💆 Control de la ansiedad',  desc: 'Reducir preocupaciones y tensión' },
    { val: 'autoestima', label: '💪 Fortalecer mi autoestima', desc: 'Mejorar mi relación conmigo mismo/a' },
    { val: 'relaciones', label: '🤝 Mejorar relaciones',      desc: 'Comunicación y vínculos más sanos' },
  ] }),
]

export const SEVERIDAD_PHQ = [
  { max: 4,  key: 'minimo',   label: 'Mínimo' },
  { max: 9,  key: 'leve',     label: 'Leve' },
  { max: 14, key: 'moderado', label: 'Moderado' },
  { max: 19, key: 'mod_severo', label: 'Moderadamente severo' },
  { max: 24, key: 'severo',   label: 'Severo' },
]
export const SEVERIDAD_GAD = [
  { max: 4,  key: 'minimo',   label: 'Mínimo' },
  { max: 9,  key: 'leve',     label: 'Leve' },
  { max: 14, key: 'moderado', label: 'Moderado' },
  { max: 21, key: 'severo',   label: 'Severo' },
]
const banda = (tabla, n) => tabla.find(b => n <= b.max) ?? tabla[tabla.length - 1]

export const LINEA_DE_LA_VIDA = { nombre: 'Línea de la Vida', tel: '800 911 2000', horario: 'gratis, 24 horas' }

export function evaluarPsicologia(a = {}) {
  const suma = prefijo => PSICOLOGIA
    .filter(p => p.id.startsWith(prefijo))
    .reduce((t, p) => t + pts(PSICOLOGIA, p.id, a), 0)
  const phq8 = suma('phq_')
  const gad7 = suma('gad_')
  const peor = Math.max(phq8, gad7)

  const score = Math.round(100 - ((phq8 + gad7) / 45) * 100)
  const nivel = peor >= 15 ? 'critico' : peor >= 10 ? 'vulnerable' : peor >= 5 ? 'moderado' : 'resiliente'

  return {
    phq8, gad7, score, nivel,
    phqBanda: banda(SEVERIDAD_PHQ, phq8),
    gadBanda: banda(SEVERIDAD_GAD, gad7),
    sugerirConsulta: peor >= 10,
    mostrarLinea: peor >= 15,
  }
}

export function payloadPsicologia(a = {}) {
  const r = evaluarPsicologia(a)
  const n4 = (n, vals) => vals[n < 5 ? 0 : n < 10 ? 1 : n < 15 ? 2 : 3]
  return {
    ...soloCampos(PSICOLOGIA, a),
    phq8_total:        r.phq8,
    gad7_total:        r.gad7,
    psico_score:       r.score,
    nivel_psicologico: r.nivel,
    // ── compatibilidad con columnas existentes ──
    estado_animo:   n4(r.phq8, ['positivo', 'variable', 'bajo', 'muy_bajo']),
    ansiedad:       n4(r.gad7, ['nunca', 'leve', 'moderado', 'intenso']),
    estres_general: n4(Math.max(r.phq8, r.gad7), ['nunca', 'ocasional', 'frecuente', 'constante']),
  }
}

/* ═════════════════════════ ALIMENTACIÓN ═════════════════════════ */

// Cuestionario de hábitos en adolescentes de la CDMX (Martínez Coronado et al., 2023, CC BY):
// cada pregunta vale 0 a 3 puntos, en el orden de sus opciones.
const BUENO = (labels, desc = '') => labels.map((label, i) => ({ val: ['0_2', '3_4', '5_6', 'diario'][i], label, desc, pts: i }))
const MALO  = labels => labels.map((label, i) => ({ val: ['5_mas', '3_4', '1_2', 'ninguno'][i], label, desc: '', pts: i }))
const DIAS_BUENO = ['0 a 2 días', '3 a 4 días', '5 a 6 días', 'Diario']
const DIAS_MALO  = ['5 días o más', '3 a 4 días', '1 a 2 días', 'Ningún día']

const SEM = 'EN UNA SEMANA NORMAL'
const CTX = 'PARA TU PLAN DE COMIDAS'

export const ALIMENTACION = [
  q(SEM, { id: 'verduras_dias',     title: '¿Cuántos días a la semana comes verduras?', help: 'Ensalada, nopales, calabaza, chayote, jitomate, zanahoria…', opts: BUENO(DIAS_BUENO) }),
  q(SEM, { id: 'frutas_dias',       title: '¿Cuántos días a la semana comes fruta?', help: 'Fruta entera o en trozos, no jugos.', opts: BUENO(DIAS_BUENO) }),
  q(SEM, { id: 'lacteos_dias',      title: '¿Cuántos días tomas leche o yogur natural, o comes queso?', opts: BUENO(DIAS_BUENO) }),
  q(SEM, { id: 'agua_vasos',        title: '¿Cuántos vasos de agua natural tomas al día?', help: 'Agua sola, sin sabor ni azúcar.', opts: [
    { val: '0_2',   label: '0 a 2 vasos',  desc: '', pts: 0 },
    { val: '3_4',   label: '3 a 4 vasos',  desc: '', pts: 1 },
    { val: '5_6',   label: '5 a 6 vasos',  desc: '', pts: 2 },
    { val: '7_mas', label: '7 vasos o más', desc: '', pts: 3 },
  ] }),
  q(SEM, { id: 'carnes_procesadas', title: '¿Cuántos días comes carnes procesadas como jamón, salchicha o chorizo?', opts: MALO(DIAS_MALO) }),
  q(SEM, { id: 'comida_rapida',     title: '¿Cuántos días comes comida rápida fuera de casa?', help: 'Hamburguesas, pizza, hot dogs, alitas, comida de puesto frita.', opts: MALO(DIAS_MALO) }),
  q(SEM, { id: 'dulces',            title: '¿Cuántos días comes dulces o chocolates?', opts: MALO(DIAS_MALO) }),
  q(SEM, { id: 'pan_dulce',         title: '¿Cuántos días comes pan dulce, galletas o pastelitos?', opts: MALO(DIAS_MALO) }),
  q(SEM, { id: 'botanas',           title: '¿Cuántos días comes papitas u otras botanas saladas de bolsa?', opts: MALO(DIAS_MALO) }),
  q(SEM, { id: 'refrescos_dias',    title: '¿Cuántos días tomas refresco, jugo embotellado o bebidas con azúcar?', opts: MALO(DIAS_MALO) }),
  q(SEM, { id: 'refrescos_vasos',   title: 'Los días que tomas bebidas con azúcar, ¿cuántos vasos tomas?', showIf: a => !!a.refrescos_dias && a.refrescos_dias !== 'ninguno', opts: [
    { val: '4_mas', label: '4 vasos o más', desc: '', pts: 0 },
    { val: '2_3',   label: '2 a 3 vasos',   desc: '', pts: 1 },
    { val: '1',     label: '1 vaso',        desc: '', pts: 2 },
  ] }),
  q(SEM, { id: 'desayuno_dias',     title: '¿Cuántos días a la semana desayunas?', opts: [
    { val: '0_1', label: '0 a 1 día',  desc: '', pts: 0 },
    { val: '2_3', label: '2 a 3 días', desc: '', pts: 1 },
    { val: '4_5', label: '4 a 5 días', desc: '', pts: 2 },
    { val: '6_7', label: '6 a 7 días', desc: '', pts: 3 },
  ] }),

  q(CTX, { id: 'objetivo', title: '¿Cuál es tu principal objetivo con tu alimentación?', opts: [
    { val: 'perder_peso',   label: '⚖️ Perder peso',             desc: 'Reducir grasa de forma saludable' },
    { val: 'ganar_masa',    label: '💪 Ganar masa muscular',      desc: 'Aumentar músculo con buena nutrición' },
    { val: 'mantener',      label: '⚡ Mantener mi peso actual',  desc: 'Equilibrio y energía estable' },
    { val: 'mejorar_salud', label: '🌿 Mejorar mi salud general', desc: 'Comer mejor sin un objetivo de peso' },
  ] }),
  q(CTX, { id: 'proteina', title: '¿Cuál es tu principal fuente de proteína?', opts: [
    { val: 'carnes',      label: '🍗 Carnes',             desc: 'Pollo, res, cerdo' },
    { val: 'pescado',     label: '🐟 Pescado y mariscos', desc: '' },
    { val: 'huevo_lacto', label: '🥚 Huevo y lácteos',    desc: '' },
    { val: 'vegetal',     label: '🫘 Vegetal',            desc: 'Frijol, lenteja, soya (vegetariano o vegano)' },
  ] }),
  q(CTX, { id: 'alergias', title: '¿Tienes alguna restricción alimentaria o alergia?', opts: [
    { val: 'ninguna', label: '✅ No tengo restricciones',       desc: '' },
    { val: 'lactosa', label: '🥛 Intolerancia a la lactosa',     desc: '' },
    { val: 'gluten',  label: '🌾 Intolerancia al gluten',        desc: '' },
    { val: 'otras',   label: '⚠️ Otras alergias o restricciones', desc: '' },
  ] }),
  q(CTX, { id: 'presupuesto', title: '¿Cómo es tu presupuesto diario para comida?', opts: [
    { val: 'muy_limitado', label: '💸 Muy limitado', desc: 'Menos de $50 al día' },
    { val: 'limitado',     label: '💰 Limitado',     desc: '$50 a $100 al día' },
    { val: 'moderado',     label: '💵 Moderado',     desc: '$100 a $200 al día' },
    { val: 'amplio',       label: '🏦 Amplio',       desc: 'Más de $200 al día' },
  ] }),
  q(CTX, { id: 'cocina', title: '¿Tienes acceso a una cocina para preparar tus alimentos?', opts: [
    { val: 'completa',  label: '🍳 Sí, cocina completa',           desc: '' },
    { val: 'limitada',  label: '🔥 Acceso limitado (microondas)',  desc: '' },
    { val: 'cafeteria', label: '🏫 Dependo de la cafetería',        desc: '' },
    { val: 'no',        label: '❌ No tengo acceso',                desc: '' },
  ] }),
  // En el onboarding se calcula con el IPAQ, por eso solo sale en el cuestionario suelto
  q(CTX, { id: 'actividad_fisica_alim', soloSuelto: true, title: '¿Qué tan activo/a eres en una semana normal?', opts: [
    { val: 'sedentario', label: '🛋️ Sedentario', desc: 'Casi no hago actividad física' },
    { val: 'leve',       label: '🚶 Leve',       desc: 'Camino o me muevo un poco' },
    { val: 'moderado',   label: '🚴 Moderado',   desc: 'Ejercicio 2 a 4 veces por semana' },
    { val: 'intenso',    label: '🏋️ Intenso',    desc: 'Entreno 5 veces o más por semana' },
  ] }),
]

const ALIM_CALIFICADAS = ALIMENTACION.filter(p => p.opts.some(o => o.pts !== undefined))
export const ALIM_MAX = ALIM_CALIFICADAS.length * 3 // 36

export function evaluarAlimentacion(a = {}) {
  let puntos = 0
  for (const p of ALIM_CALIFICADAS) {
    if (p.id === 'refrescos_vasos' && (!a.refrescos_dias || a.refrescos_dias === 'ninguno')) { puntos += 3; continue }
    puntos += pts(ALIMENTACION, p.id, a)
  }
  const pct = puntos / ALIM_MAX
  const score = Math.round(pct * 100)
  const nivel = pct < 0.5 ? 'deficiente' : pct < 0.75 ? 'mejorable' : pct < 0.9 ? 'adecuado' : 'optimo'
  const clasificacion = pct < 0.5 ? 'Inadecuada' : pct < 0.75 ? 'Parcialmente inadecuada' : 'Adecuada'
  return { puntos, max: ALIM_MAX, score, nivel, clasificacion }
}

/** Lista corta de hábitos con 0 o 1 punto, para mostrar y para el prompt de la dieta */
export function habitosAMejorar(a = {}) {
  const textos = {
    verduras_dias: 'comer verduras más días', frutas_dias: 'comer fruta más días',
    lacteos_dias: 'incluir lácteos naturales', agua_vasos: 'tomar más agua natural',
    carnes_procesadas: 'menos jamón, salchicha y chorizo', comida_rapida: 'menos comida rápida',
    dulces: 'menos dulces y chocolates', pan_dulce: 'menos pan dulce y galletas',
    botanas: 'menos botanas de bolsa', refrescos_dias: 'menos refresco y jugos embotellados',
    refrescos_vasos: 'menos vasos de bebidas azucaradas', desayuno_dias: 'desayunar más seguido',
  }
  return ALIM_CALIFICADAS
    .filter(p => visibles([p], a).length && a[p.id] !== undefined && pts(ALIMENTACION, p.id, a) <= 1)
    .map(p => textos[p.id])
    .filter(Boolean)
    .slice(0, 5)
}

export function payloadAlimentacion(a = {}, { ipaq } = {}) {
  const r = evaluarAlimentacion(a)
  const p = id => pts(ALIMENTACION, id, a)
  const fv = (p('verduras_dias') + p('frutas_dias')) / 2
  const peorUltra = Math.min(p('comida_rapida'), p('botanas'), p('refrescos_dias'))
  const actividad = a.actividad_fisica_alim ?? (ipaq
    ? (ipaq.met === 0 ? 'sedentario' : { bajo: 'leve', moderado: 'moderado', alto: 'intenso' }[ipaq.categoria])
    : null)
  return {
    ...soloCampos(ALIMENTACION, a),
    actividad_fisica_alim: actividad,
    nutricion_score: r.score,
    nivel_nutricion: r.nivel,
    // ── compatibilidad con columnas existentes ──
    verduras_frutas:  fv >= 2.5 ? 'diario' : fv >= 1.5 ? 'frecuente' : fv >= 0.5 ? 'ocasional' : 'casi_nunca',
    ultra_procesados: ['diario', 'frecuente', 'una_vez', 'casi_nunca'][peorUltra],
    agua:             { '0_2': 'menos_4', '3_4': 'menos_4', '5_6': '4_6', '7_mas': '6_8' }[a.agua_vasos] ?? null,
    desayuno:         { '0_1': 'nunca', '2_3': 'a_veces', '4_5': 'casi_siempre', '6_7': 'siempre' }[a.desayuno_dias] ?? null,
  }
}

/* ───────────────────────── util ───────────────────────── */

/**
 * Respuestas de este cuestionario listas para enviar.
 * Una pregunta que ya no aplica (p. ej. "¿cuántas bebidas?" después de cambiar a "Nunca")
 * se manda como null aunque se haya contestado antes, para no guardar datos falsos.
 */
function soloCampos(preguntas, a) {
  const aplican = new Set(visibles(preguntas, a).map(p => p.id))
  const out = {}
  for (const p of preguntas) {
    out[p.id] = aplican.has(p.id) && a[p.id] !== undefined ? a[p.id] : null
  }
  return out
}
