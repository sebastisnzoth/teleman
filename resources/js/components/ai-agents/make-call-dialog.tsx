import { useState } from 'react';
import { Phone, Loader2 } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import axios from 'axios';
import { toast } from 'sonner';
import type { AiAgent } from '@/types/ai-agent';

interface MakeCallDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  agent: AiAgent;
  onCallInitiated?: (call: any) => void;
}

export function MakeCallDialog({
  open,
  onOpenChange,
  agent,
  onCallInitiated,
}: MakeCallDialogProps) {
  const [phoneNumber, setPhoneNumber] = useState('');
  const [loading, setLoading] = useState(false);

  const handleMakeCall = async () => {
    if (!phoneNumber.trim()) {
      toast.error('Please enter a phone number');
      return;
    }

    setLoading(true);

    try {
      const response = await axios.post('/api/v1/ai-agent-calls/initiate', {
        ai_agent_id: agent.id,
        to_number: phoneNumber,
      });

      toast.success('Call initiated successfully!');
      
      if (onCallInitiated) {
        onCallInitiated(response.data.call);
      }

      setPhoneNumber('');
      onOpenChange(false);
    } catch (error: any) {
      const message = error.response?.data?.message || 'Failed to initiate call';
      toast.error(message);
      console.error('Failed to make call:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Make Outbound Call</DialogTitle>
          <DialogDescription>
            Call a phone number using {agent.name}. The AI will start the
            conversation automatically.
          </DialogDescription>
        </DialogHeader>

        <div className="grid gap-4 py-4">
          <div className="grid gap-2">
            <Label htmlFor="phone">Phone Number</Label>
            <Input
              id="phone"
              type="tel"
              placeholder="+1234567890"
              value={phoneNumber}
              onChange={(e) => setPhoneNumber(e.target.value)}
              disabled={loading}
            />
            <p className="text-sm text-muted-foreground">
              Include country code (e.g., +1 for US, +88 for Bangladesh)
            </p>
          </div>

          <div className="rounded-lg border bg-muted/50 p-4">
            <div className="space-y-1">
              <p className="text-sm font-medium">AI Agent Details</p>
              <div className="text-xs text-muted-foreground space-y-1">
                <div>From: {agent.phone_number || 'Not configured'}</div>
                <div>Model: {agent.model}</div>
                <div>Voice: {agent.voice}</div>
              </div>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={loading}
          >
            Cancel
          </Button>
          <Button onClick={handleMakeCall} disabled={loading || !phoneNumber.trim()}>
            {loading ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Calling...
              </>
            ) : (
              <>
                <Phone className="mr-2 h-4 w-4" />
                Make Call
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
