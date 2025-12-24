import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
};

interface GitHubCommit {
  sha: string;
  commit: {
    message: string;
    author: {
      name: string;
      date: string;
    };
  };
  html_url: string;
}

interface UpdateInfo {
  hasUpdates: boolean;
  currentVersion: string;
  latestVersion: string;
  commits: Array<{
    sha: string;
    message: string;
    author: string;
    date: string;
    url: string;
  }>;
  lastChecked: string;
}

Deno.serve(async (req) => {
  // Handle CORS preflight requests
  if (req.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const supabaseUrl = Deno.env.get('SUPABASE_URL')!;
    const supabaseKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!;
    const supabase = createClient(supabaseUrl, supabaseKey);

    // Get GitHub repository URL from settings
    const { data: repoSettings, error: settingsError } = await supabase
      .from('system_settings')
      .select('value')
      .eq('key', 'github_repo')
      .single();

    let repoUrl = 'https://github.com/pranto48/ampos.git';
    
    if (repoSettings && repoSettings.value) {
      const settingsValue = repoSettings.value as { url?: string };
      repoUrl = settingsValue.url || repoUrl;
    }

    // Parse GitHub repo URL to get owner and repo name
    const repoMatch = repoUrl.match(/github\.com\/([^\/]+)\/([^\/\.]+)/);
    if (!repoMatch) {
      throw new Error('Invalid GitHub repository URL');
    }

    const [, owner, repo] = repoMatch;
    console.log(`Checking updates for: ${owner}/${repo}`);

    // Get current version from settings
    const { data: versionSettings } = await supabase
      .from('system_settings')
      .select('value')
      .eq('key', 'version')
      .single();

    let currentSha = '';
    let currentVersion = '1.0.0';
    
    if (versionSettings && versionSettings.value) {
      const versionValue = versionSettings.value as { current?: string; sha?: string };
      currentVersion = versionValue.current || '1.0.0';
      currentSha = versionValue.sha || '';
    }

    // Fetch latest commits from GitHub API
    const githubApiUrl = `https://api.github.com/repos/${owner}/${repo}/commits?per_page=10`;
    console.log(`Fetching commits from: ${githubApiUrl}`);

    const githubResponse = await fetch(githubApiUrl, {
      headers: {
        'Accept': 'application/vnd.github.v3+json',
        'User-Agent': 'AMPOS-Update-Checker',
      },
    });

    if (!githubResponse.ok) {
      const errorText = await githubResponse.text();
      console.error('GitHub API error:', errorText);
      throw new Error(`GitHub API error: ${githubResponse.status} - ${errorText}`);
    }

    const commits: GitHubCommit[] = await githubResponse.json();
    console.log(`Found ${commits.length} commits`);

    // Find new commits since last check
    let newCommits: GitHubCommit[] = [];
    if (currentSha) {
      const currentIndex = commits.findIndex(c => c.sha === currentSha);
      if (currentIndex > 0) {
        newCommits = commits.slice(0, currentIndex);
      } else if (currentIndex === -1) {
        // Current SHA not found in recent commits, all are new
        newCommits = commits;
      }
    } else {
      // No current SHA, consider latest commit as the update
      newCommits = commits.slice(0, 1);
    }

    const hasUpdates = newCommits.length > 0;
    const latestCommit = commits[0];
    const latestVersion = latestCommit ? latestCommit.sha.substring(0, 7) : currentVersion;

    const updateInfo: UpdateInfo = {
      hasUpdates,
      currentVersion: currentSha ? currentSha.substring(0, 7) : currentVersion,
      latestVersion,
      commits: newCommits.map(c => ({
        sha: c.sha.substring(0, 7),
        message: c.commit.message.split('\n')[0], // First line only
        author: c.commit.author.name,
        date: c.commit.author.date,
        url: c.html_url,
      })),
      lastChecked: new Date().toISOString(),
    };

    // Update last checked timestamp in database
    await supabase
      .from('system_settings')
      .upsert({
        key: 'version',
        value: {
          current: currentVersion,
          sha: currentSha,
          latest: latestVersion,
          latestSha: latestCommit?.sha || '',
          lastChecked: updateInfo.lastChecked,
        },
      }, { onConflict: 'key' });

    // Log the update check
    await supabase
      .from('update_logs')
      .insert({
        version: latestVersion,
        status: hasUpdates ? 'available' : 'current',
        message: hasUpdates 
          ? `${newCommits.length} update(s) available` 
          : 'System is up to date',
        details: { commits: updateInfo.commits },
      });

    console.log('Update check completed:', updateInfo);

    return new Response(JSON.stringify(updateInfo), {
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    });

  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    console.error('Error checking for updates:', error);
    return new Response(
      JSON.stringify({ 
        error: errorMessage,
        hasUpdates: false,
        commits: [],
      }),
      {
        status: 500,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      }
    );
  }
});
