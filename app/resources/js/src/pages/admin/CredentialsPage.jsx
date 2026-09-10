import React, { useEffect, useState } from 'react';
import api, { getApiErrorMessage } from '../../api';

const SCOPES = [
    'events:read',
    'analytics:read',
    'exports:create',
    'products:manage',
    'credentials:manage',
];

export default function CredentialsPage() {
    const [products, setProducts] = useState([]);
    const [ingestion, setIngestion] = useState([]);
    const [apiTokens, setApiTokens] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [ingestionForm, setIngestionForm] = useState({ product_id: '', environment_id: '', name: '' });
    const [tokenForm, setTokenForm] = useState({
        name: '',
        role: 'analyst',
        scopes: ['analytics:read', 'events:read'],
    });

    const loadAll = async () => {
        setLoading(true);
        setError('');
        try {
            const [productsRes, ingestionRes, tokensRes] = await Promise.all([
                api.get('/admin/products'),
                api.get('/admin/credentials/ingestion'),
                api.get('/admin/credentials/api-tokens'),
            ]);
            setProducts(productsRes.data.data || []);
            setIngestion(ingestionRes.data.data || []);
            setApiTokens(tokensRes.data.data || []);
        } catch (err) {
            setError(getApiErrorMessage(err));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadAll();
    }, []);

    const selectedProduct = products.find((p) => p.id === ingestionForm.product_id);
    const environments = selectedProduct?.environments || [];

    const createIngestion = async (e) => {
        e.preventDefault();
        setMessage('');
        setError('');
        try {
            const res = await api.post('/admin/credentials/ingestion', ingestionForm);
            setMessage(`Ingestion token created: ${res.data.data?.token || '(copy from response)'}`);
            setIngestionForm({ product_id: '', environment_id: '', name: '' });
            await loadAll();
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
    };

    const createApiToken = async (e) => {
        e.preventDefault();
        setMessage('');
        setError('');
        try {
            const res = await api.post('/admin/credentials/api-tokens', tokenForm);
            setMessage(`API token created: ${res.data.data?.token || '(copy from response)'}`);
            setTokenForm({ name: '', role: 'analyst', scopes: ['analytics:read', 'events:read'] });
            await loadAll();
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
    };

    const revokeIngestion = async (id) => {
        try {
            await api.delete(`/admin/credentials/ingestion/${id}`);
            await loadAll();
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
    };

    const revokeApiToken = async (id) => {
        try {
            await api.delete(`/admin/credentials/api-tokens/${id}`);
            await loadAll();
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
    };

    const toggleScope = (scope) => {
        setTokenForm((current) => ({
            ...current,
            scopes: current.scopes.includes(scope)
                ? current.scopes.filter((item) => item !== scope)
                : [...current.scopes, scope],
        }));
    };

    if (loading) {
        return (
            <div className="text-center py-5">
                <div className="spinner-border text-info" role="status" />
            </div>
        );
    }

    return (
        <div>
            <h2 className="h4 mb-3">Credentials</h2>
            {error && <div className="alert alert-danger">{error}</div>}
            {message && <div className="alert alert-success">{message}</div>}

            <div className="card mb-4">
                <div className="card-header">Create ingestion credential</div>
                <div className="card-body">
                    <form className="row g-3" onSubmit={createIngestion}>
                        <div className="col-md-3">
                            <label className="form-label">Product</label>
                            <select
                                className="form-select"
                                required
                                value={ingestionForm.product_id}
                                onChange={(e) => setIngestionForm({
                                    product_id: e.target.value,
                                    environment_id: '',
                                    name: ingestionForm.name,
                                })}
                            >
                                <option value="">Select…</option>
                                {products.map((product) => (
                                    <option key={product.id} value={product.id}>{product.product_name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-3">
                            <label className="form-label">Environment</label>
                            <select
                                className="form-select"
                                required
                                value={ingestionForm.environment_id}
                                onChange={(e) => setIngestionForm({ ...ingestionForm, environment_id: e.target.value })}
                            >
                                <option value="">Select…</option>
                                {environments.map((env) => (
                                    <option key={env.id} value={env.id}>{env.environment_key}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-4">
                            <label className="form-label">Name</label>
                            <input
                                className="form-control"
                                required
                                value={ingestionForm.name}
                                onChange={(e) => setIngestionForm({ ...ingestionForm, name: e.target.value })}
                            />
                        </div>
                        <div className="col-md-2 d-flex align-items-end">
                            <button className="btn btn-info w-100" type="submit">Create</button>
                        </div>
                    </form>
                </div>
            </div>

            <div className="card mb-4">
                <div className="card-header">Create API token</div>
                <div className="card-body">
                    <form onSubmit={createApiToken}>
                        <div className="row g-3 mb-3">
                            <div className="col-md-4">
                                <label className="form-label">Name</label>
                                <input
                                    className="form-control"
                                    required
                                    value={tokenForm.name}
                                    onChange={(e) => setTokenForm({ ...tokenForm, name: e.target.value })}
                                />
                            </div>
                            <div className="col-md-3">
                                <label className="form-label">Role</label>
                                <select
                                    className="form-select"
                                    value={tokenForm.role}
                                    onChange={(e) => setTokenForm({ ...tokenForm, role: e.target.value })}
                                >
                                    <option value="admin">admin</option>
                                    <option value="analyst">analyst</option>
                                    <option value="viewer">viewer</option>
                                </select>
                            </div>
                        </div>
                        <div className="d-flex flex-wrap gap-2 mb-3">
                            {SCOPES.map((scope) => (
                                <label key={scope} className="form-check">
                                    <input
                                        className="form-check-input"
                                        type="checkbox"
                                        checked={tokenForm.scopes.includes(scope)}
                                        onChange={() => toggleScope(scope)}
                                    />
                                    <span className="form-check-label">{scope}</span>
                                </label>
                            ))}
                        </div>
                        <button className="btn btn-info" type="submit">Create API token</button>
                    </form>
                </div>
            </div>

            <h3 className="h6">Ingestion credentials</h3>
            <div className="table-responsive mb-4">
                <table className="table table-dark table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Product</th>
                            <th>Environment</th>
                            <th>Prefix</th>
                            <th>Status</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {ingestion.map((item) => (
                            <tr key={item.id}>
                                <td>{item.name}</td>
                                <td>{item.product?.product_key || item.product_id}</td>
                                <td>{item.environment?.environment_key || item.environment_id}</td>
                                <td><code>{item.token_prefix}</code></td>
                                <td>{item.is_active ? 'Active' : 'Revoked'}</td>
                                <td>
                                    {item.is_active && (
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-outline-danger"
                                            onClick={() => revokeIngestion(item.id)}
                                        >
                                            Revoke
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <h3 className="h6">API tokens</h3>
            <div className="table-responsive">
                <table className="table table-dark table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Scopes</th>
                            <th>Prefix</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        {apiTokens.map((item) => (
                            <tr key={item.id}>
                                <td>{item.name}</td>
                                <td>{item.role}</td>
                                <td className="small">{(item.scopes || []).join(', ')}</td>
                                <td><code>{item.token_prefix}</code></td>
                                <td>
                                    {item.is_active && (
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-outline-danger"
                                            onClick={() => revokeApiToken(item.id)}
                                        >
                                            Revoke
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
