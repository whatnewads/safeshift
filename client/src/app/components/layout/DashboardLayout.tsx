import {
  Bell,
  Search,
  Settings,
  User,
  LogOut,
  WifiOff,
  Wifi,
  RefreshCw,
  Home,
  FileText,
  Users,
  Activity,
  Shield,
  Lock,
  Menu,
  X,
  Bug,
  Video,
  Lightbulb,
  ChevronRight,
  CalendarClock,
  CheckCircle2,
  AlertTriangle,
  AlertCircle,
  Circle,
  Moon,
  Sun,
  Plus,
  Loader2,
} from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';
import { Link, useNavigate, useLocation } from 'react-router-dom';
import { Button } from '../ui/button';
import { Input } from '../ui/input';
import { Badge } from '../ui/badge';
import { SimpleDropdown, SimpleDropdownItem, SimpleDropdownLabel, SimpleDropdownSeparator } from '../ui/simple-dropdown';
import { ScrollArea } from '../ui/scroll-area';
import { Avatar, AvatarFallback } from '../ui/avatar';
import { SetShiftModal } from '../shift/SetShiftModal';
import { useAuth } from '../../contexts/AuthContext';
import { useSync } from '../../contexts/SyncContext';
import { useShift } from '../../contexts/ShiftContext';
import { useEncounter } from '../../contexts/EncounterContext';
import { useDarkMode } from '../../contexts/DarkModeContext';
import { EncounterNav } from '../encounter/EncounterNav';
import { useNotifications } from '../../hooks/useNotifications.js';

// Connection status type for Wi-Fi indicator
type ConnectionStatus = 'connected' | 'disconnected' | 'reconnecting';

// Helper function to format role names
const formatRoleName = (role: string): string => {
  const roleMap: Record<string, string> = {
    'provider': 'Clinical Provider',
    'registration': 'Registration',
    'admin': 'Admin',
    'super-admin': 'Super Admin',
  };
  return roleMap[role] || role;
};

interface DashboardLayoutProps {
  children: React.ReactNode;
}

export default function DashboardLayout({ children }: DashboardLayoutProps) {
  const { user, logout, switchRole } = useAuth();
  const { isSyncing, pendingCount, triggerSync } = useSync();
  const { shiftData } = useShift();
  const encounterContext = useEncounter();
  const activeEncounter = encounterContext?.activeEncounter || null;
  const { isDarkMode, toggleDarkMode } = useDarkMode();
  const navigate = useNavigate();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false); // Default to closed on mobile
  const [setShiftModalOpen, setSetShiftModalOpen] = useState(false);
  
  // Wi-Fi connection status with reconnection logic
  const [connectionStatus, setConnectionStatus] = useState<ConnectionStatus>(
    navigator.onLine ? 'connected' : 'disconnected'
  );
  
  // Notifications hook for dynamic badge count
  const { unreadCounts } = useNotifications({}, true, 30000); // Auto-refresh every 30 seconds
  const notificationCount = unreadCounts?.all || 0;

  // Handle online/offline events
  useEffect(() => {
    const handleOnline = () => setConnectionStatus('connected');
    const handleOffline = () => setConnectionStatus('disconnected');
    
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOffline);
    
    return () => {
      window.removeEventListener('online', handleOnline);
      window.removeEventListener('offline', handleOffline);
    };
  }, []);

  // Handle reconnection attempt
  const handleReconnect = useCallback(async () => {
    if (connectionStatus === 'reconnecting') return;
    
    setConnectionStatus('reconnecting');
    
    try {
      // Attempt to ping the backend health endpoint
      const response = await fetch('/api/health', {
        method: 'GET',
        cache: 'no-store'
      });
      
      if (response.ok || navigator.onLine) {
        setConnectionStatus('connected');
        // Also trigger sync retry
        triggerSync();
      } else {
        setConnectionStatus('disconnected');
      }
    } catch {
      // If fetch fails, check navigator.onLine as fallback
      if (navigator.onLine) {
        setConnectionStatus('connected');
      } else {
        setConnectionStatus('disconnected');
      }
    }
  }, [connectionStatus, triggerSync]);

  // Get Wi-Fi status indicator props
  const getWifiStatusProps = useCallback(() => {
    switch (connectionStatus) {
      case 'connected':
        return {
          icon: Wifi,
          className: 'text-green-600',
          title: 'Connected to Wi-Fi. Reports will automatically sync.',
          onClick: undefined,
          cursor: 'cursor-default',
        };
      case 'disconnected':
        return {
          icon: WifiOff,
          className: 'text-red-600',
          title: 'Not connected. Click to attempt reconnect. Reports will be saved to local files.',
          onClick: handleReconnect,
          cursor: 'cursor-pointer',
        };
      case 'reconnecting':
        return {
          icon: Wifi,
          className: 'text-yellow-500 animate-wifi-blink',
          title: 'Attempting to reconnect...',
          onClick: undefined,
          cursor: 'cursor-wait',
        };
    }
  }, [connectionStatus, handleReconnect]);

  if (!user) return null;

  // Check if we're on an encounter page
  const isOnEncounter = location.pathname.startsWith('/encounters/');

  const getNavItems = () => {
    const role = user.currentRole;
    const menuItems = [
      { icon: Home, label: 'Dashboard', href: '/dashboard', roles: ['all'] },
      { icon: Plus, label: 'Start Encounter', href: '/encounters/workspace', roles: ['provider', 'admin'] },
      { icon: Video, label: 'Video Meeting', href: '/video', roles: ['provider', 'admin'] },
      { icon: CalendarClock, label: 'Set Shift', href: '#', roles: ['all'], onClick: () => setSetShiftModalOpen(true) },
      { icon: Users, label: 'Patients', href: '/patients', roles: ['provider', 'admin', 'registration'] },
      { icon: Activity, label: 'Cases', href: '/cases', roles: ['admin', 'registration'] },
      { icon: Shield, label: 'Compliance', href: '/compliance', roles: ['admin', 'registration'] },
      { icon: Lock, label: 'Security', href: '/security', roles: ['admin', 'registration'] },
      { icon: Settings, label: 'Settings', href: '/settings', roles: ['all'] },
    ];

    return menuItems.filter(item => 
      item.roles.includes('all') || item.roles.includes(role)
    );
  };

  const initials = user.name
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase();

  return (
    <div className="flex h-screen bg-slate-50 dark:bg-slate-900">
      {/* Mobile Overlay - Only show when sidebar is open and NOT on encounter page */}
      {sidebarOpen && !isOnEncounter && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      {/* Left Sidebar - Hidden when on EHR/Encounter pages */}
      {!isOnEncounter && (
      <aside
        className={`${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        } ${
          sidebarOpen ? 'w-64' : 'lg:w-20 w-64'
        } fixed lg:static inset-y-0 left-0 z-50 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 flex flex-col transition-all duration-300`}
      >
        {/* Logo */}
        <div className="h-16 flex items-center justify-between px-4 border-b border-slate-200 dark:border-slate-700">
          {(sidebarOpen || window.innerWidth >= 1024) && (
            <div className="flex items-center gap-2">
              <Activity className="h-6 w-6 text-blue-600 dark:text-blue-400" />
              <span className={`font-semibold dark:text-white ${!sidebarOpen && 'lg:hidden'}`}>OccHealth EHR</span>
            </div>
          )}
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setSidebarOpen(!sidebarOpen)}
            className="lg:flex hidden"
          >
            {sidebarOpen ? <X className="h-4 w-4" /> : <Menu className="h-4 w-4" />}
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => setSidebarOpen(false)}
            className="lg:hidden"
          >
            <X className="h-4 w-4" />
          </Button>
        </div>

        {/* Navigation */}
        <ScrollArea className="flex-1 px-3 py-4">
          {isOnEncounter && activeEncounter ? (
            <EncounterNav
              encounterContext={activeEncounter}
              sidebarOpen={sidebarOpen}
            />
          ) : (
            <nav className="space-y-1">
              {getNavItems().map((item) => (
                item.onClick ? (
                  <button
                    key={item.label}
                    onClick={item.onClick}
                    className="flex items-center gap-3 px-3 py-2 text-sm rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white w-full text-left font-normal"
                  >
                    <item.icon className="h-5 w-5 flex-shrink-0" />
                    {sidebarOpen && <span>{item.label}</span>}
                  </button>
                ) : (
                  <Link
                    key={item.label}
                    to={item.href}
                    className="flex items-center gap-3 px-3 py-2 text-sm rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white"
                  >
                    <item.icon className="h-5 w-5" />
                    {sidebarOpen && <span>{item.label}</span>}
                  </Link>
                )
              ))}
            </nav>
          )}
        </ScrollArea>

        {/* Role Switcher */}
        {user.availableRoles.length > 1 && sidebarOpen && (
          <div className="p-3 border-t border-slate-200 dark:border-slate-700">
            <SimpleDropdown
              side="right"
              align="end"
              trigger={
                <Button variant="outline" className="w-full justify-start text-sm">
                  <User className="h-4 w-4 mr-2" />
                  Switch Role
                </Button>
              }
              className="w-56"
            >
              <SimpleDropdownLabel>Available Roles</SimpleDropdownLabel>
              <SimpleDropdownSeparator />
              {user.availableRoles.map((role) => (
                <SimpleDropdownItem
                  key={role}
                  onClick={() => {
                    switchRole(role);
                    navigate('/dashboard');
                  }}
                  className={role === user.currentRole ? 'bg-slate-100' : ''}
                >
                  {formatRoleName(role)}
                  {role === user.currentRole && (
                    <ChevronRight className="ml-auto h-4 w-4" />
                  )}
                </SimpleDropdownItem>
              ))}
            </SimpleDropdown>
          </div>
        )}
      </aside>
      )}

      {/* Main Content */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Top Bar */}
        <header className="h-16 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-4 lg:px-6">
          {/* Mobile menu button + Search */}
          <div className="flex items-center gap-3 flex-1">
            {/* Mobile Menu Button - Hidden on encounter pages where MobileEncounterNav provides navigation */}
            {!isOnEncounter && (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => setSidebarOpen(true)}
                className="lg:hidden"
              >
                <Menu className="h-5 w-5" />
              </Button>
            )}

            {/* Search */}
            <div className="flex-1 max-w-xl">
              <div className="relative hidden sm:block">
                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-slate-400 dark:text-slate-500" />
                <Input
                  placeholder="Search patients, encounters, cases..."
                  className="pl-9"
                />
              </div>
              <Button
                variant="ghost"
                size="sm"
                className="sm:hidden"
              >
                <Search className="h-5 w-5" />
              </Button>
            </div>
          </div>

          {/* Right side */}
          <div className="flex items-center gap-2 sm:gap-4">
            {/* Theme & Colorblind Mode Toggle */}
            <SimpleDropdown
              align="end"
              trigger={
                <Button
                  variant="ghost"
                  size="sm"
                  className="gap-2"
                >
                  {isDarkMode ? (
                    <Sun className="h-5 w-5" />
                  ) : (
                    <Moon className="h-5 w-5" />
                  )}
                </Button>
              }
              className="w-56"
            >
              <SimpleDropdownLabel>Display Mode</SimpleDropdownLabel>
              <SimpleDropdownSeparator />
              
              {/* Light Mode */}
              <SimpleDropdownItem 
                onClick={() => {
                  if (isDarkMode) toggleDarkMode();
                  // TODO: Reset colorblind mode
                }}
                className={!isDarkMode ? 'bg-slate-100 dark:bg-slate-700' : ''}
              >
                <Sun className="mr-2 h-4 w-4" />
                Light Mode
                {!isDarkMode && (
                  <CheckCircle2 className="ml-auto h-4 w-4 text-green-600" />
                )}
              </SimpleDropdownItem>
              
              {/* Dark Mode */}
              <SimpleDropdownItem 
                onClick={() => {
                  if (!isDarkMode) toggleDarkMode();
                  // TODO: Reset colorblind mode
                }}
                className={isDarkMode ? 'bg-slate-100 dark:bg-slate-700' : ''}
              >
                <Moon className="mr-2 h-4 w-4" />
                Dark Mode
                {isDarkMode && (
                  <CheckCircle2 className="ml-auto h-4 w-4 text-green-600" />
                )}
              </SimpleDropdownItem>
              
            </SimpleDropdown>

            {/* Wi-Fi/Sync Status */}
            {(() => {
              const wifiProps = getWifiStatusProps();
              const WifiIcon = wifiProps.icon;
              return (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={wifiProps.onClick || (connectionStatus === 'connected' ? triggerSync : undefined)}
                  className={`gap-2 ${wifiProps.cursor}`}
                  title={wifiProps.title}
                  disabled={connectionStatus === 'reconnecting'}
                >
                  <WifiIcon className={`h-4 w-4 ${wifiProps.className}`} />
                  {pendingCount > 0 && (
                    <Badge variant="destructive" className="ml-1">
                      {pendingCount}
                    </Badge>
                  )}
                  {(isSyncing || connectionStatus === 'reconnecting') && (
                    <Loader2 className="h-3 w-3 animate-spin" />
                  )}
                </Button>
              );
            })()}

            {/* Notifications */}
            <Button variant="ghost" size="sm" asChild className="relative">
              <Link to="/notifications">
                <Bell className="h-5 w-5" />
                {/* Dynamic unread badge - only show if count > 0 */}
                {notificationCount > 0 && (
                  <span className="absolute -top-1 -right-1 h-5 min-w-5 px-1 rounded-full bg-red-600 text-white text-xs flex items-center justify-center">
                    {notificationCount > 99 ? '99+' : notificationCount}
                  </span>
                )}
              </Link>
            </Button>

            {/* User Menu */}
            <SimpleDropdown
              align="end"
              trigger={
                <Button variant="ghost" size="sm" className="gap-2">
                  <Avatar className="h-8 w-8">
                    <AvatarFallback>{initials}</AvatarFallback>
                  </Avatar>
                  <div className="text-left hidden md:block">
                    <div className="text-sm">{user.name}</div>
                    <div className="text-xs text-slate-500">
                      {formatRoleName(user.currentRole)}
                    </div>
                  </div>
                </Button>
              }
              className="w-56"
            >
              <SimpleDropdownLabel>My Account</SimpleDropdownLabel>
              <SimpleDropdownSeparator />
              
              {/* Role Switcher in Profile Menu */}
              {user.availableRoles.length > 1 && (
                <>
                  <SimpleDropdownLabel className="text-xs text-slate-500 font-normal px-2 py-1.5">
                    Switch Role
                  </SimpleDropdownLabel>
                  {user.availableRoles.map((role) => (
                    <SimpleDropdownItem
                      key={role}
                      onClick={() => {
                        switchRole(role);
                        navigate('/dashboard');
                      }}
                      className={role === user.currentRole ? 'bg-slate-100' : ''}
                    >
                      <User className="mr-2 h-4 w-4" />
                      {formatRoleName(role)}
                      {role === user.currentRole && (
                        <ChevronRight className="ml-auto h-4 w-4" />
                      )}
                    </SimpleDropdownItem>
                  ))}
                  <SimpleDropdownSeparator />
                </>
              )}
              
              <SimpleDropdownItem onClick={() => navigate('/settings')}>
                <Settings className="mr-2 h-4 w-4" />
                Settings
              </SimpleDropdownItem>
              <SimpleDropdownItem onClick={() => navigate('/submit-ticket')}>
                <FileText className="mr-2 h-4 w-4" />
                Submit Ticket
              </SimpleDropdownItem>
              <SimpleDropdownItem onClick={() => navigate('/submit-bug')}>
                <Bug className="mr-2 h-4 w-4" />
                Submit a Bug
              </SimpleDropdownItem>
              <SimpleDropdownItem onClick={() => navigate('/request-feature')}>
                <Lightbulb className="mr-2 h-4 w-4" />
                Request a Feature
              </SimpleDropdownItem>
              <SimpleDropdownSeparator />
              <SimpleDropdownItem onClick={logout} className="text-red-600">
                <LogOut className="mr-2 h-4 w-4" />
                Log Out
              </SimpleDropdownItem>
            </SimpleDropdown>
          </div>
        </header>

        {/* Page Content - TASK 3: overflow fix + TASK 1: bottom spacing + MOBILE-2: scroll behavior */}
        <main className="flex-1 overflow-auto overflow-x-hidden scroll-smooth">
          <div className="max-w-full w-full box-border pb-safe-bottom">
            {children}
          </div>
        </main>
      </div>

      {/* Set Shift Modal */}
      <SetShiftModal
        isOpen={setShiftModalOpen}
        onClose={() => setSetShiftModalOpen(false)}
      />
    </div>
  );
}