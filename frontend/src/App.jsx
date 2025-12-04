import { useEffect, useMemo, useState } from 'react';

const API_BASE = '/api';

const statusPalette = {
  low: '#22c55e',
  medium: '#f59e0b',
  high: '#ef4444',
};

const sampleFiles = [
  { name: 'Documents', type: 'folder', items: 12 },
  { name: 'Media', type: 'folder', items: 34 },
  { name: 'docker-compose.yml', type: 'file', size: '3 KB' },
  { name: 'backup.tar.gz', type: 'file', size: '1.2 GB' },
];

const catalogApps = [
  { id: 'appstore', name: 'AMPOS App Store', info: 'Browse curated services and one-click install.' },
  { id: 'files', name: 'File Browser', info: 'Share a folder with a web UI for uploads.' },
  { id: 'media', name: 'Media Server', info: 'Serve Plex/Jellyfin-style media libraries.' },
  { id: 'git', name: 'Gitea', info: 'Lightweight, self-hosted Git service.' },
];

function formatUsage(value) {
  if (value <= 50) return 'low';
  if (value <= 80) return 'medium';
  return 'high';
}

export default function App() {
  const [status, setStatus] = useState(null);
  const [loading, setLoading] = useState(false);
  const [updateState, setUpdateState] = useState({ running: false, message: '' });
  const [theme, setTheme] = useState('dark');
  const [hostname, setHostname] = useState('ampbox');
  const [network, setNetwork] = useState({ mode: 'dhcp', ip: '192.168.1.20', dns: '1.1.1.1' });
  const [mappedDrives, setMappedDrives] = useState([]);
  const [driveForm, setDriveForm] = useState({ path: '\\\\nas\\media', label: 'Media', username: '', password: '' });
  const [catalogState, setCatalogState] = useState({ installing: null, installed: [] });
  const [settingsMessage, setSettingsMessage] = useState('');
  const [networkMessage, setNetworkMessage] = useState('');
  const usageLevel = useMemo(() => (status ? formatUsage(status.cpu) : 'low'), [status]);

  useEffect(() => {
    fetchStatus();
    const interval = setInterval(fetchStatus, 10000);
    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
  }, [theme]);

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

  function toggleTheme(nextTheme) {
    setTheme(nextTheme);
  }

  function saveHostname(event) {
    event.preventDefault();
    setSettingsMessage('Hostname saved locally. Apply to your host configuration to persist.');
  }

  function saveNetwork(event) {
    event.preventDefault();
    setNetworkMessage('Network plan staged. Push to your OS tooling to enforce.');
  }

  function setNetworkField(field, value) {
    setNetwork((previous) => ({ ...previous, [field]: value }));
  }

  function mapDrive(event) {
    event.preventDefault();
    setMappedDrives((previous) => [...previous, driveForm]);
    setDriveForm({ path: '\\\\nas\\share', label: 'New Share', username: '', password: '' });
  }

  function installApp(appId) {
    setCatalogState((previous) => ({ ...previous, installing: appId }));
    setTimeout(() => {
      setCatalogState((previous) => ({
        installing: null,
        installed: [...new Set([...previous.installed, appId])],
      }));
    }, 800);
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
          <p className="eyebrow">File Explorer</p>
          <h2>Browse volumes and shares</h2>
          <p className="muted">Quickly jump into mounted volumes or network shares. Upload/download wiring can be added later.</p>
          <div className="file-grid">
            {sampleFiles.map((item) => (
              <div key={item.name} className="file-row">
                <div>
                  <p className="file-name">{item.name}</p>
                  <p className="muted">{item.type === 'folder' ? `${item.items} items` : item.size}</p>
                </div>
                <button className="ghost" aria-label={`Open ${item.name}`}>
                  Open
                </button>
              </div>
            ))}
          </div>
        </section>

        <section className="card">
          <p className="eyebrow">Settings</p>
          <h2>Personalize AMPOS</h2>
          <form className="form" onSubmit={saveHostname}>
            <label className="form-label" htmlFor="hostname">
              Hostname
            </label>
            <div className="form-row">
              <input
                id="hostname"
                name="hostname"
                value={hostname}
                onChange={(event) => setHostname(event.target.value)}
              />
              <button className="primary" type="submit">
                Save
              </button>
            </div>
          </form>
          <div className="pill-row">
            <span className="pill">Current: {hostname}</span>
            <span className="pill">API: /api/status</span>
          </div>
          {settingsMessage && <p className="hint">{settingsMessage}</p>}
          <div className="theme-toggle">
            <p className="muted">Theme</p>
            <div className="theme-options">
              <button
                type="button"
                className={theme === 'light' ? 'ghost active' : 'ghost'}
                onClick={() => toggleTheme('light')}
              >
                Light
              </button>
              <button
                type="button"
                className={theme === 'dark' ? 'ghost active' : 'ghost'}
                onClick={() => toggleTheme('dark')}
              >
                Dark
              </button>
            </div>
          </div>
        </section>

        <section className="card">
          <p className="eyebrow">Internet settings</p>
          <h2>LAN + DNS</h2>
          <form className="form" onSubmit={saveNetwork}>
            <div className="form-grid">
              <label>
                Mode
                <select name="mode" value={network.mode} onChange={(event) => setNetworkField('mode', event.target.value)}>
                  <option value="dhcp">DHCP</option>
                  <option value="static">Static</option>
                </select>
              </label>
              <label>
                IP address
                <input name="ip" value={network.ip} onChange={(event) => setNetworkField('ip', event.target.value)} />
              </label>
              <label>
                DNS
                <input name="dns" value={network.dns} onChange={(event) => setNetworkField('dns', event.target.value)} />
              </label>
            </div>
            <div className="form-row">
              <button className="primary" type="submit">
                Apply network plan
              </button>
              <span className="muted">Active: {network.mode.toUpperCase()} — {network.ip} / DNS {network.dns}</span>
            </div>
            {networkMessage && <p className="hint">{networkMessage}</p>}
          </form>
        </section>

        <section className="card">
          <p className="eyebrow">Map drive</p>
          <h2>Attach network shares</h2>
          <form className="form" onSubmit={mapDrive}>
            <div className="form-grid">
              <label>
                Path
                <input
                  required
                  value={driveForm.path}
                  onChange={(event) => setDriveForm({ ...driveForm, path: event.target.value })}
                />
              </label>
              <label>
                Label
                <input
                  required
                  value={driveForm.label}
                  onChange={(event) => setDriveForm({ ...driveForm, label: event.target.value })}
                />
              </label>
              <label>
                Username
                <input
                  value={driveForm.username}
                  onChange={(event) => setDriveForm({ ...driveForm, username: event.target.value })}
                />
              </label>
              <label>
                Password
                <input
                  type="password"
                  value={driveForm.password}
                  onChange={(event) => setDriveForm({ ...driveForm, password: event.target.value })}
                />
              </label>
            </div>
            <div className="form-row">
              <button className="primary" type="submit">
                Map drive
              </button>
              <span className="muted">{mappedDrives.length} mapped</span>
            </div>
          </form>
          {mappedDrives.length > 0 && (
            <div className="file-grid">
              {mappedDrives.map((drive) => (
                <div key={`${drive.label}-${drive.path}`} className="file-row">
                  <div>
                    <p className="file-name">{drive.label}</p>
                    <p className="muted">{drive.path}</p>
                  </div>
                  <button className="ghost" type="button">
                    Eject
                  </button>
                </div>
              ))}
            </div>
          )}
        </section>

        <section className="card">
          <p className="eyebrow">App install system</p>
          <h2>CasaOS-style catalog</h2>
          <p className="muted">Add your own Compose files or use the curated apps below to seed the marketplace.</p>
          <div className="catalog">
            {catalogApps.map((app) => {
              const installing = catalogState.installing === app.id;
              const installed = catalogState.installed.includes(app.id);
              return (
                <div key={app.id} className="catalog-row">
                  <div>
                    <p className="file-name">{app.name}</p>
                    <p className="muted">{app.info}</p>
                  </div>
                  <button
                    className="primary"
                    disabled={installing || installed}
                    onClick={() => installApp(app.id)}
                  >
                    {installed ? 'Installed' : installing ? 'Installing…' : 'Install'}
                  </button>
                </div>
              );
            })}
          </div>
        </section>
      </main>
    </div>
  );
}
