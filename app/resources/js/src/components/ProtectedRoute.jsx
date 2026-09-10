import React from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { saveRedirectPath } from '../auth/redirect';

export default function ProtectedRoute({ children }) {
    const { user, loading } = useAuth();
    const location = useLocation();

    if (loading) {
        return (
            <div className="text-center py-5">
                <div className="spinner-border text-info" role="status" />
            </div>
        );
    }

    if (!user) {
        saveRedirectPath(`${location.pathname}${location.search || ''}`);
        return <Navigate to="/login" replace />;
    }

    return children;
}
