import React, { useEffect, useMemo, useState } from 'react';
import api, { getApiErrorMessage } from '../api';
import QueryControls from '../components/QueryControls';
import { useAuth } from '../context/AuthContext';
import { useProductContext } from '../context/ProductContext';

const SIGNAL_TABS = [
    { key: 'events', label: 'Events', nameField: 'event_type' },
    { key: 'metrics', label: 'Metrics', nameField: 'name' },
    { key: 'logs', label: 'Logs', nameField: 'message' },
    { key: 'traces', label: 'Traces', nameField: 'operation_name' },
    { key: 'sessions', label: 'Sessions', nameField: null },
];

const EMPTY_FILTERS = {
    name: '',
    user_id: '',
    session_id: '',
    correlation_id: '',
    metadata: '',
};

function buildFilterArray(signal, filters) {
    const result = [];
    const tab = SIGNAL_TABS.find((item) => item.key === signal);
    const nameField = tab?.nameField;

    if (nameField && filters.name.trim()) {
        result.push({ field: nameField, operator: 'contains', value: filters.name.trim() });
    }

    if (filters.user_id.trim()) {
        result.push({ field: 'user_id', operator: 'eq', value: filters.user_id.trim() });
    }

    if (filters.session_id.trim()) {
        result.push({ field: 'session_id', operator: 'eq', value: filters.session_id.trim() });
    }

    if (filters.correlation_id.trim()) {
        result.push({ field: 'correlation_id', operator: 'eq', value: filters.correlation_id.trim() });
    }

    if (filters.metadata.trim()) {
        const raw = filters.metadata.trim();
        const eqIndex = raw.indexOf('=');

        if (eqIndex > 0) {
            const key = raw.slice(0, eqIndex).trim();
            const value = raw.slice(eqIndex + 1).trim();
            if (key) {
                result.push({
                    field: key.startsWith('metadata.') ? key : `metadata.${key}`,
                    operator: 'eq',
                    value,
                });
            }
        } else {
            result.push({ field: 'metadata', operator: 'contains', value: raw });
        }
    }

    return result;
}

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

export default function ExplorerPage() {
    const { isAnalyst } = useAuth();
    const { queryParams, productId } = useProductContext();
    const [signal, setSignal] = useState('events');
    const [filters, setFilters] = useState(EMPTY_FILTERS);
    const [sortDirection, setSortDirection] = useState('desc');
    const [rows, setRows] = useState([]);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [saveOpen, setSaveOpen] = useState(false);
    const [saveName, setSaveName] = useState('');
    const [saveMessage, setSaveMessage] = useState('');
    const [saveError, setSaveError] = useState('');
    const [saving, setSaving] = useState(false);

    const activeTab = SIGNAL_TABS.find((tab) => tab.key === signal) || SIGNAL_TABS[0];
    const filterArray = useMemo(() => buildFilterArray(signal, filters), [signal, filters]);

    useEffect(() => {
        if (!productId || !queryParams.environment) {
            return;
        }

        let cancelled = false;

        (async () => {
            setLoading(true);
            setError('');
            try {
                const params = {
                    ...queryParams,
                    limit: 100,
                    order_by: [{ field: 'occurred_at', direction: sortDirection }],
                };

                if (filterArray.length) {
                    params.filters = filterArray;
                }

                const res = await api.get(`/explorer/${signal}`, {
                    params,
                    skipErrorToast: true,
                });

                if (!cancelled) {
                    const payload = res.data.data;
                    setRows(extractRows(payload));
                    setTotal(typeof payload?.total === 'number' ? payload.total : extractRows(payload).length);
                }
            } catch (err) {
                if (!cancelled) {
                    setRows([]);
                    setTotal(0);
                    setError(getApiErrorMessage(err, 'Failed to load explorer data.'));
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [signal, filterArray, sortDirection, productId, queryParams]);

    const saveAnalysis = async (e) => {
        e.preventDefault();
        setSaving(true);
        setSaveError('');
        setSaveMessage('');
        try {
            await api.post('/saved-analyses', {
                name: saveName.trim(),
                analysis_type: 'search',
                definition: {
                    signal_family: signal,
                    filters: filterArray,
                    order_by: [{ field: 'occurred_at', direction: sortDirection }],
                    limit: 100,
                },
                product_ids: [productId],
                environment_keys: [queryParams.environment],
            });
            setSaveMessage('Analysis saved.');
            setSaveName('');
            setSaveOpen(false);
        } catch (err) {
            setSaveError(getApiErrorMessage(err, 'Failed to save analysis.'));
        } finally {
            setSaving(false);
        }
    };

    return (
        <div>
            <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h2 className="h4 mb-0">Explorer</h2>
                {isAnalyst && (
                    <button
                        type="button"
                        className="btn btn-sm btn-info"
                        onClick={() => {
                            setSaveOpen(true);
                            setSaveMessage('');
                            setSaveError('');
                        }}
                    >
                        Save analysis
                    </button>
                )}
            </div>
            <QueryControls />

            <ul className="nav nav-tabs mb-3">
                {SIGNAL_TABS.map((tab) => (
                    <li className="nav-item" key={tab.key}>
                        <button
                            type="button"
                            className={`nav-link${signal === tab.key ? ' active' : ''}`}
                            onClick={() => setSignal(tab.key)}
                        >
                            {tab.label}
                        </button>
                    </li>
                ))}
            </ul>

            <div className="card mb-3">
                <div className="card-body">
                    <div className="row g-3">
                        {activeTab.nameField && (
                            <div className="col-md-4">
                                <label className="form-label">
                                    {signal === 'events' ? 'Event type' : 'Name'}
                                </label>
                                <input
                                    type="search"
                                    className="form-control"
                                    placeholder={signal === 'events' ? 'e.g. navigation.view_started' : 'Filter by name'}
                                    value={filters.name}
                                    onChange={(e) => setFilters({ ...filters, name: e.target.value })}
                                />
                            </div>
                        )}
                        <div className="col-md-4">
                            <label className="form-label">User ID</label>
                            <input
                                type="search"
                                className="form-control"
                                value={filters.user_id}
                                onChange={(e) => setFilters({ ...filters, user_id: e.target.value })}
                            />
                        </div>
                        <div className="col-md-4">
                            <label className="form-label">Session ID</label>
                            <input
                                type="search"
                                className="form-control"
                                value={filters.session_id}
                                onChange={(e) => setFilters({ ...filters, session_id: e.target.value })}
                            />
                        </div>
                        <div className="col-md-4">
                            <label className="form-label">Correlation ID</label>
                            <input
                                type="search"
                                className="form-control"
                                value={filters.correlation_id}
                                onChange={(e) => setFilters({ ...filters, correlation_id: e.target.value })}
                            />
                        </div>
                        <div className="col-md-4">
                            <label className="form-label">Metadata search</label>
                            <input
                                type="search"
                                className="form-control"
                                placeholder="key=value or free text"
                                value={filters.metadata}
                                onChange={(e) => setFilters({ ...filters, metadata: e.target.value })}
                            />
                        </div>
                        <div className="col-md-4">
                            <label className="form-label">Sort by occurred_at</label>
                            <select
                                className="form-select"
                                value={sortDirection}
                                onChange={(e) => setSortDirection(e.target.value)}
                            >
                                <option value="desc">Newest first</option>
                                <option value="asc">Oldest first</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {saveMessage && <div className="alert alert-success">{saveMessage}</div>}

            {loading && (
                <div className="text-center py-4">
                    <div className="spinner-border text-info" role="status" />
                </div>
            )}
            {error && !loading && <div className="alert alert-danger">{error}</div>}

            {!loading && !error && (
                <>
                    <div className="text-muted small mb-2">
                        Showing {rows.length} result{rows.length === 1 ? '' : 's'}
                        {total > rows.length ? ` of ${total}` : ''}
                    </div>
                    <div className="table-responsive">
                        <table className="table table-dark table-striped table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Summary</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.length === 0 ? (
                                    <tr>
                                        <td colSpan={3} className="text-muted">No results for the selected range.</td>
                                    </tr>
                                ) : (
                                    rows.map((row, index) => (
                                        <tr key={row.id || row.event_id || row.session_id || index}>
                                            <td className="text-nowrap small">
                                                {row.occurred_at || row.started_at || row.timestamp || row.created_at || '—'}
                                            </td>
                                            <td className="small">
                                                {row.event_type || row.metric_name || row.name || row.operation_name || row.level || '—'}
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
                </>
            )}

            {saveOpen && (
                <div className="modal show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.6)' }}>
                    <div className="modal-dialog">
                        <div className="modal-content bg-dark text-light border-secondary">
                            <form onSubmit={saveAnalysis}>
                                <div className="modal-header border-secondary">
                                    <h5 className="modal-title">Save analysis</h5>
                                    <button
                                        type="button"
                                        className="btn-close btn-close-white"
                                        onClick={() => setSaveOpen(false)}
                                    />
                                </div>
                                <div className="modal-body">
                                    {saveError && <div className="alert alert-danger">{saveError}</div>}
                                    <label className="form-label">Name</label>
                                    <input
                                        className="form-control"
                                        required
                                        value={saveName}
                                        onChange={(e) => setSaveName(e.target.value)}
                                        placeholder="My explorer query"
                                    />
                                </div>
                                <div className="modal-footer border-secondary">
                                    <button type="button" className="btn btn-outline-secondary" onClick={() => setSaveOpen(false)}>
                                        Cancel
                                    </button>
                                    <button type="submit" className="btn btn-info" disabled={saving}>
                                        {saving ? 'Saving…' : 'Save'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
