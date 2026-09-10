import React, {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import api from '../api';
import { useAuth } from './AuthContext';

const STORAGE_KEY = 'telemetry_product_context';

const ProductContext = createContext(null);

function loadStoredSelection() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function saveStoredSelection(selection) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(selection));
    } catch {
        // ignore
    }
}

function defaultTimeRange() {
    const end = new Date();
    const start = new Date();
    start.setDate(start.getDate() - 7);
    return {
        start: start.toISOString().slice(0, 16),
        end: end.toISOString().slice(0, 16),
    };
}

export function ProductProvider({ children }) {
    const { user, isAdmin } = useAuth();
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(false);
    const [productId, setProductId] = useState('');
    const [environment, setEnvironment] = useState('');
    const [timeRange, setTimeRange] = useState(defaultTimeRange);

    const refreshProducts = useCallback(async () => {
        if (!user) {
            setProducts([]);
            return;
        }

        setLoading(true);
        try {
            if (isAdmin) {
                const res = await api.get('/admin/products', { skipErrorToast: true });
                setProducts(res.data.data || []);
            } else {
                const res = await api.get('/dashboards', { skipErrorToast: true });
                const dashboards = res.data.data || [];
                const derived = [];
                const seen = new Set();

                dashboards.forEach((dashboard) => {
                    (dashboard.product_ids || []).forEach((id, index) => {
                        if (seen.has(id)) {
                            return;
                        }
                        seen.add(id);
                        derived.push({
                            id,
                            product_key: id.slice(0, 8),
                            product_name: `Product ${id.slice(0, 8)}`,
                            environments: (dashboard.environment_keys || []).map((key) => ({
                                environment_key: key,
                                display_name: key,
                            })),
                        });
                    });
                });

                setProducts(derived);
            }
        } catch {
            setProducts([]);
        } finally {
            setLoading(false);
        }
    }, [user, isAdmin]);

    useEffect(() => {
        refreshProducts();
    }, [refreshProducts]);

    useEffect(() => {
        if (!products.length) {
            return;
        }

        const stored = loadStoredSelection();
        const product = products.find((item) => item.id === stored?.productId) || products[0];
        const envKey = stored?.environment
            || product.environments?.[0]?.environment_key
            || 'production';

        setProductId(product.id);
        setEnvironment(envKey);
    }, [products]);

    useEffect(() => {
        if (productId && environment) {
            saveStoredSelection({ productId, environment });
        }
    }, [productId, environment]);

    const selectedProduct = products.find((item) => item.id === productId) || null;
    const environments = selectedProduct?.environments || [];

    const value = useMemo(() => ({
        products,
        loading,
        productId,
        environment,
        environments,
        selectedProduct,
        timeRange,
        setProductId,
        setEnvironment,
        setTimeRange,
        refreshProducts,
        queryParams: {
            product_id: productId,
            environment,
            start: new Date(timeRange.start).toISOString(),
            end: new Date(timeRange.end).toISOString(),
        },
    }), [
        products,
        loading,
        productId,
        environment,
        environments,
        selectedProduct,
        timeRange,
        refreshProducts,
    ]);

    return (
        <ProductContext.Provider value={value}>
            {children}
        </ProductContext.Provider>
    );
}

export function useProductContext() {
    const ctx = useContext(ProductContext);
    if (!ctx) {
        throw new Error('useProductContext must be used within ProductProvider');
    }
    return ctx;
}

export default ProductContext;
