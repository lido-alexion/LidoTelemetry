import React, { useEffect, useState } from 'react';
import api, { getApiErrorMessage } from '../api';
import QueryControls from '../components/QueryControls';
import { useProductContext } from '../context/ProductContext';

const SIGNAL_TABS = [
    { key: 'events', label: 'Events' },
    { key: 'metrics', label: 'Metrics' },
    { key: 'logs', label: 'Logs' },
];

export default function ExplorerPage() {
    const { queryParams, productId } = useProductContext();
    const [signal, setSignal] = useState('events');
    const [filterText, setFilterText] = useState('');
    const [rows, setRows] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

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
                };

                if (filterText.trim()) {
                    params.filters = { search: filterText.trim() };
                }

                const res = await api.get(`/explorer/${signal}`, {
                    params,
                    skipErrorToast: true,
                });
                if (!cancelled) {
                    const data = res.data.data;
                    setRows(Array.isArray(data?.rows) ? data.rows : (Array.isArray(data) ? data : []));
                }
            } catch (err) {
                if (!cancelled) {
                    setRows([]);
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
    }, [signal, filterText, productId, queryParams]);

    return (
        <div>
            <h2 className="h4 mb-3">Explorer</h2>
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
                    <label className="form-label">Filter</label>
                    <input
                        type="search"
                        className="form-control"
                        placeholder="Search or filter (metadata key/value)"
                        value={filterText}
                        onChange={(e) => setFilterText(e.target.value)}
                    />
                </div>
            </div>

            {loading && (
                <div className="text-center py-4">
                    <div className="spinner-border text-info" role="status" />
                </div>
            )}
            {error && !loading && <div className="alert alert-danger">{error}</div>}

            {!loading && !error && (
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
                                    <tr key={row.id || row.event_id || index}>
                                        <td className="text-nowrap small">
                                            {row.occurred_at || row.timestamp || row.created_at || '—'}
                                        </td>
                                        <td className="small">
                                            {row.event_type || row.metric_name || row.level || row.name || '—'}
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
            )}
        </div>
    );
}
