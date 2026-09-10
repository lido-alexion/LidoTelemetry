import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import './src/styles/telemetry-app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom';
import App from './src/App';
import { AuthProvider } from './src/context/AuthContext';
import { ProductProvider } from './src/context/ProductContext';
import { getAppBase } from './src/appBase';

const routerBasename = getAppBase() || undefined;

function showBootFailure(message, error) {
    const lines = [
        message,
        error?.stack || error?.message || String(error),
        `URL: ${window.location.href}`,
    ].filter(Boolean);
    const root = document.getElementById('app');
    if (root) {
        root.innerHTML = `
            <div style="padding:1rem;font-family:system-ui,sans-serif;max-width:640px;margin:0 auto;color:#e5e7eb;background:#1a1a1a;min-height:100vh">
                <h1 style="font-size:1.1rem">App failed to start</h1>
                <pre style="white-space:pre-wrap;word-break:break-word;font-size:.75rem">${lines.join('\n')}</pre>
            </div>`;
    }
}

try {
    const rootEl = document.getElementById('app');
    createRoot(rootEl).render(
        <React.StrictMode>
            <BrowserRouter basename={routerBasename}>
                <AuthProvider>
                    <ProductProvider>
                        <App />
                    </ProductProvider>
                </AuthProvider>
            </BrowserRouter>
        </React.StrictMode>,
    );
} catch (error) {
    showBootFailure('React mount failed', error);
}
