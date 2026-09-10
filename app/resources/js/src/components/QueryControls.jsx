import React from 'react';
import { useProductContext } from '../context/ProductContext';

export default function QueryControls() {
    const {
        products,
        loading,
        productId,
        environment,
        environments,
        timeRange,
        setProductId,
        setEnvironment,
        setTimeRange,
    } = useProductContext();

    if (loading) {
        return (
            <div className="telemetry-query-controls card mb-3">
                <div className="card-body py-3 text-muted small">Loading products…</div>
            </div>
        );
    }

    if (!products.length) {
        return (
            <div className="telemetry-query-controls card mb-3">
                <div className="card-body py-3 text-warning small">
                    No products available. Ask an administrator to configure products.
                </div>
            </div>
        );
    }

    return (
        <div className="telemetry-query-controls card mb-3">
            <div className="card-body">
                <div className="row g-3 align-items-end">
                    <div className="col-md-3">
                        <label className="form-label">Product</label>
                        <select
                            className="form-select"
                            value={productId}
                            onChange={(e) => setProductId(e.target.value)}
                        >
                            {products.map((product) => (
                                <option key={product.id} value={product.id}>
                                    {product.product_name} ({product.product_key})
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="col-md-2">
                        <label className="form-label">Environment</label>
                        <select
                            className="form-select"
                            value={environment}
                            onChange={(e) => setEnvironment(e.target.value)}
                        >
                            {(environments.length ? environments : [{ environment_key: environment }]).map((env) => (
                                <option key={env.environment_key} value={env.environment_key}>
                                    {env.display_name || env.environment_key}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="col-md-3">
                        <label className="form-label">Start</label>
                        <input
                            type="datetime-local"
                            className="form-control"
                            value={timeRange.start}
                            onChange={(e) => setTimeRange({ ...timeRange, start: e.target.value })}
                        />
                    </div>
                    <div className="col-md-3">
                        <label className="form-label">End</label>
                        <input
                            type="datetime-local"
                            className="form-control"
                            value={timeRange.end}
                            onChange={(e) => setTimeRange({ ...timeRange, end: e.target.value })}
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
