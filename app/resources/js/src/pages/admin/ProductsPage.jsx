import React, { useEffect, useState } from 'react';
import api, { getApiErrorMessage } from '../../api';

const EMPTY_FORM = {
    product_key: '',
    product_name: '',
    environment_key: 'production',
    environment_name: 'Production',
};

const EMPTY_ENV_FORM = {
    environment_key: '',
    display_name: '',
    raw_retention_days: '',
    aggregate_retention_days: '',
};

export default function ProductsPage() {
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [form, setForm] = useState(EMPTY_FORM);
    const [submitting, setSubmitting] = useState(false);
    const [editingNames, setEditingNames] = useState({});
    const [addingEnvFor, setAddingEnvFor] = useState('');
    const [envForm, setEnvForm] = useState(EMPTY_ENV_FORM);
    const [editingEnv, setEditingEnv] = useState(null);
    const [remoteConfigDrafts, setRemoteConfigDrafts] = useState({});

    const loadProducts = async () => {
        setLoading(true);
        setError('');
        try {
            const res = await api.get('/admin/products');
            setProducts(res.data.data || []);
        } catch (err) {
            setError(getApiErrorMessage(err));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadProducts();
    }, []);

    const createProduct = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError('');
        try {
            await api.post('/admin/products', {
                product_key: form.product_key,
                product_name: form.product_name,
                environments: [{
                    environment_key: form.environment_key,
                    display_name: form.environment_name,
                }],
            });
            setForm(EMPTY_FORM);
            setMessage('Product created.');
            await loadProducts();
        } catch (err) {
            setError(getApiErrorMessage(err));
        } finally {
            setSubmitting(false);
        }
    };

    const toggleActive = async (product) => {
        try {
            await api.put(`/admin/products/${product.id}`, {
                is_active: !product.is_active,
            });
            await loadProducts();
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
    };

    const saveProductName = async (product) => {
        const nextName = (editingNames[product.id] ?? product.product_name).trim();
        if (!nextName || nextName === product.product_name) {
            return;
        }

        try {
            await api.put(`/admin/products/${product.id}`, { product_name: nextName });
            await loadProducts();
            setMessage('Product name updated.');
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
    };

    const addEnvironment = async (product, e) => {
        e.preventDefault();
        try {
            await api.post(`/admin/products/${product.id}/environments`, {
                environment_key: envForm.environment_key,
                display_name: envForm.display_name || envForm.environment_key,
                raw_retention_days: envForm.raw_retention_days ? Number(envForm.raw_retention_days) : null,
                aggregate_retention_days: envForm.aggregate_retention_days ? Number(envForm.aggregate_retention_days) : null,
            });
            setAddingEnvFor('');
            setEnvForm(EMPTY_ENV_FORM);
            setMessage('Environment added.');
            await loadProducts();
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
    };

    const saveEnvironment = async (product, environment) => {
        try {
            await api.put(`/admin/products/${product.id}/environments/${environment.id}`, {
                display_name: editingEnv.display_name,
                raw_retention_days: editingEnv.raw_retention_days ? Number(editingEnv.raw_retention_days) : null,
                aggregate_retention_days: editingEnv.aggregate_retention_days ? Number(editingEnv.aggregate_retention_days) : null,
            });
            setEditingEnv(null);
            setMessage('Environment updated.');
            await loadProducts();
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
    };

    const saveRemoteConfig = async (product, environment) => {
        const draftKey = `${product.id}:${environment.id}`;
        const raw = remoteConfigDrafts[draftKey] ?? '{}';

        try {
            const config = JSON.parse(raw);
            await api.put(`/admin/products/${product.id}/environments/${environment.id}/remote-config`, { config });
            setMessage('Remote config updated.');
        } catch (err) {
            setError(getApiErrorMessage(err, 'Invalid remote config JSON.'));
        }
    };

    return (
        <div>
            <h2 className="h4 mb-3">Products</h2>
            {error && <div className="alert alert-danger">{error}</div>}
            {message && <div className="alert alert-success">{message}</div>}

            <div className="card mb-4">
                <div className="card-header">Create product</div>
                <div className="card-body">
                    <form className="row g-3" onSubmit={createProduct}>
                        <div className="col-md-3">
                            <label className="form-label">Product key</label>
                            <input
                                className="form-control"
                                required
                                value={form.product_key}
                                onChange={(e) => setForm({ ...form, product_key: e.target.value })}
                            />
                        </div>
                        <div className="col-md-3">
                            <label className="form-label">Product name</label>
                            <input
                                className="form-control"
                                required
                                value={form.product_name}
                                onChange={(e) => setForm({ ...form, product_name: e.target.value })}
                            />
                        </div>
                        <div className="col-md-2">
                            <label className="form-label">Environment key</label>
                            <input
                                className="form-control"
                                required
                                value={form.environment_key}
                                onChange={(e) => setForm({ ...form, environment_key: e.target.value })}
                            />
                        </div>
                        <div className="col-md-2">
                            <label className="form-label">Environment name</label>
                            <input
                                className="form-control"
                                value={form.environment_name}
                                onChange={(e) => setForm({ ...form, environment_name: e.target.value })}
                            />
                        </div>
                        <div className="col-md-2 d-flex align-items-end">
                            <button className="btn btn-info w-100" type="submit" disabled={submitting}>
                                {submitting ? 'Creating…' : 'Create'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {loading ? (
                <div className="text-center py-4">
                    <div className="spinner-border text-info" role="status" />
                </div>
            ) : (
                <div className="table-responsive">
                    <table className="table table-dark table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Name</th>
                                <th>Environments</th>
                                <th>Status</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {products.map((product) => (
                                <React.Fragment key={product.id}>
                                    <tr>
                                        <td><code>{product.product_key}</code></td>
                                        <td>
                                            <div className="d-flex gap-2">
                                                <input
                                                    className="form-control form-control-sm"
                                                    value={editingNames[product.id] ?? product.product_name}
                                                    onChange={(e) => setEditingNames({
                                                        ...editingNames,
                                                        [product.id]: e.target.value,
                                                    })}
                                                />
                                                <button
                                                    type="button"
                                                    className="btn btn-sm btn-outline-info"
                                                    onClick={() => saveProductName(product)}
                                                >
                                                    Save
                                                </button>
                                            </div>
                                        </td>
                                        <td className="small">
                                            {(product.environments || []).map((env) => env.environment_key).join(', ')}
                                        </td>
                                        <td>
                                            <span className={`badge ${product.is_active ? 'bg-success' : 'bg-secondary'}`}>
                                                {product.is_active ? 'Active' : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="text-nowrap">
                                            <button
                                                type="button"
                                                className="btn btn-sm btn-outline-secondary me-2"
                                                onClick={() => toggleActive(product)}
                                            >
                                                {product.is_active ? 'Disable' : 'Enable'}
                                            </button>
                                            <button
                                                type="button"
                                                className="btn btn-sm btn-outline-info"
                                                onClick={() => {
                                                    setAddingEnvFor(product.id);
                                                    setEnvForm(EMPTY_ENV_FORM);
                                                }}
                                            >
                                                Add environment
                                            </button>
                                        </td>
                                    </tr>

                                    {(product.environments || []).map((environment) => {
                                        const draftKey = `${product.id}:${environment.id}`;
                                        const isEditing = editingEnv?.id === environment.id;

                                        return (
                                            <tr key={`${product.id}-${environment.id}`} className="table-secondary">
                                                <td colSpan={2} className="small ps-4">
                                                    <strong>{environment.environment_key}</strong>
                                                    {environment.display_name ? ` — ${environment.display_name}` : ''}
                                                </td>
                                                <td colSpan={3}>
                                                    {isEditing ? (
                                                        <div className="row g-2 align-items-end">
                                                            <div className="col-md-3">
                                                                <label className="form-label small mb-0">Display name</label>
                                                                <input
                                                                    className="form-control form-control-sm"
                                                                    value={editingEnv.display_name}
                                                                    onChange={(e) => setEditingEnv({ ...editingEnv, display_name: e.target.value })}
                                                                />
                                                            </div>
                                                            <div className="col-md-2">
                                                                <label className="form-label small mb-0">Raw retention (days)</label>
                                                                <input
                                                                    type="number"
                                                                    className="form-control form-control-sm"
                                                                    value={editingEnv.raw_retention_days}
                                                                    onChange={(e) => setEditingEnv({ ...editingEnv, raw_retention_days: e.target.value })}
                                                                />
                                                            </div>
                                                            <div className="col-md-2">
                                                                <label className="form-label small mb-0">Aggregate retention (days)</label>
                                                                <input
                                                                    type="number"
                                                                    className="form-control form-control-sm"
                                                                    value={editingEnv.aggregate_retention_days}
                                                                    onChange={(e) => setEditingEnv({ ...editingEnv, aggregate_retention_days: e.target.value })}
                                                                />
                                                            </div>
                                                            <div className="col-md-5">
                                                                <button type="button" className="btn btn-sm btn-info me-2" onClick={() => saveEnvironment(product, environment)}>Save</button>
                                                                <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setEditingEnv(null)}>Cancel</button>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="d-flex flex-wrap gap-2 align-items-center">
                                                            <span className="small text-muted">
                                                                Raw: {environment.raw_retention_days ?? 'default'} · Aggregate: {environment.aggregate_retention_days ?? 'default'}
                                                            </span>
                                                            <button
                                                                type="button"
                                                                className="btn btn-sm btn-outline-secondary"
                                                                onClick={() => setEditingEnv({
                                                                    id: environment.id,
                                                                    display_name: environment.display_name || '',
                                                                    raw_retention_days: environment.raw_retention_days ?? '',
                                                                    aggregate_retention_days: environment.aggregate_retention_days ?? '',
                                                                })}
                                                            >
                                                                Edit retention
                                                            </button>
                                                            <textarea
                                                                className="form-control form-control-sm font-monospace"
                                                                rows={2}
                                                                placeholder='Remote config JSON, e.g. {"sampling_rate":1}'
                                                                value={remoteConfigDrafts[draftKey] ?? JSON.stringify(environment.settings?.remote_config || {}, null, 2)}
                                                                onChange={(e) => setRemoteConfigDrafts({
                                                                    ...remoteConfigDrafts,
                                                                    [draftKey]: e.target.value,
                                                                })}
                                                            />
                                                            <button
                                                                type="button"
                                                                className="btn btn-sm btn-outline-info"
                                                                onClick={() => saveRemoteConfig(product, environment)}
                                                            >
                                                                Save remote config
                                                            </button>
                                                        </div>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}

                                    {addingEnvFor === product.id && (
                                        <tr className="table-secondary">
                                            <td colSpan={5}>
                                                <form className="row g-2 align-items-end" onSubmit={(e) => addEnvironment(product, e)}>
                                                    <div className="col-md-2">
                                                        <label className="form-label small mb-0">Environment key</label>
                                                        <input
                                                            className="form-control form-control-sm"
                                                            required
                                                            value={envForm.environment_key}
                                                            onChange={(e) => setEnvForm({ ...envForm, environment_key: e.target.value })}
                                                        />
                                                    </div>
                                                    <div className="col-md-2">
                                                        <label className="form-label small mb-0">Display name</label>
                                                        <input
                                                            className="form-control form-control-sm"
                                                            value={envForm.display_name}
                                                            onChange={(e) => setEnvForm({ ...envForm, display_name: e.target.value })}
                                                        />
                                                    </div>
                                                    <div className="col-md-2">
                                                        <label className="form-label small mb-0">Raw retention</label>
                                                        <input
                                                            type="number"
                                                            className="form-control form-control-sm"
                                                            value={envForm.raw_retention_days}
                                                            onChange={(e) => setEnvForm({ ...envForm, raw_retention_days: e.target.value })}
                                                        />
                                                    </div>
                                                    <div className="col-md-2">
                                                        <label className="form-label small mb-0">Aggregate retention</label>
                                                        <input
                                                            type="number"
                                                            className="form-control form-control-sm"
                                                            value={envForm.aggregate_retention_days}
                                                            onChange={(e) => setEnvForm({ ...envForm, aggregate_retention_days: e.target.value })}
                                                        />
                                                    </div>
                                                    <div className="col-md-4">
                                                        <button type="submit" className="btn btn-sm btn-info me-2">Add</button>
                                                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={() => setAddingEnvFor('')}>Cancel</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>
                                    )}
                                </React.Fragment>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
