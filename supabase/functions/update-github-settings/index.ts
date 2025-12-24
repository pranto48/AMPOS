import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
};

Deno.serve(async (req) => {
  // Handle CORS preflight requests
  if (req.method === 'OPTIONS') {
    return new Response(null, { headers: corsHeaders });
  }

  try {
    const supabaseUrl = Deno.env.get('SUPABASE_URL')!;
    const supabaseKey = Deno.env.get('SUPABASE_SERVICE_ROLE_KEY')!;
    const supabase = createClient(supabaseUrl, supabaseKey);

    const { repoUrl, markAsUpdated } = await req.json();

    if (markAsUpdated) {
      // Get latest version info
      const { data: versionSettings } = await supabase
        .from('system_settings')
        .select('value')
        .eq('key', 'version')
        .single();

      if (versionSettings && versionSettings.value) {
        const versionValue = versionSettings.value as { 
          current?: string; 
          sha?: string;
          latestSha?: string;
          latest?: string;
        };

        // Update current to latest
        await supabase
          .from('system_settings')
          .upsert({
            key: 'version',
            value: {
              ...versionValue,
              current: versionValue.latest || versionValue.current,
              sha: versionValue.latestSha || versionValue.sha,
            },
          }, { onConflict: 'key' });

        // Log the update
        await supabase
          .from('update_logs')
          .insert({
            version: versionValue.latest || 'unknown',
            status: 'applied',
            message: 'Update marked as applied',
          });

        return new Response(JSON.stringify({ success: true, message: 'Marked as updated' }), {
          headers: { ...corsHeaders, 'Content-Type': 'application/json' },
        });
      }
    }

    if (repoUrl) {
      // Validate GitHub URL
      const repoMatch = repoUrl.match(/github\.com\/([^\/]+)\/([^\/\.]+)/);
      if (!repoMatch) {
        throw new Error('Invalid GitHub repository URL. Please use format: https://github.com/owner/repo.git');
      }

      // Update repository URL
      await supabase
        .from('system_settings')
        .upsert({
          key: 'github_repo',
          value: { url: repoUrl },
        }, { onConflict: 'key' });

      console.log('GitHub repository URL updated:', repoUrl);

      return new Response(JSON.stringify({ success: true, repoUrl }), {
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      });
    }

    throw new Error('No action specified');

  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    console.error('Error updating settings:', error);
    return new Response(
      JSON.stringify({ error: errorMessage }),
      {
        status: 400,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      }
    );
  }
});
