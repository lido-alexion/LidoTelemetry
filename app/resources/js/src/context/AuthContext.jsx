import React, {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import api from '../api';
import { ensureCsrfCookie, resetCsrfCookie } from '../auth/csrf';
import { consumeRedirectPath, saveRedirectPath } from '../auth/redirect';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [loading, setLoading] = useState(true);
    const [sessionExpired, setSessionExpired] = useState(false);
    const userRef = useRef(null);

    useEffect(() => {
        userRef.current = user;
    }, [user]);

    const refreshUser = useCallback(async () => {
        try {
            const res = await api.get('/auth/me');
            setUser(res.data.user || null);
            setSessionExpired(false);
            return res.data.user;
        } catch (error) {
            if (error?.response?.status === 401) {
                setUser(null);
            }
            return null;
        }
    }, []);

    useEffect(() => {
        let cancelled = false;

        (async () => {
            try {
                await ensureCsrfCookie();
            } catch {
                // Login will retry with force=true
            }
            await refreshUser();
            if (!cancelled) {
                setLoading(false);
            }
        })();

        const onUnauthorized = () => {
            const wasLoggedIn = Boolean(userRef.current);
            setUser(null);
            if (wasLoggedIn) {
                setSessionExpired(true);
                saveRedirectPath(window.location.pathname);
            }
        };

        window.addEventListener('telemetry-unauthorized', onUnauthorized);

        return () => {
            cancelled = true;
            window.removeEventListener('telemetry-unauthorized', onUnauthorized);
        };
    }, [refreshUser]);

    const login = useCallback(async ({ email, password, remember = false }) => {
        setSessionExpired(false);

        const attempt = async () => {
            await ensureCsrfCookie({ force: true });
            return api.post('/auth/login', { email, password, remember });
        };

        try {
            const res = await attempt();
            setUser(res.data.user);
            return res.data.user;
        } catch (error) {
            if (error?.response?.status === 419) {
                resetCsrfCookie();
                const res = await attempt();
                setUser(res.data.user);
                return res.data.user;
            }
            throw error;
        }
    }, []);

    const logout = useCallback(async () => {
        try {
            await api.post('/auth/logout');
        } catch {
            // Session may already be invalid.
        }
        resetCsrfCookie();
        setUser(null);
        setSessionExpired(false);
    }, []);

    const value = useMemo(() => ({
        user,
        loading,
        isAuthenticated: Boolean(user),
        isAdmin: user?.role === 'admin',
        isAnalyst: user?.role === 'admin' || user?.role === 'analyst',
        isViewer: user?.role === 'viewer',
        sessionExpired,
        login,
        logout,
        refreshUser,
        consumeRedirectPath,
    }), [user, loading, sessionExpired, login, logout, refreshUser]);

    return (
        <AuthContext.Provider value={value}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const ctx = useContext(AuthContext);
    if (!ctx) {
        throw new Error('useAuth must be used within AuthProvider');
    }
    return ctx;
}

export default AuthContext;
