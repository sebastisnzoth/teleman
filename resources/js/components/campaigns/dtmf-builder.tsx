import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Plus, X, Hash } from 'lucide-react';
import { useState } from 'react';

interface DtmfAction {
  key: string;
  label: string;
  action: string;
}

interface Props {
  value: DtmfAction[];
  onChange: (actions: DtmfAction[]) => void;
  error?: string;
}

export default function DtmfBuilder({ value = [], onChange, error }: Props) {
  const [newKey, setNewKey] = useState('');
  const [newLabel, setNewLabel] = useState('');
  const [newAction, setNewAction] = useState('');

  const handleAdd = () => {
    if (!newKey || !newLabel) return;

    // Validate key is a single digit
    if (!/^[0-9*#]$/.test(newKey)) {
      alert('Key must be a single digit (0-9) or * or #');
      return;
    }

    // Check if key already exists
    if (value.some(action => action.key === newKey)) {
      alert('This key is already configured');
      return;
    }

    const newActions = [
      ...value,
      { key: newKey, label: newLabel, action: newAction || 'record' }
    ];
    
    onChange(newActions);
    
    // Reset form
    setNewKey('');
    setNewLabel('');
    setNewAction('');
  };

  const handleRemove = (key: string) => {
    onChange(value.filter(action => action.key !== key));
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>DTMF Configuration</CardTitle>
        <CardDescription>
          Configure button press actions for interactive campaigns
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {/* Existing Actions */}
        {value.length > 0 && (
          <div className="space-y-2">
            <Label>Configured Actions:</Label>
            {value.map((action) => (
              <div key={action.key} className="flex items-center justify-between rounded-lg border p-3">
                <div className="flex items-center gap-3">
                  <Badge variant="outline" className="flex items-center gap-1">
                    <Hash className="h-3 w-3" />
                    {action.key}
                  </Badge>
                  <div>
                    <div className="font-medium">{action.label}</div>
                    <div className="text-xs text-slate-500">Action: {action.action}</div>
                  </div>
                </div>
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={() => handleRemove(action.key)}
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
            ))}
          </div>
        )}

        {/* Add New Action Form */}
        <div className="space-y-3 rounded-lg border border-dashed p-4">
          <div className="text-sm font-medium">Add New DTMF Action:</div>
          
          <div className="grid grid-cols-3 gap-3">
            <div>
              <Label htmlFor="dtmf-key">Key *</Label>
              <Input
                id="dtmf-key"
                type="text"
                placeholder="1"
                maxLength={1}
                value={newKey}
                onChange={(e) => setNewKey(e.target.value)}
              />
              <p className="mt-1 text-xs text-slate-500">0-9, *, or #</p>
            </div>
            
            <div className="col-span-2">
              <Label htmlFor="dtmf-label">Label *</Label>
              <Input
                id="dtmf-label"
                type="text"
                placeholder="Press 1 for Yes"
                value={newLabel}
                onChange={(e) => setNewLabel(e.target.value)}
              />
            </div>
          </div>

          <div>
            <Label htmlFor="dtmf-action">Action Description (Optional)</Label>
            <Textarea
              id="dtmf-action"
              placeholder="What happens when this key is pressed..."
              value={newAction}
              onChange={(e) => setNewAction(e.target.value)}
              rows={2}
            />
          </div>

          <Button
            type="button"
            variant="outline"
            onClick={handleAdd}
            disabled={!newKey || !newLabel}
          >
            <Plus className="mr-2 h-4 w-4" />
            Add Action
          </Button>
        </div>

        {error && (
          <p className="text-sm text-red-500">{error}</p>
        )}

        {/* Helper Text */}
        <div className="rounded-lg bg-blue-50 p-3 text-sm text-blue-900">
          <strong>Tip:</strong> DTMF (button press) actions allow callers to interact with your campaign.
          For example: "Press 1 for Yes, Press 2 for No, Press 9 to speak with an agent"
        </div>

        {/* Popular Examples */}
        {value.length === 0 && (
          <div className="space-y-2">
            <Label>Quick Examples:</Label>
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onChange([
                  { key: '1', label: 'Press 1 for Yes', action: 'record_yes' },
                  { key: '2', label: 'Press 2 for No', action: 'record_no' },
                ])}
              >
                Yes/No Survey
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onChange([
                  { key: '1', label: 'Press 1 to confirm', action: 'confirm' },
                  { key: '2', label: 'Press 2 to reschedule', action: 'reschedule' },
                  { key: '9', label: 'Press 9 for agent', action: 'transfer' },
                ])}
              >
                Appointment Reminder
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => onChange([
                  { key: '1', label: 'Press 1 - Very Satisfied', action: 'nps_5' },
                  { key: '2', label: 'Press 2 - Satisfied', action: 'nps_4' },
                  { key: '3', label: 'Press 3 - Neutral', action: 'nps_3' },
                  { key: '4', label: 'Press 4 - Dissatisfied', action: 'nps_2' },
                  { key: '5', label: 'Press 5 - Very Dissatisfied', action: 'nps_1' },
                ])}
              >
                5-Point Scale
              </Button>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
