import React, { useEffect, useState } from 'react';
import api, { getApiErrorMessage } from '../../api';

const ROLES = ['admin', 'analyst', 'viewer'];

export default function UsersPage() {
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    const loadUsers = async () => {
        setLoading(true);
        setError('');
        try {
            const res = await api.get('/admin/users');
            setUsers(res.data.data || []);
        } catch (err) {
            setError(getApiErrorMessage(err));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadUsers();
    }, []);

    const updateUser = async (user, changes) => {
        try {
            await api.put(`/admin/users/${user.id}`, changes);
            await loadUsers();
        } catch (err) {
            setError(getApiErrorMessage(err));
        }
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
            <h2 className="h4 mb-3">Users</h2>
            {error && <div className="alert alert-danger">{error}</div>}
            <div className="table-responsive">
                <table className="table table-dark table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.map((user) => (
                            <tr key={user.id}>
                                <td>{user.name}</td>
                                <td>{user.email}</td>
                                <td>
                                    <select
                                        className="form-select form-select-sm"
                                        value={user.role}
                                        onChange={(e) => updateUser(user, { role: e.target.value })}
                                    >
                                        {ROLES.map((role) => (
                                            <option key={role} value={role}>{role}</option>
                                        ))}
                                    </select>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        className={`btn btn-sm ${user.is_active ? 'btn-outline-success' : 'btn-outline-secondary'}`}
                                        onClick={() => updateUser(user, { is_active: !user.is_active })}
                                    >
                                        {user.is_active ? 'Active' : 'Inactive'}
                                    </button>
                                </td>
                                <td className="small text-muted">{user.created_at}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
