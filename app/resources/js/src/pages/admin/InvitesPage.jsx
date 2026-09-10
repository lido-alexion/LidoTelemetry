import React, { useEffect, useState } from 'react';
import api, { getApiErrorMessage } from '../../api';

const ROLES = ['admin', 'analyst', 'viewer'];

export default function InvitesPage() {
    const [invites, setInvites] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [form, setForm] = useState({ email: '', role: 'analyst' });

    const loadInvites = async () => {
        setLoading(true);
        setError('');
        try {
            const res = await api.get('/admin/invites');
            setInvites(res.data.data || []);
        } catch (err) {
            setError(getApiErrorMessage(err));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadInvites();
    }, []);

    const createInvite = async (e) => {
        e.preventDefault();
        setMessage('');
        setError('');
        try {
            const res = await api.post('/admin/invites', form);
            const inviteUrl = res.data.data?.invite_url;
            setMessage(inviteUrl ? `Invite created: ${inviteUrl}` : 'Invite created.');
            setForm({ email: '', role: 'analyst' });
            await loadInvites();
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
    };

    const regenerateInvite = async (invite) => {
        setMessage('');
        setError('');
        try {
            const res = await api.post(`/admin/invites/${invite.id}/regenerate`);
            const inviteUrl = res.data.data?.invite_url;
            setMessage(inviteUrl ? `Invite regenerated: ${inviteUrl}` : 'Invite regenerated.');
            await loadInvites();
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
    };

    const revokeInvite = async (invite) => {
        try {
            await api.delete(`/admin/invites/${invite.id}`);
            await loadInvites();
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
    };

    return (
        <div>
            <h2 className="h4 mb-3">Invites</h2>
            {error && <div className="alert alert-danger">{error}</div>}
            {message && <div className="alert alert-success">{message}</div>}

            <div className="card mb-4">
                <div className="card-header">Send invite</div>
                <div className="card-body">
                    <form className="row g-3" onSubmit={createInvite}>
                        <div className="col-md-5">
                            <label className="form-label">Email</label>
                            <input
                                type="email"
                                className="form-control"
                                required
                                value={form.email}
                                onChange={(e) => setForm({ ...form, email: e.target.value })}
                            />
                        </div>
                        <div className="col-md-3">
                            <label className="form-label">Role</label>
                            <select
                                className="form-select"
                                value={form.role}
                                onChange={(e) => setForm({ ...form, role: e.target.value })}
                            >
                                {ROLES.map((role) => (
                                    <option key={role} value={role}>{role}</option>
                                ))}
                            </select>
                        </div>
                        <div className="col-md-2 d-flex align-items-end">
                            <button className="btn btn-info w-100" type="submit">Create invite</button>
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
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Expires</th>
                                <th>Invite URL</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {invites.map((invite) => (
                                <tr key={invite.id}>
                                    <td>{invite.email}</td>
                                    <td>{invite.role}</td>
                                    <td>{invite.status}</td>
                                    <td className="small">{invite.expires_at}</td>
                                    <td className="small">
                                        {invite.invite_url ? (
                                            <code className="d-block text-break">{invite.invite_url}</code>
                                        ) : '—'}
                                    </td>
                                    <td className="text-nowrap">
                                        {invite.status === 'pending' && (
                                            <>
                                                <button
                                                    type="button"
                                                    className="btn btn-sm btn-outline-info me-1"
                                                    onClick={() => regenerateInvite(invite)}
                                                >
                                                    Regenerate
                                                </button>
                                                <button
                                                    type="button"
                                                    className="btn btn-sm btn-outline-danger"
                                                    onClick={() => revokeInvite(invite)}
                                                >
                                                    Revoke
                                                </button>
                                            </>
                                        )}
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
