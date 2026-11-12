import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Input } from '@/components/ui/input';
import { Calendar, Clock, Zap } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface CampaignSchedulingProps {
  launchType: 'instant' | 'scheduled' | 'draft';
  scheduledAt: string;
  onLaunchTypeChange: (type: 'instant' | 'scheduled' | 'draft') => void;
  onScheduledAtChange: (datetime: string) => void;
  error?: string;
}

export default function CampaignScheduling({
  launchType,
  scheduledAt,
  onLaunchTypeChange,
  onScheduledAtChange,
  error,
}: CampaignSchedulingProps) {
  // Get minimum datetime (now + 5 minutes)
  const getMinDateTime = () => {
    const now = new Date();
    now.setMinutes(now.getMinutes() + 5);
    return now.toISOString().slice(0, 16);
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Campaign Launch</CardTitle>
        <CardDescription>
          Choose when to launch this campaign
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <RadioGroup value={launchType} onValueChange={(value) => onLaunchTypeChange(value as 'instant' | 'scheduled' | 'draft')}>
          {/* Save as Draft */}
          <div className="flex items-start space-x-3 space-y-0 rounded-lg border p-4 hover:bg-accent cursor-pointer">
            <RadioGroupItem value="draft" id="draft" />
            <Label htmlFor="draft" className="flex-1 cursor-pointer">
              <div className="flex items-center gap-2 mb-1">
                <Calendar className="h-4 w-4 text-gray-500" />
                <span className="font-semibold">Save as Draft</span>
              </div>
              <p className="text-sm text-muted-foreground">
                Save the campaign without launching. You can edit and launch it later.
              </p>
            </Label>
          </div>

          {/* Launch Immediately */}
          <div className="flex items-start space-x-3 space-y-0 rounded-lg border p-4 hover:bg-accent cursor-pointer">
            <RadioGroupItem value="instant" id="instant" />
            <Label htmlFor="instant" className="flex-1 cursor-pointer">
              <div className="flex items-center gap-2 mb-1">
                <Zap className="h-4 w-4 text-orange-500" />
                <span className="font-semibold">Launch Immediately</span>
              </div>
              <p className="text-sm text-muted-foreground">
                Start calling contacts as soon as the campaign is created.
              </p>
            </Label>
          </div>

          {/* Schedule for Later */}
          <div className="flex items-start space-x-3 space-y-0 rounded-lg border p-4 hover:bg-accent cursor-pointer">
            <RadioGroupItem value="scheduled" id="scheduled" />
            <Label htmlFor="scheduled" className="flex-1 cursor-pointer">
              <div className="flex items-center gap-2 mb-1">
                <Clock className="h-4 w-4 text-blue-500" />
                <span className="font-semibold">Schedule for Later</span>
              </div>
              <p className="text-sm text-muted-foreground mb-3">
                Set a specific date and time to automatically launch this campaign.
              </p>

              {launchType === 'scheduled' && (
                <div className="space-y-2" onClick={(e) => e.stopPropagation()}>
                  <Label htmlFor="scheduled_at" className="text-sm">
                    Start Date & Time
                  </Label>
                  <Input
                    id="scheduled_at"
                    type="datetime-local"
                    value={scheduledAt}
                    onChange={(e) => onScheduledAtChange(e.target.value)}
                    min={getMinDateTime()}
                    className={error ? 'border-red-500' : ''}
                  />
                  {error && (
                    <p className="text-sm text-red-500">{error}</p>
                  )}
                  <p className="text-xs text-muted-foreground">
                    Campaign will automatically start at the scheduled time (your local timezone)
                  </p>
                </div>
              )}
            </Label>
          </div>
        </RadioGroup>

        {launchType === 'instant' && (
          <Alert>
            <Zap className="h-4 w-4" />
            <AlertDescription>
              <strong>Note:</strong> The campaign will start immediately after creation. 
              Make sure you have added contacts before creating the campaign.
            </AlertDescription>
          </Alert>
        )}

        {launchType === 'scheduled' && scheduledAt && (
          <Alert>
            <Clock className="h-4 w-4" />
            <AlertDescription>
              Campaign will launch on {new Date(scheduledAt).toLocaleString()}
            </AlertDescription>
          </Alert>
        )}
      </CardContent>
    </Card>
  );
}
