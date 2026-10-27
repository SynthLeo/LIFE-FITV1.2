import api from '@/lib/api'
import { CLINICO, evaluarClinico, payloadClinico } from '@/modulos/cuestionarios/instrumentos'
import Cuestionario from '@/presentacion/componentes/cuestionario/Cuestionario'

/* ─────────────────────────────────────────────
   SCORING
───────────────────────────────────────────── */
function calcSaludScore(ans) {
  return evaluarClinico(ans).score
}

function getNivelSalud(score) {
  return score < 40 ? 'critico' : score < 60 ? 'moderado' : score < 80 ? 'bueno' : 'optimo'
}

function getRecomendaciones(ans) {
  const ev = evaluarClinico(ans)
  const recs = []
  if (ans.presion_dx === 'alta')
    recs.push({ icon: '🩸', titulo: 'Control de Presión Arterial', desc: 'Te dijeron que tienes la presión alta. Lleva un registro en el módulo de signos vitales y da seguimiento con tu médico.', badge: 'seguimiento', badgeClass: 'high' })
  if (ans.glucosa_dx === 'alta')
    recs.push({ icon: '🩺', titulo: 'Control de Glucosa', desc: 'Una glucosa alta necesita seguimiento médico. Agenda una consulta para revisar tus estudios.', badge: 'seguimiento', badgeClass: 'high' })
  if (ev.findrisc > 0 && ans.glucosa_dx === 'nunca')
    recs.push({ icon: '🧬', titulo: 'Mide tu Glucosa', desc: 'Tienes antecedente familiar de diabetes y nunca te la han medido. Es un estudio sencillo que vale la pena hacer.', badge: 'prevención', badgeClass: 'med' })
  if (ans.srh_salud === 'mala' || ans.srh_salud === 'muy_mala')
    recs.push({ icon: '🏥', titulo: 'Consulta Médica General', desc: 'Calificaste tu salud como mala. Agenda una consulta para revisar qué está pasando.', badge: 'prioritario', badgeClass: 'high' })
  if (ans.tabaco_actual && ans.tabaco_actual !== 'no' || ans.vapeo_actual && ans.vapeo_actual !== 'no')
    recs.push({ icon: '🚭', titulo: 'Apoyo para Dejar el Tabaco o Vapeo', desc: 'La Línea de la Vida (800 911 2000, gratis y 24 h) también orienta sobre tabaco y vapeo. El servicio médico puede acompañarte.', badge: 'recomendado', badgeClass: 'med' })
  if (ev.auditPositivo)
    recs.push({ icon: '🍺', titulo: 'Revisa tu Consumo de Alcohol', desc: `Tu AUDIT-C es de ${ev.auditC}/12, arriba del punto de corte. Platícalo con el equipo clínico, sin juicios.`, badge: 'atención', badgeClass: 'med' })
  if (ans.sueno_horas === 'menos_6' || ans.sueno_horas === '6_7')
    recs.push({ icon: '😴', titulo: 'Duerme Más', desc: 'Entre los 13 y 18 años se recomiendan 8 a 10 horas, y en adultos 7 o más (AASM). Mantén un horario fijo para dormir.', badge: 'hábito', badgeClass: 'med' })
  if (ans.vacunas && ans.vacunas !== 'completo')
    recs.push({ icon: '💉', titulo: 'Revisa tu Cartilla de Vacunación', desc: 'Lleva tu Cartilla Nacional de Salud a tu centro de salud. Las vacunas del esquema son gratuitas.', badge: 'preventivo', badgeClass: '' })
  if (ans.chequeo_ultimo === 'mas_2' || ans.chequeo_ultimo === 'nunca')
    recs.push({ icon: '📋', titulo: 'Chequeo Preventivo', desc: 'Hace mucho que no te haces un chequeo. Uno al año ayuda a detectar problemas a tiempo.', badge: 'preventivo', badgeClass: '' })
  if (recs.length === 0)
    recs.push({ icon: '⭐', titulo: 'Mantén tus Hábitos', desc: 'Tu perfil de salud es sólido. Sigue con tus chequeos preventivos y hábitos saludables.', badge: 'excelente', badgeClass: 'high' })
  return recs.slice(0, 4)
}

function getTipsClinico(ans) {
  const ev = evaluarClinico(ans)
  const tips = []
  if (ans.presion_dx === 'nunca')
    tips.push({ icon: '📏', text: 'Nunca te han medido la presión. Puedes hacerlo gratis en el módulo clínico o en tu centro de salud.' })
  if (ans.colesterol_dx === 'alta')
    tips.push({ icon: '🥑', text: 'Con colesterol alto ayuda comer más fibra (frijol, avena, verduras) y menos frituras y embutidos.' })
  if (ev.auditC > 0)
    tips.push({ icon: 'ℹ️', text: 'Si eres menor de 18 años, lo recomendable es no tomar alcohol. Tu cuerpo y cerebro siguen en desarrollo.' })
  if (ev.score >= 85)
    tips.push({ icon: '🌟', text: 'Tu salud general es excelente. Comparte tus buenos hábitos con tu entorno.' })
  if (tips.length === 0)
    tips.push({ icon: '📋', text: 'Registra tus signos vitales una vez al mes. Detectar cambios a tiempo es clave.' })
  tips.push({ icon: '🔎', text: 'Este cuestionario es de tamizaje: orienta, pero no sustituye una consulta médica.' })
  return tips.slice(0, 3)
}

const ICONOS    = { critico: '🚨', moderado: '⚠️', bueno: '🔵', optimo: '🌟' }
const ETIQUETAS = { critico: 'Atención Prioritaria', moderado: 'Salud Moderada', bueno: 'Buen Estado', optimo: 'Salud Óptima' }
const ESTADO    = { critico: 'Crítico', moderado: 'Moderado', bueno: 'Bueno', optimo: 'Óptimo' }
const SUENO     = { menos_6: 'Insuficiente', '6_7': 'Bajo', '7_8': 'Adecuado', '8_10': 'Óptimo', mas_10: 'Excesivo' }

function resultados(answers) {
  const score = calcSaludScore(answers)
  const nivel = getNivelSalud(score)
  const ev = evaluarClinico(answers)
  return {
    score,
    etiquetaScore: 'Score de salud clínica',
    icono: ICONOS[nivel],
    nivel: ETIQUETAS[nivel],
    metricas: [
      { val: `${score}/100`, lbl: 'Score de salud' },
      { val: ESTADO[nivel], lbl: 'Estado general' },
      { val: SUENO[answers.sueno_horas] || '—', lbl: 'Horas de sueño' },
      { val: `${ev.auditC}/12`, lbl: ev.auditPositivo ? 'AUDIT-C · revisar' : 'AUDIT-C · bajo riesgo' },
    ],
    tituloLista: 'Recomendaciones clínicas',
    lista: getRecomendaciones(answers),
    tips: getTipsClinico(answers),
  }
}

async function guardar(answers) {
  await api.post('/Cuestionario/GuardarClinico.php', payloadClinico(answers))
  return { mensaje: 'Perfil clínico guardado exitosamente.', ruta: '/clinico' }
}

export default function CuestionarioClinico() {
  return (
    <Cuestionario
      titulo="Cuestionario Clínico"
      tituloResultados="Resultados Clínicos"
      color="#0284C7" colorRgb="2,132,199"
      preguntas={CLINICO}
      resultados={resultados}
      guardar={guardar}
      textoGuardar="Guardar perfil y ver módulo clínico →"
    />
  )
}
