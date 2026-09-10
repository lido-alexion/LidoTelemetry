import axios from 'axios';
import { ensureCsrfCookie, getRequestCsrfToken, isPlainCsrfToken, resetCsrfCookie } from './auth/csrf';
import { appUrl } from './appBase';

const api = axios.create({
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    withCredentials: true,
});

api.interceptors.request.use((config) => {
    config.baseURL = appUrl('/api/v1');

    const csrf = getRequestCsrfToken();
    if (csrf) {
        if (isPlainCsrfToken()) {
            config.headers['X-CSRF-TOKEN'] = csrf;
        } else {
            config.headers['X-XSRF-TOKEN'] = csrf;
        }
    }

    return config;
});

export function getApiErrorMessage(error, fallback = 'Something went wrong. Please try again.') {
    const data = error?.response?.data ?? {};
    const validationErrors = data.errors;

    if (validationErrors && typeof validationErrors === 'object') {
        const first = Object.values(validationErrors).flat().find(Boolean);
        if (first) {
            return first;
        }
    }

    if (typeof data.message === 'string' && data.message.trim()) {
        return data.message.trim();
    }

    if (typeof error?.message === 'string' && error.message.trim()) {
        return error.message.trim();
    }

    return fallback;
}

api.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error?.response?.status;
        const url = error?.config?.url || '';
        const isPublicAuthRoute = url.includes('/auth/login')
            || url.includes('/auth/me')
            || url.includes('/invites/');

        if (status === 401 && !isPublicAuthRoute) {
            window.dispatchEvent(new CustomEvent('telemetry-unauthorized'));
            return Promise.reject(error);
        }

        if (status === 419 && error?.config && !error.config._csrfRetried) {
            error.config._csrfRetried = true;
            resetCsrfCookie();
            try {
                await ensureCsrfCookie({ force: true });
                return api.request(error.config);
            } catch {
                return Promise.reject(error);
            }
        }

        return Promise.reject(error);
    },
);

export default api;
