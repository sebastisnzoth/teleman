import { useState } from 'react';
import { useSoftphone } from '@/contexts/SoftphoneContext';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { PhoneInput } from '@/components/calls/phone-input';
import { Edit3, Loader2, Save } from 'lucide-react';
import { toast } from 'sonner';
import { normalizeToE164 } from '@/lib/phone-validation';

interface ContactData {
  first_name?: string;
  last_name?: string;
  phone_number?: string;
  notes?: string;
}

export function ContactQuickEdit() {
  const { activeContactId, activeContactName, phoneNumber, setPhoneNumber } = useSoftphone();
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [form, setForm] = useState<ContactData>({
    first_name: '',
    last_name: '',
    phone_number: '',
    notes: '',
  });

  // Don't render if no active contact
  if (!activeContactId) return null;

  const loadContact = async () => {
    if (!activeContactId) return;
    setLoading(true);
    try {
      const res = await window.axios.get(`/contacts/${activeContactId}/json`);
      const contact = res.data?.contact || res.data;
      setForm({
        first_name: contact.first_name || '',
        last_name: contact.last_name || '',
        phone_number: contact.phone_number || phoneNumber || '',
        notes: contact.notes || '',
      });
    } catch (err) {
      // Fallback: use whatever info we have from the softphone context
      const [firstName = '', lastName = ''] = (activeContactName || '').split(' ');
      setForm({
        first_name: firstName,
        last_name: lastName,
        phone_number: phoneNumber || '',
        notes: '',
      });
    } finally {
      setLoading(false);
    }
  };

  const handleOpen = (isOpen: boolean) => {
    setOpen(isOpen);
    if (isOpen) loadContact();
  };

  const handleSave = async () => {
    const normalizedPhone = normalizeToE164(form.phone_number || '', 'US');
    if (!normalizedPhone) {
      toast.error('Phone number is required');
      return;
    }
    setSaving(true);
    try {
      await window.axios.put(`/contacts/${activeContactId}`, {
        phone_number: normalizedPhone,
        first_name: form.first_name || null,
        last_name: form.last_name || null,
        notes: form.notes || null,
      }, {
        headers: { Accept: 'application/json' },
      });
      // Sync normalized phone to the softphone dialer
      setPhoneNumber(normalizedPhone);
      toast.success('Contact updated');
      setOpen(false);
    } catch (err: any) {
      // Prefer field-specific validation errors over the generic "given data was invalid" message.
      const fieldErrors = Object.values(err?.response?.data?.errors || {}).flat() as string[];
      const msg = fieldErrors.length > 0
        ? fieldErrors.join(' ')
        : (err?.response?.data?.message || 'Failed to update contact');
      toast.error(msg);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={handleOpen}>
      <Button
        variant="outline"
        size="sm"
        className="w-full"
        onClick={() => handleOpen(true)}
        type="button"
      >
        <Edit3 className="mr-2 h-3.5 w-3.5" />
        Edit Contact
      </Button>

      <DialogContent className="sm:max-w-md z-[10000]">
        <DialogHeader>
          <DialogTitle>Edit Contact</DialogTitle>
          <DialogDescription>
            Update phone number, name, or notes. Changes save immediately.
          </DialogDescription>
        </DialogHeader>

        {loading ? (
          <div className="flex items-center justify-center py-8 text-muted-foreground">
            <Loader2 className="h-5 w-5 animate-spin mr-2" />
            Loading...
          </div>
        ) : (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2">
                <Label htmlFor="qe_first_name">First name</Label>
                <Input
                  id="qe_first_name"
                  value={form.first_name || ''}
                  onChange={(e) => setForm({ ...form, first_name: e.target.value })}
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="qe_last_name">Last name</Label>
                <Input
                  id="qe_last_name"
                  value={form.last_name || ''}
                  onChange={(e) => setForm({ ...form, last_name: e.target.value })}
                />
              </div>
            </div>

            <PhoneInput
              label="Phone number"
              defaultCountry="US"
              value={(form.phone_number || '') as any}
              onChange={(v) => setForm({ ...form, phone_number: (v as string) || '' })}
            />

            <div className="space-y-2">
              <Label htmlFor="qe_notes">Notes</Label>
              <Textarea
                id="qe_notes"
                rows={4}
                value={form.notes || ''}
                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                placeholder="Add notes about this contact..."
              />
            </div>
          </div>
        )}

        <DialogFooter>
          <Button variant="outline" onClick={() => setOpen(false)} disabled={saving}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={saving || loading}>
            {saving ? <Loader2 className="h-4 w-4 animate-spin mr-2" /> : <Save className="h-4 w-4 mr-2" />}
            Save
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
