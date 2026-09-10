import React, { useEffect, useState } from 'react';
import api, { getApiErrorMessage } from '../api';
import QueryControls from '../components/QueryControls';
import { useProductContext } from '../context/ProductContext';

function firstNumericValue(data) {
    if (data == null || typeof data !== 'object' || Array.isArray(data)) {
        return null;
    }

    if (typeof data.value === 'number' || typeof data.count === 'number') {
        return data.value ?? data.count;
    }

    const first = Object.values(data).find((value) => typeof value === 'number');
    return first ?? null;
}

function normalizeGroupRows(data) {
    if (Array.isArray(data?.groups)) {
        return data.groups;
    }

    if (Array.isArray(data)) {
        return data.map((row) => ({
            key: Object.fromEntries(
                Object.entries(row).filter(([key]) => !['count', 'value'].includes(key)),
            ),
            value: row.value ?? row.count,
            count: row.count ?? row.value,
        }));
    }

    return [];
}

function normalizeTimeSeriesPoints(data) {
    if (Array.isArray(data?.points)) {
        return data.points;
    }

    if (Array.isArray(data)) {
        return data.map((point) => ({
            bucket: point.bucket || point.timestamp,
            timestamp: point.timestamp || point.bucket,
            value: point.value ?? point.count,
            count: point.count ?? point.value,
        }));
    }

    return [];
}

function normalizeSearchRows(data) {
    if (Array.isArray(data?.rows)) {
        return data.rows;
    }

    if (Array.isArray(data?.data)) {
        return data.data;
    }

    if (Array.isArray(data)) {
        return data;
    }

    return [];
}

function WidgetCard({ widget }) {
    const { title, widget_type, analysis_type, data } = widget;

    return (
        <div className="col-md-6 mb-3">
            <div className="card telemetry-widget-card h-100">
                <div className="card-header d-flex justify-content-between align-items-center">
                    <span>{title}</span>
                    <span className="badge bg-secondary">{widget_type}</span>
                </div>
                <div className="card-body">
                    {analysis_type === 'aggregate' && (
                        <div className="display-6">
                            {firstNumericValue(data) ?? JSON.stringify(data)}
                        </div>
                    )}
                    {analysis_type === 'time_series' && (
                        <ul className="list-unstyled mb-0 small">
                            {normalizeTimeSeriesPoints(data).slice(0, 8).map((point) => (
                                <li key={point.bucket || point.timestamp} className="d-flex justify-content-between">
                                    <span>{point.bucket || point.timestamp}</span>
                                    <span>{point.value ?? point.count}</span>
                                </li>
                            ))}
                            {normalizeTimeSeriesPoints(data).length > 8 && (
                                <li className="text-muted">…{normalizeTimeSeriesPoints(data).length - 8} more</li>
                            )}
                        </ul>
                    )}
                    {analysis_type === 'group_by' && (
                        <ul className="list-unstyled mb-0 small">
                            {normalizeGroupRows(data).slice(0, 10).map((group, index) => (
                                <li key={JSON.stringify(group.key) || index} className="d-flex justify-content-between">
                                    <span>{Object.values(group.key || group).filter((value) => typeof value !== 'number').join(' / ') || '—'}</span>
                                    <span>{group.value ?? group.count}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {(analysis_type === 'search' || analysis_type === 'sessions') && (
                        <ul className="list-unstyled mb-0 small">
                            {normalizeSearchRows(data).slice(0, 8).map((row, index) => (
                                <li key={row.id || row.event_id || row.session_id || index} className="mb-2">
                                    <div>{row.event_type || row.name || row.operation_name || row.session_id || 'Row'}</div>
                                    <pre className="telemetry-json-preview mb-0">{JSON.stringify(row, null, 2)}</pre>
                                </li>
                            ))}
                            {normalizeSearchRows(data).length > 8 && (
                                <li className="text-muted">…{normalizeSearchRows(data).length - 8} more</li>
                            )}
                        </ul>
                    )}
                    {analysis_type === 'funnels' && Array.isArray(data) && (
                        <ul className="list-unstyled mb-0 small">
                            {data.map((step) => (
                                <li key={step.step || step.event_type} className="d-flex justify-content-between">
                                    <span>{step.label || step.event_type}</span>
                                    <span>{step.session_count ?? step.count}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {!['aggregate', 'time_series', 'group_by', 'search', 'sessions', 'funnels'].includes(analysis_type) && (
                        <pre className="telemetry-json-preview mb-0">{JSON.stringify(data, null, 2)}</pre>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function DashboardPage({ slug, title }) {
    const { queryParams, productId } = useProductContext();
    const [dashboard, setDashboard] = useState(null);
    const [widgets, setWidgets] = useState([]);
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
                const res = await api.get(`/dashboards/builtin/${slug}/data`, {
                    params: queryParams,
                    skipErrorToast: true,
                });
                if (!cancelled) {
                    setDashboard(res.data.dashboard);
                    setWidgets(res.data.widgets || []);
                }
            } catch (err) {
                if (!cancelled) {
                    setDashboard(null);
                    setWidgets([]);
                    setError(getApiErrorMessage(err, 'Failed to load dashboard.'));
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
    }, [slug, productId, queryParams]);

    return (
        <div>
            <div className="d-flex justify-content-between align-items-center mb-3">
                <h2 className="h4 mb-0">{title || dashboard?.name || 'Dashboard'}</h2>
            </div>
            <QueryControls />
            {loading && (
                <div className="text-center py-5">
                    <div className="spinner-border text-info" role="status" />
                </div>
            )}
            {error && !loading && <div className="alert alert-danger">{error}</div>}
            {!loading && !error && (
                <div className="row">
                    {widgets.length === 0 ? (
                        <div className="col-12 text-muted">No widgets configured for this dashboard.</div>
                    ) : (
                        widgets.map((widget) => (
                            <WidgetCard key={widget.widget_id} widget={widget} />
                        ))
                    )}
                </div>
            )}
        </div>
    );
}
