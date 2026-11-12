import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, Trash2, Info } from 'lucide-react';
import InputError from '@/components/input-error';

interface DtmfAction {
  key: string;
  label: string;
  action: string;
  value?: string;
}

interface DtmfSettingsProps {
  data: {
    enable_dtmf: boolean;
    dtmf_num_digits: number;
    dtmf_timeout: number;
    dtmf_prompt: string;
    dtmf_actions: DtmfAction[];
  };
  errors: {
    enable_dtmf?: string;
    dtmf_num_digits?: string;
    dtmf_timeout?: string;
    dtmf_prompt?: string;
    dtmf_actions?: string;
  };
  onChange: (field: string, value: boolean | number | string | DtmfAction[]) => void;
}

export function DtmfSettings({ data, errors, onChange }: DtmfSettingsProps) {
  const addDtmfAction = () => {
    const newActions = [...(data.dtmf_actions || [])];
    newActions.push({
      key: '',
      label: '',
      action: 'message',
      value: '',
    });
    onChange('dtmf_actions', newActions);
  };

  const removeDtmfAction = (index: number) => {
    const newActions = [...(data.dtmf_actions || [])];
    newActions.splice(index, 1);
    onChange('dtmf_actions', newActions);
  };

  const updateDtmfAction = (index: number, field: string, value: string) => {
    const newActions = [...(data.dtmf_actions || [])];
    newActions[index] = { ...newActions[index], [field]: value };
    onChange('dtmf_actions', newActions);
  };

  return (
    <Card className={!data.enable_dtmf ? 'opacity-60' : ''}>
      <CardHeader>
        <div className="flex items-start justify-between">
          <div>
            <CardTitle>DTMF Settings (Interactive Keypad)</CardTitle>
            <CardDescription>
              Configure interactive voice response - let callers press keys to take actions
            </CardDescription>
          </div>
          <div className="flex items-center space-x-2">
            <input
              type="checkbox"
              id="enable_dtmf"
              checked={data.enable_dtmf}
              onChange={(e) => onChange('enable_dtmf', e.target.checked)}
              className="h-4 w-4 rounded border-slate-300"
            />
            <Label htmlFor="enable_dtmf" className="cursor-pointer font-normal">
              Enable DTMF
            </Label>
          </div>
        </div>
      </CardHeader>
      
      {data.enable_dtmf && (
        <CardContent className="space-y-6">
          {/* Basic DTMF Settings */}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="dtmf_num_digits">Number of Digits to Collect</Label>
              <Select
                value={data.dtmf_num_digits?.toString() || '1'}
                onValueChange={(value) => onChange('dtmf_num_digits', parseInt(value))}
              >
                <SelectTrigger id="dtmf_num_digits" className="mt-1.5">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {[1, 2, 3, 4, 5].map(num => (
                    <SelectItem key={num} value={num.toString()}>
                      {num} digit{num > 1 ? 's' : ''}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.dtmf_num_digits && <InputError message={errors.dtmf_num_digits} />}
              <p className="mt-1 text-xs text-slate-500">How many digits to collect (1-5)</p>
            </div>

            <div>
              <Label htmlFor="dtmf_timeout">Input Timeout (seconds)</Label>
              <Input
                id="dtmf_timeout"
                type="number"
                min="3"
                max="10"
                value={data.dtmf_timeout || 5}
                onChange={(e) => onChange('dtmf_timeout', parseInt(e.target.value) || 5)}
                className="mt-1.5"
              />
              {errors.dtmf_timeout && <InputError message={errors.dtmf_timeout} />}
              <p className="mt-1 text-xs text-slate-500">Wait time for input (3-10 seconds)</p>
            </div>
          </div>

          {/* DTMF Prompt Message */}
          <div>
            <Label htmlFor="dtmf_prompt">DTMF Prompt Message</Label>
            <Textarea
              id="dtmf_prompt"
              value={data.dtmf_prompt || ''}
              onChange={(e) => onChange('dtmf_prompt', e.target.value)}
              placeholder="Press 1 for sales, 2 for support, or 9 to opt-out"
              className="mt-1.5 min-h-[80px]"
            />
            {errors.dtmf_prompt && <InputError message={errors.dtmf_prompt} />}
            <p className="mt-1 text-xs text-slate-500">
              The message spoken to prompt for keypad input
            </p>
          </div>

          {/* DTMF Key Mappings */}
          <div>
            <div className="flex items-center justify-between mb-3">
              <div>
                <Label>Key Action Mappings</Label>
                <p className="text-xs text-muted-foreground mt-1">
                  Define what happens when each key is pressed
                </p>
              </div>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={addDtmfAction}
              >
                <Plus className="h-4 w-4 mr-1" />
                Add Key
              </Button>
            </div>

            {data.dtmf_actions && data.dtmf_actions.length > 0 ? (
              <div className="space-y-3">
                {data.dtmf_actions.map((action, index) => (
                  <Card key={index} className="p-4">
                    <div className="grid grid-cols-12 gap-3">
                      {/* Key */}
                      <div className="col-span-2">
                        <Label className="text-xs">Key</Label>
                        <Input
                          value={action.key}
                          onChange={(e) => updateDtmfAction(index, 'key', e.target.value)}
                          placeholder="1"
                          maxLength={1}
                          className="mt-1"
                        />
                      </div>

                      {/* Label */}
                      <div className="col-span-3">
                        <Label className="text-xs">Label</Label>
                        <Input
                          value={action.label}
                          onChange={(e) => updateDtmfAction(index, 'label', e.target.value)}
                          placeholder="Sales"
                          className="mt-1"
                        />
                      </div>

                      {/* Action Type */}
                      <div className="col-span-3">
                        <Label className="text-xs">Action</Label>
                        <Select
                          value={action.action}
                          onValueChange={(value) => updateDtmfAction(index, 'action', value)}
                        >
                          <SelectTrigger className="mt-1">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="message">Play Message</SelectItem>
                            <SelectItem value="transfer">Transfer to Number</SelectItem>
                            <SelectItem value="opt_out">Opt-Out & Remove</SelectItem>
                            <SelectItem value="interested">Mark as Interested</SelectItem>
                            <SelectItem value="callback">Request Callback</SelectItem>
                            <SelectItem value="hangup">End Call</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>

                      {/* Action Value */}
                      <div className="col-span-3">
                        <Label className="text-xs">
                          {action.action === 'transfer' ? 'Phone Number' : 'Message/Value'}
                        </Label>
                        <Input
                          value={action.value || ''}
                          onChange={(e) => updateDtmfAction(index, 'value', e.target.value)}
                          placeholder={
                            action.action === 'transfer'
                              ? '+1234567890'
                              : action.action === 'message'
                              ? 'Thank you for your interest'
                              : 'Optional'
                          }
                          className="mt-1"
                        />
                      </div>

                      {/* Remove Button */}
                      <div className="col-span-1 flex items-end">
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => removeDtmfAction(index)}
                          className="text-destructive hover:text-destructive"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  </Card>
                ))}
              </div>
            ) : (
              <div className="text-center py-8 border-2 border-dashed rounded-lg">
                <Info className="h-8 w-8 mx-auto text-muted-foreground mb-2" />
                <p className="text-sm text-muted-foreground">
                  No key mappings defined. Click "Add Key" to create your first action.
                </p>
              </div>
            )}

            {errors.dtmf_actions && <InputError message={errors.dtmf_actions} />}
          </div>

          {/* Help Text */}
          <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <div className="flex gap-3">
              <Info className="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5" />
              <div className="text-sm text-blue-900">
                <p className="font-medium mb-1">How DTMF Works:</p>
                <ul className="list-disc list-inside space-y-1 text-blue-800">
                  <li>After your message plays, the DTMF prompt will be spoken</li>
                  <li>Caller presses a key on their phone</li>
                  <li>The configured action for that key is executed automatically</li>
                  <li>All responses are tracked in campaign reports</li>
                </ul>
              </div>
            </div>
          </div>
        </CardContent>
      )}
    </Card>
  );
}
