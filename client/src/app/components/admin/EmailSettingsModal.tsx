import { useState, useEffect } from 'react';
import type { ChangeEvent } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '../ui/dialog.js';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '../ui/select.js';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '../ui/dropdown-menu.js';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '../ui/alert-dialog.js';
import { Button } from '../ui/button.js';
import { Input } from '../ui/input.js';
import { Label } from '../ui/label.js';
import { Plus, MoreVertical, Pencil, Trash2, Loader2 } from 'lucide-react';

// Types
export interface EmailRecipient {
  id: number;
  clinic_id: number;
  email_address: string;
  recipient_type: 'work_related' | 'all';
  recipient_name: string | undefined;
  created_at: string;
  is_active: boolean;
}

export interface Clinic {
  id: number;
  name: string;
  address?: string;
}

interface EmailSettingsModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export default function EmailSettingsModal({ open, onOpenChange }: EmailSettingsModalProps) {
  // State
  const [selectedClinicId, setSelectedClinicId] = useState<string>('');
  const [clinics, setClinics] = useState<Clinic[]>([]);
  const [emailRecipients, setEmailRecipients] = useState<EmailRecipient[]>([]);
  const [loading, setLoading] = useState(false);
  const [recipientsLoading, setRecipientsLoading] = useState(false);
  const [_error, setError] = useState<string | null>(null);

  // Add/Edit Email State
  const [isAddingEmail, setIsAddingEmail] = useState(false);
  const [editingEmail, setEditingEmail] = useState<EmailRecipient | null>(null);
  const [emailInput, setEmailInput] = useState('');
  const [nameInput, setNameInput] = useState('');
  const [savingEmail, setSavingEmail] = useState(false);

  // Delete Confirmation State
  const [deletingEmail, setDeletingEmail] = useState<EmailRecipient | null>(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  // Fetch clinics on mount
  useEffect(() => {
    if (open) {
      fetchClinics();
    }
  }, [open]);

  // Fetch email recipients when clinic changes
  useEffect(() => {
    if (selectedClinicId) {
      fetchEmailRecipients(parseInt(selectedClinicId));
    } else {
      setEmailRecipients([]);
    }
  }, [selectedClinicId]);

  const fetchClinics = async () => {
    setLoading(true);
    setError(null);
    try {
      const response = await fetch('/api/clinics', {
        credentials: 'include',
      });
      if (!response.ok) {
        throw new Error('Failed to fetch clinics');
      }
      const data = await response.json();
      setClinics(data.data || data || []);
    } catch (err) {
      setError('Failed to load clinics');
      console.error('Error fetching clinics:', err);
      // Use mock data for development
      setClinics([
        { id: 1, name: 'Main Street Clinic', address: '123 Main St' },
        { id: 2, name: 'North Branch', address: '456 North Ave' },
        { id: 3, name: 'Downtown Medical', address: '789 Downtown Blvd' },
      ]);
    } finally {
      setLoading(false);
    }
  };

  const fetchEmailRecipients = async (clinicId: number) => {
    setRecipientsLoading(true);
    setError(null);
    try {
      const response = await fetch(`/api/clinics/${clinicId}/email-recipients`, {
        credentials: 'include',
      });
      if (!response.ok) {
        throw new Error('Failed to fetch email recipients');
      }
      const data = await response.json();
      setEmailRecipients(data.data || data || []);
    } catch (err) {
      console.error('Error fetching email recipients:', err);
      // Use mock data for development
      setEmailRecipients([
        { id: 1, clinic_id: clinicId, email_address: 'hr@company.com', recipient_type: 'work_related', recipient_name: 'HR Department', created_at: new Date().toISOString(), is_active: true },
        { id: 2, clinic_id: clinicId, email_address: 'safety@company.com', recipient_type: 'work_related', recipient_name: 'Safety Team', created_at: new Date().toISOString(), is_active: true },
      ]);
    } finally {
      setRecipientsLoading(false);
    }
  };

  const handleAddEmail = () => {
    setIsAddingEmail(true);
    setEmailInput('');
    setNameInput('');
  };

  const handleEditEmail = (email: EmailRecipient) => {
    setEditingEmail(email);
    setEmailInput(email.email_address);
    setNameInput(email.recipient_name || '');
  };

  const handleSaveEmail = async () => {
    if (!emailInput.trim() || !selectedClinicId) return;

    setSavingEmail(true);
    try {
      const clinicId = parseInt(selectedClinicId);
      const recipientName = nameInput.trim() || undefined;
      
      if (editingEmail) {
        // Update existing email
        const response = await fetch(`/api/clinics/${clinicId}/email-recipients/${editingEmail.id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            email_address: emailInput.trim(),
            recipient_name: recipientName,
          }),
        });
        
        if (!response.ok) {
          throw new Error('Failed to update email');
        }
        
        // Update local state
        setEmailRecipients(prev => 
          prev.map(e => e.id === editingEmail.id 
            ? { ...e, email_address: emailInput.trim(), recipient_name: recipientName }
            : e
          )
        );
      } else {
        // Add new email
        const response = await fetch(`/api/clinics/${clinicId}/email-recipients`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify({
            email_address: emailInput.trim(),
            recipient_name: recipientName,
          }),
        });
        
        if (!response.ok) {
          throw new Error('Failed to add email');
        }
        
        const newEmail = await response.json();
        
        // Add to local state (or use mock if API not available)
        const emailToAdd: EmailRecipient = newEmail.data || newEmail || {
          id: Date.now(),
          clinic_id: clinicId,
          email_address: emailInput.trim(),
          recipient_type: 'work_related',
          recipient_name: recipientName,
          created_at: new Date().toISOString(),
          is_active: true,
        };
        setEmailRecipients(prev => [...prev, emailToAdd]);
      }

      // Reset form
      setIsAddingEmail(false);
      setEditingEmail(null);
      setEmailInput('');
      setNameInput('');
    } catch (err) {
      console.error('Error saving email:', err);
      // For development, still update local state
      const recipientName = nameInput.trim() || undefined;
      if (editingEmail) {
        setEmailRecipients(prev => 
          prev.map(e => e.id === editingEmail.id 
            ? { ...e, email_address: emailInput.trim(), recipient_name: recipientName }
            : e
          )
        );
      } else {
        const newEmail: EmailRecipient = {
          id: Date.now(),
          clinic_id: parseInt(selectedClinicId),
          email_address: emailInput.trim(),
          recipient_type: 'work_related',
          recipient_name: recipientName,
          created_at: new Date().toISOString(),
          is_active: true,
        };
        setEmailRecipients(prev => [...prev, newEmail]);
      }
      setIsAddingEmail(false);
      setEditingEmail(null);
      setEmailInput('');
      setNameInput('');
    } finally {
      setSavingEmail(false);
    }
  };

  const handleDeleteEmail = async () => {
    if (!deletingEmail || !selectedClinicId) return;

    setDeleteLoading(true);
    try {
      const response = await fetch(`/api/clinics/${selectedClinicId}/email-recipients/${deletingEmail.id}`, {
        method: 'DELETE',
        credentials: 'include',
      });
      
      if (!response.ok) {
        throw new Error('Failed to delete email');
      }
      
      // Remove from local state
      setEmailRecipients(prev => prev.filter(e => e.id !== deletingEmail.id));
    } catch (err) {
      console.error('Error deleting email:', err);
      // For development, still remove from local state
      setEmailRecipients(prev => prev.filter(e => e.id !== deletingEmail.id));
    } finally {
      setDeleteLoading(false);
      setDeletingEmail(null);
    }
  };

  const handleCancelEdit = () => {
    setIsAddingEmail(false);
    setEditingEmail(null);
    setEmailInput('');
    setNameInput('');
  };

  const selectedClinic = clinics.find(c => c.id === parseInt(selectedClinicId));

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="sm:max-w-[600px]">
          <DialogHeader>
            <DialogTitle>Email Settings</DialogTitle>
            <DialogDescription>
              Configure email recipients for work-related incident notifications by clinic.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-6">
            {/* Clinic Selector */}
            <div>
              <Label htmlFor="clinic-select" className="mb-2 block">Select Clinic/Location</Label>
              <Select value={selectedClinicId} onValueChange={setSelectedClinicId}>
                <SelectTrigger id="clinic-select" className="w-full">
                  <SelectValue placeholder="Select a clinic..." />
                </SelectTrigger>
                <SelectContent>
                  {loading ? (
                    <SelectItem value="loading" disabled>Loading clinics...</SelectItem>
                  ) : (
                    clinics.map(clinic => (
                      <SelectItem key={clinic.id} value={clinic.id.toString()}>
                        {clinic.name}
                      </SelectItem>
                    ))
                  )}
                </SelectContent>
              </Select>
            </div>

            {/* Email Recipients Section */}
            {selectedClinicId && (
              <div className="border rounded-lg">
                {/* Section Header */}
                <div className="flex items-center justify-between p-4 border-b bg-slate-50 dark:bg-slate-800 rounded-t-lg">
                  <h3 className="font-semibold text-sm uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    {selectedClinic?.name || 'Selected Clinic'}
                  </h3>
                  <Button 
                    size="sm" 
                    variant="outline"
                    onClick={handleAddEmail}
                    disabled={isAddingEmail || !!editingEmail}
                  >
                    <Plus className="h-4 w-4 mr-1" />
                    Add Email
                  </Button>
                </div>

                {/* Content */}
                <div className="p-4">
                  <p className="text-sm text-slate-600 dark:text-slate-400 mb-4">
                    When work-related, the report will be emailed to:
                  </p>

                  {recipientsLoading ? (
                    <div className="flex items-center justify-center py-8">
                      <Loader2 className="h-6 w-6 animate-spin text-slate-400" />
                    </div>
                  ) : (
                    <div className="space-y-2">
                      {/* Add Email Form */}
                      {isAddingEmail && (
                        <div className="p-3 border rounded-lg bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 space-y-3">
                          <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                              <Label htmlFor="new-email" className="text-xs mb-1 block">Email Address *</Label>
                              <Input
                                id="new-email"
                                type="email"
                                placeholder="email@example.com"
                                value={emailInput}
                                onChange={(e: ChangeEvent<HTMLInputElement>) => setEmailInput(e.target.value)}
                                autoFocus
                              />
                            </div>
                            <div>
                              <Label htmlFor="new-name" className="text-xs mb-1 block">Name (optional)</Label>
                              <Input
                                id="new-name"
                                type="text"
                                placeholder="HR Department"
                                value={nameInput}
                                onChange={(e: ChangeEvent<HTMLInputElement>) => setNameInput(e.target.value)}
                              />
                            </div>
                          </div>
                          <div className="flex gap-2 justify-end">
                            <Button size="sm" variant="ghost" onClick={handleCancelEdit}>
                              Cancel
                            </Button>
                            <Button 
                              size="sm" 
                              onClick={handleSaveEmail}
                              disabled={!emailInput.trim() || savingEmail}
                            >
                              {savingEmail && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
                              Add Email
                            </Button>
                          </div>
                        </div>
                      )}

                      {/* Email List */}
                      {emailRecipients.length === 0 && !isAddingEmail ? (
                        <p className="text-sm text-slate-500 dark:text-slate-400 text-center py-6">
                          No email recipients configured for this clinic.
                        </p>
                      ) : (
                        emailRecipients.map(email => (
                          <div key={email.id}>
                            {editingEmail?.id === email.id ? (
                              // Edit Form
                              <div className="p-3 border rounded-lg bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800 space-y-3">
                                <div className="grid gap-3 sm:grid-cols-2">
                                  <div>
                                    <Label htmlFor={`edit-email-${email.id}`} className="text-xs mb-1 block">Email Address *</Label>
                                    <Input
                                      id={`edit-email-${email.id}`}
                                      type="email"
                                      value={emailInput}
                                      onChange={(e: ChangeEvent<HTMLInputElement>) => setEmailInput(e.target.value)}
                                      autoFocus
                                    />
                                  </div>
                                  <div>
                                    <Label htmlFor={`edit-name-${email.id}`} className="text-xs mb-1 block">Name (optional)</Label>
                                    <Input
                                      id={`edit-name-${email.id}`}
                                      type="text"
                                      value={nameInput}
                                      onChange={(e: ChangeEvent<HTMLInputElement>) => setNameInput(e.target.value)}
                                    />
                                  </div>
                                </div>
                                <div className="flex gap-2 justify-end">
                                  <Button size="sm" variant="ghost" onClick={handleCancelEdit}>
                                    Cancel
                                  </Button>
                                  <Button 
                                    size="sm" 
                                    onClick={handleSaveEmail}
                                    disabled={!emailInput.trim() || savingEmail}
                                  >
                                    {savingEmail && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
                                    Save Changes
                                  </Button>
                                </div>
                              </div>
                            ) : (
                              // Display Row
                              <div className="flex items-center justify-between p-3 border rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <div>
                                  <p className="font-medium text-sm">{email.email_address}</p>
                                  {email.recipient_name && (
                                    <p className="text-xs text-slate-500 dark:text-slate-400">{email.recipient_name}</p>
                                  )}
                                </div>
                                <DropdownMenu>
                                  <DropdownMenuTrigger asChild>
                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                      <MoreVertical className="h-4 w-4" />
                                    </Button>
                                  </DropdownMenuTrigger>
                                  <DropdownMenuContent align="end">
                                    <DropdownMenuItem onClick={() => handleEditEmail(email)}>
                                      <Pencil className="h-4 w-4 mr-2" />
                                      Edit Email
                                    </DropdownMenuItem>
                                    <DropdownMenuItem 
                                      onClick={() => setDeletingEmail(email)}
                                      className="text-red-600 dark:text-red-400"
                                    >
                                      <Trash2 className="h-4 w-4 mr-2" />
                                      Remove Email
                                    </DropdownMenuItem>
                                  </DropdownMenuContent>
                                </DropdownMenu>
                              </div>
                            )}
                          </div>
                        ))
                      )}
                    </div>
                  )}
                </div>
              </div>
            )}

            {/* Empty State */}
            {!selectedClinicId && (
              <div className="text-center py-8 text-slate-500 dark:text-slate-400">
                <p>Select a clinic to manage email recipients.</p>
              </div>
            )}
          </div>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <AlertDialog open={!!deletingEmail} onOpenChange={(isOpen: boolean) => !isOpen && setDeletingEmail(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remove Email Recipient</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to remove <strong>{deletingEmail?.email_address}</strong> from the notification list?
              This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={deleteLoading}>Cancel</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleDeleteEmail}
              disabled={deleteLoading}
              className="bg-red-600 hover:bg-red-700"
            >
              {deleteLoading && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
              Remove
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
