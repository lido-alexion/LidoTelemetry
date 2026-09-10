import { describe, expect, it, beforeEach, afterEach } from 'vitest';
import { appUrl, getAppBase } from '../../resources/js/src/appBase';

describe('appBase', () => {
    const originalMeta = document.querySelector('meta[name="app-base"]');

    beforeEach(() => {
        document.querySelector('meta[name="app-base"]')?.remove();
        delete window.__TELEMETRY_APP_BASE__;
    });

    afterEach(() => {
        if (originalMeta) {
            document.head.appendChild(originalMeta);
        }
    });

    it('returns empty base when unset', () => {
        expect(getAppBase()).toBe('');
        expect(appUrl('/api/v1/auth/me')).toBe('/api/v1/auth/me');
    });

    it('prefixes paths with configured base', () => {
        const meta = document.createElement('meta');
        meta.name = 'app-base';
        meta.content = '/telemetry';
        document.head.appendChild(meta);

        expect(getAppBase()).toBe('/telemetry');
        expect(appUrl('/login')).toBe('/telemetry/login');
    });
});
