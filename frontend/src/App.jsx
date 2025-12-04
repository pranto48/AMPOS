import { useEffect, useMemo, useState } from 'react';

const API_BASE = '/api';

const statusPalette = {
  low: '#22c55e',
  medium: '#f59e0b',
  high: '#ef4444',
};

function formatUsage(value) {
  if (value <= 50) return 'low';
  if (value <= 80) return 'medium';
  return 'high';
}

export default function App() {
  const [status, setStatus] = useState(null);
  const [loading, setLoading] = useState(false);
  const [updateState, setUpdateState] = useState({ running: false, message: '' });
  const usageLevel = useMemo(() => (status ? formatUsage(status.cpu) : 'low'), [status]);

  useEffect(() => {
    fetchStatus();
    const interval = setInterval(fetchStatus, 10000);
    return () => clearInterval(interval);
  }, []);

  async function fetchStatus() {
    setLoading(true);
    try {
      const response = await fetch(`${API_BASE}/status`);
      const data = await response.json();
      setStatus(data);
    } catch (error) {
      console.error('Failed to load status', error);
      setStatus(null);
    } finally {
      setLoading(false);
    }
  }

  async function triggerUpdate() {
    setUpdateState({ running: true, message: 'Starting update...' });
    try {
      const response = await fetch(`${API_BASE}/update`, { method: 'POST' });
      const data = await response.json();
      setUpdateState({
        running: false,
        message: data.message || 'Update triggered. Backend will handle the restart.',
      });
    } catch (error) {
      setUpdateState({ running: false, message: 'Update failed. Please check logs.' });
    }
  }

  return (
    <div className="layout">
      <header className="hero">
        <div>
          <p className="eyebrow">AMPOS • Web-first OS layer</p>
          <h1>Control your servers like a desktop OS</h1>
          <p className="lead">
            AMPOS is a lightweight, CasaOS-inspired web experience for managing Windows or Linux hosts.
            View health metrics, run updates, and expose your own apps in one dashboard.
          </p>
          <div className="actions">
            <button className="primary" onClick={triggerUpdate} disabled={updateState.running}>
              {updateState.running ? 'Updating…' : 'Run Update'}
            </button>
            <button className="ghost" onClick={fetchStatus} disabled={loading}>
              {loading ? 'Refreshing…' : 'Refresh Status'}
            </button>
          </div>
          {updateState.message && <p className="hint">{updateState.message}</p>}
        </div>
        <div className="hero-card">
          <p className="muted">Live Status</p>
          <h3>{status ? 'Connected' : 'Waiting for backend'}</h3>
          <div className="meter" aria-label="CPU usage">
            <div
              className="meter-fill"
              style={{ width: `${status?.cpu ?? 0}%`, backgroundColor: statusPalette[usageLevel] }}
            />
          </div>
          <div className="stat-grid">
            <div>
              <p className="muted">CPU</p>
              <p className="stat-value">{status ? `${status.cpu}%` : '--'}</p>
            </div>
            <div>
              <p className="muted">RAM</p>
              <p className="stat-value">{status ? `${status.ram.used} / ${status.ram.total} GB` : '--'}</p>
            </div>
            <div>
              <p className="muted">Storage</p>
              <p className="stat-value">{status ? status.storage : '--'}</p>
            </div>
          </div>
        </div>
      </header>

      <main className="grid">
        <section className="card">
          <p className="eyebrow">Quick actions</p>
          <h2>Deploy apps fast</h2>
          <p className="muted">
            Drop your Docker Compose files or package manifests into AMPOS to deploy apps with guardrails. A
            curated catalog of community services will ship next.
          </p>
          <ul className="list">
            <li>Simple API to trigger updates or service restarts.</li>
            <li>Same workflow works on Linux and Windows hosts.</li>
            <li>Serve the built UI via the Node backend on port 3001.</li>
          </ul>
        </section>

        <section className="card">
          <p className="eyebrow">Observability</p>
          <h2>Stay in control</h2>
          <p className="muted">
            The dashboard surfaces CPU, memory, and storage. Replace the mocked metrics in `backend/server.js`
            with real data from your platform of choice when you are ready.
          </p>
          <div className="pill-row">
            <span className="pill">Backend: Express</span>
            <span className="pill">Frontend: React + Vite</span>
            <span className="pill">API: /api/status & /api/update</span>
          </div>
        </section>
      </main>
    </div>
  );
}
