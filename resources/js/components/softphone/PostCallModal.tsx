import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useSoftphone } from '@/contexts/SoftphoneContext';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Loader2 } from 'lucide-react';

const DISPOSITIONS = [
  { value: 'interested', label: 'calls.disposition_interested', color: 'text-green-600' },
  { value: 'not_interested', label: 'calls.disposition_not_interested', color: 'text-red-600' },
  { value: 'callback', label: 'calls.disposition_callback', color: 'text-blue-600' },
  { value: 'wrong_number', label: 'calls.disposition_wrong_number', color: 'text-orange-600' },
  { value: 'voicemail', label: 'calls.disposition_voicemail', color: 'text-purple-600' },
  { value: 'no_answer', label: 'calls.disposition_no_answer', color: 'text-gray-600' },
  { value: 'do_not_call', label: 'calls.disposition_do_not_call', color: 'text-red-800' },
];

export function PostCallModal() {
  const { t } = useTranslation();
  const {
    showDispositionModal,
    setShowDispositionModal,
    lastCallId,
    activeContactName,
    onDispositionSavedCallback,
  } = useSoftphone();

  const [disposition, setDisposition] = useState('');
  const [callNotes, setCallNotes] = useState('');
  const [callbackAt, setCallbackAt] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSave = async () => {
    if (!disposition || !lastCallId) return;

    setSaving(true);
    try {
      await window.axios.post(`/calls/${lastCallId}/disposition`, {
        disposition,
        call_notes: callNotes || null,
        callback_at: callbackAt || null,
      });

      // Reset state
      setDisposition('');
      setCallNotes('');
      setCallbackAt('');
      setShowDispositionModal(false);
      onDispositionSavedCallback?.();
    } catch (error) {
      console.error('Failed to save disposition:', error);
    } finally {
      setSaving(false);
    }
  };

  const handleSkip = () => {
    setDisposition('');
    setCallNotes('');
    setCallbackAt('');
    setShowDispositionModal(false);
  };

  return (
    <Dialog open={showDispositionModal} onOpenChange={setShowDispositionModal}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>{t('calls.post_call_notes')}</DialogTitle>
          <DialogDescription>
            {activeContactName
              ? t('calls.post_call_description_contact', { name: activeContactName })
              : t('calls.post_call_description')}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-4">
          <div className="space-y-2">
            <Label>{t('calls.disposition')}</Label>
            <Select value={disposition} onValueChange={setDisposition}>
              <SelectTrigger>
                <SelectValue placeholder={t('calls.select_disposition')} />
              </SelectTrigger>
              <SelectContent>
                {DISPOSITIONS.map((d) => (
                  <SelectItem key={d.value} value={d.value}>
                    <span className={d.color}>{t(d.label)}</span>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label>{t('calls.call_notes')}</Label>
            <Textarea
              value={callNotes}
              onChange={(e) => setCallNotes(e.target.value)}
              placeholder={t('calls.call_notes_placeholder')}
              rows={3}
            />
          </div>

          {disposition === 'callback' && (
            <div className="space-y-2">
              <Label>{t('calls.callback_date')}</Label>
              <Input
                type="datetime-local"
                value={callbackAt}
                onChange={(e) => setCallbackAt(e.target.value)}
              />
            </div>
          )}
        </div>

        <DialogFooter>
          <Button variant="ghost" onClick={handleSkip}>
            {t('common.skip')}
          </Button>
          <Button onClick={handleSave} disabled={!disposition || saving}>
            {saving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {t('common.save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
