import api from '@/lib/api'
import { EJERCICIO, evaluarEjercicio, payloadEjercicio } from '@/modulos/cuestionarios/instrumentos'
import Cuestionario from '@/presentacion/componentes/cuestionario/Cuestionario'

/* ─────────────────────────────────────────────
   SCORING / RUTINAS / TIPS
───────────────────────────────────────────── */
function calcFitnessScore(ans) {
  return evaluarEjercicio(ans).score
}

// Nivel según la categoría IPAQ; con algún "Sí" en el PAR-Q+ se queda en principiante
function getNivel(_score, ans) {
  return evaluarEjercicio(ans).nivel
}

function getRutinas(ans) {
  const ev = evaluarEjercicio(ans)
  const nivel = ev.nivel
  const nivelBadge = n => n === 'avanzado' ? 'high' : n === 'intermedio' ? 'med' : ''
  if (ev.parqSi.length) {
    return [{ icon: '🩺', name: 'Rutina de Bajo Impacto', desc: 'Caminata y ejercicios suaves mientras consultas a un profesional de la salud, como indica el PAR-Q+.', badge: 'baja intensidad', badgeClass: '' }]
  }
  const rutinas = []
  const enCasa = ans.lugar_ejercicio === 'casa' || ans.lugar_ejercicio === 'ninguno' || ans.equipo === 'peso_corporal' || ans.equipo === 'bandas'
  if (ans.objetivo_ejercicio === 'resistencia' || ans.motivacion === 'rendimiento') {
    const names = { principiante: 'Cardio Base 3x Semana', intermedio: 'Endurance Circuit', avanzado: 'HIIT Performance' }
    rutinas.push({ icon: '🏃', name: names[nivel], desc: 'Mejora tu capacidad cardiovascular y tu resistencia.', badge: nivel, badgeClass: nivelBadge(nivel) })
  }
  if (['fuerza', 'masa_muscular', 'perdida_grasa'].includes(ans.objetivo_ejercicio) || ans.motivacion === 'estetica' || ans.motivacion === 'salud') {
    if (ans.lugar_ejercicio === 'exterior') {
      rutinas.push({ icon: '🌳', name: 'Entrenamiento Funcional al Aire Libre', desc: 'Fuerza y cardio combinados en parque o pista.', badge: nivel, badgeClass: nivelBadge(nivel) })
    } else if (enCasa) {
      const names = { principiante: 'Calistenia Inicial', intermedio: 'Fuerza en Casa', avanzado: 'Calistenia Avanzada' }
      rutinas.push({ icon: '🤸', name: names[nivel], desc: 'Fuerza con peso corporal o bandas, sin gimnasio.', badge: nivel, badgeClass: nivelBadge(nivel) })
    } else {
      const names = { principiante: 'Full Body Gym (3x)', intermedio: 'Hipertrofia', avanzado: 'Fuerza y Potencia' }
      rutinas.push({ icon: '🏋️', name: names[nivel], desc: 'Rutina de gimnasio con máquinas y pesas libres.', badge: nivel, badgeClass: nivelBadge(nivel) })
    }
  }
  if (ev.categoria === 'bajo') {
    rutinas.push({ icon: '🚶', name: 'Programa de Inicio Activo', desc: 'Empieza con 20 a 30 minutos de caminata la mayoría de los días y súbele poco a poco.', badge: 'principiante', badgeClass: '' })
  }
  if (ans.ipaq_sentado === '7_9' || ans.ipaq_sentado === '10_mas') {
    rutinas.push({ icon: '💺', name: 'Rutina Anti-Sedentarismo', desc: 'Pausas activas de 3 a 5 minutos cada hora que pasas sentado/a.', badge: 'complementaria', badgeClass: '' })
  }
  if (rutinas.length === 0) {
    rutinas.push({ icon: '✨', name: 'Rutina General de Bienestar', desc: 'Combina fuerza, cardio y flexibilidad según tu perfil.', badge: nivel, badgeClass: nivelBadge(nivel) })
  }
  return rutinas
}

function getTips(ans) {
  const ev = evaluarEjercicio(ans)
  const tips = []
  if (ev.parqSi.length)
    tips.push({ icon: '🩺', text: 'Respondiste "Sí" en el PAR-Q+. Antes de aumentar tu actividad, consulta a un médico o profesional del ejercicio.' })
  if (ans.ipaq_sentado === '10_mas' || ans.ipaq_sentado === '7_9')
    tips.push({ icon: '⏰', text: 'Levántate y muévete unos minutos cada hora. Estar mucho tiempo sentado afecta tu salud aunque hagas ejercicio.' })
  if (ev.categoria === 'bajo')
    tips.push({ icon: '🌱', text: 'La OMS recomienda al menos 60 minutos diarios de actividad moderada a vigorosa entre los 5 y 17 años, y de 150 a 300 minutos por semana en adultos.' })
  if (ev.detalle.vigD === 0 && ev.categoria !== 'bajo')
    tips.push({ icon: '🔥', text: 'Agrega 2 o 3 días de actividad vigorosa a la semana: con menos tiempo obtienes más beneficio.' })
  if (tips.length === 0)
    tips.push({ icon: '📈', text: 'Tu nivel de actividad es alto. Periodiza tu entrenamiento y respeta los días de descanso para seguir progresando.' })
  tips.push({ icon: '🔥', text: 'Calienta 5 a 10 minutos antes de cada sesión para reducir el riesgo de lesiones.' })
  return tips.slice(0, 4)
}

const ICONOS    = { principiante: '🌱', intermedio: '⚡', avanzado: '🔥' }
const ETIQUETAS = { principiante: 'Perfil Principiante', intermedio: 'Perfil Intermedio', avanzado: 'Perfil Avanzado' }
const IPAQ      = { bajo: 'Bajo', moderado: 'Moderado', alto: 'Alto' }

function resultados(answers) {
  const score = calcFitnessScore(answers)
  const nivel = getNivel(score, answers)
  const ev    = evaluarEjercicio(answers)
  const n     = ev.parqSi.length
  return {
    score,
    etiquetaScore: 'Score de condición física',
    icono: ICONOS[nivel],
    nivel: ETIQUETAS[nivel],
    metricas: [
      { val: `${score}/100`, lbl: 'Score fitness' },
      { val: nivel.charAt(0).toUpperCase() + nivel.slice(1), lbl: 'Nivel recomendado' },
      { val: ev.met.toLocaleString('es-MX'), lbl: 'MET-min por semana' },
      { val: IPAQ[ev.categoria], lbl: 'Actividad (IPAQ)' },
    ],
    aviso: n > 0 ? {
      alerta: true,
      titulo: '🩺 Consulta antes de aumentar tu actividad',
      texto: `Respondiste "Sí" a ${n} pregunta${n > 1 ? 's' : ''} del PAR-Q+. Antes de hacer ejercicio más intenso, platícalo con un médico o un profesional del ejercicio y, si te lo piden, completa el PAR-Q+ completo o el ePARmed-X+.`,
    } : null,
    tituloLista: 'Rutinas recomendadas',
    lista: getRutinas(answers).map(r => ({ ...r, titulo: r.name })),
    tips: getTips(answers),
  }
}

async function guardar(answers, avisar) {
  await api.post('/Cuestionario/Guardar.php', payloadEjercicio(answers))
  // Con el perfil guardado se genera la rutina. Si la IA falla, el perfil ya quedó.
  avisar('Generando tu rutina con IA...')
  try {
    await api.post('/IA/GenerarRutina.php', {})
    return { mensaje: '¡Rutina generada! Redirigiendo...', ruta: '/rutinas' }
  } catch {
    return { mensaje: 'Perfil guardado. Puedes generar tu rutina desde Rutinas.', ruta: '/rutinas' }
  }
}

export default function CuestionarioPage() {
  return (
    <Cuestionario
      titulo="Cuestionario de Ejercicio"
      tituloResultados="Resultados"
      color="#0891B2" colorRgb="8,145,178"
      preguntas={EJERCICIO}
      resultados={resultados}
      guardar={guardar}
      textoGuardar="Guardar perfil y generar mi rutina →"
    />
  )
}
