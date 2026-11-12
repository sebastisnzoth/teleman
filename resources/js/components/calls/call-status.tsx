import { Badge } from '@/components/ui/badge';
import { CallState } from '@/hooks/useCallState';
import { Phone, PhoneCall, PhoneIncoming, PhoneOff } from 'lucide-react';

export interface CallStatusProps {
  state: CallState;
  phoneNumber?: string;
}

const stateConfig: Record<CallState, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: React.ReactNode }> = {
  idle: {
    label: 'Ready to call',
    variant: 'outline',
    icon: <Phone className="h-3.5 w-3.5" />,
  },
  dialing: {
    label: 'Dialing...',
    variant: 'default',
    icon: <PhoneCall className="h-3.5 w-3.5 animate-pulse" />,
  },
  ringing: {
    label: 'Ringing...',
    variant: 'default',
    icon: <PhoneIncoming className="h-3.5 w-3.5 animate-pulse" />,
  },
  connected: {
    label: 'Connected',
    variant: 'default',
    icon: <PhoneCall className="h-3.5 w-3.5" />,
  },
  ended: {
    label: 'Call ended',
    variant: 'secondary',
    icon: <PhoneOff className="h-3.5 w-3.5" />,
  },
  error: {
    label: 'Call failed',
    variant: 'destructive',
    icon: <PhoneOff className="h-3.5 w-3.5" />,
  },
};

export function CallStatus({ state, phoneNumber }: CallStatusProps) {
  const config = stateConfig[state];

  return (
    <div className="flex flex-col items-center gap-3">
      <Badge variant={config.variant} className="gap-1.5 px-3 py-1.5">
        {config.icon}
        <span>{config.label}</span>
      </Badge>
      {phoneNumber && state !== 'idle' && (
        <div className="text-sm text-slate-600 font-medium">{phoneNumber}</div>
      )}
    </div>
  );
}
