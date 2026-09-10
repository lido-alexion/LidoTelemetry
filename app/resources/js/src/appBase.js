/**
 * App URL path prefix when hosted in a subdirectory (e.g. /telemetry).
 * Set via <meta name="app-base"> and window.__TELEMETRY_APP_BASE__ in app.blade.php.
 */
export function getAppBase() {
    const meta = document.querySelector('meta[name="app-base"]');
    const fromMeta = meta?.getAttribute('content') ?? '';
    const fromWindow = typeof window !== 'undefined' ? (window.__TELEMETRY_APP_BASE__ ?? '') : '';
    const raw = fromMeta || fromWindow || inferAppBaseFromLocation();
    const base = String(raw).replace(/\/$/, '');
    return base === '/' ? '' : base;
}

function inferAppBaseFromLocation() {
    if (typeof window === 'undefined') {
        return '';
    }
    const match = window.location.pathname.match(/^(\/[^/]+)(?:\/|$)/);
    if (match?.[1] === '/telemetry') {
        return '/telemetry';
    }
    return '';
}

export function appUrl(path = '') {
    const base = getAppBase();
    const suffix = path.startsWith('/') ? path : `/${path}`;
    return `${base}${suffix}` || '/';
}

export function getBrandName() {
    if (typeof window !== 'undefined' && window.__TELEMETRY_BRAND_NAME__) {
        return window.__TELEMETRY_BRAND_NAME__;
    }
    return 'Lido Telemetry';
}
