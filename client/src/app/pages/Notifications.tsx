import { useState, useEffect } from 'react';
import { Button } from '../components/ui/button.js';
import { Card } from '../components/ui/card.js';
import { Badge } from '../components/ui/badge.js';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../components/ui/tabs.js';
import {
  GraduationCap,
  Award,
  FileText,
  AlertCircle,
  MessageSquare,
  CheckCircle2,
  Clock,
  AlertTriangle,
  Bell,
  BellOff,
  Trash2,
  Eye,
  Search,
  Loader2,
  RefreshCw,
} from 'lucide-react';
import { Input } from '../components/ui/input.js';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '../components/ui/select.js';
import { useNotifications } from '../hooks/useNotifications.js';
import type {
  NotificationType,
  NotificationPriority,
  Notification,
} from '../services/notification.service.js';

export default function NotificationsPage() {
  const [selectedTab, setSelectedTab] = useState<NotificationType>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [filterPriority, setFilterPriority] = useState<string>('all');
  const [filterRead, setFilterRead] = useState<string>('all');

  // Use the notifications hook
  const {
    notifications,
    unreadCounts,
    isLoading,
    isRefreshing,
    error,
    markRead,
    markUnread,
    markAllRead,
    remove,
    refresh,
    setFilters,
  } = useNotifications({}, true, 60000); // Auto-refresh every minute

  // Update filters when search/filter criteria change
  useEffect(() => {
    const filters: {
      type?: NotificationType;
      read?: boolean;
      priority?: NotificationPriority;
      search?: string;
    } = {};

    if (selectedTab !== 'all') {
      filters.type = selectedTab as NotificationType;
    }

    if (filterRead === 'unread') {
      filters.read = false;
    } else if (filterRead === 'read') {
      filters.read = true;
    }

    if (filterPriority !== 'all') {
      filters.priority = filterPriority as NotificationPriority;
    }

    if (searchQuery) {
      filters.search = searchQuery;
    }

    setFilters(filters);
  }, [selectedTab, filterRead, filterPriority, searchQuery, setFilters]);

  // Filter notifications client-side for search (API handles the rest)
  const filteredNotifications = notifications.filter((notification) => {
    if (searchQuery) {
      const query = searchQuery.toLowerCase();
      if (
        !notification.title.toLowerCase().includes(query) &&
        !(notification.message || '').toLowerCase().includes(query)
      ) {
        return false;
      }
    }
    return true;
  });

  const handleMarkAsRead = async (id: string) => {
    await markRead(id);
  };

  const handleMarkAsUnread = async (id: string) => {
    await markUnread(id);
  };

  const handleMarkAllAsRead = async () => {
    await markAllRead(selectedTab !== 'all' ? selectedTab : undefined);
  };

  const handleDelete = async (id: string) => {
    await remove(id);
  };

  const getNotificationIcon = (type: string) => {
    switch (type) {
      case 'training':
        return <GraduationCap className="h-5 w-5" />;
      case 'licensure':
        return <Award className="h-5 w-5" />;
      case 'registrar':
        return <FileText className="h-5 w-5" />;
      case 'case':
        return <AlertCircle className="h-5 w-5" />;
      case 'message':
        return <MessageSquare className="h-5 w-5" />;
      default:
        return <Bell className="h-5 w-5" />;
    }
  };

  const getNotificationColor = (type: string) => {
    switch (type) {
      case 'training':
        return 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-400';
      case 'licensure':
        return 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400';
      case 'registrar':
        return 'bg-purple-100 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400';
      case 'case':
        return 'bg-orange-100 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400';
      case 'message':
        return 'bg-cyan-100 dark:bg-cyan-900/30 text-cyan-600 dark:text-cyan-400';
      default:
        return 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400';
    }
  };

  const getPriorityBadge = (priority: string) => {
    switch (priority) {
      case 'high':
        return <Badge variant="destructive">High Priority</Badge>;
      case 'medium':
        return <Badge variant="default">Medium</Badge>;
      case 'low':
        return <Badge variant="outline">Low</Badge>;
      default:
        return <Badge variant="outline">Normal</Badge>;
    }
  };

  // Extract metadata from notification data
  const getMetadata = (notification: Notification) => {
    const data = notification.data || notification.metadata || {};
    return {
      user: data.user as string | undefined,
      dueDate: data.dueDate as string | undefined,
      expiryDate: data.expiryDate as string | undefined,
      patientName: data.patientName as string | undefined,
      trainingModule: data.trainingModule as string | undefined,
      certificationName: data.certificationName as string | undefined,
    };
  };

  return (
    <div className="p-4 sm:p-6 space-y-4 sm:space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold">Notifications</h1>
          {error && (
            <p className="text-sm text-red-600 dark:text-red-400 mt-1">
              {error}
            </p>
          )}
        </div>
        <div className="flex items-center gap-2">
          <Button
            onClick={() => refresh()}
            variant="outline"
            size="icon"
            disabled={isRefreshing}
          >
            <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
          </Button>
          <Button onClick={handleMarkAllAsRead} variant="outline" disabled={isLoading}>
            <CheckCircle2 className="h-4 w-4 mr-2" />
            Mark All as Read
          </Button>
        </div>
      </div>

      {/* Filters and Search */}
      <Card className="p-4">
        <div className="flex flex-col sm:flex-row gap-4">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-slate-400" />
            <Input
              placeholder="Search notifications..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-9"
            />
          </div>
          <Select value={filterPriority} onValueChange={setFilterPriority}>
            <SelectTrigger className="w-full sm:w-48">
              <SelectValue placeholder="All Priorities" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Priorities</SelectItem>
              <SelectItem value="high">High Priority</SelectItem>
              <SelectItem value="medium">Medium Priority</SelectItem>
              <SelectItem value="low">Low Priority</SelectItem>
              <SelectItem value="normal">Normal Priority</SelectItem>
            </SelectContent>
          </Select>
          <Select value={filterRead} onValueChange={setFilterRead}>
            <SelectTrigger className="w-full sm:w-48">
              <SelectValue placeholder="All Status" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All Status</SelectItem>
              <SelectItem value="unread">Unread Only</SelectItem>
              <SelectItem value="read">Read Only</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </Card>

      {/* Tabbed Notifications */}
      <Tabs value={selectedTab} onValueChange={(value) => setSelectedTab(value as NotificationType)} className="w-full">
        <div className="overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
          <TabsList className="inline-flex w-full sm:grid sm:grid-cols-6 min-w-max sm:min-w-0">
            <TabsTrigger value="all" className="whitespace-nowrap">
              <Bell className="h-4 w-4 mr-2" />
              All
              {unreadCounts.all > 0 && (
                <Badge variant="destructive" className="ml-2 h-5 w-5 rounded-full p-0 flex items-center justify-center text-xs">
                  {unreadCounts.all}
                </Badge>
              )}
            </TabsTrigger>
            <TabsTrigger value="training" className="whitespace-nowrap">
              <GraduationCap className="h-4 w-4 mr-2" />
              Training
              {unreadCounts.training > 0 && (
                <Badge variant="destructive" className="ml-2 h-5 w-5 rounded-full p-0 flex items-center justify-center text-xs">
                  {unreadCounts.training}
                </Badge>
              )}
            </TabsTrigger>
            <TabsTrigger value="licensure" className="whitespace-nowrap">
              <Award className="h-4 w-4 mr-2" />
              Licensure
              {unreadCounts.licensure > 0 && (
                <Badge variant="destructive" className="ml-2 h-5 w-5 rounded-full p-0 flex items-center justify-center text-xs">
                  {unreadCounts.licensure}
                </Badge>
              )}
            </TabsTrigger>
            <TabsTrigger value="registrar" className="whitespace-nowrap">
              <FileText className="h-4 w-4 mr-2" />
              Registrar
              {unreadCounts.registrar > 0 && (
                <Badge variant="destructive" className="ml-2 h-5 w-5 rounded-full p-0 flex items-center justify-center text-xs">
                  {unreadCounts.registrar}
                </Badge>
              )}
            </TabsTrigger>
            <TabsTrigger value="case" className="whitespace-nowrap">
              <AlertCircle className="h-4 w-4 mr-2" />
              Cases
              {unreadCounts.case > 0 && (
                <Badge variant="destructive" className="ml-2 h-5 w-5 rounded-full p-0 flex items-center justify-center text-xs">
                  {unreadCounts.case}
                </Badge>
              )}
            </TabsTrigger>
            <TabsTrigger value="message" className="whitespace-nowrap">
              <MessageSquare className="h-4 w-4 mr-2" />
              Messages
              {unreadCounts.message > 0 && (
                <Badge variant="destructive" className="ml-2 h-5 w-5 rounded-full p-0 flex items-center justify-center text-xs">
                  {unreadCounts.message}
                </Badge>
              )}
            </TabsTrigger>
          </TabsList>
        </div>

        <TabsContent value={selectedTab} className="space-y-3 mt-6">
          {isLoading && notifications.length === 0 ? (
            <Card className="p-12 text-center">
              <Loader2 className="h-12 w-12 mx-auto text-slate-300 dark:text-slate-700 mb-4 animate-spin" />
              <h3 className="text-lg font-semibold mb-2">Loading notifications...</h3>
              <p className="text-sm text-slate-600 dark:text-slate-400">
                Please wait while we fetch your notifications.
              </p>
            </Card>
          ) : filteredNotifications.length === 0 ? (
            <Card className="p-12 text-center">
              <BellOff className="h-12 w-12 mx-auto text-slate-300 dark:text-slate-700 mb-4" />
              <h3 className="text-lg font-semibold mb-2">No notifications</h3>
              <p className="text-sm text-slate-600 dark:text-slate-400">
                You're all caught up! No notifications to display.
              </p>
            </Card>
          ) : (
            filteredNotifications.map((notification) => {
              const metadata = getMetadata(notification);
              return (
                <Card
                  key={notification.id}
                  className={`p-4 transition-colors ${
                    !notification.read ? 'bg-blue-50/50 dark:bg-blue-950/20 border-blue-200 dark:border-blue-800' : ''
                  }`}
                >
                  <div className="flex items-start gap-4">
                    {/* Icon */}
                    <div className={`h-10 w-10 rounded-lg flex items-center justify-center flex-shrink-0 ${getNotificationColor(notification.type)}`}>
                      {getNotificationIcon(notification.type)}
                    </div>

                    {/* Content */}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-4 mb-2">
                        <div className="flex-1">
                          <div className="flex items-center gap-2 mb-1">
                            <h3 className="font-semibold">{notification.title}</h3>
                            {!notification.read && (
                              <div className="h-2 w-2 rounded-full bg-blue-600 dark:bg-blue-400 flex-shrink-0" />
                            )}
                          </div>
                          <p className="text-sm text-slate-600 dark:text-slate-400">{notification.message}</p>
                        </div>
                        <div className="flex items-center gap-2 flex-shrink-0">
                          {getPriorityBadge(notification.priority)}
                          {notification.action_required && (
                            <Badge variant="outline" className="bg-amber-50 dark:bg-amber-900/20 border-amber-300 dark:border-amber-700">
                              <AlertTriangle className="h-3 w-3 mr-1" />
                              Action Required
                            </Badge>
                          )}
                        </div>
                      </div>

                      {/* Metadata */}
                      {(metadata.user || metadata.dueDate || metadata.expiryDate || metadata.patientName) && (
                        <div className="flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400 mb-3">
                          {metadata.user && (
                            <span className="flex items-center gap-1">
                              <Eye className="h-3 w-3" />
                              {metadata.user}
                            </span>
                          )}
                          {metadata.dueDate && (
                            <span className="flex items-center gap-1">
                              <Clock className="h-3 w-3" />
                              Due: {metadata.dueDate}
                            </span>
                          )}
                          {metadata.expiryDate && (
                            <span className="flex items-center gap-1">
                              <Clock className="h-3 w-3" />
                              Expires: {metadata.expiryDate}
                            </span>
                          )}
                          {metadata.patientName && (
                            <span className="flex items-center gap-1">
                              <AlertCircle className="h-3 w-3" />
                              Patient: {metadata.patientName}
                            </span>
                          )}
                        </div>
                      )}

                      {/* Actions */}
                      <div className="flex items-center gap-2">
                        <span className="text-xs text-slate-500 dark:text-slate-400">{notification.timestamp}</span>
                        <div className="flex-1" />
                        {notification.read ? (
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => handleMarkAsUnread(notification.id)}
                          >
                            <BellOff className="h-4 w-4 mr-2" />
                            Mark Unread
                          </Button>
                        ) : (
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => handleMarkAsRead(notification.id)}
                          >
                            <CheckCircle2 className="h-4 w-4 mr-2" />
                            Mark Read
                          </Button>
                        )}
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => handleDelete(notification.id)}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  </div>
                </Card>
              );
            })
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
