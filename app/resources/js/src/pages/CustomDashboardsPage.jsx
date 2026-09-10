import React, { useEffect, useState } from 'react';
import api, { getApiErrorMessage } from '../api';
import { useProductContext } from '../context/ProductContext';

const EMPTY_WIDGET = {
    widget_type: 'aggregate',
    title: '',
    config: '{\n  "analysis_type": "aggregate",\n  "query": {\n    "signal_family": "events",\n    "aggregations": [{"function": "count"}]\n  }\n}',
};

const EMPTY_DASHBOARD = {
    name: '',
    widgets: [{ ...EMPTY_WIDGET, position: 0 }],
};

function parseConfig(text) {
    return JSON.parse(text);
}

export default function CustomDashboardsPage() {
    const { productId, environment } = useProductContext();
    const [dashboards, setDashboards] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState(EMPTY_DASHBOARD);
    const [submitting, setSubmitting] = useState(false);

    const loadDashboards = async () => {
        setLoading(true);
        setError('');
        try {
            const res = await api.get('/dashboards', { skipErrorToast: true });
            const custom = (res.data.data || []).filter((item) => !item.is_builtin);
            setDashboards(custom);
        } catch (err) {
            setError(getApiErrorMessage(err, 'Failed to load dashboards.'));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadDashboards();
    }, []);

    const startCreate = () => {
        setEditing('new');
        setForm(EMPTY_DASHBOARD);
        setMessage('');
        setError('');
    };

    const startEdit = async (dashboard) => {
        setError('');
        setMessage('');
        try {
            const res = await api.get(`/dashboards/${dashboard.id}`, { skipErrorToast: true });
            const data = res.data.data;
            setEditing(data.id);
            setForm({
                name: data.name,
                widgets: (data.widgets || []).map((widget, index) => ({
                    widget_type: widget.widget_type,
                    title: widget.title,
                    config: JSON.stringify(widget.config, null, 2),
                    position: widget.position ?? index,
                })),
            });
        } catch (err) {
            setError(getApiErrorMessage(err, 'Failed to load dashboard.'));
        }
    };

    const updateWidget = (index, changes) => {
        setForm((current) => ({
            ...current,
            widgets: current.widgets.map((widget, widgetIndex) => (
                widgetIndex === index ? { ...widget, ...changes } : widget
            )),
        }));
    };

    const addWidget = () => {
        setForm((current) => ({
            ...current,
            widgets: [...current.widgets, { ...EMPTY_WIDGET, position: current.widgets.length }],
        }));
    };

    const removeWidget = (index) => {
        setForm((current) => ({
            ...current,
            widgets: current.widgets.filter((_, widgetIndex) => widgetIndex !== index),
        }));
    };

    const moveWidget = (index, direction) => {
        setForm((current) => {
            const widgets = [...current.widgets];
            const target = index + direction;
            if (target < 0 || target >= widgets.length) {
                return current;
            }
            [widgets[index], widgets[target]] = [widgets[target], widgets[index]];
            return {
                ...current,
                widgets: widgets.map((widget, widgetIndex) => ({ ...widget, position: widgetIndex })),
            };
        });
    };

    const saveDashboard = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError('');
        setMessage('');

        try {
            const widgets = form.widgets.map((widget, index) => ({
                widget_type: widget.widget_type,
                title: widget.title,
                config: parseConfig(widget.config),
                position: widget.position ?? index,
            }));

            const payload = {
                name: form.name.trim(),
                product_ids: productId ? [productId] : [],
                environment_keys: environment ? [environment] : [],
                widgets,
            };

            if (editing === 'new') {
                await api.post('/dashboards', payload);
                setMessage('Dashboard created.');
            } else {
                await api.put(`/dashboards/${editing}`, payload);
                setMessage('Dashboard updated.');
            }

            setEditing(null);
            setForm(EMPTY_DASHBOARD);
            await loadDashboards();
        } catch (err) {
            setError(getApiErrorMessage(err, 'Failed to save dashboard.'));
        } finally {
            setSubmitting(false);
        }
    };

    const deleteDashboard = async (dashboard) => {
        if (!window.confirm(`Delete dashboard "${dashboard.name}"?`)) {
            return;
        }

        try {
            await api.delete(`/dashboards/${dashboard.id}`);
            await loadDashboards();
        } catch (err) {
            setError(getApiErrorMessage(err, 'Failed to delete dashboard.'));
        }
    };

    return (
        <div>
            <div className="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h2 className="h4 mb-0">Custom Dashboards</h2>
                <button type="button" className="btn btn-sm btn-info" onClick={startCreate}>
                    New dashboard
                </button>
            </div>

            {error && <div className="alert alert-danger">{error}</div>}
            {message && <div className="alert alert-success">{message}</div>}

            {editing && (
                <div className="card mb-4">
                    <div className="card-header">{editing === 'new' ? 'Create dashboard' : 'Edit dashboard'}</div>
                    <div className="card-body">
                        <form onSubmit={saveDashboard}>
                            <div className="mb-3">
                                <label className="form-label">Title</label>
                                <input
                                    className="form-control"
                                    required
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                />
                            </div>

                            {form.widgets.map((widget, index) => (
                                <div className="card mb-3 border-secondary" key={`widget-${index}`}>
                                    <div className="card-header d-flex justify-content-between align-items-center">
                                        <span>Widget {index + 1}</span>
                                        <div className="btn-group btn-group-sm">
                                            <button type="button" className="btn btn-outline-secondary" onClick={() => moveWidget(index, -1)} disabled={index === 0}>↑</button>
                                            <button type="button" className="btn btn-outline-secondary" onClick={() => moveWidget(index, 1)} disabled={index === form.widgets.length - 1}>↓</button>
                                            <button type="button" className="btn btn-outline-danger" onClick={() => removeWidget(index)} disabled={form.widgets.length === 1}>Remove</button>
                                        </div>
                                    </div>
                                    <div className="card-body row g-3">
                                        <div className="col-md-4">
                                            <label className="form-label">Widget title</label>
                                            <input
                                                className="form-control"
                                                required
                                                value={widget.title}
                                                onChange={(e) => updateWidget(index, { title: e.target.value })}
                                            />
                                        </div>
                                        <div className="col-md-4">
                                            <label className="form-label">Widget type</label>
                                            <select
                                                className="form-select"
                                                value={widget.widget_type}
                                                onChange={(e) => updateWidget(index, { widget_type: e.target.value })}
                                            >
                                                <option value="aggregate">aggregate</option>
                                                <option value="time_series">time_series</option>
                                                <option value="group_by">group_by</option>
                                                <option value="table">table</option>
                                                <option value="breakdown">breakdown</option>
                                            </select>
                                        </div>
                                        <div className="col-12">
                                            <label className="form-label">Query config JSON</label>
                                            <textarea
                                                className="form-control font-monospace"
                                                rows={8}
                                                required
                                                value={widget.config}
                                                onChange={(e) => updateWidget(index, { config: e.target.value })}
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}

                            <div className="d-flex gap-2">
                                <button type="button" className="btn btn-outline-secondary" onClick={addWidget}>Add widget</button>
                                <button type="submit" className="btn btn-info" disabled={submitting}>
                                    {submitting ? 'Saving…' : 'Save dashboard'}
                                </button>
                                <button type="button" className="btn btn-outline-secondary" onClick={() => setEditing(null)}>
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {loading ? (
                <div className="text-center py-4">
                    <div className="spinner-border text-info" role="status" />
                </div>
            ) : (
                <div className="table-responsive">
                    <table className="table table-dark table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Widgets</th>
                                <th>Updated</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {dashboards.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="text-muted">No custom dashboards yet.</td>
                                </tr>
                            ) : (
                                dashboards.map((dashboard) => (
                                    <tr key={dashboard.id}>
                                        <td>{dashboard.name}</td>
                                        <td><code>{dashboard.slug}</code></td>
                                        <td>{dashboard.widgets?.length ?? '—'}</td>
                                        <td className="small">{dashboard.updated_at || dashboard.created_at || '—'}</td>
                                        <td className="text-nowrap">
                                            <button type="button" className="btn btn-sm btn-outline-info me-2" onClick={() => startEdit(dashboard)}>
                                                Edit
                                            </button>
                                            <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => deleteDashboard(dashboard)}>
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
        </div>
    );
}
