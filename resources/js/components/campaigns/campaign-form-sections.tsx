import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import InputError from '@/components/input-error';
import { Link } from '@inertiajs/react';
import { ExternalLink, Bot } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';

interface CampaignBasicInfoProps {
  data: {
    name: string;
    type: 'text_to_speech' | 'voice_to_voice';
  };
  errors: {
    name?: string;
    type?: string;
  };
  onChange: (field: string, value: string) => void;
}

export function CampaignBasicInfo({ data, errors, onChange }: CampaignBasicInfoProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Basic Information</CardTitle>
        <CardDescription>Configure the campaign name and type</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div>
          <Label htmlFor="name">Campaign Name</Label>
          <Input
            id="name"
            value={data.name}
            onChange={(e) => onChange('name', e.target.value)}
            placeholder="e.g., Summer Sale Outreach"
            className="mt-1.5"
          />
          {errors.name && <InputError message={errors.name} />}
        </div>

        <div>
          <Label>Campaign Type</Label>
          <RadioGroup
            value={data.type}
            onValueChange={(value: string) => onChange('type', value)}
            className="mt-2 space-y-3"
          >
            <div className="flex items-start space-x-3">
              <RadioGroupItem value="text_to_speech" id="text_to_speech" className="mt-0.5" />
              <div className="flex-1">
                <Label htmlFor="text_to_speech" className="cursor-pointer font-medium">
                  Text to Speech
                </Label>
                <p className="text-sm text-slate-500">
                  Convert written message to speech using AI voices
                </p>
              </div>
            </div>
            <div className="flex items-start space-x-3">
              <RadioGroupItem value="voice_to_voice" id="voice_to_voice" className="mt-0.5" />
              <div className="flex-1">
                <Label htmlFor="voice_to_voice" className="cursor-pointer font-medium">
                  Voice to Voice
                </Label>
                <p className="text-sm text-slate-500">
                  Play a pre-recorded audio file to recipients
                </p>
              </div>
            </div>
          </RadioGroup>
          {errors.type && <InputError message={errors.type} />}
        </div>
      </CardContent>
    </Card>
  );
}

interface CampaignMessageProps {
  data: {
    message: string;
  };
  errors: {
    message?: string;
  };
  onChange: (field: string, value: string) => void;
}

export function CampaignMessageInput({ data, errors, onChange }: CampaignMessageProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Message</CardTitle>
        <CardDescription>
          Write the message to be converted to speech. Use variables like {'{{first_name}}'} for personalization.
        </CardDescription>
      </CardHeader>
      <CardContent>
        <Textarea
          value={data.message}
          onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => onChange('message', e.target.value)}
          placeholder="Hello {{first_name}}, this is a call from our company..."
          rows={6}
          className="font-mono text-sm"
        />
        {errors.message && <InputError message={errors.message} />}
        <p className="mt-2 text-xs text-slate-500">
          Available variables: {'{{first_name}}'}, {'{{last_name}}'}, {'{{company}}'}
        </p>
      </CardContent>
    </Card>
  );
}

interface CampaignVoiceSettingsProps {
  data: {
    language: string;
    voice: string;
  };
  errors: {
    language?: string;
    voice?: string;
  };
  onChange: (field: string, value: string) => void;
}

const voices = [
  { value: 'Joanna', label: 'Joanna (US English, Female)' },
  { value: 'Matthew', label: 'Matthew (US English, Male)' },
  { value: 'Ivy', label: 'Ivy (US English, Female, Child)' },
  { value: 'Joey', label: 'Joey (US English, Male)' },
  { value: 'Justin', label: 'Justin (US English, Male, Child)' },
  { value: 'Kendra', label: 'Kendra (US English, Female)' },
  { value: 'Kimberly', label: 'Kimberly (US English, Female)' },
  { value: 'Salli', label: 'Salli (US English, Female)' },
  { value: 'Nicole', label: 'Nicole (Australian English, Female)' },
  { value: 'Russell', label: 'Russell (Australian English, Male)' },
  { value: 'Amy', label: 'Amy (British English, Female)' },
  { value: 'Emma', label: 'Emma (British English, Female)' },
  { value: 'Brian', label: 'Brian (British English, Male)' },
];

export function CampaignVoiceSettings({ data, errors, onChange }: CampaignVoiceSettingsProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Voice Settings</CardTitle>
        <CardDescription>Configure language and voice for text-to-speech</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div>
          <Label htmlFor="language">Language</Label>
          <select
            id="language"
            value={data.language}
            onChange={(e) => onChange('language', e.target.value)}
            className="mt-1.5 flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2"
          >
            <option value="en-US">English (US)</option>
            <option value="en-GB">English (UK)</option>
            <option value="en-AU">English (AU)</option>
            <option value="es-ES">Spanish (Spain)</option>
            <option value="es-MX">Spanish (Mexico)</option>
            <option value="fr-FR">French</option>
            <option value="de-DE">German</option>
          </select>
          {errors.language && <InputError message={errors.language} />}
        </div>

        <div>
          <Label htmlFor="voice">Voice</Label>
          <select
            id="voice"
            value={data.voice}
            onChange={(e) => onChange('voice', e.target.value)}
            className="mt-1.5 flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2"
          >
            <option value="">Select a voice</option>
            {voices.map((voice) => (
              <option key={voice.value} value={voice.value}>
                {voice.label}
              </option>
            ))}
          </select>
          {errors.voice && <InputError message={errors.voice} />}
        </div>
      </CardContent>
    </Card>
  );
}

interface CampaignSettingsProps {
  data: {
    enable_recording: boolean;
    enable_dtmf: boolean;
    max_concurrent_calls: number;
    retry_attempts: number;
    retry_delay_minutes: number;
  };
  errors: {
    enable_recording?: string;
    enable_dtmf?: string;
    max_concurrent_calls?: string;
    retry_attempts?: string;
    retry_delay_minutes?: string;
  };
  onChange: (field: string, value: string | boolean | number) => void;
}

export function CampaignSettings({ data, errors, onChange }: CampaignSettingsProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Campaign Settings</CardTitle>
        <CardDescription>Configure call behavior and retry logic</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-3">
          <div className="flex items-center space-x-2">
            <input
              type="checkbox"
              id="enable_recording"
              checked={data.enable_recording}
              onChange={(e) => onChange('enable_recording', e.target.checked)}
              className="h-4 w-4 rounded border-slate-300"
            />
            <Label htmlFor="enable_recording" className="cursor-pointer font-normal">
              Enable call recording
            </Label>
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
              Enable DTMF (keypad input)
            </Label>
          </div>
        </div>

        <div>
          <Label htmlFor="max_concurrent_calls">Maximum Concurrent Calls</Label>
          <Input
            id="max_concurrent_calls"
            type="number"
            min="1"
            max="100"
            value={data.max_concurrent_calls}
            onChange={(e) => onChange('max_concurrent_calls', parseInt(e.target.value) || 1)}
            className="mt-1.5"
          />
          {errors.max_concurrent_calls && <InputError message={errors.max_concurrent_calls} />}
          <p className="mt-1 text-xs text-slate-500">How many calls to make simultaneously (1-100)</p>
        </div>

        <div>
          <Label htmlFor="retry_attempts">Retry Attempts</Label>
          <Input
            id="retry_attempts"
            type="number"
            min="0"
            max="5"
            value={data.retry_attempts}
            onChange={(e) => onChange('retry_attempts', parseInt(e.target.value) || 0)}
            className="mt-1.5"
          />
          {errors.retry_attempts && <InputError message={errors.retry_attempts} />}
          <p className="mt-1 text-xs text-slate-500">Number of times to retry failed calls (0-5)</p>
        </div>

        <div>
          <Label htmlFor="retry_delay_minutes">Retry Delay (minutes)</Label>
          <Input
            id="retry_delay_minutes"
            type="number"
            min="1"
            max="1440"
            value={data.retry_delay_minutes}
            onChange={(e) => onChange('retry_delay_minutes', parseInt(e.target.value) || 5)}
            className="mt-1.5"
          />
          {errors.retry_delay_minutes && <InputError message={errors.retry_delay_minutes} />}
          <p className="mt-1 text-xs text-slate-500">Wait time between retry attempts</p>
        </div>
      </CardContent>
    </Card>
  );
}

interface AudioFileSelectorProps {
  data: {
    audio_file_id: number | null;
  };
  errors: {
    audio_file_id?: string;
  };
  audioFiles: Array<{ id: number; filename: string }>;
  onChange: (field: string, value: number | null) => void;
}

export function AudioFileSelector({ data, errors, audioFiles, onChange }: AudioFileSelectorProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Audio File</CardTitle>
        <CardDescription>
          Select a pre-recorded audio file for voice-to-voice campaigns
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div>
          <Label htmlFor="audio_file_id">Audio File</Label>
          <select
            id="audio_file_id"
            value={data.audio_file_id || ''}
            onChange={(e) => onChange('audio_file_id', e.target.value ? parseInt(e.target.value) : null)}
            className="mt-1.5 flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm ring-offset-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2"
          >
            <option value="">Select an audio file</option>
            {audioFiles.map((file) => (
              <option key={file.id} value={file.id}>
                {file.filename}
              </option>
            ))}
          </select>
          {errors.audio_file_id && <InputError message={errors.audio_file_id} />}
        </div>

        {audioFiles.length === 0 && (
          <div className="rounded-md bg-amber-50 p-3 text-sm">
            <p className="text-amber-900">
              No audio files found.{' '}
              <Link href="/audio-files" className="inline-flex items-center gap-1 font-medium underline">
                Upload an audio file first
                <ExternalLink className="h-3 w-3" />
              </Link>
            </p>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

interface PhoneNumber {
  id: number;
  number: string;
  formatted_number?: string;
  friendly_name?: string;
  ai_agent?: {
    id: number;
    name: string;
    type: string;
    is_active: boolean;
  };
}

interface PhoneNumberSelectorProps {
  data: {
    phone_number_id: number | null;
  };
  phoneNumbers: PhoneNumber[];
  errors: {
    phone_number_id?: string;
  };
  onChange: (field: string, value: number | null) => void;
}

export interface SelectableAiAgent {
  id: number;
  name: string;
  type: string;
}

interface AiAgentSelectorProps {
  data: {
    ai_agent_id: number | null;
  };
  aiAgents: SelectableAiAgent[];
  /** The agent attached to the number that is currently selected, if any. */
  numberAgent?: { id: number; name: string } | null;
  errors: {
    ai_agent_id?: string;
  };
  onChange: (field: string, value: number | null) => void;
}

/**
 * Choosing the AI agent that answers for a campaign.
 *
 * The campaign used to get whichever agent happened to be attached to the
 * number it dialled from, with nothing on screen to say so — the choice was
 * made blind, and an agent could be attached or silently missed without the
 * campaign's author ever seeing which. Picking a number still fills this in,
 * so the convenience is unchanged; it is now visible and can be overridden.
 */
export function AiAgentSelector({ data, aiAgents, numberAgent, errors, onChange }: AiAgentSelectorProps) {
  const selected = aiAgents.find((agent) => agent.id === data.ai_agent_id) ?? null;

  return (
    <Card>
      <CardHeader>
        <CardTitle>AI Agent</CardTitle>
        <CardDescription>
          Choose the AI agent that speaks to the person who answers. Without one, the campaign plays
          its own message instead.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        <div>
          <Label htmlFor="ai_agent_id">AI Agent</Label>
          <Select
            value={data.ai_agent_id?.toString() || 'none'}
            onValueChange={(value) => onChange('ai_agent_id', value === 'none' ? null : parseInt(value))}
          >
            <SelectTrigger className="mt-1.5">
              <SelectValue placeholder="No AI agent" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">No AI agent — play the campaign message</SelectItem>
              {aiAgents.length === 0 && (
                <div className="p-2 text-sm text-muted-foreground">
                  No AI agent is set up for outbound calls yet
                </div>
              )}
              {aiAgents.map((agent) => (
                <SelectItem key={agent.id} value={agent.id.toString()}>
                  <div className="flex items-center gap-2">
                    <Bot className="h-3 w-3" />
                    <span>{agent.name}</span>
                  </div>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.ai_agent_id && <InputError message={errors.ai_agent_id} />}
        </div>

        {selected && (
          <div className="rounded-md border border-sky-200 bg-sky-50 p-3 text-sm">
            <span className="font-medium">{selected.name} speaks on these calls.</span>{' '}
            The campaign type, its message and any audio file are not used while an AI agent is
            answering — they apply only to campaigns without one.
          </div>
        )}

        {numberAgent && selected?.id === numberAgent.id && (
          <p className="text-sm text-muted-foreground">
            Filled in from the phone number you selected. You can change it.
          </p>
        )}

        {numberAgent && data.ai_agent_id !== numberAgent.id && (
          <p className="text-sm text-muted-foreground">
            The number you selected answers with <span className="font-medium">{numberAgent.name}</span> on
            inbound calls. This campaign will use your choice above instead.
          </p>
        )}
      </CardContent>
    </Card>
  );
}

export function PhoneNumberSelector({ data, phoneNumbers, errors, onChange }: PhoneNumberSelectorProps) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Caller ID / Phone Number</CardTitle>
        <CardDescription>
          Select which phone number to use for outbound calls. If an AI agent is assigned to this number, it will automatically handle the calls. Required to launch campaign.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div>
          <Label htmlFor="phone_number_id">Phone Number</Label>
          <Select
            value={data.phone_number_id?.toString() || 'none'}
            onValueChange={(value) => onChange('phone_number_id', value === 'none' ? null : parseInt(value))}
          >
            <SelectTrigger className="mt-1.5">
              <SelectValue placeholder="Select a phone number (optional)" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="none">No Phone Number (Draft Only)</SelectItem>
              {phoneNumbers.length === 0 && (
                <div className="p-2 text-sm text-muted-foreground">
                  No phone numbers available
                </div>
              )}
              {phoneNumbers.map((phone) => (
                <SelectItem key={phone.id} value={phone.id.toString()}>
                  <div className="flex items-center justify-between gap-2 w-full">
                    <span className="font-mono text-sm">
                      {phone.formatted_number || phone.number}
                      {phone.friendly_name && (
                        <span className="ml-2 text-muted-foreground font-normal">
                          ({phone.friendly_name})
                        </span>
                      )}
                    </span>
                    {phone.ai_agent && (
                      <Badge variant={phone.ai_agent.is_active ? "default" : "secondary"} className="ml-2 gap-1">
                        <Bot className="h-3 w-3" />
                        {phone.ai_agent.name}
                      </Badge>
                    )}
                  </div>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {errors.phone_number_id && <InputError message={errors.phone_number_id} />}
        </div>

        {!data.phone_number_id && (
          <div className="rounded-md bg-amber-50 border border-amber-200 p-3">
            <div className="flex items-start gap-2">
              <div className="flex-1">
                <p className="text-sm font-medium text-amber-900">
                  Campaign will be saved as Draft
                </p>
                <p className="text-sm text-amber-700 mt-1">
                  You must select a phone number before you can launch this campaign.
                </p>
              </div>
            </div>
          </div>
        )}

        {phoneNumbers.length === 0 && (
          <div className="rounded-md bg-amber-50 p-3 text-sm">
            <p className="text-amber-900">
              No phone numbers configured.{' '}
              <Link href="/customer/numbers" className="inline-flex items-center gap-1 font-medium underline">
                Configure phone numbers first
                <ExternalLink className="h-3 w-3" />
              </Link>
            </p>
          </div>
        )}

        {data.phone_number_id && phoneNumbers.find(p => p.id === data.phone_number_id)?.ai_agent && (
          <div className="rounded-md bg-blue-50 border border-blue-200 p-3">
            <div className="flex items-start gap-2">
              <Bot className="h-5 w-5 text-blue-600 mt-0.5" />
              <div className="flex-1">
                <p className="text-sm font-medium text-blue-900">
                  AI Agent Will Handle Calls
                </p>
                <p className="text-sm text-blue-700 mt-1">
                  {phoneNumbers.find(p => p.id === data.phone_number_id)?.ai_agent?.name} is assigned to this phone number and will automatically handle all campaign calls with AI-powered conversations.
                </p>
              </div>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}
