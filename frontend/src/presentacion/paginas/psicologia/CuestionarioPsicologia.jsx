import api from '@/lib/api'
import { PSICOLOGIA, evaluarPsicologia, payloadPsicologia, LINEA_DE_LA_VIDA } from '@/modulos/cuestionarios/instrumentos'
import Cuestionario from '@/presentacion/componentes/cuestionario/Cuestionario'

/* ─────────────────────────────────────────────
   SCORING
───────────────────────────────────────────── */
function calcPsicoScore(ans) {
  return evaluarPsicologia(ans).score
}

// El nivel sale de la escala con puntaje más alto (PHQ-8 o GAD-7), no del promedio
function getNivelPsico(_score, ans) {
  return evaluarPsicologia(ans).nivel
}

function getRecursosPsico(ans) {
  const ev = evaluarPsicologia(ans)
  const recursos = []
  if (ev.sugerirConsulta)
    recursos.push({ icon: '💬', titulo: 'Agenda una Consulta Psicológica', desc: 'Tus respuestas sugieren que te vendría bien hablar con un profesional. Puedes agendar desde el módulo de psicología.', badge: 'recomendado', badgeClass: 'high' })
  if (ev.gad7 >= 5)
    recursos.push({ icon: '🧘', titulo: 'Respiración y Relajación', desc: 'Practica la respiración 4-7-8 o la relajación muscular 5 minutos al día. Las encuentras en Herramientas de bienestar.', badge: 'ansiedad', badgeClass: 'med' })
  if (ev.phq8 >= 5)
    recursos.push({ icon: '🌱', titulo: 'Actividades que Disfrutas', desc: 'Agenda cada día una actividad pequeña que antes te gustaba, aunque no tengas ganas. Moverte y convivir mejora el ánimo.', badge: 'ánimo', badgeClass: 'med' })
  if (Number(ans.phq_sueno) >= 2)
    recursos.push({ icon: '😴', titulo: 'Higiene del Sueño', desc: 'Misma hora para dormir y despertar, sin pantallas una hora antes y el cuarto oscuro y fresco.', badge: 'hábito', badgeClass: '' })
  if (recursos.length === 0)
    recursos.push({ icon: '🌟', titulo: 'Mantén tu Bienestar', desc: 'Tus puntajes están en rango mínimo. Sigue con tus hábitos de autocuidado y tus conexiones sociales.', badge: 'excelente', badgeClass: 'high' })
  return recursos.slice(0, 4)
}

function getTipsPsico(ans) {
  const tips = []
  if (Number(ans.phq_concentracion) >= 2)
    tips.push({ icon: '📚', text: 'La técnica Pomodoro (25 min de trabajo y 5 de descanso) ayuda cuando cuesta concentrarse.' })
  if (Number(ans.phq_autoestima) >= 2)
    tips.push({ icon: '💪', text: 'Anota cada día una cosa que hiciste bien, aunque sea pequeña. La autoestima se construye con evidencia.' })
  if (ans.metas_bienestar === 'estres')
    tips.push({ icon: '🧘', text: 'Diez minutos diarios de meditación guiada tienen efecto comprobado sobre el estrés.' })
  if (ans.metas_bienestar === 'relaciones')
    tips.push({ icon: '🤝', text: 'Busca un grupo o club en tu escuela. Convivir con regularidad protege tu salud mental.' })
  if (tips.length === 0)
    tips.push({ icon: '✨', text: 'Comparte tus estrategias de bienestar con quienes te rodean. El bienestar mental también es colectivo.' })
  return tips.slice(0, 3)
}

const ICONOS    = { critico: '💜', vulnerable: '⚠️', moderado: '🔵', resiliente: '🌟' }
const ETIQUETAS = { critico: 'Busca Apoyo Pronto', vulnerable: 'Perfil Vulnerable', moderado: 'Bienestar Moderado', resiliente: 'Perfil Resiliente' }
const ESTADO    = { critico: 'Crítico', vulnerable: 'Vulnerable', moderado: 'Moderado', resiliente: 'Resiliente' }

function resultados(answers) {
  const score = calcPsicoScore(answers)
  const nivel = getNivelPsico(score, answers)
  const ev = evaluarPsicologia(answers)
  return {
    score,
    etiquetaScore: 'Score de bienestar mental',
    icono: ICONOS[nivel],
    nivel: ETIQUETAS[nivel],
    metricas: [
      { val: `${score}/100`, lbl: 'Score mental' },
      { val: ESTADO[nivel], lbl: 'Nivel de bienestar' },
      { val: `${ev.phq8}/24`, lbl: `Ánimo (PHQ-8) · ${ev.phqBanda.label}` },
      { val: `${ev.gad7}/21`, lbl: `Ansiedad (GAD-7) · ${ev.gadBanda.label}` },
    ],
    aviso: ev.mostrarLinea
      ? {
          alerta: true,
          titulo: 'No tienes que pasar por esto solo/a 💜',
          texto: `Tus respuestas indican que estás pasando por un momento difícil. Hablar con alguien hoy puede ayudarte. La ${LINEA_DE_LA_VIDA.nombre} atiende ${LINEA_DE_LA_VIDA.horario}.`,
          telefono: LINEA_DE_LA_VIDA.tel,
        }
      : {
          titulo: 'Esto es un tamizaje, no un diagnóstico',
          texto: 'El PHQ-8 y el GAD-7 detectan señales de ánimo bajo y ansiedad en las últimas 2 semanas. Solo un profesional puede darte un diagnóstico.',
        },
    tituloLista: 'Recursos y recomendaciones',
    lista: getRecursosPsico(answers),
    tips: getTipsPsico(answers),
  }
}

async function guardar(answers) {
  await api.post('/Cuestionario/GuardarPsicologico.php', payloadPsicologia(answers))
  return { mensaje: 'Perfil psicológico guardado exitosamente.', ruta: '/psicologia' }
}

export default function CuestionarioPsicologia() {
  return (
    <Cuestionario
      titulo="Cuestionario Psicológico"
      tituloResultados="Resultados Psicológicos"
      color="#7C3AED" colorRgb="124,58,237"
      preguntas={PSICOLOGIA}
      resultados={resultados}
      guardar={guardar}
      textoGuardar="Guardar perfil y ver módulo de psicología →"
    />
  )
}
