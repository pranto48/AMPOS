import { createClient } from 'https://esm.sh/@supabase/supabase-js@2';

const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type, x-hub-signature-256, x-github-event',
};

interface GitHubPushPayload {
  ref: string;
  repository: {
    full_name: string;
    html_url: string;
  };
  head_commit: {
    id: string;
    message: string;
    author: {
      name: string;
      email: string;
    };
    url: string;
    timestamp: string;
  } | null;
  commits: Array<{
    id: string;
    message: string;
    author: {
      name: string;
    };
    url: string;
  }>;
  pusher: {
    name: string;
  };
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

    const eventType = req.headers.get('x-github-event') || 'unknown';
    console.log(`Received GitHub webhook event: ${eventType}`);

    const payload = await req.json();
    console.log('Payload received:', JSON.stringify(payload).substring(0, 500));

    // Handle push events
    if (eventType === 'push') {
      const pushPayload = payload as GitHubPushPayload;
      const headCommit = pushPayload.head_commit;
      
      if (headCommit) {
        // Insert notification for the head commit
        const { error } = await supabase
          .from('webhook_notifications')
          .insert({
            event_type: 'push',
            repository: pushPayload.repository.full_name,
            commit_sha: headCommit.id.substring(0, 7),
            commit_message: headCommit.message.split('\n')[0],
            commit_author: headCommit.author.name,
            commit_url: headCommit.url,
            payload: {
              ref: pushPayload.ref,
              pusher: pushPayload.pusher.name,
              total_commits: pushPayload.commits.length,
              repository_url: pushPayload.repository.html_url,
            },
          });

        if (error) {
          console.error('Error inserting notification:', error);
          throw error;
        }

        console.log(`Push notification created for ${pushPayload.repository.full_name}`);
      }

      // Also insert for additional commits if there are multiple
      if (pushPayload.commits.length > 1) {
        const additionalCommits = pushPayload.commits.slice(0, -1); // Exclude head commit
        
        for (const commit of additionalCommits.slice(0, 4)) { // Limit to 4 additional
          await supabase
            .from('webhook_notifications')
            .insert({
              event_type: 'push',
              repository: pushPayload.repository.full_name,
              commit_sha: commit.id.substring(0, 7),
              commit_message: commit.message.split('\n')[0],
              commit_author: commit.author.name,
              commit_url: commit.url,
              payload: {
                ref: pushPayload.ref,
                is_additional: true,
              },
            });
        }
      }
    } else if (eventType === 'ping') {
      // Handle ping event (sent when webhook is first set up)
      await supabase
        .from('webhook_notifications')
        .insert({
          event_type: 'ping',
          repository: payload.repository?.full_name || 'unknown',
          commit_message: 'Webhook connected successfully!',
          commit_author: 'GitHub',
          payload: {
            zen: payload.zen,
            hook_id: payload.hook_id,
          },
        });

      console.log('Ping notification created');
    } else {
      // Handle other events generically
      await supabase
        .from('webhook_notifications')
        .insert({
          event_type: eventType,
          repository: payload.repository?.full_name || 'unknown',
          commit_message: `GitHub ${eventType} event received`,
          commit_author: payload.sender?.login || 'unknown',
          payload: payload,
        });

      console.log(`Generic notification created for event: ${eventType}`);
    }

    return new Response(JSON.stringify({ success: true, event: eventType }), {
      headers: { ...corsHeaders, 'Content-Type': 'application/json' },
    });

  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    console.error('Webhook error:', error);
    return new Response(
      JSON.stringify({ error: errorMessage }),
      {
        status: 500,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      }
    );
  }
});
