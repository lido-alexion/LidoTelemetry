import React, { useState } from 'react';
import api, { getApiErrorMessage } from '../api';
import QueryControls from '../components/QueryControls';
import { useAuth } from '../context/AuthContext';
import { useProductContext } from '../context/ProductContext';

const SIGNAL_FAMILIES = ['events', 'metrics', 'logs', 'traces'];
const FORMATS = ['csv', 'json', 'ndjson'];
const EXPORT_TYPES = ['raw', 'aggregates', 'sessions', 'views'];

function parseFilename(contentDisposition, fallback) {
    if (!contentDisposition) {
        return fallback;
    }

    const match = /filename="?([^";]+)"?/i.exec(contentDisposition);
    return match?.[1] || fallback;
}

export default function ExportPage() {
    const { isAnalyst } = useAuth();
    const { queryParams, productId, environment } = useProductContext();
    const [signalFamily, setSignalFamily] = useState('events');
    const [format, setFormat] = useState('csv');
    const [exportType, setExportType] = useState('raw');
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');

    const handleExport = async (e) => {
        e.preventDefault();
        if (!productId) {
            setError('Select a product before exporting.');
            return;
        }

        setSubmitting(true);
        setError('');
        setMessage('');

        try {
            const payload = {
                format,
                signal_family: signalFamily,
                export_type: exportType,
                product_ids: [productId],
                environment_keys: environment ? [environment] : [],
                time_range: {
                    start: queryParams.start,
                    end: queryParams.end,
                },
            };

            const response = await api.post('/exports', payload, {
                responseType: 'blob',
                skipErrorToast: true,
            });

            const fallbackName = `telemetry-${signalFamily}-${exportType}.${format === 'ndjson' ? 'ndjson' : format}`;
            const filename = parseFilename(response.headers['content-disposition'], fallbackName);
            const blob = new Blob([response.data], {
                type: response.headers['content-type'] || 'application/octet-stream',
            });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
            setMessage(`Download started: ${filename}`);
        } catch (err) {
            if (err?.response?.data instanceof Blob) {
                try {
                    const text = await err.response.data.text();
                    const parsed = JSON.parse(text);
                    setError(parsed.message || 'Export failed.');
                } catch {
                    setError('Export failed.');
                }
            } else {
                setError(getApiErrorMessage(err, 'Export failed.'));
            }
        } finally {
            setSubmitting(false);
        }
    };

    if (!isAnalyst) {
        return (
            <div>
                <h2 className="h4 mb-3">Export</h2>
                <div className="alert alert-warning">Export requires analyst or admin access.</div>
            </div>
        );
    }

    return (
        <div>
            <h2 className="h4 mb-3">Export</h2>
            <QueryControls />

            {error && <div className="alert alert-danger">{error}</div>}
            {message && <div className="alert alert-success">{message}</div>}

            <div className="card">
                <div className="card-header">Create export</div>
                <div className="card-body">
                    <form className="row g-3" onSubmit={handleExport}>
                        <div className="col-md-3">
                            <label className="form-label">Signal family</label>
                            <select
                                className="form-select"
                                value={signalFamily}
                                onChange={(e) => setSignalFamily(e.target.value)}
                            >
                                {SIGNAL_FAMILIES.map((item) => (
                                    <option key={item} value={item}>{item}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-3">
                            <label className="form-label">Format</label>
                            <select
                                className="form-select"
                                value={format}
                                onChange={(e) => setFormat(e.target.value)}
                            >
                                {FORMATS.map((item) => (
                                    <option key={item} value={item}>{item.toUpperCase()}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-3">
                            <label className="form-label">Export type</label>
                            <select
                                className="form-select"
                                value={exportType}
                                onChange={(e) => setExportType(e.target.value)}
                            >
                                {EXPORT_TYPES.map((item) => (
                                    <option key={item} value={item}>{item}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-3 d-flex align-items-end">
                            <button type="submit" className="btn btn-info w-100" disabled={submitting}>
                                {submitting ? 'Exporting…' : 'Export & download'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
