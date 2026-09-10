import React, { useEffect, useState } from 'react';
import api, { getApiErrorMessage } from '../api';
import QueryControls from '../components/QueryControls';
import { useProductContext } from '../context/ProductContext';

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
                            {data?.value ?? data?.count ?? JSON.stringify(data)}
                        </div>
                    )}
                    {analysis_type === 'time_series' && Array.isArray(data?.points) && (
                        <ul className="list-unstyled mb-0 small">
                            {data.points.slice(0, 8).map((point) => (
                                <li key={point.bucket || point.timestamp} className="d-flex justify-content-between">
                                    <span>{point.bucket || point.timestamp}</span>
                                    <span>{point.value ?? point.count}</span>
                                </li>
                            ))}
                            {data.points.length > 8 && (
                                <li className="text-muted">…{data.points.length - 8} more</li>
                            )}
                        </ul>
                    )}
                    {analysis_type === 'group_by' && Array.isArray(data?.groups) && (
                        <ul className="list-unstyled mb-0 small">
                            {data.groups.slice(0, 10).map((group) => (
                                <li key={JSON.stringify(group.key)} className="d-flex justify-content-between">
                                    <span>{Object.values(group.key || {}).join(' / ') || '—'}</span>
                                    <span>{group.value ?? group.count}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {!['aggregate', 'time_series', 'group_by'].includes(analysis_type) && (
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
