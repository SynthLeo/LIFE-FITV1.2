// Exporta las opciones válidas de cada pregunta (y sus puntos) a JSON para el backend PHP.
// Uso:  node scripts/exportar-instrumentos.mjs ../backend/Configuracion/instrumentos.json
// Vuelve a correrlo cada vez que cambies src/modulos/cuestionarios/instrumentos.js
import { writeFileSync } from 'node:fs'
import { EJERCICIO, CLINICO, PSICOLOGIA, ALIMENTACION } from '../src/modulos/cuestionarios/instrumentos.js'

const exportar = preguntas => Object.fromEntries(preguntas.map(p => [p.id, {
  condicional: !!p.showIf,
  opciones: Object.fromEntries(p.opts.map(o => [o.val, {
    ...(o.pts !== undefined && { pts: o.pts }),
    ...(o.v !== undefined && { v: o.v }),
    ...(o.findrisc !== undefined && { findrisc: o.findrisc }),
  }])),
}]))

const salida = {
  _aviso: 'Archivo generado por scripts/exportar-instrumentos.mjs a partir de instrumentos.js. No lo edites a mano.',
  ejercicio: exportar(EJERCICIO),
  clinico: exportar(CLINICO),
  psicologia: exportar(PSICOLOGIA),
  alimentacion: exportar(ALIMENTACION),
}
const destino = process.argv[2] ?? 'instrumentos.json'
writeFileSync(destino, JSON.stringify(salida, null, 2) + '\n')
console.log('Escrito', destino, Object.fromEntries(Object.entries(salida).filter(([k]) => k[0] !== '_').map(([k, v]) => [k, Object.keys(v).length])))
