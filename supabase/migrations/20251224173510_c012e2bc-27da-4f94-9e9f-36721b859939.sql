-- Create webhook_notifications table
CREATE TABLE public.webhook_notifications (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  event_type TEXT NOT NULL,
  repository TEXT NOT NULL,
  commit_sha TEXT,
  commit_message TEXT,
  commit_author TEXT,
  commit_url TEXT,
  payload JSONB,
  is_read BOOLEAN NOT NULL DEFAULT false,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

-- Enable RLS
ALTER TABLE public.webhook_notifications ENABLE ROW LEVEL SECURITY;

-- Allow authenticated users to read notifications
CREATE POLICY "Notifications are readable by authenticated users"
ON public.webhook_notifications
FOR SELECT
TO authenticated
USING (true);

-- Allow authenticated users to update notifications (mark as read)
CREATE POLICY "Notifications are updatable by authenticated users"
ON public.webhook_notifications
FOR UPDATE
TO authenticated
USING (true);

-- Allow service role to insert (for webhook)
CREATE POLICY "Service role can insert notifications"
ON public.webhook_notifications
FOR INSERT
TO service_role
WITH CHECK (true);

-- Enable realtime for notifications
ALTER PUBLICATION supabase_realtime ADD TABLE public.webhook_notifications;