import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Save } from 'lucide-react';
import { router } from '@inertiajs/react';

interface Campaign {
  id: number;
  name: string;
  type: string;
  message: string | null;
  language: string | null;
  voice: string | null;
  enable_recording: boolean;
  enable_dtmf: boolean;
  max_concurrent_calls: number;
  retry_attempts: number;
  retry_delay_minutes: number;
  dtmf_actions?: Array<{key: string; label: string; action: string}>;
}

interface Props {
  campaign: Campaign;
}

export default function SaveAsTemplateButton({ campaign }: Props) {
  const [open, setOpen] = useState(false);
  const [name, setName] = useState(`${campaign.name} Template`);
  const [description, setDescription] = useState('');
  const [category, setCategory] = useState<string>('promotional');
  const [saving, setSaving] = useState(false);

  const handleSave = () => {
    setSaving(true);

    const templateData = {
      message: campaign.message,
      language: campaign.language,
      voice: campaign.voice,
      enable_recording: campaign.enable_recording,
      enable_dtmf: campaign.enable_dtmf,
      max_concurrent_calls: campaign.max_concurrent_calls,
      retry_attempts: campaign.retry_attempts,
      retry_delay_minutes: campaign.retry_delay_minutes,
      dtmf_actions: campaign.dtmf_actions,
    };

    router.post('/campaign-templates', {
      name,
      description,
      category,
      template_data: templateData,
    }, {
      onSuccess: () => {
        setOpen(false);
        setSaving(false);
      },
      onError: () => {
        setSaving(false);
      },
    });
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="outline">
          <Save className="mr-2 h-4 w-4" />
          Save as Template
        </Button>
      </DialogTrigger>
      <DialogContent className="sm:max-w-[525px]">
        <DialogHeader>
          <DialogTitle>Save Campaign as Template</DialogTitle>
          <DialogDescription>
            Create a reusable template from this campaign's configuration
          </DialogDescription>
        </DialogHeader>
        <div className="grid gap-4 py-4">
          <div className="grid gap-2">
            <Label htmlFor="template-name">Template Name *</Label>
            <Input
              id="template-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="My Campaign Template"
            />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="template-description">Description</Label>
            <Textarea
              id="template-description"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Describe when to use this template..."
              rows={3}
            />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="template-category">Category *</Label>
            <Select value={category} onValueChange={setCategory}>
              <SelectTrigger id="template-category">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="promotional">Promotional</SelectItem>
                <SelectItem value="lead_qualification">Lead Qualification</SelectItem>
                <SelectItem value="survey">Survey</SelectItem>
                <SelectItem value="notification">Notification</SelectItem>
                <SelectItem value="personalized">Personalized</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => setOpen(false)} disabled={saving}>
            Cancel
          </Button>
          <Button onClick={handleSave} disabled={saving || !name}>
            {saving ? 'Saving...' : 'Save Template'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
