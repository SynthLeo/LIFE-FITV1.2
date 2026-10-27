// src/lib/exportar.js
// Descarga de rutina y dieta en PDF / CSV. Todo se arma en el navegador.
// Regla: aquí NUNCA entran datos sensibles (PHQ-8, GAD-7, AUDIT-C, citas, signos vitales).

const DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']
const VERDE = [22, 163, 74]

// jsPDF con la fuente estándar no dibuja emojis: se quitan para que no salgan símbolos raros
const limpio = (t) =>
  String(t ?? '')
    .replace(/[\p{Extended_Pictographic}️‍]/gu, '')
    .replace(/\s{2,}/g, ' ')
    .trim()

const fechaHoy = () => new Date().toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
const nombreArchivo = (base) => `${base}-${new Date().toISOString().slice(0, 10)}`

async function nuevoPDF(titulo, subtitulo) {
  // Carga perezosa: la librería solo se baja cuando alguien descarga
  const [{ jsPDF }, at] = await Promise.all([import('jspdf'), import('jspdf-autotable')])
  const autoTable = [at.autoTable, at.default, at.default?.default, at.default?.autoTable].find(f => typeof f === 'function')
  const doc = new jsPDF({ unit: 'pt', format: 'letter' })
  doc.setFillColor(...VERDE)
  doc.rect(0, 0, doc.internal.pageSize.getWidth(), 6, 'F')
  doc.setFont('helvetica', 'bold').setFontSize(18).setTextColor(20)
  doc.text(limpio(titulo), 40, 48)
  doc.setFont('helvetica', 'normal').setFontSize(10).setTextColor(110)
  doc.text(limpio(subtitulo), 40, 66)
  return { doc, autoTable, y: 84 }
}

function pie(doc, aviso) {
  const n = doc.getNumberOfPages()
  const ancho = doc.internal.pageSize.getWidth()
  const alto = doc.internal.pageSize.getHeight()
  for (let i = 1; i <= n; i++) {
    doc.setPage(i)
    doc.setFontSize(8).setTextColor(130)
    if (aviso) {
      const lineas = doc.splitTextToSize(limpio(aviso), ancho - 80)
      doc.text(lineas, 40, alto - 32 - (lineas.length - 1) * 10)
    }
    doc.text(`LIFE FIT · ${fechaHoy()} · Página ${i} de ${n}`, 40, alto - 20)
  }
}

const estiloTabla = {
  theme: 'striped',
  styles: { font: 'helvetica', fontSize: 9, cellPadding: 5, overflow: 'linebreak' },
  headStyles: { fillColor: VERDE, textColor: 255 },
  margin: { left: 40, right: 40, bottom: 80 },
}

const AVISO_DIETA  = 'Plan orientativo generado con IA. No sustituye la consulta con un nutriólogo. Si tienes alguna enfermedad, alergia o estás embarazada, consúltalo antes.'
const AVISO_RUTINA = 'Calienta 5-10 minutos antes de entrenar. Detente si sientes dolor en el pecho, mareo o falta de aire anormal y busca atención médica.'

function parrafo(doc, texto, y) {
  doc.setFontSize(9).setTextColor(70)
  const lineas = doc.splitTextToSize(limpio(texto), doc.internal.pageSize.getWidth() - 80)
  doc.text(lineas, 40, y)
  return y + lineas.length * 12 + 8
}

function subtitulo(doc, texto, y) {
  doc.setFont('helvetica', 'bold').setFontSize(13).setTextColor(...VERDE)
  doc.text(limpio(texto), 40, y)
  doc.setFont('helvetica', 'normal')
  return y + 18
}

// ── DIETA ─────────────────────────────────────────────

function pintarDieta(doc, autoTable, plan, y) {
  if (plan.nota) y = parrafo(doc, plan.nota, y)
  for (const { dia, comidas } of plan.dias) {
    autoTable(doc, {
      ...estiloTabla,
      startY: y,
      head: [[limpio(dia), 'Comida', 'kcal']],
      body: (comidas ?? []).map((c) => [limpio(c.tiempo), limpio(c.descripcion), c.kcal ?? '']),
      columnStyles: { 0: { cellWidth: 80 }, 2: { cellWidth: 45, halign: 'right' } },
    })
    y = doc.lastAutoTable.finalY + 14
  }
  return y
}

export async function descargarDietaPDF(plan, { nombre } = {}) {
  if (!plan?.dias?.length) throw new Error('No hay plan para descargar.')
  const sub = [nombre, plan.calorias_dia ? `~${plan.calorias_dia} kcal por día` : null, fechaHoy()].filter(Boolean).join(' · ')
  const { doc, autoTable, y } = await nuevoPDF('Plan de alimentación semanal', sub)
  pintarDieta(doc, autoTable, plan, y)
  pie(doc, AVISO_DIETA)
  doc.save(`${nombreArchivo('dieta-semanal')}.pdf`)
}

export function descargarDietaCSV(plan) {
  if (!plan?.dias?.length) throw new Error('No hay plan para descargar.')
  const celda = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`
  const filas = [['Día', 'Tiempo', 'Comida', 'kcal']]
  for (const { dia, comidas } of plan.dias) for (const c of comidas ?? []) filas.push([dia, c.tiempo, c.descripcion, c.kcal])
  // BOM para que Excel abra bien los acentos
  const csv = '\uFEFF' + filas.map((f) => f.map(celda).join(',')).join('\r\n')
  bajar(new Blob([csv], { type: 'text/csv;charset=utf-8' }), `${nombreArchivo('dieta-semanal')}.csv`)
}

// ── RUTINA ────────────────────────────────────────────

const subRutina = (r) => [r.objetivo?.replace('_', ' '), r.nivel, `${r.dias_semana} días por semana`].filter(Boolean).join(' · ')

function pintarRutina(doc, autoTable, rutina, y, parqConsultar) {
  if (parqConsultar) {
    doc.setFillColor(254, 243, 199).rect(40, y - 10, doc.internal.pageSize.getWidth() - 80, 34, 'F')
    doc.setFontSize(9).setTextColor(120, 53, 15)
    doc.text(doc.splitTextToSize('Tu PAR-Q+ indica consultar a un profesional de la salud antes de aumentar la intensidad. Esta rutina es de baja intensidad.', doc.internal.pageSize.getWidth() - 100), 50, y + 4)
    y += 40
  }
  if (rutina.descripcion) y = parrafo(doc, rutina.descripcion, y)
  const porDia = {}
  for (const e of rutina.ejercicios) (porDia[e.dia_semana] ??= []).push(e)
  for (const dia of Object.keys(porDia).sort((a, b) => a - b)) {
    autoTable(doc, {
      ...estiloTabla,
      startY: y,
      head: [[DIAS[dia] ?? `Día ${dia}`, 'Músculo', 'Series', 'Reps', 'Peso sug.']],
      body: porDia[dia].map((e) => [
        limpio(e.nombre),
        limpio(e.grupo_muscular),
        e.series_objetivo ?? '',
        e.reps_objetivo ?? '',
        Number(e.peso_sugerido) > 0 ? `${e.peso_sugerido} kg` : '—',
      ]),
      columnStyles: { 2: { halign: 'center', cellWidth: 45 }, 3: { halign: 'center', cellWidth: 45 }, 4: { halign: 'right', cellWidth: 60 } },
    })
    y = doc.lastAutoTable.finalY + 14
  }
  return y
}

export async function descargarRutinaPDF(rutina, { parqConsultar = false } = {}) {
  if (!rutina?.ejercicios?.length) throw new Error('La rutina no tiene ejercicios.')
  const { doc, autoTable, y } = await nuevoPDF(rutina.nombre || 'Mi rutina', `${subRutina(rutina)} · ${fechaHoy()}`)
  pintarRutina(doc, autoTable, rutina, y, parqConsultar)
  pie(doc, AVISO_RUTINA)
  doc.save(`${nombreArchivo(limpio(rutina.nombre || 'rutina').toLowerCase().replace(/[^a-z0-9áéíóúñ]+/gi, '-'))}.pdf`)
}

// ── TODO JUNTO (rutina activa + dieta) ───────────────

export async function descargarTodoPDF({ rutina, plan, parqConsultar = false }) {
  const hayRutina = rutina?.ejercicios?.length > 0
  const hayDieta  = plan?.dias?.length > 0
  if (!hayRutina && !hayDieta) throw new Error('Todavía no tienes rutina ni dieta para descargar.')
  const { doc, autoTable, y: y0 } = await nuevoPDF('Mi plan LIFE FIT', fechaHoy())
  let y = y0
  if (hayRutina) {
    y = subtitulo(doc, `Rutina: ${rutina.nombre || 'Mi rutina'}`, y)
    y = parrafo(doc, subRutina(rutina), y)
    y = pintarRutina(doc, autoTable, rutina, y, parqConsultar)
  }
  if (hayDieta) {
    if (hayRutina) { doc.addPage(); y = 50 }
    y = subtitulo(doc, `Dieta semanal${plan.calorias_dia ? ` · ~${plan.calorias_dia} kcal por día` : ''}`, y)
    pintarDieta(doc, autoTable, plan, y)
  }
  pie(doc, [hayRutina && AVISO_RUTINA, hayDieta && AVISO_DIETA].filter(Boolean).join(' '))
  doc.save(`${nombreArchivo('mi-plan-lifefit')}.pdf`)
}

function bajar(blob, nombre) {
  const url = URL.createObjectURL(blob)
  const a = Object.assign(document.createElement('a'), { href: url, download: nombre })
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
