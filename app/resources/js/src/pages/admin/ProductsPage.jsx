import React, { useEffect, useState } from 'react';
import api, { getApiErrorMessage } from '../../api';

const EMPTY_FORM = {
    product_key: '',
    product_name: '',
    environment_key: 'production',
    environment_name: 'Production',
};

export default function ProductsPage() {
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [form, setForm] = useState(EMPTY_FORM);
    const [submitting, setSubmitting] = useState(false);

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

    return (
        <div>
            <h2 className="h4 mb-3">Products</h2>
            {error && <div className="alert alert-danger">{error}</div>}

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
                                <tr key={product.id}>
                                    <td><code>{product.product_key}</code></td>
                                    <td>{product.product_name}</td>
                                    <td className="small">
                                        {(product.environments || []).map((env) => env.environment_key).join(', ')}
                                    </td>
                                    <td>
                                        <span className={`badge ${product.is_active ? 'bg-success' : 'bg-secondary'}`}>
                                            {product.is_active ? 'Active' : 'Inactive'}
                                        </span>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            className="btn btn-sm btn-outline-secondary"
                                            onClick={() => toggleActive(product)}
                                        >
                                            {product.is_active ? 'Disable' : 'Enable'}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
