import React from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';
import AppHeader from './components/AppHeader';
import ProtectedRoute from './components/ProtectedRoute';
import AdminRoute from './components/AdminRoute';
import LoginPage from './pages/LoginPage';
import AcceptInvitePage from './pages/AcceptInvitePage';
import DashboardPage from './pages/DashboardPage';
import ExplorerPage from './pages/ExplorerPage';
import ExportPage from './pages/ExportPage';
import SavedAnalysesPage from './pages/SavedAnalysesPage';
import CustomDashboardsPage from './pages/CustomDashboardsPage';
import ProductsPage from './pages/admin/ProductsPage';
import CredentialsPage from './pages/admin/CredentialsPage';
import UsersPage from './pages/admin/UsersPage';
import InvitesPage from './pages/admin/InvitesPage';
import DeletionsPage from './pages/admin/DeletionsPage';
import AuditPage from './pages/admin/AuditPage';

function AppLayout({ children }) {
    return (
        <div className="telemetry-app-frame">
            <AppHeader />
            <main className="container-fluid py-3 telemetry-main">
                {children}
            </main>
        </div>
    );
}

function ProtectedLayout({ children }) {
    return (
        <ProtectedRoute>
            <AppLayout>{children}</AppLayout>
        </ProtectedRoute>
    );
}

function AdminLayout({ children }) {
    return (
        <ProtectedRoute>
            <AdminRoute>
                <AppLayout>{children}</AppLayout>
            </AdminRoute>
        </ProtectedRoute>
    );
}

export default function App() {
    return (
        <Routes>
            <Route path="/login" element={<LoginPage />} />
            <Route path="/invite/:token" element={<AcceptInvitePage />} />

            <Route path="/" element={<ProtectedLayout><DashboardPage slug="overview" title="Overview" /></ProtectedLayout>} />
            <Route path="/usage" element={<ProtectedLayout><DashboardPage slug="product-usage" title="Product Usage" /></ProtectedLayout>} />
            <Route path="/navigation" element={<ProtectedLayout><DashboardPage slug="navigation-views" title="Navigation / Views" /></ProtectedLayout>} />
            <Route path="/sessions" element={<ProtectedLayout><DashboardPage slug="sessions" title="Sessions" /></ProtectedLayout>} />
            <Route path="/funnels" element={<ProtectedLayout><DashboardPage slug="funnels" title="Funnels" /></ProtectedLayout>} />
            <Route path="/errors" element={<ProtectedLayout><DashboardPage slug="errors-reliability" title="Errors & Reliability" /></ProtectedLayout>} />
            <Route path="/latency" element={<ProtectedLayout><DashboardPage slug="latency-performance" title="Latency & Performance" /></ProtectedLayout>} />
            <Route path="/ops" element={<ProtectedLayout><DashboardPage slug="operational-health" title="Operational Health" /></ProtectedLayout>} />
            <Route path="/explorer" element={<ProtectedLayout><ExplorerPage /></ProtectedLayout>} />
            <Route path="/export" element={<ProtectedLayout><ExportPage /></ProtectedLayout>} />
            <Route path="/saved-analyses" element={<ProtectedLayout><SavedAnalysesPage /></ProtectedLayout>} />
            <Route path="/dashboards/custom" element={<ProtectedLayout><CustomDashboardsPage /></ProtectedLayout>} />

            <Route path="/admin/products" element={<AdminLayout><ProductsPage /></AdminLayout>} />
            <Route path="/admin/credentials" element={<AdminLayout><CredentialsPage /></AdminLayout>} />
            <Route path="/admin/users" element={<AdminLayout><UsersPage /></AdminLayout>} />
            <Route path="/admin/invites" element={<AdminLayout><InvitesPage /></AdminLayout>} />
            <Route path="/admin/deletions" element={<AdminLayout><DeletionsPage /></AdminLayout>} />
            <Route path="/admin/audit" element={<AdminLayout><AuditPage /></AdminLayout>} />

            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}
