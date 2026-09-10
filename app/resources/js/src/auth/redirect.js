const REDIRECT_KEY = 'telemetry_auth_redirect';

export function saveRedirectPath(pathname) {
    if (!pathname || pathname === '/' || pathname.startsWith('/login')) {
        return;
    }
    sessionStorage.setItem(REDIRECT_KEY, pathname);
}

export function consumeRedirectPath() {
    const path = sessionStorage.getItem(REDIRECT_KEY);
    sessionStorage.removeItem(REDIRECT_KEY);
    return path || '/';
}
