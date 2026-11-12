import { useSoftphone } from '@/contexts/SoftphoneContext';
import { PhoneIncoming, Phone, PhoneOff } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function IncomingCallAlert() {
  const { incomingCall, handleAcceptCall, handleRejectCall } = useSoftphone();

  if (!incomingCall) return null;

  return (
    <div className="p-3 pb-0 animate-in fade-in-0 slide-in-from-top-2">
      <div className="bg-gradient-to-r from-primary/10 via-primary/5 to-primary/10 border-2 border-primary rounded-lg p-3 shadow-lg">
        <div className="flex items-center justify-between mb-2">
          <div className="flex items-center gap-2">
            <div className="relative">
              <div className="absolute inset-0 bg-primary rounded-full animate-ping opacity-75"></div>
              <div className="relative bg-primary rounded-full p-1.5">
                <PhoneIncoming className="h-3.5 w-3.5 text-primary-foreground" />
              </div>
            </div>
            <div>
              <div className="text-xs font-medium text-muted-foreground">Incoming Call</div>
              <div className="text-sm font-bold text-foreground">
                {incomingCall.fromNumber || 'Unknown'}
              </div>
            </div>
          </div>
        </div>
        <div className="flex gap-2">
          <Button
            onClick={handleAcceptCall}
            className="flex-1 bg-green-600 hover:bg-green-700 dark:bg-green-700 dark:hover:bg-green-800 text-white h-9"
            size="sm"
          >
            <Phone className="mr-1.5 h-4 w-4" />
            Accept
          </Button>
          <Button
            onClick={handleRejectCall}
            variant="destructive"
            className="flex-1 h-9"
            size="sm"
          >
            <PhoneOff className="mr-1.5 h-4 w-4" />
            Reject
          </Button>
        </div>
      </div>
    </div>
  );
}
