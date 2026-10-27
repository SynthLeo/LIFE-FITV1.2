import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'

// Fuentes empaquetadas con la app (antes no cargaban nunca)
import '@fontsource/bebas-neue'
import '@fontsource-variable/inter'

import './index.css'

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
)
