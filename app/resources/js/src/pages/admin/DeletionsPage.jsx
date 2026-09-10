import React, { useState } from 'react';
import api, { getApiErrorMessage } from '../../api';

const SCOPES = [
    { value: 'product', label: 'Product', fields: ['product_id'] },
    { value: 'environment', label: 'Environment', fields: ['product_id', 'environment'] },
    { value: 'user', label: 'User', fields: ['product_id', 'environment', 'scope_value'] },
    { value: 'anonymous', label: 'Anonymous ID', fields: ['product_id', 'environment', 'scope_value'] },
    { value: 'session', label: 'Session', fields: ['product_id', 'environment', 'scope_value'] },
    { value: 'range', label: 'Date range', fields: ['product_id', 'environment', 'range_start', 'range_end'] },
];

const EMPTY_FORM = {
    scope_type: 'environment',
    product_id: '',
    environment: '',
    scope_value: '',
    range_start: '',
    range_end: '',
};

export default function DeletionsPage() {
    const [form, setForm] = useState(EMPTY_FORM);
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState('');
    const [result, setResult] = useState(null);

    const activeScope = SCOPES.find((scope) => scope.value === form.scope_type) || SCOPES[0];

    const handleSubmit = async (e) => {
        e.preventDefault();
        if (!window.confirm(`Execute ${form.scope_type} deletion? This cannot be undone.`)) {
            return;
        }

        setSubmitting(true);
        setError('');
        setResult(null);

        const payload = { scope_type: form.scope_type };

        activeScope.fields.forEach((field) => {
            if (!form[field]) {
                return;
            }

            if (field === 'range_start' || field === 'range_end') {
                payload[field] = new Date(form[field]).toISOString();
                return;
            }

            payload[field] = form[field];
        });

        try {
            const res = await api.post('/admin/deletions', payload);
            setResult(res.data.data || res.data);
            setForm(EMPTY_FORM);
        } catch (err) {
            setError(getApiErrorMessage(err, 'Deletion failed.'));
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div>
            <h2 className="h4 mb-3">Data Deletions</h2>
            <p className="text-muted small">
                Targeted deletions create tombstones and permanently remove matching telemetry payloads.
            </p>

            {error && <div className="alert alert-danger">{error}</div>}
            {result && (
                <div className="alert alert-success">
                    Deletion completed. Tombstone: <code>{result.tombstone_id}</code>
                </div>
            )}

            <div className="card">
                <div className="card-header">Deletion scope</div>
                <div className="card-body">
                    <form className="row g-3" onSubmit={handleSubmit}>
                        <div className="col-md-4">
                            <label className="form-label">Scope type</label>
                            <select
                                className="form-select"
                                value={form.scope_type}
                                onChange={(e) => setForm({ ...EMPTY_FORM, scope_type: e.target.value })}
                            >
                                {SCOPES.map((scope) => (
                                    <option key={scope.value} value={scope.value}>{scope.label}</option>
                                ))}
                            </select>
                        </div>

                        {activeScope.fields.includes('product_id') && (
                            <div className="col-md-4">
                                <label className="form-label">Product ID</label>
                                <input
                                    className="form-control"
                                    required
                                    value={form.product_id}
                                    onChange={(e) => setForm({ ...form, product_id: e.target.value })}
                                />
                            </div>
                        )}

                        {activeScope.fields.includes('environment') && (
                            <div className="col-md-4">
                                <label className="form-label">Environment</label>
                                <input
                                    className="form-control"
                                    required
                                    value={form.environment}
                                    onChange={(e) => setForm({ ...form, environment: e.target.value })}
                                />
                            </div>
                        )}

                        {activeScope.fields.includes('scope_value') && (
                            <div className="col-md-4">
                                <label className="form-label">Scope value</label>
                                <input
                                    className="form-control"
                                    required
                                    value={form.scope_value}
                                    onChange={(e) => setForm({ ...form, scope_value: e.target.value })}
                                />
                            </div>
                        )}

                        {activeScope.fields.includes('range_start') && (
                            <div className="col-md-4">
                                <label className="form-label">Range start</label>
                                <input
                                    type="datetime-local"
                                    className="form-control"
                                    required
                                    value={form.range_start}
                                    onChange={(e) => setForm({ ...form, range_start: e.target.value })}
                                />
                            </div>
                        )}

                        {activeScope.fields.includes('range_end') && (
                            <div className="col-md-4">
                                <label className="form-label">Range end</label>
                                <input
                                    type="datetime-local"
                                    className="form-control"
                                    required
                                    value={form.range_end}
                                    onChange={(e) => setForm({ ...form, range_end: e.target.value })}
                                />
                            </div>
                        )}

                        <div className="col-12">
                            <button type="submit" className="btn btn-danger" disabled={submitting}>
                                {submitting ? 'Deleting…' : 'Execute deletion'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
