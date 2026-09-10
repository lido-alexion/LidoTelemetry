import React from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { getBrandName } from '../appBase';

const NAV_ITEMS = [
    { to: '/', label: 'Overview', end: true },
    { to: '/usage', label: 'Usage' },
    { to: '/navigation', label: 'Navigation' },
    { to: '/sessions', label: 'Sessions' },
    { to: '/funnels', label: 'Funnels' },
    { to: '/errors', label: 'Errors' },
    { to: '/latency', label: 'Latency' },
    { to: '/ops', label: 'Ops' },
    { to: '/explorer', label: 'Explorer' },
    { to: '/export', label: 'Export' },
    { to: '/saved-analyses', label: 'Saved Analyses' },
    { to: '/dashboards/custom', label: 'Dashboards' },
];

const ADMIN_ITEMS = [
    { to: '/admin/products', label: 'Products' },
    { to: '/admin/credentials', label: 'Credentials' },
    { to: '/admin/users', label: 'Users' },
    { to: '/admin/invites', label: 'Invites' },
    { to: '/admin/deletions', label: 'Deletions' },
    { to: '/admin/audit', label: 'Audit' },
];

export default function AppHeader() {
    const { user, isAdmin, logout } = useAuth();
    const navigate = useNavigate();
    const brandName = getBrandName();

    const handleLogout = async () => {
        await logout();
        navigate('/login');
    };

    return (
        <header className="telemetry-header">
            <div className="telemetry-header-bar">
                <div className="telemetry-header-brand">
                    <Link to="/" className="telemetry-header-title-link">
                        <h1 className="telemetry-header-title">{brandName}</h1>
                    </Link>
                </div>
                {user && (
                    <div className="telemetry-header-actions">
                        <span className="telemetry-header-user text-muted small">
                            {user.name}
                            <span className="badge bg-secondary ms-2">{user.role}</span>
                        </span>
                        <button type="button" className="btn btn-sm btn-outline-secondary" onClick={handleLogout}>
                            Sign out
                        </button>
                    </div>
                )}
            </div>
            {user && (
                <nav className="telemetry-nav">
                    <ul className="nav nav-pills flex-wrap gap-1">
                        {NAV_ITEMS.map((item) => (
                            <li className="nav-item" key={item.to}>
                                <NavLink
                                    to={item.to}
                                    end={item.end}
                                    className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}
                                >
                                    {item.label}
                                </NavLink>
                            </li>
                        ))}
                        {isAdmin && ADMIN_ITEMS.map((item) => (
                            <li className="nav-item" key={item.to}>
                                <NavLink
                                    to={item.to}
                                    className={({ isActive }) => `nav-link telemetry-nav-admin${isActive ? ' active' : ''}`}
                                >
                                    {item.label}
                                </NavLink>
                            </li>
                        ))}
                    </ul>
                </nav>
            )}
        </header>
    );
}
