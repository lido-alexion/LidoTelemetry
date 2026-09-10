import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api, { getApiErrorMessage } from '../api';
import { useAuth } from '../context/AuthContext';

function extractRows(payload) {
    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload?.data)) {
        return payload.data;
    }

    if (Array.isArray(payload?.rows)) {
        return payload.rows;
    }

    return [];
}

export default function SavedAnalysesPage() {
    const { isAnalyst } = useAuth();
    const [analyses, setAnalyses] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [runResult, setRunResult] = useState(null);
    const [runningId, setRunningId] = useState('');

    const loadAnalyses = async () => {
        setLoading(true);
        setError('');
        try {
            const res = await api.get('/saved-analyses', { skipErrorToast: true });
            setAnalyses(res.data.data || []);
        } catch (err) {
            setError(getApiErrorMessage(err, 'Failed to load saved analyses.'));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (isAnalyst) {
            loadAnalyses();
        } else {
            setLoading(false);
        }
    }, [isAnalyst]);

    const deleteAnalysis = async (analysis) => {
        if (!window.confirm(`Delete "${analysis.name}"?`)) {
            return;
        }

        try {
            await api.delete(`/saved-analyses/${analysis.id}`);
            await loadAnalyses();
            if (runResult?.analysisId === analysis.id) {
                setRunResult(null);
            }
        } catch (err) {
            setError(getApiErrorMessage(err, 'Failed to delete analysis.'));
        }
    };

    const runAnalysis = async (analysis) => {
        setRunningId(analysis.id);
        setError('');
        try {
            const res = await api.post(`/saved-analyses/${analysis.id}/run`, {}, { skipErrorToast: true });
            const payload = res.data.data ?? res.data;
            setRunResult({
                analysisId: analysis.id,
                name: analysis.name,
                rows: extractRows(payload),
                total: payload?.total,
            });
        } catch (err) {
            setError(getApiErrorMessage(err, 'Failed to run analysis.'));
        } finally {
            setRunningId('');
        }
    };

    if (!isAnalyst) {
        return (
            <div>
                <h2 className="h4 mb-3">Saved Analyses</h2>
                <div className="alert alert-warning">Saved analyses require analyst or admin access.</div>
            </div>
        );
    }

    return (
        <div>
            <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h2 className="h4 mb-0">Saved Analyses</h2>
                <Link to="/explorer" className="btn btn-sm btn-outline-info">Open Explorer</Link>
            </div>

            {error && <div className="alert alert-danger">{error}</div>}

            {loading ? (
                <div className="text-center py-4">
                    <div className="spinner-border text-info" role="status" />
                </div>
            ) : (
                <div className="table-responsive mb-4">
                    <table className="table table-dark table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Products</th>
                                <th>Updated</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {analyses.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="text-muted">No saved analyses yet.</td>
                                </tr>
                            ) : (
                                analyses.map((analysis) => (
                                    <tr key={analysis.id}>
                                        <td>{analysis.name}</td>
                                        <td><code>{analysis.analysis_type}</code></td>
                                        <td className="small">{(analysis.product_ids || []).length} product(s)</td>
                                        <td className="small">{analysis.updated_at || analysis.created_at || '—'}</td>
                                        <td className="text-nowrap">
                                            <button
                                                type="button"
                                                className="btn btn-sm btn-outline-info me-2"
                                                disabled={runningId === analysis.id}
                                                onClick={() => runAnalysis(analysis)}
                                            >
                                                {runningId === analysis.id ? 'Running…' : 'Run'}
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-sm btn-outline-danger"
                                                onClick={() => deleteAnalysis(analysis)}
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            {runResult && (
                <div className="card">
                    <div className="card-header d-flex justify-content-between align-items-center">
                        <span>Results: {runResult.name}</span>
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setRunResult(null)}>
                            Close
                        </button>
                    </div>
                    <div className="card-body p-0">
                        <div className="table-responsive">
                            <table className="table table-dark table-striped mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th>Summary</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {runResult.rows.length === 0 ? (
                                        <tr>
                                            <td colSpan={2} className="text-muted">No rows returned.</td>
                                        </tr>
                                    ) : (
                                        runResult.rows.slice(0, 25).map((row, index) => (
                                            <tr key={row.id || row.event_id || index}>
                                                <td className="small">
                                                    {row.event_type || row.metric_name || row.name || row.operation_name || '—'}
                                                </td>
                                                <td className="small">
                                                    <pre className="telemetry-json-preview mb-0">
                                                        {JSON.stringify(row, null, 2)}
                                                    </pre>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
