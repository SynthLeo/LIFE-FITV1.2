import { lazy, Suspense } from 'react'
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom'
import { AuthProvider, useAuth } from '@/context/AuthContext'
import { ThemeProvider } from '@/context/ThemeContext'
import ProtectedRoute from '@/presentacion/componentes/ProtectedRoute'
import AppLayout from '@/presentacion/componentes/layout/AppLayout'

import LoginPage              from '@/presentacion/paginas/ejercicio/LoginPage'
import RegistroPage           from '@/presentacion/paginas/ejercicio/RegistroPage'
const OnboardingPage = lazy(() => import('@/presentacion/paginas/ejercicio/OnboardingPage'))
const CuestionarioPage = lazy(() => import('@/presentacion/paginas/ejercicio/CuestionarioPage'))
import DashboardPage          from '@/presentacion/paginas/ejercicio/DashboardPage'
const CatalogoPage = lazy(() => import('@/presentacion/paginas/ejercicio/CatalogoPage'))
const RutinasPage = lazy(() => import('@/presentacion/paginas/ejercicio/RutinasPage'))
const EntrenamientoPage = lazy(() => import('@/presentacion/paginas/ejercicio/EntrenamientoPage'))
const EstadisticasPage = lazy(() => import('@/presentacion/paginas/ejercicio/EstadisticasPage'))
const SocialPage = lazy(() => import('@/presentacion/paginas/ejercicio/SocialPage'))
const Clinico = lazy(() => import('@/presentacion/paginas/clinico/Clinico'))
const Dieta = lazy(() => import('@/presentacion/paginas/dieta/Dieta'))
const Psicologia = lazy(() => import('@/presentacion/paginas/psicologia/Psicologia'))
const SaludPage = lazy(() => import('@/presentacion/paginas/salud/SaludPage'))

const CuestionarioClinico = lazy(() => import('@/presentacion/paginas/clinico/CuestionarioClinico'))
const CuestionarioPsicologia = lazy(() => import('@/presentacion/paginas/psicologia/CuestionarioPsicologia'))
const CuestionarioAlimentacion = lazy(() => import('@/presentacion/paginas/dieta/CuestionarioAlimentacion'))
import { ConConsentimiento }     from '@/presentacion/componentes/Consentimiento'

function AuthRedirect() {
  const { isAuthenticated, needsOnboarding } = useAuth()
  if (!isAuthenticated)  return <Navigate to="/login"      replace />
  if (needsOnboarding)   return <Navigate to="/onboarding" replace />
  return <Navigate to="/dashboard" replace />
}

function AppRoutes() {
  return (
    <Suspense fallback={<div role="status" className="min-h-screen grid place-items-center text-sm text-muted-foreground">Cargando…</div>}>
    <Routes>
      {/* Públicas */}
      <Route path="/login"    element={<LoginPage />} />
      <Route path="/registro" element={<RegistroPage />} />

      {/* Onboarding */}
      <Route path="/onboarding" element={
        <ProtectedRoute requireOnboarding={false}><ConConsentimiento><OnboardingPage /></ConConsentimiento></ProtectedRoute>
      } />

      {/* Cuestionarios */}
      <Route path="/cuestionario" element={
        <ProtectedRoute><ConConsentimiento><CuestionarioPage /></ConConsentimiento></ProtectedRoute>
      } />
      <Route path="/cuestionario-clinico" element={
        <ProtectedRoute><ConConsentimiento><CuestionarioClinico /></ConConsentimiento></ProtectedRoute>
      } />
      <Route path="/cuestionario-psicologia" element={
        <ProtectedRoute><ConConsentimiento><CuestionarioPsicologia /></ConConsentimiento></ProtectedRoute>
      } />
      <Route path="/cuestionario-alimentacion" element={
        <ProtectedRoute><ConConsentimiento><CuestionarioAlimentacion /></ConConsentimiento></ProtectedRoute>
      } />

      {/* App principal */}
      <Route path="/dashboard" element={
        <ProtectedRoute><AppLayout><DashboardPage /></AppLayout></ProtectedRoute>
      } />
      <Route path="/catalogo" element={
        <ProtectedRoute><AppLayout><CatalogoPage /></AppLayout></ProtectedRoute>
      } />
      <Route path="/rutinas" element={
        <ProtectedRoute><AppLayout><RutinasPage /></AppLayout></ProtectedRoute>
      } />
      <Route path="/entrena" element={
        <ProtectedRoute><AppLayout><EntrenamientoPage /></AppLayout></ProtectedRoute>
      } />
      <Route path="/estadisticas" element={
        <ProtectedRoute><AppLayout><EstadisticasPage /></AppLayout></ProtectedRoute>
      } />
      <Route path="/social" element={
        <ProtectedRoute><AppLayout><SocialPage /></AppLayout></ProtectedRoute>
      } />
      <Route path="/salud" element={
        <ProtectedRoute><AppLayout><SaludPage /></AppLayout></ProtectedRoute>
      } />
      <Route path="/clinico" element={
        <ProtectedRoute><AppLayout><Clinico /></AppLayout></ProtectedRoute>
      } />
      <Route path="/dieta" element={
        <ProtectedRoute><AppLayout><Dieta /></AppLayout></ProtectedRoute>
      } />
      <Route path="/psicologia" element={
        <ProtectedRoute><AppLayout><Psicologia /></AppLayout></ProtectedRoute>
      } />

      <Route path="*" element={<AuthRedirect />} />
    </Routes>
    </Suspense>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <ThemeProvider>
        <AuthProvider>
          <AppRoutes />
        </AuthProvider>
      </ThemeProvider>
    </BrowserRouter>
  )
}