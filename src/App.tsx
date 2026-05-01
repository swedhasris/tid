import React, { Suspense, lazy } from "react";
import { BrowserRouter as Router, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider, useAuth } from "./contexts/AuthContext";
import { Sidebar } from "./components/Sidebar";
import { AppNavbar } from "./components/AppNavbar";
import { ErrorBoundary } from "./components/ErrorBoundary";
import { AIChatbot } from "./components/AIChatbot";
import { seedInitialData } from "./lib/seed";
import { useEffect } from "react";

// Lazy loaded components
const Dashboard = lazy(() => import("./pages/Dashboard").then(m => ({ default: m.Dashboard })));
const Tickets = lazy(() => import("./pages/Tickets").then(m => ({ default: m.Tickets })));
const TicketDetail = lazy(() => import("./pages/TicketDetail").then(m => ({ default: m.TicketDetail })));
const GlobalHistory = lazy(() => import("./pages/GlobalHistory").then(m => ({ default: m.GlobalHistory })));
const SLAManagement = lazy(() => import("./pages/SLAManagement").then(m => ({ default: m.SLAManagement })));
const Approvals = lazy(() => import("./pages/Approvals").then(m => ({ default: m.Approvals })));
const Users = lazy(() => import("./pages/Users").then(m => ({ default: m.Users })));
const Reports = lazy(() => import("./pages/Reports").then(m => ({ default: m.Reports })));
const Settings = lazy(() => import("./pages/Settings").then(m => ({ default: m.Settings })));
const ServiceCatalog = lazy(() => import("./pages/ServiceCatalog").then(m => ({ default: m.ServiceCatalog })));
const Conversations = lazy(() => import("./pages/Conversations").then(m => ({ default: m.Conversations })));
const ProblemManagement = lazy(() => import("./pages/ProblemManagement").then(m => ({ default: m.ProblemManagement })));
const ChangeManagement = lazy(() => import("./pages/ChangeManagement").then(m => ({ default: m.ChangeManagement })));
const KnowledgeBase = lazy(() => import("./pages/KnowledgeBase").then(m => ({ default: m.KnowledgeBase })));
const ServicePortal = lazy(() => import("./pages/ServicePortal").then(m => ({ default: m.ServicePortal })));
const Login = lazy(() => import("./pages/Login").then(m => ({ default: m.Login })));
const Register = lazy(() => import("./pages/Register").then(m => ({ default: m.Register })));
const Timesheet = lazy(() => import("./pages/Timesheet").then(m => ({ default: m.Timesheet })));
const TimesheetReports = lazy(() => import("./pages/TimesheetReports").then(m => ({ default: m.TimesheetReports })));
const TimesheetApprovals = lazy(() => import("./pages/TimesheetApprovals").then(m => ({ default: m.TimesheetApprovals })));
const AccessControl = lazy(() => import("./pages/AccessControl").then(m => ({ default: m.AccessControl })));
const ApprovedTickets = lazy(() => import("./pages/ApprovedTickets").then(m => ({ default: m.ApprovedTickets })));

function LoadingScreen() {
  return (
    <div className="min-h-screen flex items-center justify-center bg-sn-dark">
      <div className="w-12 h-12 border-4 border-sn-green border-t-transparent rounded-full animate-spin" />
    </div>
  );
}

function ProtectedRoute({ children, minRole }: { children: React.ReactNode; minRole?: string }) {
  const { user, profile, loading } = useAuth();
  const LEVELS: Record<string,number> = { user:1, agent:2, sub_admin:3, admin:4, super_admin:5, ultra_super_admin:6 };

  if (loading) return <LoadingScreen />;
  if (!user) return <Navigate to="/login" />;

  if (minRole) {
    const userLevel = LEVELS[profile?.role || "user"] || 1;
    const required  = LEVELS[minRole] || 1;
    if (userLevel < required) {
      return (
        <div className="flex min-h-screen bg-background">
          <Sidebar />
          <div className="flex-grow flex flex-col overflow-hidden">
            <AppNavbar />
            <main className="flex-grow p-8 overflow-y-auto flex items-center justify-center">
              <div className="text-center space-y-4">
                <div className="text-6xl">🔒</div>
                <h2 className="text-2xl font-bold">Access Restricted</h2>
                <p className="text-muted-foreground">You need <strong>{minRole.replace("_"," ")}</strong> access or above.</p>
              </div>
            </main>
          </div>
        </div>
      );
    }
  }

  return (
    <div className="flex min-h-screen bg-background">
      <Sidebar />
      <div className="flex-grow flex flex-col overflow-hidden">
        <AppNavbar />
        <main className="flex-grow p-8 overflow-y-auto">
          <ErrorBoundary>
            <Suspense fallback={<LoadingScreen />}>
              {children}
            </Suspense>
          </ErrorBoundary>
        </main>
      </div>
      <AIChatbot />
    </div>
  );
}

function HomeRedirect() {
  const { loading } = useAuth();
  if (loading) return <LoadingScreen />;
  return <Dashboard />;
}

export default function App() {
  return (
    <AuthProvider>
      <AppBody />
    </AuthProvider>
  );
}

function AppBody() {
  const { user } = useAuth();
  
  useEffect(() => {
    if (user) {
      seedInitialData();
    }
  }, [user]);

  return (
    <Router>
      <Suspense fallback={<LoadingScreen />}>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/register" element={<Register />} />
            <Route 
              path="/" 
              element={
                <ProtectedRoute>
                  <HomeRedirect />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/tickets" 
              element={
                <ProtectedRoute>
                  <Tickets />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/tickets/:id" 
              element={
                <ProtectedRoute>
                  <TicketDetail />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/history" 
              element={
                <ProtectedRoute>
                  <GlobalHistory />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/sla" 
              element={
                <ProtectedRoute>
                  <SLAManagement />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/approvals" 
              element={
                <ProtectedRoute>
                  <Approvals />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/users" 
              element={
                <ProtectedRoute minRole="admin">
                  <Users />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/reports" 
              element={
                <ProtectedRoute>
                  <Reports />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/catalog" 
              element={
                <ProtectedRoute>
                  <ServiceCatalog />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/conversations" 
              element={
                <ProtectedRoute>
                  <Conversations />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/problem" 
              element={
                <ProtectedRoute>
                  <ProblemManagement />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/change" 
              element={
                <ProtectedRoute>
                  <ChangeManagement />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/kb" 
              element={
                <ProtectedRoute>
                  <KnowledgeBase />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/settings" 
              element={
                <ProtectedRoute minRole="admin">
                  <Settings />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/timesheet" 
              element={
                <ProtectedRoute>
                  <Timesheet />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/timesheet/reports" 
              element={
                <ProtectedRoute>
                  <TimesheetReports />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/timesheet/approvals" 
              element={
                <ProtectedRoute minRole="admin">
                  <TimesheetApprovals />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/access-control" 
              element={
                <ProtectedRoute minRole="admin">
                  <AccessControl />
                </ProtectedRoute>
              } 
            />
            <Route 
              path="/approved-tickets" 
              element={
                <ProtectedRoute>
                  <ApprovedTickets />
                </ProtectedRoute>
              } 
            />
            <Route path="*" element={<Navigate to="/" />} />
          </Routes>
        </Suspense>
      </Router>
    );
  }
