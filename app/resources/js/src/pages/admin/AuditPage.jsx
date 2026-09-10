import React, { useEffect, useState } from 'react';
import api, { getApiErrorMessage } from '../../api';

const PAGE_SIZE = 25;

export default function AuditPage() {
    const [entries, setEntries] = useState([]);
    const [page, setPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);
    const [total, setTotal] = useState(0);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const loadAudit = async (targetPage = page) => {
        setLoading(true);
        setError('');
        try {
            const res = await api.get('/admin/audit', {
                params: { page: targetPage, per_page: PAGE_SIZE },
                skipErrorToast: true,
            });
            const payload = res.data;
            setEntries(payload.data || []);
            setTotal(payload.meta?.total ?? payload.total ?? (payload.data || []).length);
            setTotalPages(payload.meta?.last_page ?? payload.last_page ?? 1);
            setPage(payload.meta?.current_page ?? payload.current_page ?? targetPage);
        } catch (err) {
            setError(getApiErrorMessage(err, 'Failed to load audit log.'));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadAudit(1);
    }, []);

    return (
        <div>
            <h2 className="h4 mb-3">Audit Log</h2>
            {error && <div className="alert alert-danger">{error}</div>}

            {loading ? (
                <div className="text-center py-4">
                    <div className="spinner-border text-info" role="status" />
                </div>
            ) : (
                <>
                    <div className="text-muted small mb-2">{total} audit entries</div>
                    <div className="table-responsive">
                        <table className="table table-dark table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>When</th>
                                    <th>Action</th>
                                    <th>User</th>
                                    <th>Subject</th>
                                    <th>Context</th>
                                </tr>
                            </thead>
                            <tbody>
                                {entries.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="text-muted">No audit entries found.</td>
                                    </tr>
                                ) : (
                                    entries.map((entry) => (
                                        <tr key={entry.id}>
                                            <td className="small text-nowrap">{entry.occurred_at || entry.created_at || '—'}</td>
                                            <td><code>{entry.action}</code></td>
                                            <td className="small">{entry.user?.email || entry.user_id || '—'}</td>
                                            <td className="small">
                                                {entry.subject_type ? `${entry.subject_type}:${entry.subject_id || '—'}` : '—'}
                                            </td>
                                            <td className="small">
                                                <pre className="telemetry-json-preview mb-0">
                                                    {JSON.stringify(entry.context || {}, null, 2)}
                                                </pre>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="d-flex justify-content-between align-items-center">
                        <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary"
                            disabled={page <= 1}
                            onClick={() => loadAudit(page - 1)}
                        >
                            Previous
                        </button>
                        <span className="small text-muted">Page {page} of {totalPages}</span>
                        <button
                            type="button"
                            className="btn btn-sm btn-outline-secondary"
                            disabled={page >= totalPages}
                            onClick={() => loadAudit(page + 1)}
                        >
                            Next
                        </button>
                    </div>
                </>
            )}
        </div>
    );
}
