// src/infraestructura/api/Api.js
// Cliente para los módulos clínico, nutrición y psicología.
// Ya no es un segundo axios: usa el mismo `lib/api` (mismo JWT, misma URL,
// mismo manejo de 401). Solo cambia la forma de responder:
//   - devuelve directamente `res.data`
//   - en error lanza un Error con el mensaje del servidor

import api from '@/lib/api'

const TIMEOUT_NORMAL = 15_000
const TIMEOUT_IA     = 180_000   // el chat con un modelo local puede tardar

const opciones = (url, config = {}) => ({
  timeout: url?.toLowerCase().includes('/ia/') ? TIMEOUT_IA : TIMEOUT_NORMAL,
  ...config,
})

const soloDatos = (promesa) => promesa.then(
  (res) => res.data,
  (err) => Promise.reject(new Error(err.response?.data?.error ?? 'Error de conexión con el servidor.', { cause: err })),
)

const apiClient = {
  get:    (url, config)       => soloDatos(api.get(url, opciones(url, config))),
  delete: (url, config)       => soloDatos(api.delete(url, opciones(url, config))),
  post:   (url, data, config) => soloDatos(api.post(url, data, opciones(url, config))),
  put:    (url, data, config) => soloDatos(api.put(url, data, opciones(url, config))),
  patch:  (url, data, config) => soloDatos(api.patch(url, data, opciones(url, config))),
}

export default apiClient
