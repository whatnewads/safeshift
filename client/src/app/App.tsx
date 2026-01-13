import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext.js';
import { SyncProvider } from './contexts/SyncContext.js';
import { ShiftProvider } from './contexts/ShiftContext.js';
import { EncounterProvider } from './contexts/EncounterContext.js';
import { DarkModeProvider } from './contexts/DarkModeContext.js';
import { Toaster } from './components/ui/sonner.js';
import { ErrorBoundary } from './components/ErrorBoundary.js';
import { SessionWarningModal } from './components/session/SessionWarningModal.js';
import { useSessionManagement } from './hooks/useSessionManagement.js';

// Auth pages
import WelcomePage from './pages/auth/Welcome.js';
import LoginPage from './pages/auth/Login.js';
import TwoFactorPage from './pages/auth/TwoFactor.js';
import SetupPage from './pages/auth/Setup.js';

// Dashboard pages
import ClinicalProviderDashboard from './pages/dashboards/ClinicalProvider.js';
import RegistrationDashboard from './pages/dashboards/Registration.js';
import AdminDashboard from './pages/dashboards/Admin.js';
import SuperAdminDashboard from './pages/dashboards/SuperAdmin.js';

// Feature pages
import StartEncounterPage from './pages/encounters/StartEncounter.js';
import EncounterWorkspacePage from './pages/encounters/EncounterWorkspace.js';
import PatientsPage from './pages/Patients.js';
import AssessmentsDemo from './pages/AssessmentsDemo.js';
import SettingsPage from './pages/settings/Settings.js';
import SubmitTicketPage from './pages/feedback/SubmitTicket.js';
import SubmitBugPage from './pages/feedback/SubmitBug.js';
import RequestFeaturePage from './pages/feedback/RequestFeature.js';
import NotificationsPage from './pages/Notifications.js';

// Video Meeting pages
import VideoMeeting from './pages/VideoMeeting.js';
import VideoMeetingJoin from './pages/VideoMeetingJoin.js';
import { VideoMeetingPage } from './components/VideoMeeting/VideoMeetingPage.js';

// Layout
import DashboardLayout from './components/layout/DashboardLayout.js';

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, loading } = useAuth();
  
  // Show nothing while loading to prevent flash of redirect
  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-900">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-slate-600 dark:text-slate-400">Loading...</p>
        </div>
      </div>
    );
  }
  
  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }
  
  return <>{children}</>;
}

function AuthRoutes() {
  const { isAuthenticated, stage } = useAuth();
  
  // Show TwoFactor page when in OTP stage
  if (stage === 'otp') {
    return <TwoFactorPage />;
  }
  
  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }
  
  return <LoginPage />;
}

function DashboardRouter() {
  const { user } = useAuth();
  
  if (!user) return <Navigate to="/login" replace />;
  
  const role = user.currentRole;
  
  // Route to appropriate dashboard based on role
  switch (role) {
    case 'provider':
      return <ClinicalProviderDashboard />;
    case 'registration':
      return <RegistrationDashboard />;
    case 'admin':
      return <AdminDashboard />;
    case 'super-admin':
      return <SuperAdminDashboard />;
    default:
      return <ClinicalProviderDashboard />;
  }
}

/**
 * SessionManager component handles session timeout warnings
 * Must be inside AuthProvider to use auth hooks
 */
function SessionManager({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuth();
  const {
    showWarning,
    remainingSeconds,
    extendSession,
    forceLogout,
  } = useSessionManagement();

  // Only show warning when authenticated
  if (!isAuthenticated) {
    return <>{children}</>;
  }

  return (
    <>
      {children}
      <SessionWarningModal
        isOpen={showWarning}
        remainingSeconds={remainingSeconds}
        onExtend={extendSession}
        onLogout={forceLogout}
        onExpired={forceLogout}
      />
    </>
  );
}

export default function App() {
  return (
    <ErrorBoundary>
      <BrowserRouter>
        <AuthProvider>
          <DarkModeProvider>
            <SyncProvider>
              <ShiftProvider>
                <EncounterProvider>
                  <SessionManager>
                    <Routes>
                    {/* Public routes */}
                    <Route path="/" element={<WelcomePage />} />
                    <Route path="/login" element={<AuthRoutes />} />
                    <Route path="/2fa" element={<TwoFactorPage />} />
                    <Route path="/setup" element={<SetupPage />} />
                    
                    {/* Protected routes */}
                    <Route
                      path="/dashboard"
                      element={
                        <ProtectedRoute>
                          <DashboardLayout>
                            <DashboardRouter />
                          </DashboardLayout>
                        </ProtectedRoute>
                      }
                    />
                    
                    <Route
                      path="/encounters/start"
                      element={
                        <ProtectedRoute>
                          <DashboardLayout>
                            <StartEncounterPage />
                          </DashboardLayout>
                        </ProtectedRoute>
                      }
                    />
                    
                    <Route
                      path="/encounters/:id"
                      element={
                        <ProtectedRoute>
                          <DashboardLayout>
                            <EncounterWorkspacePage />
                          </DashboardLayout>
                        </ProtectedRoute>
                      }
                    />
                    
                    <Route
                      path="/encounters/workspace"
                      element={
                        <ProtectedRoute>
                          <DashboardLayout>
                            <EncounterWorkspacePage />
                          </DashboardLayout>
                        </ProtectedRoute>
                      }
                    />
                    
                    <Route
                      path="/patients"
                      element={
                        <ProtectedRoute>
                          <DashboardLayout>
                            <PatientsPage />
                          </DashboardLayout>
                        </ProtectedRoute>
                      }
                    />
                    
                    <Route
                      path="/assessments"
                      element={
                        <ProtectedRoute>
                          <AssessmentsDemo />
                        </ProtectedRoute>
                      }
                    />
                    
                    <Route
                      path="/settings"
                      element={
                        <ProtectedRoute>
                          <DashboardLayout>
                            <SettingsPage />
                          </DashboardLayout>
                        </ProtectedRoute>
                      }
                    />
                    
                    <Route
                      path="/notifications"
                      element={
                        <ProtectedRoute>
                          <DashboardLayout>
                            <NotificationsPage />
                          </DashboardLayout>
                        </ProtectedRoute>
                      }
                    />
                    
                    <Route
                      path="/submit-ticket"
                      element={
                        <ProtectedRoute>
                          <DashboardLayout>
                            <SubmitTicketPage />
                          </DashboardLayout>
                        </ProtectedRoute>
                      }
                    />
                    
                    <Route
                      path="/submit-bug"
                      element={
                        <ProtectedRoute>
                          <DashboardLayout>
                            <SubmitBugPage />
                          </DashboardLayout>
                        </ProtectedRoute>
                      }
                    />
                    
                    <Route
                      path="/request-feature"
                      element={
                        <ProtectedRoute>
                          <DashboardLayout>
                            <RequestFeaturePage />
                          </DashboardLayout>
                          </ProtectedRoute>
                      }
                    />
                    
                    {/* Video Meeting routes */}
                    <Route
                      path="/video"
                      element={
                        <ProtectedRoute>
                          <DashboardLayout>
                            <VideoMeeting />
                          </DashboardLayout>
                        </ProtectedRoute>
                      }
                    />
                    
                    {/* Public video meeting join page (no auth required) */}
                    <Route
                      path="/video/join"
                      element={<VideoMeetingJoin />}
                    />
                    
                    {/* Active video meeting page */}
                    <Route
                      path="/video/meeting/:meetingId"
                      element={
                        <ProtectedRoute>
                          <VideoMeetingPage />
                        </ProtectedRoute>
                      }
                    />
                    
                    {/* Catch all */}
                    <Route path="*" element={<Navigate to="/dashboard" replace />} />
                    </Routes>
                    <Toaster />
                  </SessionManager>
                </EncounterProvider>
              </ShiftProvider>
            </SyncProvider>
          </DarkModeProvider>
        </AuthProvider>
      </BrowserRouter>
    </ErrorBoundary>
  );
}