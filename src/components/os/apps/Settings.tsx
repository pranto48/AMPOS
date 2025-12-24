import React, { useState, useEffect } from 'react';
import { 
  User, 
  Palette, 
  Bell, 
  Shield, 
  Globe, 
  Database,
  RefreshCw,
  Download,
  Key,
  Monitor,
  Github,
  ExternalLink,
  Check,
  AlertCircle,
  Loader2,
  Save,
  Webhook,
  Copy,
  Play,
  Sparkles,
  Moon
} from 'lucide-react';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast } from 'sonner';
import { supabase } from '@/integrations/supabase/client';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useTheme } from '@/contexts/ThemeContext';

const settingsCategories = [
  { id: 'account', name: 'Account', icon: User },
  { id: 'appearance', name: 'Appearance', icon: Palette },
  { id: 'notifications', name: 'Notifications', icon: Bell },
  { id: 'security', name: 'Security', icon: Shield },
  { id: 'webhook', name: 'Webhook', icon: Webhook },
  { id: 'network', name: 'Network', icon: Globe },
  { id: 'storage', name: 'Storage', icon: Database },
  { id: 'updates', name: 'Updates', icon: RefreshCw },
];

interface CommitInfo {
  sha: string;
  message: string;
  author: string;
  date: string;
  url: string;
}

interface UpdateInfo {
  hasUpdates: boolean;
  currentVersion: string;
  latestVersion: string;
  commits: CommitInfo[];
  lastChecked: string;
  error?: string;
}

const WEBHOOK_URL = 'https://hjsugraqchavtzmomfki.supabase.co/functions/v1/github-webhook';

const Settings: React.FC = () => {
  const { theme, setTheme } = useTheme();
  const [selectedCategory, setSelectedCategory] = useState('account');
  const [settings, setSettings] = useState({
    darkMode: true,
    notifications: true,
    autoUpdates: true,
    twoFactor: false,
  });
  
  const [repoUrl, setRepoUrl] = useState('https://github.com/pranto48/ampos.git');
  const [isCheckingUpdates, setIsCheckingUpdates] = useState(false);
  const [isSavingRepo, setIsSavingRepo] = useState(false);
  const [updateInfo, setUpdateInfo] = useState<UpdateInfo | null>(null);
  const [lastChecked, setLastChecked] = useState<string | null>(null);
  const [isTestingWebhook, setIsTestingWebhook] = useState(false);
  const [webhookTestResult, setWebhookTestResult] = useState<{ success: boolean; message: string } | null>(null);

  useEffect(() => {
    loadSettings();
  }, []);

  const loadSettings = async () => {
    try {
      const { data: repoData } = await supabase
        .from('system_settings')
        .select('value')
        .eq('key', 'github_repo')
        .maybeSingle();

      if (repoData?.value) {
        const repoValue = repoData.value as { url?: string };
        if (repoValue.url) {
          setRepoUrl(repoValue.url);
        }
      }

      const { data: versionData } = await supabase
        .from('system_settings')
        .select('value')
        .eq('key', 'version')
        .maybeSingle();

      if (versionData?.value) {
        const versionValue = versionData.value as { lastChecked?: string };
        if (versionValue.lastChecked) {
          setLastChecked(versionValue.lastChecked);
        }
      }
    } catch (error) {
      console.error('Error loading settings:', error);
    }
  };

  const handleSaveRepoUrl = async () => {
    setIsSavingRepo(true);
    try {
      const { data, error } = await supabase.functions.invoke('update-github-settings', {
        body: { repoUrl },
      });

      if (error) throw error;
      if (data.error) throw new Error(data.error);

      toast.success('GitHub repository URL saved!');
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to save repository URL';
      toast.error(message);
    } finally {
      setIsSavingRepo(false);
    }
  };

  const handleCheckForUpdates = async () => {
    setIsCheckingUpdates(true);
    setUpdateInfo(null);
    
    try {
      toast.info('Checking for updates from GitHub...');
      
      const { data, error } = await supabase.functions.invoke('check-github-updates');

      if (error) throw error;
      if (data.error) throw new Error(data.error);

      setUpdateInfo(data);
      setLastChecked(data.lastChecked);

      if (data.hasUpdates) {
        toast.success(`${data.commits.length} update(s) available!`);
      } else {
        toast.success('System is up to date!');
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to check for updates';
      toast.error(message);
      setUpdateInfo({
        hasUpdates: false,
        currentVersion: '1.0.0',
        latestVersion: '1.0.0',
        commits: [],
        lastChecked: new Date().toISOString(),
        error: message,
      });
    } finally {
      setIsCheckingUpdates(false);
    }
  };

  const handleMarkAsUpdated = async () => {
    try {
      const { data, error } = await supabase.functions.invoke('update-github-settings', {
        body: { markAsUpdated: true },
      });

      if (error) throw error;
      if (data.error) throw new Error(data.error);

      toast.success('Marked as updated! Your system is now current.');
      setUpdateInfo(null);
      loadSettings();
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to mark as updated';
      toast.error(message);
    }
  };

  const handleCopyWebhookUrl = () => {
    navigator.clipboard.writeText(WEBHOOK_URL);
    toast.success('Webhook URL copied to clipboard!');
  };

  const handleTestWebhook = async () => {
    setIsTestingWebhook(true);
    setWebhookTestResult(null);

    try {
      const response = await fetch(WEBHOOK_URL, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'x-github-event': 'ping',
        },
        body: JSON.stringify({
          zen: 'Test webhook from AMPOS Settings',
          hook_id: 'test-' + Date.now(),
          repository: {
            full_name: 'test/ampos-webhook-test',
          },
        }),
      });

      const data = await response.json();

      if (response.ok) {
        setWebhookTestResult({
          success: true,
          message: 'Webhook is working! Check your notifications.',
        });
        toast.success('Webhook test successful!');
      } else {
        throw new Error(data.error || 'Webhook test failed');
      }
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Failed to test webhook';
      setWebhookTestResult({
        success: false,
        message,
      });
      toast.error(message);
    } finally {
      setIsTestingWebhook(false);
    }
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const renderContent = () => {
    switch (selectedCategory) {
      case 'account':
        return (
          <div className="space-y-6">
            <div>
              <h3 className="text-lg font-medium text-foreground mb-4">Account Settings</h3>
              <div className="glass p-4 space-y-4">
                <div className="flex items-center gap-4">
                  <div className="w-16 h-16 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center">
                    <span className="text-2xl font-bold text-primary-foreground">A</span>
                  </div>
                  <div>
                    <p className="text-foreground font-medium">admin</p>
                    <p className="text-sm text-muted-foreground">admin@itsupport.com.bd</p>
                    <p className="text-xs text-primary">Administrator</p>
                  </div>
                </div>
                <div className="grid gap-4">
                  <div>
                    <label className="text-sm text-muted-foreground">Username</label>
                    <Input defaultValue="admin" className="mt-1 bg-secondary/50" />
                  </div>
                  <div>
                    <label className="text-sm text-muted-foreground">Email</label>
                    <Input defaultValue="admin@itsupport.com.bd" className="mt-1 bg-secondary/50" />
                  </div>
                </div>
                <Button className="bg-primary text-primary-foreground">
                  Save Changes
                </Button>
              </div>
            </div>
          </div>
        );

      case 'appearance':
        return (
          <div className="space-y-6">
            <h3 className="text-lg font-medium text-foreground mb-4">Appearance</h3>
            
            {/* Theme Selection */}
            <div className="glass p-4 space-y-4">
              <h4 className="font-medium text-foreground">Theme</h4>
              <div className="grid grid-cols-2 gap-4">
                {/* Default Theme */}
                <button
                  onClick={() => setTheme('default')}
                  className={`relative p-4 rounded-xl border-2 transition-all ${
                    theme === 'default'
                      ? 'border-primary bg-primary/10'
                      : 'border-border hover:border-primary/50'
                  }`}
                >
                  <div className="w-full h-24 rounded-lg bg-gradient-to-br from-[hsl(222,47%,8%)] to-[hsl(222,47%,12%)] mb-3 flex items-center justify-center overflow-hidden">
                    <div className="w-3/4 h-16 rounded bg-[hsl(199,89%,48%)]/20 border border-[hsl(199,89%,48%)]/30" />
                  </div>
                  <div className="flex items-center gap-2">
                    <Moon className="w-4 h-4 text-primary" />
                    <span className="text-sm font-medium text-foreground">Default Dark</span>
                  </div>
                  {theme === 'default' && (
                    <div className="absolute top-2 right-2 w-5 h-5 bg-primary rounded-full flex items-center justify-center">
                      <Check className="w-3 h-3 text-primary-foreground" />
                    </div>
                  )}
                </button>

                {/* Liquid Glass Theme */}
                <button
                  onClick={() => setTheme('liquid-glass')}
                  className={`relative p-4 rounded-xl border-2 transition-all ${
                    theme === 'liquid-glass'
                      ? 'border-primary bg-primary/10'
                      : 'border-border hover:border-primary/50'
                  }`}
                >
                  <div className="w-full h-24 rounded-lg bg-gradient-to-br from-purple-900/50 via-blue-900/30 to-cyan-900/50 mb-3 flex items-center justify-center overflow-hidden relative">
                    <div className="absolute inset-0 bg-gradient-to-br from-purple-500/20 to-cyan-500/20" />
                    <div className="w-3/4 h-16 rounded bg-gradient-to-br from-purple-500/30 to-cyan-500/20 border border-purple-400/40 backdrop-blur" />
                    <div className="absolute top-2 left-2 w-8 h-8 rounded-full bg-purple-500/30 blur-xl" />
                    <div className="absolute bottom-2 right-2 w-6 h-6 rounded-full bg-cyan-500/30 blur-lg" />
                  </div>
                  <div className="flex items-center gap-2">
                    <Sparkles className="w-4 h-4 text-purple-400" />
                    <span className="text-sm font-medium text-foreground">Liquid Glass</span>
                  </div>
                  {theme === 'liquid-glass' && (
                    <div className="absolute top-2 right-2 w-5 h-5 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-full flex items-center justify-center">
                      <Check className="w-3 h-3 text-white" />
                    </div>
                  )}
                </button>
              </div>
            </div>

            <div className="glass p-4 space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-foreground">Dark Mode</p>
                  <p className="text-sm text-muted-foreground">Use dark theme</p>
                </div>
                <Switch
                  checked={settings.darkMode}
                  onCheckedChange={(checked) =>
                    setSettings({ ...settings, darkMode: checked })
                  }
                />
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-foreground">Accent Color</p>
                  <p className="text-sm text-muted-foreground">Choose your accent color</p>
                </div>
                <div className="flex gap-2">
                  {['bg-primary', 'bg-success', 'bg-warning', 'bg-destructive'].map((color) => (
                    <button
                      key={color}
                      className={`w-6 h-6 rounded-full ${color} ring-2 ring-offset-2 ring-offset-card ring-transparent hover:ring-primary transition-all`}
                    />
                  ))}
                </div>
              </div>
            </div>
          </div>
        );

      case 'notifications':
        return (
          <div className="space-y-6">
            <h3 className="text-lg font-medium text-foreground mb-4">Notifications</h3>
            <div className="glass p-4 space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-foreground">Enable Notifications</p>
                  <p className="text-sm text-muted-foreground">Receive system notifications</p>
                </div>
                <Switch
                  checked={settings.notifications}
                  onCheckedChange={(checked) =>
                    setSettings({ ...settings, notifications: checked })
                  }
                />
              </div>
            </div>
          </div>
        );

      case 'security':
        return (
          <div className="space-y-6">
            <h3 className="text-lg font-medium text-foreground mb-4">Security</h3>
            <div className="glass p-4 space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-foreground">Two-Factor Authentication</p>
                  <p className="text-sm text-muted-foreground">Add extra security to your account</p>
                </div>
                <Switch
                  checked={settings.twoFactor}
                  onCheckedChange={(checked) =>
                    setSettings({ ...settings, twoFactor: checked })
                  }
                />
              </div>
              <div>
                <Button variant="outline" className="w-full justify-start gap-2">
                  <Key className="w-4 h-4" />
                  Change Password
                </Button>
              </div>
            </div>
          </div>
        );

      case 'webhook':
        return (
          <div className="space-y-6">
            <h3 className="text-lg font-medium text-foreground mb-4">Webhook Configuration</h3>
            
            {/* Webhook URL */}
            <div className="glass p-4 space-y-4">
              <div className="flex items-center gap-2 mb-2">
                <Webhook className="w-5 h-5 text-primary" />
                <h4 className="font-medium text-foreground">GitHub Webhook URL</h4>
              </div>
              <p className="text-sm text-muted-foreground mb-3">
                Use this URL to configure webhooks in your GitHub repository settings.
              </p>
              <div className="flex gap-2">
                <Input
                  value={WEBHOOK_URL}
                  readOnly
                  className="flex-1 bg-secondary/50 font-mono text-xs"
                />
                <Button
                  onClick={handleCopyWebhookUrl}
                  variant="outline"
                  size="icon"
                >
                  <Copy className="w-4 h-4" />
                </Button>
              </div>
            </div>

            {/* Setup Instructions */}
            <div className="glass p-4 space-y-4">
              <h4 className="font-medium text-foreground">Setup Instructions</h4>
              <div className="space-y-3 text-sm">
                <div className="flex gap-3">
                  <span className="w-6 h-6 rounded-full bg-primary/20 text-primary flex items-center justify-center text-xs font-bold shrink-0">1</span>
                  <p className="text-muted-foreground">Go to your GitHub repository → Settings → Webhooks</p>
                </div>
                <div className="flex gap-3">
                  <span className="w-6 h-6 rounded-full bg-primary/20 text-primary flex items-center justify-center text-xs font-bold shrink-0">2</span>
                  <p className="text-muted-foreground">Click "Add webhook"</p>
                </div>
                <div className="flex gap-3">
                  <span className="w-6 h-6 rounded-full bg-primary/20 text-primary flex items-center justify-center text-xs font-bold shrink-0">3</span>
                  <p className="text-muted-foreground">Paste the webhook URL above in "Payload URL"</p>
                </div>
                <div className="flex gap-3">
                  <span className="w-6 h-6 rounded-full bg-primary/20 text-primary flex items-center justify-center text-xs font-bold shrink-0">4</span>
                  <p className="text-muted-foreground">Set Content type to "application/json"</p>
                </div>
                <div className="flex gap-3">
                  <span className="w-6 h-6 rounded-full bg-primary/20 text-primary flex items-center justify-center text-xs font-bold shrink-0">5</span>
                  <p className="text-muted-foreground">Select "Just the push event" or customize as needed</p>
                </div>
                <div className="flex gap-3">
                  <span className="w-6 h-6 rounded-full bg-primary/20 text-primary flex items-center justify-center text-xs font-bold shrink-0">6</span>
                  <p className="text-muted-foreground">Click "Add webhook" to save</p>
                </div>
              </div>
            </div>

            {/* Test Webhook */}
            <div className="glass p-4 space-y-4">
              <h4 className="font-medium text-foreground">Test Webhook</h4>
              <p className="text-sm text-muted-foreground">
                Send a test ping to verify the webhook is working correctly.
              </p>
              <Button
                onClick={handleTestWebhook}
                disabled={isTestingWebhook}
                className="w-full bg-primary text-primary-foreground gap-2"
              >
                {isTestingWebhook ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    Testing...
                  </>
                ) : (
                  <>
                    <Play className="w-4 h-4" />
                    Send Test Ping
                  </>
                )}
              </Button>
              
              {webhookTestResult && (
                <div className={`p-3 rounded-lg flex items-center gap-2 ${
                  webhookTestResult.success 
                    ? 'bg-success/10 text-success' 
                    : 'bg-destructive/10 text-destructive'
                }`}>
                  {webhookTestResult.success ? (
                    <Check className="w-5 h-5" />
                  ) : (
                    <AlertCircle className="w-5 h-5" />
                  )}
                  <span className="text-sm">{webhookTestResult.message}</span>
                </div>
              )}
            </div>

            {/* Documentation Link */}
            <div className="text-center">
              <a
                href="https://docs.github.com/en/webhooks"
                target="_blank"
                rel="noopener noreferrer"
                className="text-sm text-primary hover:underline inline-flex items-center gap-1"
              >
                GitHub Webhooks Documentation
                <ExternalLink className="w-3 h-3" />
              </a>
            </div>
          </div>
        );

      case 'updates':
        return (
          <div className="space-y-6">
            <h3 className="text-lg font-medium text-foreground mb-4">System Updates</h3>
            
            {/* GitHub Repository Settings */}
            <div className="glass p-4 space-y-4">
              <div className="flex items-center gap-2 mb-2">
                <Github className="w-5 h-5 text-primary" />
                <h4 className="font-medium text-foreground">GitHub Repository</h4>
              </div>
              <div className="flex gap-2">
                <Input
                  value={repoUrl}
                  onChange={(e) => setRepoUrl(e.target.value)}
                  placeholder="https://github.com/owner/repo.git"
                  className="flex-1 bg-secondary/50"
                />
                <Button 
                  onClick={handleSaveRepoUrl}
                  disabled={isSavingRepo}
                  size="icon"
                  className="bg-primary text-primary-foreground"
                >
                  {isSavingRepo ? (
                    <Loader2 className="w-4 h-4 animate-spin" />
                  ) : (
                    <Save className="w-4 h-4" />
                  )}
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">
                Enter the GitHub repository URL to check for code updates
              </p>
            </div>

            {/* Auto Updates Toggle */}
            <div className="glass p-4 space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-foreground">Automatic Updates</p>
                  <p className="text-sm text-muted-foreground">
                    Automatically check for updates from GitHub
                  </p>
                </div>
                <Switch
                  checked={settings.autoUpdates}
                  onCheckedChange={(checked) =>
                    setSettings({ ...settings, autoUpdates: checked })
                  }
                />
              </div>
            </div>

            {/* Check for Updates */}
            <div className="glass p-4 space-y-4">
              <div className="flex items-center justify-between mb-2">
                <span className="text-foreground font-medium">Current Version</span>
                <span className="text-primary font-mono">
                  {updateInfo?.currentVersion || 'v1.0.0'}
                </span>
              </div>
              {lastChecked && (
                <p className="text-xs text-muted-foreground mb-4">
                  Last checked: {formatDate(lastChecked)}
                </p>
              )}
              <Button 
                onClick={handleCheckForUpdates}
                disabled={isCheckingUpdates}
                className="w-full bg-primary text-primary-foreground gap-2"
              >
                {isCheckingUpdates ? (
                  <>
                    <Loader2 className="w-4 h-4 animate-spin" />
                    Checking...
                  </>
                ) : (
                  <>
                    <Download className="w-4 h-4" />
                    Check for Updates
                  </>
                )}
              </Button>
            </div>

            {/* Update Results */}
            {updateInfo && (
              <div className="glass p-4 space-y-4">
                {updateInfo.error ? (
                  <div className="flex items-center gap-2 text-destructive">
                    <AlertCircle className="w-5 h-5" />
                    <span>{updateInfo.error}</span>
                  </div>
                ) : updateInfo.hasUpdates ? (
                  <>
                    <div className="flex items-center gap-2 text-warning">
                      <RefreshCw className="w-5 h-5" />
                      <span className="font-medium">
                        {updateInfo.commits.length} Update(s) Available
                      </span>
                    </div>
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-muted-foreground">Latest version:</span>
                      <span className="text-primary font-mono">{updateInfo.latestVersion}</span>
                    </div>
                    
                    <ScrollArea className="h-48 rounded-lg border border-border bg-secondary/30">
                      <div className="p-3 space-y-3">
                        {updateInfo.commits.map((commit) => (
                          <div 
                            key={commit.sha} 
                            className="p-3 rounded-lg bg-background/50 border border-border space-y-2"
                          >
                            <div className="flex items-start justify-between gap-2">
                              <p className="text-sm text-foreground font-medium line-clamp-2">
                                {commit.message}
                              </p>
                              <a
                                href={commit.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-primary hover:text-primary/80 shrink-0"
                              >
                                <ExternalLink className="w-4 h-4" />
                              </a>
                            </div>
                            <div className="flex items-center gap-3 text-xs text-muted-foreground">
                              <span className="font-mono text-primary">{commit.sha}</span>
                              <span>by {commit.author}</span>
                              <span>{formatDate(commit.date)}</span>
                            </div>
                          </div>
                        ))}
                      </div>
                    </ScrollArea>

                    <div className="p-3 rounded-lg bg-warning/10 border border-warning/30">
                      <p className="text-sm text-foreground mb-3">
                        <strong>To apply updates:</strong> Pull the latest changes from GitHub to your hosting environment (cPanel, XAMPP, etc.)
                      </p>
                      <Button 
                        onClick={handleMarkAsUpdated}
                        variant="outline"
                        className="w-full gap-2 border-warning text-warning hover:bg-warning/10"
                      >
                        <Check className="w-4 h-4" />
                        I've Updated - Mark as Current
                      </Button>
                    </div>
                  </>
                ) : (
                  <div className="flex items-center gap-2 text-success">
                    <Check className="w-5 h-5" />
                    <span className="font-medium">System is up to date!</span>
                  </div>
                )}
              </div>
            )}

            <p className="text-xs text-muted-foreground text-center">
              Updates are fetched from{' '}
              <a 
                href={repoUrl.replace('.git', '')} 
                target="_blank"
                rel="noopener noreferrer"
                className="text-primary hover:underline inline-flex items-center gap-1"
              >
                GitHub <ExternalLink className="w-3 h-3" />
              </a>
            </p>
          </div>
        );

      default:
        return (
          <div className="flex items-center justify-center h-full text-muted-foreground">
            <Monitor className="w-8 h-8 mr-2" />
            Select a category
          </div>
        );
    }
  };

  return (
    <div className="flex h-full">
      {/* Sidebar */}
      <div className="w-56 border-r border-border p-2 bg-secondary/20">
        {settingsCategories.map((category) => {
          const Icon = category.icon;
          return (
            <button
              key={category.id}
              onClick={() => setSelectedCategory(category.id)}
              className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition-colors ${
                selectedCategory === category.id
                  ? 'bg-primary/20 text-primary'
                  : 'text-foreground hover:bg-secondary/50'
              }`}
            >
              <Icon className="w-4 h-4" />
              {category.name}
            </button>
          );
        })}
      </div>

      {/* Content */}
      <div className="flex-1 p-6 overflow-auto">
        {renderContent()}
      </div>
    </div>
  );
};

export default Settings;
