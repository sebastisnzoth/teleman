import { useSoftphone } from '@/contexts/SoftphoneContext';
import { Phone, PhoneIncoming, PhoneCall } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface MinimizedWidgetProps {
  position: { x: number; y: number };
  onExpand: () => void;
}

export function MinimizedWidget({ position, onExpand }: MinimizedWidgetProps) {
  const { callState, duration, deviceState, incomingCall } = useSoftphone();

  const isCallActive = ['dialing', 'ringing', 'connected'].includes(callState);
  const hasIncomingCall = !!incomingCall && callState === 'idle';

  // Format duration for display
  const formatDuration = () => {
    const minutes = Math.floor(duration / 60);
    const seconds = duration % 60;
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
  };

  return (
    <Button
      onClick={onExpand}
      className={cn(
        'fixed z-[9999] rounded-full shadow-2xl transition-all duration-300',
        'hover:scale-110 active:scale-95',
        isCallActive && 'bg-green-600 hover:bg-green-700 animate-pulse',
        hasIncomingCall && 'bg-blue-600 hover:bg-blue-700 animate-bounce',
        !isCallActive && !hasIncomingCall && 'bg-primary hover:bg-primary/90'
      )}
      style={{
        bottom: `${position.y}px`,
        right: `${position.x}px`,
        width: isCallActive ? '120px' : '56px',
        height: '56px',
      }}
      size="icon"
    >
      <div className="flex items-center gap-2">
        {hasIncomingCall ? (
          <>
            <PhoneIncoming className="h-6 w-6 animate-bounce" />
            <span className="text-xs font-semibold">Incoming</span>
          </>
        ) : isCallActive ? (
          <>
            <PhoneCall className="h-5 w-5" />
            <span className="text-xs font-mono">{formatDuration()}</span>
          </>
        ) : (
          <Phone className="h-6 w-6" />
        )}
      </div>
      
      {/* Status indicator */}
      {deviceState === 'registered' && !isCallActive && !hasIncomingCall && (
        <div className="absolute -top-1 -right-1 w-3 h-3 bg-green-500 rounded-full border-2 border-white" />
      )}
    </Button>
  );
}
