import React, { useEffect, useState } from 'react';
import { Bell, Github, ExternalLink, Check, X } from 'lucide-react';
import { supabase } from '@/integrations/supabase/client';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Button } from '@/components/ui/button';
import { toast } from 'sonner';

interface Notification {
  id: string;
  event_type: string;
  repository: string;
  commit_sha: string | null;
  commit_message: string | null;
  commit_author: string | null;
  commit_url: string | null;
  is_read: boolean;
  created_at: string;
}

interface NotificationCenterProps {
  isOpen: boolean;
  onClose: () => void;
}

const NotificationCenter: React.FC<NotificationCenterProps> = ({ isOpen, onClose }) => {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);

  useEffect(() => {
    fetchNotifications();

    // Subscribe to realtime updates
    const channel = supabase
      .channel('webhook-notifications')
      .on(
        'postgres_changes',
        {
          event: 'INSERT',
          schema: 'public',
          table: 'webhook_notifications',
        },
        (payload) => {
          console.log('New notification received:', payload);
          const newNotification = payload.new as Notification;
          setNotifications((prev) => [newNotification, ...prev]);
          setUnreadCount((prev) => prev + 1);
          
          // Show toast for new notification
          toast.info(`New update from ${newNotification.repository}`, {
            description: newNotification.commit_message || 'New GitHub event',
          });
        }
      )
      .subscribe();

    return () => {
      supabase.removeChannel(channel);
    };
  }, []);

  const fetchNotifications = async () => {
    const { data, error } = await supabase
      .from('webhook_notifications')
      .select('*')
      .order('created_at', { ascending: false })
      .limit(20);

    if (error) {
      console.error('Error fetching notifications:', error);
      return;
    }

    setNotifications(data || []);
    setUnreadCount(data?.filter((n) => !n.is_read).length || 0);
  };

  const markAsRead = async (id: string) => {
    const { error } = await supabase
      .from('webhook_notifications')
      .update({ is_read: true })
      .eq('id', id);

    if (!error) {
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, is_read: true } : n))
      );
      setUnreadCount((prev) => Math.max(0, prev - 1));
    }
  };

  const markAllAsRead = async () => {
    const unreadIds = notifications.filter((n) => !n.is_read).map((n) => n.id);
    
    if (unreadIds.length === 0) return;

    const { error } = await supabase
      .from('webhook_notifications')
      .update({ is_read: true })
      .in('id', unreadIds);

    if (!error) {
      setNotifications((prev) => prev.map((n) => ({ ...n, is_read: true })));
      setUnreadCount(0);
    }
  };

  const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  };

  const getEventIcon = (eventType: string) => {
    switch (eventType) {
      case 'push':
        return <Github className="w-4 h-4 text-primary" />;
      case 'ping':
        return <Check className="w-4 h-4 text-success" />;
      default:
        return <Bell className="w-4 h-4 text-muted-foreground" />;
    }
  };

  if (!isOpen) return null;

  return (
    <div className="absolute bottom-12 right-0 w-80 bg-card border border-border rounded-lg shadow-xl z-50 overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between p-3 border-b border-border bg-secondary/30">
        <div className="flex items-center gap-2">
          <Bell className="w-4 h-4 text-primary" />
          <span className="font-medium text-foreground">Notifications</span>
          {unreadCount > 0 && (
            <span className="px-1.5 py-0.5 text-xs bg-primary text-primary-foreground rounded-full">
              {unreadCount}
            </span>
          )}
        </div>
        <div className="flex items-center gap-1">
          {unreadCount > 0 && (
            <Button
              variant="ghost"
              size="sm"
              onClick={markAllAsRead}
              className="text-xs h-7 px-2"
            >
              Mark all read
            </Button>
          )}
          <Button
            variant="ghost"
            size="icon"
            onClick={onClose}
            className="h-7 w-7"
          >
            <X className="w-4 h-4" />
          </Button>
        </div>
      </div>

      {/* Notifications List */}
      <ScrollArea className="h-72">
        {notifications.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-full text-muted-foreground p-4">
            <Bell className="w-8 h-8 mb-2 opacity-50" />
            <p className="text-sm">No notifications yet</p>
            <p className="text-xs mt-1">Set up GitHub webhook to receive updates</p>
          </div>
        ) : (
          <div className="divide-y divide-border">
            {notifications.map((notification) => (
              <div
                key={notification.id}
                onClick={() => !notification.is_read && markAsRead(notification.id)}
                className={`p-3 hover:bg-secondary/30 cursor-pointer transition-colors ${
                  !notification.is_read ? 'bg-primary/5' : ''
                }`}
              >
                <div className="flex items-start gap-3">
                  <div className="mt-0.5">{getEventIcon(notification.event_type)}</div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <span className="text-xs text-muted-foreground">
                        {notification.repository}
                      </span>
                      {!notification.is_read && (
                        <span className="w-2 h-2 bg-primary rounded-full" />
                      )}
                    </div>
                    <p className="text-sm text-foreground mt-0.5 line-clamp-2">
                      {notification.commit_message || 'GitHub event'}
                    </p>
                    <div className="flex items-center gap-2 mt-1">
                      {notification.commit_sha && (
                        <span className="text-xs font-mono text-primary">
                          {notification.commit_sha}
                        </span>
                      )}
                      {notification.commit_author && (
                        <span className="text-xs text-muted-foreground">
                          by {notification.commit_author}
                        </span>
                      )}
                    </div>
                    <div className="flex items-center justify-between mt-1">
                      <span className="text-xs text-muted-foreground">
                        {formatDate(notification.created_at)}
                      </span>
                      {notification.commit_url && (
                        <a
                          href={notification.commit_url}
                          target="_blank"
                          rel="noopener noreferrer"
                          onClick={(e) => e.stopPropagation()}
                          className="text-primary hover:text-primary/80"
                        >
                          <ExternalLink className="w-3 h-3" />
                        </a>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </ScrollArea>

      {/* Footer with webhook URL */}
      <div className="p-2 border-t border-border bg-secondary/20">
        <p className="text-xs text-muted-foreground text-center">
          Webhook: hjsugraqchavtzmomfki.supabase.co/functions/v1/github-webhook
        </p>
      </div>
    </div>
  );
};

export default NotificationCenter;
