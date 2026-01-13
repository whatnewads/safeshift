import { Card } from '../../components/ui/card.js';
import { Button } from '../../components/ui/button.js';
import { Input } from '../../components/ui/input.js';
import { Label } from '../../components/ui/label.js';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '../../components/ui/tabs.js';
import { Badge } from '../../components/ui/badge.js';
import {
  User,
  Lock,
  ShieldCheck,
  Bell,
  Monitor,
} from 'lucide-react';
import { Switch } from '../../components/ui/switch.js';
import { SessionSettings } from '../../components/settings/SessionSettings.js';

export default function SettingsPage() {
  return (
    <div className="p-6 max-w-5xl mx-auto space-y-6">
      <div>
        <h1 className="text-3xl font-bold mb-2">Settings</h1>
        <p className="text-slate-600 dark:text-slate-400">Manage your account and preferences</p>
      </div>

      <Tabs defaultValue="profile" className="space-y-6">
        <TabsList>
          <TabsTrigger value="profile" className="gap-2">
            <User className="h-4 w-4" />
            Profile
          </TabsTrigger>
          <TabsTrigger value="security" className="gap-2">
            <Lock className="h-4 w-4" />
            Security
          </TabsTrigger>
          <TabsTrigger value="notifications" className="gap-2">
            <Bell className="h-4 w-4" />
            Notifications
          </TabsTrigger>
          <TabsTrigger value="sessions" className="gap-2">
            <Monitor className="h-4 w-4" />
            Sessions
          </TabsTrigger>
        </TabsList>

        <TabsContent value="profile" className="space-y-6">
          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-6">Profile Information</h2>
            <div className="space-y-4">
              <div className="grid md:grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="firstName">First Name</Label>
                  <Input id="firstName" defaultValue="Sarah" />
                </div>
                <div>
                  <Label htmlFor="lastName">Last Name</Label>
                  <Input id="lastName" defaultValue="Johnson" />
                </div>
              </div>

              <div>
                <Label htmlFor="email">Email</Label>
                <Input id="email" type="email" defaultValue="sarah.johnson@occupational-health.com" disabled />
                <p className="text-xs text-slate-500 mt-1">Email cannot be changed</p>
              </div>

              <div>
                <Label htmlFor="title">Job Title</Label>
                <Input id="title" defaultValue="Clinical Provider / Nurse Practitioner" />
              </div>

              <div>
                <Label htmlFor="license">License Number</Label>
                <Input id="license" defaultValue="NP-12345-CA" />
              </div>

              <div className="pt-4">
                <Button>Save Changes</Button>
              </div>
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="security" className="space-y-6">
          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-6">Change Password</h2>
            <div className="space-y-4 max-w-md">
              <div>
                <Label htmlFor="current">Current Password</Label>
                <Input id="current" type="password" />
              </div>
              <div>
                <Label htmlFor="new">New Password</Label>
                <Input id="new" type="password" />
              </div>
              <div>
                <Label htmlFor="confirm">Confirm New Password</Label>
                <Input id="confirm" type="password" />
              </div>
              <Button>Update Password</Button>
            </div>
          </Card>

          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-6 flex items-center gap-2">
              <ShieldCheck className="h-5 w-5" />
              Two-Factor Authentication
            </h2>
            <div className="flex items-center justify-between">
              <div>
                <p className="font-medium">Microsoft Authenticator</p>
                <p className="text-sm text-slate-600 dark:text-slate-400">Enabled and active</p>
              </div>
              <Badge>Active</Badge>
            </div>
            <div className="mt-4 space-x-2">
              <Button variant="outline" size="sm">View Backup Codes</Button>
              <Button variant="outline" size="sm">Reconfigure</Button>
            </div>
          </Card>

          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-4">Trusted Devices</h2>
            <div className="space-y-3">
              <div className="flex items-center justify-between p-3 border border-slate-200 dark:border-slate-700 rounded-lg">
                <div>
                  <p className="font-medium">Work Laptop - Chrome</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">Last active: 5 minutes ago</p>
                </div>
                <Button variant="ghost" size="sm" className="text-red-600">Revoke</Button>
              </div>
              <div className="flex items-center justify-between p-3 border border-slate-200 dark:border-slate-700 rounded-lg">
                <div>
                  <p className="font-medium">Office Desktop - Edge</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">Last active: 2 days ago</p>
                </div>
                <Button variant="ghost" size="sm" className="text-red-600">Revoke</Button>
              </div>
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="notifications" className="space-y-6">
          <Card className="p-6">
            <h2 className="text-xl font-semibold mb-6">Notification Preferences</h2>
            <div className="space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium">Follow-up Reminders</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">Get notified about upcoming follow-ups</p>
                </div>
                <Switch defaultChecked />
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium">Case Updates</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">Notifications when cases are updated</p>
                </div>
                <Switch defaultChecked />
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium">OSHA Alerts</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">Important OSHA submission reminders</p>
                </div>
                <Switch defaultChecked />
              </div>
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium">Training Due</p>
                  <p className="text-sm text-slate-600 dark:text-slate-400">Remind me about training deadlines</p>
                </div>
                <Switch defaultChecked />
              </div>
            </div>
          </Card>
        </TabsContent>

        <TabsContent value="sessions" className="space-y-6">
          <SessionSettings />
        </TabsContent>
      </Tabs>
    </div>
  );
}
